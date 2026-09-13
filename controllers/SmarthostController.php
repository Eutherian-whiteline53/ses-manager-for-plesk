<?php

use Library\Repository\SettingsRepository;
use Library\Service\SmarthostService;
use Library\Service\SmtpTestService;

class SmarthostController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('smarthostPageTitle');
    }

    public function indexAction()
    {
        $settingsRepository = new SettingsRepository();
        $settings = $settingsRepository->get();
        $smarthostService = new SmarthostService();
        $currentConfig = $smarthostService->getCurrentConfig();

        $this->view->preview = $smarthostService->previewConfig($settings['default_region']);
        $this->view->currentConfig = $currentConfig;
        $this->view->smarthostEnabled = !empty($currentConfig['enabled']);
        $this->view->hasSnapshot = $settingsRepository->getSmarthostSnapshot() !== null;
    }

    public function applyAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        if (!$this->_requirePost('index')) {
            return;
        }

        $settingsRepository = new SettingsRepository();
        $settings = $settingsRepository->get();
        $smarthostService = new SmarthostService();

        $config = $smarthostService->previewConfig($settings['default_region']);
        $result = $smarthostService->applyConfig($config);

        if ($result->success) {
            $smtpCredentials = $settingsRepository->getSmtpCredentials();
            $testResult = (new SmtpTestService())->testCredentials(
                trim($config->host, '[]'),
                $config->port,
                $smtpCredentials['username'],
                $smtpCredentials['password']
            );

            if (!$testResult->success) {
                $smarthostService->rollbackLastConfig();
                $this->_status->addMessage('error', pm_Locale::lmsg('smarthostApplyFailedRolledBack'));
                $this->_redirectTo('index');
                return;
            }
        }

        $this->_status->addMessage($result->success ? 'info' : 'error', $result->message);
        $this->_redirectTo('index');
    }

    public function rollbackAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        if (!$this->_requirePost('index')) {
            return;
        }

        $smarthostService = new SmarthostService();
        $result = $smarthostService->rollbackLastConfig();

        $this->_status->addMessage($result->success ? 'info' : 'error', $result->message);
        $this->_redirectTo('index');
    }
}
