<?php

use Library\Repository\DomainRepository;
use Library\Repository\JobRepository;
use Library\Service\SesConnectionService;

class IndexController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('pageTitle');
    }

    public function indexAction()
    {
        $domainRepository = new DomainRepository();
        $domains = $domainRepository->findAll();

        $this->view->hasDomains = !empty($domains);
        $this->view->domains = $domains;
        $this->view->setupUrl = $this->_helper->url('index', 'setup') . '#setup-wizard';

        $criticalDomains = array();
        $totalScore = 0;

        foreach ($domains as $domain) {
            $totalScore += (int) $domain['score'];

            if ((int) $domain['score'] < 50 || $domain['verification_status'] === 'failed') {
                $criticalDomains[] = $domain;
            }
        }

        $this->view->averageScore = $domains ? (int) round($totalScore / count($domains)) : null;
        $this->view->criticalDomains = $criticalDomains;
        $this->view->autoJobs = (new JobRepository())->findRecentByType('auto_domain_verify', 5);
        $this->view->sesConnection = (new SesConnectionService())->getStatus(false);
    }
}
