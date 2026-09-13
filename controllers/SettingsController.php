<?php

use Library\Form\CloudflareForm;
use Library\Form\SettingsForm;
use Library\Form\SetupForm;
use Library\Repository\SettingsRepository;
use Library\Service\AwsSesService;
use Library\Service\LicenseService;
use Library\Service\SesConnectionService;
use Library\Service\SmtpTestService;

class SettingsController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('settingsPageTitle');
    }

    public function indexAction()
    {
        $repository = new SettingsRepository();
        $settings = $repository->get();
        $licenseService = new LicenseService();
        $featureGates = $this->_featureGates($licenseService);

        $generalForm = new SettingsForm();
        $generalForm->setDefaults(array(
            'default_spf_policy' => $settings['default_spf_policy'],
            'default_dmarc_policy' => $settings['default_dmarc_policy'],
            'mail_from_subdomain' => $settings['mail_from_subdomain'],
            'auto_ses_on_create' => $settings['auto_ses_on_create'],
            'smarthost_enabled' => $settings['smarthost_enabled'],
            'sns_topic_arn' => $settings['sns_topic_arn'],
            'cache_ttl_seconds' => $settings['cache_ttl_seconds'],
            'retention_days' => $settings['retention_days'],
        ));

        $setupForm = new SetupForm();
        $setupForm->setDefault('region', $settings['default_region']);
        $setupForm->addElement('hidden', 'settings_form', array(
            'value' => 'aws',
            'decorators' => array('ViewHelper'),
        ));

        $cloudflareForm = new CloudflareForm();
        $cloudflareForm->setDefault('cloudflare_sync_mode', $settings['cloudflare_sync_mode']);
        $cloudflareForm->setDefault('cloudflare_account_id', $settings['cloudflare_account_id']);
        $cloudflareForm->addElement('hidden', 'settings_form', array(
            'value' => 'cloudflare',
            'decorators' => array('ViewHelper'),
        ));

        $generalForm->addElement('hidden', 'settings_form', array(
            'value' => 'general',
            'decorators' => array('ViewHelper'),
        ));

        $this->_applyFormGates($generalForm, $cloudflareForm, $featureGates);

        if ($this->getRequest()->isPost()) {
            if (!$this->_validateCsrfToken()) {
                $this->_status->addMessage('error', pm_Locale::lmsg('csrfValidationFailed'));
                $this->_redirectTo('index');
                return;
            }

            $formName = (string) $this->_getParam('settings_form');
            $post = $this->getRequest()->getPost();

            if ($formName === 'aws' && $setupForm->isValid($post)) {
                $this->_handleAwsSubmit($repository, $setupForm->getValues());
                $this->_redirectTo('index');
                return;
            }

            if ($formName === 'cloudflare' && $cloudflareForm->isValid($post)) {
                if (!$featureGates['cloudflare_dns']['enabled']) {
                    $this->_status->addMessage('warning', $this->_featureLockedMessage('cloudflare_dns', $featureGates));
                    $this->_redirectTo('index');
                    return;
                }

                $values = $cloudflareForm->getValues();
                if ($values['cloudflare_sync_mode'] === 'all' && !$featureGates['cloudflare_sync_all']['enabled']) {
                    $values['cloudflare_sync_mode'] = 'ses';
                    $this->_status->addMessage('warning', $this->_featureLockedMessage('cloudflare_sync_all', $featureGates));
                }

                if (!empty($values['cloudflare_token'])) {
                    $repository->saveCloudflareToken($values['cloudflare_token']);
                }
                $repository->saveCloudflareSettings($values['cloudflare_sync_mode'], $values['cloudflare_account_id']);
                $this->_status->addMessage('info', pm_Locale::lmsg('settingsSaved'));
                $this->_redirectTo('index');
                return;
            }

            if ($formName === 'general' && $generalForm->isValid($post)) {
                $values = $generalForm->getValues();
                $values['auto_ses_on_create'] = empty($values['auto_ses_on_create']) ? 0 : 1;
                $values['smarthost_enabled'] = empty($values['smarthost_enabled']) ? 0 : 1;

                if ($values['auto_ses_on_create'] && !$featureGates['auto_ses_on_create']['enabled']) {
                    $values['auto_ses_on_create'] = 0;
                    $this->_status->addMessage('warning', $this->_featureLockedMessage('auto_ses_on_create', $featureGates));
                }

                if (!$featureGates['sns_automation']['enabled']) {
                    $values['sns_topic_arn'] = $settings['sns_topic_arn'];
                    if (!empty($post['sns_topic_arn']) && $post['sns_topic_arn'] !== $settings['sns_topic_arn']) {
                        $this->_status->addMessage('warning', $this->_featureLockedMessage('sns_automation', $featureGates));
                    }
                }

                $repository->saveGeneralSettings($values);
                $this->_status->addMessage('info', pm_Locale::lmsg('settingsSaved'));
                $this->_redirectTo('index');
                return;
            }
        }

        $this->view->generalForm = $generalForm;
        $this->view->setupForm = $setupForm;
        $this->view->cloudflareForm = $cloudflareForm;
        $this->view->setupWizardUrl = $this->_helper->url('index', 'setup') . '#setup-wizard';
        $this->view->setupRestartUrl = $this->_helper->url('restart', 'setup');
        $this->view->settings = $settings;
        $this->view->featureGates = $featureGates;
        $this->view->upgradeUrl = $licenseService->getUpgradeUrl();
        $this->view->credentialStatus = array(
            'awsAccessKey' => $repository->hasAwsAccessKey(),
            'awsSecretKey' => $repository->hasAwsSecretKey(),
            'smtpUsername' => $repository->hasSmtpUsername(),
            'smtpPassword' => $repository->hasSmtpPassword(),
            'cloudflareToken' => $repository->hasCloudflareToken(),
        );
        $this->view->sesConnection = (new SesConnectionService($repository))->getStatus(false);
    }

    private function _featureGates(LicenseService $licenseService)
    {
        return array(
            'auto_ses_on_create' => $licenseService->featureGate(LicenseService::FEATURE_AUTO_SES_ON_CREATE),
            'cloudflare_dns' => $licenseService->featureGate(LicenseService::FEATURE_CLOUDFLARE_DNS),
            'cloudflare_sync_all' => $licenseService->featureGate(LicenseService::FEATURE_CLOUDFLARE_SYNC_ALL),
            'sns_automation' => $licenseService->featureGate(LicenseService::FEATURE_SNS_AUTOMATION),
        );
    }

    private function _applyFormGates(SettingsForm $generalForm, CloudflareForm $cloudflareForm, array $featureGates)
    {
        if (!$featureGates['auto_ses_on_create']['enabled'] && $generalForm->getElement('auto_ses_on_create')) {
            $generalForm->getElement('auto_ses_on_create')->setAttrib('disabled', 'disabled');
        }

        if (!$featureGates['sns_automation']['enabled'] && $generalForm->getElement('sns_topic_arn')) {
            $generalForm->getElement('sns_topic_arn')->setAttrib('disabled', 'disabled');
        }

        if (!$featureGates['cloudflare_dns']['enabled']) {
            foreach (array('cloudflare_token', 'cloudflare_account_id', 'cloudflare_sync_mode') as $elementName) {
                if ($cloudflareForm->getElement($elementName)) {
                    $cloudflareForm->getElement($elementName)->setAttrib('disabled', 'disabled');
                }
            }
        }
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

    private function _handleAwsSubmit(SettingsRepository $repository, array $values)
    {
        $existingAws = $repository->getAwsCredentials();
        $accessKey = $values['aws_access_key'] !== '' ? $values['aws_access_key'] : $existingAws['accessKey'];
        $secretKey = $values['aws_secret_key'] !== '' ? $values['aws_secret_key'] : $existingAws['secretKey'];
        $region = $values['region'];

        if ($accessKey === null || $secretKey === null) {
            $this->_status->addMessage('error', pm_Locale::lmsg('credentialsMissing'));
            return;
        }

        $repository->saveAwsCredentials($accessKey, $secretKey, $region);

        if ($values['smtp_username'] !== '' && $values['smtp_password'] !== '') {
            $repository->saveSmtpCredentials($values['smtp_username'], $values['smtp_password']);
        }

        $connectionResult = (new AwsSesService())->testConnection($accessKey, $secretKey, $region);
        $this->_status->addMessage($connectionResult->success ? 'info' : 'error', $connectionResult->message);

        $smtpCredentials = $repository->getSmtpCredentials();
        if ($smtpCredentials['username'] !== null && $smtpCredentials['password'] !== null) {
            $host = sprintf('email-smtp.%s.amazonaws.com', $region);
            $smtpResult = (new SmtpTestService())->testCredentials($host, 587, $smtpCredentials['username'], $smtpCredentials['password']);
            $this->_status->addMessage($smtpResult->success ? 'info' : 'error', $smtpResult->message);
        }
    }
}
