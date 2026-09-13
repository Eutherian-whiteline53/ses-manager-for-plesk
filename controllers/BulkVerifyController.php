<?php

use Library\LongTask\BulkVerifyTask;
use Library\Repository\DomainRepository;
use Library\Repository\JobRepository;
use Library\Service\LicenseService;

class BulkVerifyController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('bulkVerifyPageTitle');
    }

    public function indexAction()
    {
        if (!$this->_requireFeature(LicenseService::FEATURE_BULK_VERIFY, 'index', 'bulk-verify')) {
            return;
        }

        $domainRepository = new DomainRepository();
        $tracked = array();
        foreach ($domainRepository->findAll() as $row) {
            $tracked[$row['domain_name']] = true;
        }

        $untracked = array();
        foreach (\pm_Domain::getAllDomains() as $domain) {
            $name = $domain->getName();
            if (isset($tracked[$name])) {
                continue;
            }

            $untracked[] = array(
                'pleskDomainId' => $domain->getId(),
                'domainName' => $name,
            );
        }

        $jobRepository = new JobRepository();

        $this->view->untracked = $untracked;
        $this->view->activeJob = $jobRepository->findActiveByType('bulk_verify');
        $this->view->recentJobs = $jobRepository->findRecent(10);
    }

    public function startAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        if (!$this->_requirePost('index')) {
            return;
        }
        if (!$this->_requireFeature(LicenseService::FEATURE_BULK_VERIFY, 'index', 'bulk-verify')) {
            return;
        }

        $jobRepository = new JobRepository();

        if ($jobRepository->findActiveByType('bulk_verify')) {
            $this->_status->addMessage('error', pm_Locale::lmsg('bulkVerifyAlreadyRunning'));
            $this->_redirectTo('index');
            return;
        }

        $selectedIds = (array) $this->_getParam('domain_id', array());
        $selectedIds = array_map('intval', $selectedIds);

        $domains = array();
        foreach (\pm_Domain::getAllDomains() as $domain) {
            if (in_array($domain->getId(), $selectedIds, true)) {
                $domains[] = array(
                    'pleskDomainId' => $domain->getId(),
                    'domainName' => $domain->getName(),
                );
            }
        }

        if (empty($domains)) {
            $this->_status->addMessage('error', pm_Locale::lmsg('bulkVerifyNoDomainsSelected'));
            $this->_redirectTo('index');
            return;
        }

        $licenseService = new LicenseService();
        $filtered = $licenseService->filterRegisterableDomains($domains);
        $domains = $filtered['allowed'];

        if (empty($domains)) {
            $this->_status->addMessage('error', pm_Locale::lmsg('domainLimitReached'));
            $this->_redirectTo('index');
            return;
        }

        if (!empty($filtered['skipped'])) {
            $this->_status->addMessage('warning', sprintf(pm_Locale::lmsg('bulkVerifyLimitTrimmed'), count($filtered['skipped'])));
        }

        $jobId = $jobRepository->create('bulk_verify', array(
            'domains' => $domains,
            'skipped' => $filtered['skipped'],
        ));

        $task = new BulkVerifyTask();
        $task->setParam('jobId', $jobId);
        $task->setParam('domains', $domains);
        $task->setParam('index', 0);

        $manager = method_exists('pm_LongTask_Manager', 'getInstance')
            ? \pm_LongTask_Manager::getInstance()
            : new \pm_LongTask_Manager();
        if (method_exists($manager, 'start')) {
            $manager->start($task);
        } else {
            $manager->launchTask($task);
        }

        $this->_status->addMessage('info', pm_Locale::lmsg('bulkVerifyStarted'));
        $this->_redirectTo('index');
    }
}
