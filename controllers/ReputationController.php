<?php

use Library\Service\IpReputationService;
use Library\Repository\IpReputationAlertRepository;
use Library\Service\LicenseService;
use Library\Service\ReputationService;

class ReputationController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('reputationPageTitle');
    }

    public function indexAction()
    {
        $this->_loadViewData(false);
    }

    public function refreshAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        if (!$this->_requirePost('index', 'reputation')) {
            return;
        }

        $this->_loadViewData(true);
        $this->_status->addMessage('info', pm_Locale::lmsg('reputationRefreshed'));
        $this->_redirectTo('index', 'reputation');
    }

    private function _loadViewData($forceRefresh)
    {
        $licenseService = new LicenseService();
        $reputation = (new ReputationService())->getSnapshot($forceRefresh);
        $ipReputation = null;
        $activeIpAlerts = array();
        $ipAlertEvents = array();

        if ($licenseService->canUseFeature(LicenseService::FEATURE_RBL_MONITORING)) {
            $ipReputation = (new IpReputationService())->check($forceRefresh);
            $alertRepository = new IpReputationAlertRepository();
            $activeIpAlerts = $alertRepository->findActive();
            $ipAlertEvents = $alertRepository->findRecentEvents(20);
        }

        if (!$reputation['success'] && $reputation['message'] !== '') {
            $this->_status->addMessage('error', $reputation['message']);
        }

        $this->view->snapshot = $reputation['snapshot'];
        $this->view->ipReputation = $ipReputation;
        $this->view->activeIpAlerts = $activeIpAlerts;
        $this->view->ipAlertEvents = $ipAlertEvents;
        $this->view->ipReputationLocked = !$licenseService->canUseFeature(LicenseService::FEATURE_RBL_MONITORING);
        $this->view->upgradeUrl = $licenseService->getUpgradeUrl();
    }
}
