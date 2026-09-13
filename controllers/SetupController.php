<?php

use Library\Repository\SettingsRepository;
use Library\Service\AwsSesService;
use Library\Service\ReputationService;
use Library\Service\SesConnectionService;
use Library\Service\SmtpTestService;
use Library\Service\SnsSetupService;

class SetupController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('setupPageTitle');
    }

    public function indexAction()
    {
        $repository = new SettingsRepository();
        $settings = $repository->get();
        $featureGates = $this->_featureGates();
        $step = $this->_normalizeStep((string) $this->_getParam('step', 'welcome'));

        if (!$this->getRequest()->isPost() && !empty($settings['setup_completed']) && $step !== 'welcome') {
            $this->_redirectTo('index', 'setup');
            return;
        }

        if ($this->getRequest()->isPost()) {
            if (!$this->_validateCsrfToken()) {
                $this->_status->addMessage('error', pm_Locale::lmsg('csrfValidationFailed'));
                $this->_redirectToWizardStep($step);
                return;
            }

            $direction = (string) $this->_getParam('direction', 'next');
            $postedStep = $this->_normalizeStep((string) $this->_getParam('step', $step));

            if ($direction === 'back') {
                $this->_redirectToWizardStep($this->_previousStep($postedStep));
                return;
            }

            if ($direction === 'skip') {
                $this->_redirectToWizardStep($this->_nextStep($postedStep));
                return;
            }

            if ($this->_handleStepSubmit($repository, $postedStep, $this->getRequest()->getPost(), $featureGates, $direction)) {
                if ($postedStep === 'summary') {
                    $this->_redirectTo('index', 'setup');
                    return;
                }

                $this->_redirectToWizardStep($this->_nextStep($postedStep));
                return;
            }

            $step = $postedStep;
            $settings = $repository->get();
        }

        $this->view->step = $step;
        $this->view->steps = $this->_steps();
        $this->view->stepIndex = array_search($step, array_keys($this->_steps()), true) + 1;
        $this->view->stepCount = count($this->_steps());
        $this->view->previousStep = $this->_previousStep($step);
        $this->view->nextStep = $this->_nextStep($step);
        $this->view->stepUrls = $this->_stepUrls();
        $this->view->formAction = $this->_helper->url('index', 'setup', null, array('step' => $step));
        $this->view->restartUrl = $this->_helper->url('restart', 'setup');
        $this->view->domainsUrl = $this->_helper->url('index', 'domains');
        $this->view->settingsUrl = $this->_helper->url('index', 'settings');
        $this->view->productUrl = 'https://opphisse.agency/products/ses-manager';
        $this->view->docsUrl = 'https://opphisse.agency/products/ses-manager/docs';
        $this->view->supportUrl = 'https://opphisse.agency/support';
        $this->view->eulaUrl = 'https://opphisse.agency/products/ses-manager/eula';
        $this->view->privacyUrl = 'https://opphisse.agency/products/ses-manager/privacy';
        $this->view->settings = $settings;
        $this->view->featureGates = $featureGates;
        $this->view->upgradeUrl = null;
        $this->view->hasAwsCredentials = $repository->hasAwsCredentials();
        $this->view->hasSmtpCredentials = $repository->hasSmtpCredentials();
        $this->view->hasCloudflareToken = $repository->hasCloudflareToken();
        $this->view->sesConnection = (new SesConnectionService($repository))->getStatus(false);
        $this->view->snapshot = (new ReputationService($repository))->getSnapshot(false);
        $this->view->webhookUrl = $this->_webhookUrl();
        $this->view->awsCredentials = $repository->getAwsCredentials();
        $this->view->smtpCredentials = $repository->getSmtpCredentials();
    }

    public function restartAction()
    {
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->_requirePost('index', 'setup')) {
            return;
        }

        (new SettingsRepository())->setSetupCompleted(false);
        $this->_status->addMessage('info', pm_Locale::lmsg('setupRestarted'));
        $this->_redirectToWizardStep('welcome');
    }

    private function _handleStepSubmit(SettingsRepository $repository, $step, array $post, array $featureGates, $direction)
    {
        switch ($step) {
            case 'welcome':
                return $this->_saveLegalStep($repository, $post);

            case 'region':
                $region = trim((string) ($post['region'] ?? ''));
                if (!preg_match('/^[a-z]{2}-[a-z]+-[0-9]$/', $region)) {
                    $this->_status->addMessage('error', pm_Locale::lmsg('setupRegionInvalid'));
                    return false;
                }
                $repository->saveDefaultRegion($region);
                return true;

            case 'aws':
                return $this->_saveAwsStep($repository, $post);

            case 'sandbox':
                $result = (new ReputationService($repository))->getSnapshot(true);
                if (!$result['success']) {
                    $this->_status->addMessage('warning', $result['message']);
                }
                return true;

            case 'smtp':
                return $this->_saveSmtpStep($repository, $post);

            case 'dns':
                return $this->_saveDnsStep($repository, $post);

            case 'automation':
                if (!$featureGates['auto_ses_on_create']['enabled']) {
                    $repository->saveGeneralSettings(array(
                        'auto_ses_on_create' => 0,
                    ));
                    $this->_status->addMessage('warning', $this->_featureLockedMessage('auto_ses_on_create', $featureGates));
                    return true;
                }

                $repository->saveGeneralSettings(array(
                    'auto_ses_on_create' => empty($post['auto_ses_on_create']) ? 0 : 1,
                ));
                return true;

            case 'cloudflare':
                return $this->_saveCloudflareStep($repository, $post, $featureGates);

            case 'sns':
                return $this->_saveSnsStep($repository, $post, $featureGates);

            case 'summary':
                if (!$repository->hasAwsCredentials()) {
                    $this->_status->addMessage('error', pm_Locale::lmsg('credentialsMissing'));
                    return false;
                }
                $repository->setSetupCompleted(true);
                $this->_status->addMessage('info', pm_Locale::lmsg('setupCompletedTitle'));
                return true;
        }

        return false;
    }

    private function _saveLegalStep(SettingsRepository $repository, array $post)
    {
        if (!$repository->hasAcceptedLegal()) {
            if (empty($post['accept_eula']) || empty($post['accept_privacy'])) {
                $this->_status->addMessage('error', pm_Locale::lmsg('setupLegalAcceptanceRequired'));
                return false;
            }

            $repository->saveLegalAcceptance(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);
        }

        return true;
    }

    private function _saveAwsStep(SettingsRepository $repository, array $post)
    {
        $existing = $repository->getAwsCredentials();
        $accessKey = trim((string) ($post['aws_access_key'] ?? ''));
        $secretKey = (string) ($post['aws_secret_key'] ?? '');
        $accessKey = $accessKey !== '' ? $accessKey : $existing['accessKey'];
        $secretKey = $secretKey !== '' ? $secretKey : $existing['secretKey'];
        $region = $existing['region'];

        if ($accessKey === null || $secretKey === null) {
            $this->_status->addMessage('error', pm_Locale::lmsg('credentialsMissing'));
            return false;
        }

        $repository->saveAwsCredentials($accessKey, $secretKey, $region);
        $result = (new AwsSesService())->testConnection($accessKey, $secretKey, $region);
        $this->_status->addMessage($result->success ? 'info' : 'error', $result->message);

        return $result->success;
    }

    private function _saveSmtpStep(SettingsRepository $repository, array $post)
    {
        $existing = $repository->getSmtpCredentials();
        $username = trim((string) ($post['smtp_username'] ?? ''));
        $password = (string) ($post['smtp_password'] ?? '');
        $username = $username !== '' ? $username : $existing['username'];
        $password = $password !== '' ? $password : $existing['password'];

        if ($username === null || $password === null) {
            $this->_status->addMessage('error', pm_Locale::lmsg('smtpCredentialsMissing'));
            return false;
        }

        $repository->saveSmtpCredentials($username, $password);

        $settings = $repository->get();
        $host = sprintf('email-smtp.%s.amazonaws.com', $settings['default_region']);
        $result = (new SmtpTestService())->testCredentials($host, 587, $username, $password);
        $this->_status->addMessage($result->success ? 'info' : 'error', $result->message);

        return $result->success;
    }

    private function _saveDnsStep(SettingsRepository $repository, array $post)
    {
        $mailFromSubdomain = trim(strtolower((string) ($post['mail_from_subdomain'] ?? 'bounce')));
        $dmarcPolicy = (string) ($post['default_dmarc_policy'] ?? 'none');

        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $mailFromSubdomain)) {
            $this->_status->addMessage('error', pm_Locale::lmsg('setupMailFromInvalid'));
            return false;
        }

        if (!in_array($dmarcPolicy, array('none', 'quarantine', 'reject'), true)) {
            $dmarcPolicy = 'none';
        }

        $repository->saveGeneralSettings(array(
            'mail_from_subdomain' => $mailFromSubdomain,
            'default_dmarc_policy' => $dmarcPolicy,
        ));

        return true;
    }

    private function _saveCloudflareStep(SettingsRepository $repository, array $post, array $featureGates)
    {
        if (!$featureGates['cloudflare_dns']['enabled']) {
            $this->_status->addMessage('warning', $this->_featureLockedMessage('cloudflare_dns', $featureGates));
            return true;
        }

        $token = (string) ($post['cloudflare_token'] ?? '');
        $accountId = trim((string) ($post['cloudflare_account_id'] ?? ''));
        $syncMode = (string) ($post['cloudflare_sync_mode'] ?? 'ses');

        if ($accountId !== '' && !preg_match('/^[a-f0-9]{32}$/i', $accountId)) {
            $this->_status->addMessage('error', pm_Locale::lmsg('setupCloudflareAccountInvalid'));
            return false;
        }

        if ($token !== '') {
            $repository->saveCloudflareToken($token);
        }

        if ($syncMode === 'all' && !$featureGates['cloudflare_sync_all']['enabled']) {
            $syncMode = 'ses';
            $this->_status->addMessage('warning', $this->_featureLockedMessage('cloudflare_sync_all', $featureGates));
        }

        $repository->saveCloudflareSettings($syncMode, $accountId);

        return true;
    }

    private function _saveSnsStep(SettingsRepository $repository, array $post, array $featureGates)
    {
        if (!$featureGates['sns_automation']['enabled']) {
            $this->_status->addMessage('warning', $this->_featureLockedMessage('sns_automation', $featureGates));
            return true;
        }

        $topicArn = trim((string) ($post['sns_topic_arn'] ?? ''));
        if ($topicArn !== '' && !preg_match('/^arn:aws:sns:[a-z0-9-]+:\d{12}:[A-Za-z0-9_.-]+$/', $topicArn)) {
            $this->_status->addMessage('error', pm_Locale::lmsg('setupSnsArnInvalid'));
            return false;
        }

        $repository->saveGeneralSettings(array(
            'sns_topic_arn' => $topicArn,
        ));

        $result = (new SnsSetupService())->provisionAll($this->_webhookUrl());
        $this->_status->addMessage($result['success'] ? 'info' : 'warning', $result['message']);

        return $result['success'];
    }

    private function _featureGates()
    {
        $enabledGate = array('enabled' => true, 'required_plan' => null, 'upgrade_url' => null);

        return array(
            'auto_ses_on_create' => $enabledGate,
            'cloudflare_dns' => $enabledGate,
            'cloudflare_sync_all' => $enabledGate,
            'sns_automation' => $enabledGate,
        );
    }

    private function _featureLockedMessage($featureKey, array $featureGates)
    {
        $requiredPlan = isset($featureGates[$featureKey]['required_plan']) ? $featureGates[$featureKey]['required_plan'] : null;
        $message = pm_Locale::lmsg('featureRequiresUpgradeMessage');

        if ($requiredPlan) {
            $message .= ' ' . sprintf(pm_Locale::lmsg('featureRequiredPlanMessage'), ucwords(str_replace('_', ' ', $requiredPlan)));
        }

        return $message;
    }

    private function _steps()
    {
        return array(
            'welcome' => array('title' => 'setupWizardWelcomeTitle', 'description' => 'setupWizardWelcomeDescription'),
            'region' => array('title' => 'setupWizardRegionTitle', 'description' => 'setupWizardRegionDescription'),
            'aws' => array('title' => 'setupWizardAwsTitle', 'description' => 'setupWizardAwsDescription'),
            'sandbox' => array('title' => 'setupWizardSandboxTitle', 'description' => 'setupWizardSandboxDescription'),
            'smtp' => array('title' => 'setupWizardSmtpTitle', 'description' => 'setupWizardSmtpDescription'),
            'dns' => array('title' => 'setupWizardDnsTitle', 'description' => 'setupWizardDnsDescription'),
            'automation' => array('title' => 'setupWizardAutomationTitle', 'description' => 'setupWizardAutomationDescription'),
            'cloudflare' => array('title' => 'setupWizardCloudflareTitle', 'description' => 'setupWizardCloudflareDescription'),
            'sns' => array('title' => 'setupWizardSnsTitle', 'description' => 'setupWizardSnsDescription'),
            'summary' => array('title' => 'setupWizardSummaryTitle', 'description' => 'setupWizardSummaryDescription'),
        );
    }

    private function _normalizeStep($step)
    {
        return array_key_exists($step, $this->_steps()) ? $step : 'welcome';
    }

    private function _nextStep($step)
    {
        $keys = array_keys($this->_steps());
        $index = array_search($step, $keys, true);

        return isset($keys[$index + 1]) ? $keys[$index + 1] : 'summary';
    }

    private function _previousStep($step)
    {
        $keys = array_keys($this->_steps());
        $index = array_search($step, $keys, true);

        return isset($keys[$index - 1]) ? $keys[$index - 1] : 'welcome';
    }

    private function _webhookUrl()
    {
        return $this->_absoluteExtensionUrl('public/sns-webhook.php');
    }

    private function _redirectToWizardStep($step)
    {
        $url = $this->_wizardStepUrl($step);
        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->_helper->redirector->jsonStatusResponse(array('redirect' => $url));
            return;
        }

        $this->_redirect($url, array('prependBase' => false));
    }

    private function _stepUrls()
    {
        $urls = array();
        foreach (array_keys($this->_steps()) as $step) {
            $urls[$step] = $this->_wizardStepUrl($step);
        }

        return $urls;
    }

    private function _wizardStepUrl($step)
    {
        return $this->_helper->url('index', 'setup', null, array('step' => $this->_normalizeStep($step))) . '#setup-wizard';
    }
}
