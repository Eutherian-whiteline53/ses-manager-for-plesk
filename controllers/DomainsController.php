<?php

use Library\Repository\DnsRecordRepository;
use Library\Repository\DomainRepository;
use Library\Repository\JobRepository;
use Library\Repository\SettingsRepository;
use Library\Service\AwsSesService;
use Library\Service\Dto\DnsRecord;
use Library\Service\Dto\VerificationResult;
use Library\Service\DomainVerificationService;
use Library\Service\LicenseService;
use Library\Service\PleskDnsService;

class DomainsController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('domainsPageTitle');
    }

    public function indexAction()
    {
        $domainRepository = new DomainRepository();

        $tracked = array();
        foreach ($domainRepository->findAll() as $row) {
            $tracked[$row['domain_name']] = $row;
        }

        $rows = array();
        foreach (\pm_Domain::getAllDomains() as $domain) {
            $name = $domain->getName();
            $rows[] = array(
                'pleskDomainId' => $domain->getId(),
                'domainName' => $name,
                'tracked' => isset($tracked[$name]) ? $tracked[$name] : null,
            );
        }

        $this->view->domains = $rows;
    }

    public function viewAction()
    {
        $pleskDomainId = (int) $this->_getParam('id');
        $domainName = $this->_getDomainName($pleskDomainId);

        $domainRepository = new DomainRepository();
        $dnsRecordRepository = new DnsRecordRepository();
        $settingsRepository = new SettingsRepository();

        $tracked = $domainRepository->findByName($domainName);
        $credentials = $settingsRepository->getAwsCredentials();

        if ($tracked) {
            $this->_syncMailFromDnsPlans($tracked, $settingsRepository, $dnsRecordRepository);
        }

        $this->view->domainName = $domainName;
        $this->view->pleskDomainId = $pleskDomainId;
        $this->view->tracked = $tracked;
        $this->view->records = $tracked ? $dnsRecordRepository->findByDomainId($tracked['id']) : array();
        $this->view->hasAwsCredentials = $credentials['accessKey'] !== null;
        $this->view->autoJob = (new JobRepository())->findLatestAutoDomainVerifyJob($domainName);
    }

    public function openAction()
    {
        $this->_helper->viewRenderer->setNoRender();

        $pleskDomainId = (int) $this->_getParam('site_id', 0);
        if ($pleskDomainId <= 0) {
            $pleskDomainId = (int) $this->_getParam('dom_id', 0);
        }
        if ($pleskDomainId <= 0) {
            $pleskDomainId = (int) $this->_getParam('id', 0);
        }

        if ($pleskDomainId <= 0 || !\pm_Domain::getByDomainId($pleskDomainId)) {
            $this->_redirectTo('index', 'domains');
            return;
        }

        $this->_redirectTo('view', 'domains', array('id' => $pleskDomainId));
    }

    public function createIdentityAction()
    {
        $this->_helper->viewRenderer->setNoRender();

        $pleskDomainId = (int) $this->_getParam('id');
        if (!$this->_requirePost('view', 'domains', array('id' => $pleskDomainId))) {
            return;
        }
        if (!$this->_requireFeature(LicenseService::FEATURE_MANUAL_IDENTITY_CREATE, 'view', 'domains', array('id' => $pleskDomainId))) {
            return;
        }

        $domainName = $this->_getDomainName($pleskDomainId);

        $result = (new DomainVerificationService())->verifyDomain($domainName, $pleskDomainId);

        if (!$result->success) {
            $messages = array(
                VerificationResult::ERROR_CREDENTIALS_MISSING => 'credentialsMissing',
                VerificationResult::ERROR_LIMIT_REACHED => 'domainLimitReached',
                VerificationResult::ERROR_IDENTITY_FAILED => 'identityCreateFailed',
            );

            $messageKey = isset($messages[$result->errorCode]) ? $messages[$result->errorCode] : 'identityCreateFailed';
            $this->_status->addMessage('error', pm_Locale::lmsg($messageKey));
            $this->_redirectTo('view', 'domains', array('id' => $pleskDomainId));
            return;
        }

        $this->_status->addMessage('info', pm_Locale::lmsg('identityCreated'));
        $this->_redirectTo('view', 'domains', array('id' => $pleskDomainId));
    }

    public function applyDnsAction()
    {
        $this->_helper->viewRenderer->setNoRender();

        $pleskDomainId = (int) $this->_getParam('id');
        if (!$this->_requirePost('view', 'domains', array('id' => $pleskDomainId))) {
            return;
        }

        $domainName = $this->_getDomainName($pleskDomainId);

        $domainRepository = new DomainRepository();
        $tracked = $domainRepository->findByName($domainName);

        if (!$tracked) {
            $this->_redirectTo('view', 'domains', array('id' => $pleskDomainId));
            return;
        }

        $requiredRecords = array();
        $requiredRecordKeys = array();
        $dnsRecordRepository = new DnsRecordRepository();
        foreach ($dnsRecordRepository->findByDomainId($tracked['id']) as $row) {
            if ($row['status'] !== \Library\Service\Dto\DnsChangeResult::STATUS_PLANNED) {
                continue;
            }

            $record = new DnsRecord($row['type'], $row['name'], $row['value'], $row['purpose']);
            $key = strtolower($record->type . '|' . $record->name . '|' . $record->value);
            $requiredRecordKeys[$key] = true;
            $requiredRecords[] = $record;
        }

        $settingsRepository = new SettingsRepository();
        $settings = $settingsRepository->get();
        $credentials = $settingsRepository->getAwsCredentials();
        $awsService = new AwsSesService();

        foreach ($awsService->getMailFromRecords($domainName, $settings['mail_from_subdomain'], $tracked['region']) as $record) {
            $key = strtolower($record->type . '|' . $record->name . '|' . $record->value);
            if (isset($requiredRecordKeys[$key])) {
                continue;
            }

            $requiredRecordKeys[$key] = true;
            $requiredRecords[] = $record;
        }

        $dmarcRecord = $awsService->getDmarcRecord($domainName, $settings['default_dmarc_policy']);
        $key = strtolower($dmarcRecord->type . '|' . $dmarcRecord->name . '|' . $dmarcRecord->value);
        if (!isset($requiredRecordKeys[$key])) {
            $requiredRecordKeys[$key] = true;
            $requiredRecords[] = $dmarcRecord;
        }

        if ($credentials['accessKey'] !== null && $credentials['secretKey'] !== null) {
            try {
                $awsService->configureMailFromDomain(
                    $credentials['accessKey'],
                    $credentials['secretKey'],
                    $tracked['region'],
                    $domainName,
                    $awsService->buildMailFromDomain($domainName, $settings['mail_from_subdomain'])
                );
            } catch (\Exception $e) {
                \pm_Log::err('SES Manager: failed to configure custom MAIL FROM for ' . $domainName . ': ' . $e->getMessage());
            }
        }

        $dnsService = new PleskDnsService();
        $plans = $dnsService->planChanges($domainName, $requiredRecords, $settings['default_spf_policy']);
        $applied = $dnsService->apply($domainName, $plans);

        $dnsRecordRepository->savePlans($tracked['id'], $applied);

        $this->_status->addMessage('info', pm_Locale::lmsg('dnsApplied'));
        $this->_redirectTo('view', 'domains', array('id' => $pleskDomainId));
    }

    private function _getDomainName($pleskDomainId)
    {
        $domain = \pm_Domain::getByDomainId($pleskDomainId);

        if (!$domain) {
            throw new \pm_Exception('Domain not found.');
        }

        return $domain->getName();
    }

    private function _syncMailFromDnsPlans(array $tracked, SettingsRepository $settingsRepository, DnsRecordRepository $dnsRecordRepository)
    {
        $settings = $settingsRepository->get();
        $awsService = new AwsSesService();

        $dnsRecordRepository->deleteRootSesSpf($tracked['id'], $tracked['domain_name']);

        $records = array_merge(
            $awsService->getMailFromRecords($tracked['domain_name'], $settings['mail_from_subdomain'], $tracked['region']),
            array($awsService->getDmarcRecord($tracked['domain_name'], $settings['default_dmarc_policy']))
        );
        $plans = (new PleskDnsService())->planChanges($tracked['domain_name'], $records, $settings['default_spf_policy']);

        $dnsRecordRepository->savePlans($tracked['id'], $plans);
    }
}
