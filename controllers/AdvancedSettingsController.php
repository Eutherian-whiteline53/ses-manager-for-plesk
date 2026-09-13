<?php

use Library\Form\AdvancedSettingsForm;
use Library\Repository\SettingsRepository;
use Library\Service\RetentionCleanupService;
use Library\Service\SupportBundleService;

class AdvancedSettingsController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('advancedSettingsPageTitle');
    }

    public function indexAction()
    {
        $repository = new SettingsRepository();
        $settings = $repository->get();
        $form = new AdvancedSettingsForm();
        $form->setDefaults($this->formDefaults($settings));
        $form->addElement('hidden', 'advanced_action', array(
            'value' => 'save',
            'decorators' => array('ViewHelper'),
        ));

        if ($this->getRequest()->isPost() && (string) $this->_getParam('advanced_action') === 'save') {
            if (!$this->_validateCsrfToken()) {
                $this->_status->addMessage('error', pm_Locale::lmsg('csrfValidationFailed'));
                $this->_redirectTo('index');
                return;
            }

            $post = $this->getRequest()->getPost();
            if ($form->isValid($post)) {
                $repository->saveAdvancedSettings($form->getValues());
                $this->_status->addMessage('info', pm_Locale::lmsg('settingsSaved'));
                $this->_redirectTo('index');
                return;
            }
        }

        $supportService = new SupportBundleService($repository);
        $supportBundle = $supportService->build();

        $this->view->form = $form;
        $this->view->settings = $settings;
        $this->view->supportPreview = $supportService->toJson($supportBundle);
        $this->view->downloadUrl = $this->_helper->url('download-support-bundle', 'advanced-settings');
        $this->view->cleanupUrl = $this->_helper->url('cleanup-retention', 'advanced-settings');
    }

    public function cleanupRetentionAction()
    {
        if (!$this->_requirePost('index')) {
            return;
        }

        $summary = (new RetentionCleanupService())->cleanup();
        $deleted = array_sum(array_map('intval', $summary));
        $this->_status->addMessage('info', sprintf(pm_Locale::lmsg('advancedCleanupCompleted'), $deleted));
        $this->_redirectTo('index');
    }

    public function downloadSupportBundleAction()
    {
        $supportService = new SupportBundleService();
        $json = $supportService->toJson($supportService->build());
        $filename = 'ses-manager-support-' . gmdate('Ymd-His') . '.json';

        $this->_helper->viewRenderer->setNoRender(true);
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json; charset=utf-8', true)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true)
            ->setBody($json);
    }

    private function formDefaults(array $settings)
    {
        $defaults = array();
        foreach (array(
            'mail_test_retention_days',
            'event_retention_days',
            'webhook_attempt_retention_days',
            'job_retention_days',
            'reputation_retention_days',
            'ip_reputation_retention_days',
            'log_retention_days',
        ) as $field) {
            $defaults[$field] = isset($settings[$field]) ? $settings[$field] : 30;
        }

        return $defaults;
    }
}
