<?php

use Library\Repository\DomainRepository;
use Library\Service\DnsAutoFixService;
use Library\Service\HealthCheckRunner;
use Library\Service\LicenseService;

class DnsHealthController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('dnsHealthPageTitle');
    }

    public function indexAction()
    {
        $domainRepository = new DomainRepository();
        $this->view->domains = $domainRepository->findAll();
    }

    public function refreshAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        if (!$this->_requirePost('index', 'dns-health')) {
            return;
        }

        $domainId = (int) $this->_getParam('id');
        $domainRepository = new DomainRepository();
        $domain = $domainRepository->findById($domainId);

        if ($domain) {
            $runner = new HealthCheckRunner();
            $runner->refreshDomain($domain);
            $this->_status->addMessage('info', pm_Locale::lmsg('healthRefreshed'));
        }

        $this->_redirectTo('index', 'dns-health');
    }

    public function fixAction()
    {
        $this->_helper->viewRenderer->setNoRender();

        $domainId = (int) $this->_getParam('id');
        if (!$this->_requirePost('index', 'dns-health')) {
            return;
        }

        $purpose = (string) $this->_getParam('purpose', 'all');
        if (!$this->_requireFeature($this->_featureForPurpose($purpose), 'index', 'dns-health')) {
            return;
        }

        $result = (new DnsAutoFixService())->fix($domainId, $purpose);
        $this->_status->addMessage($result['success'] ? 'info' : 'warning', $result['message']);
        $this->_redirectTo('index', 'dns-health');
    }

    private function _featureForPurpose($purpose)
    {
        switch ($purpose) {
            case 'spf':
                return LicenseService::FEATURE_SPF_AUTO_FIX;
            case 'dkim':
                return LicenseService::FEATURE_DKIM_AUTO_FIX;
            case 'dmarc':
                return LicenseService::FEATURE_DMARC_AUTO_FIX;
            case 'mail_from_mx':
            case 'mx':
                return LicenseService::FEATURE_MAIL_FROM_AUTO_FIX;
            case 'all':
            default:
                return LicenseService::FEATURE_DNS_AUTO_FIX;
        }
    }
}
