<?php

use Library\Client\CloudflareClient;
use Library\Repository\DnsRecordRepository;
use Library\Repository\DomainRepository;
use Library\Repository\SettingsRepository;
use Library\Service\AwsSesService;
use Library\Service\CloudflareDnsService;
use Library\Service\Dto\DnsRecord;
use Library\Service\LicenseService;

class CloudflareController extends \Library\Controller\ActionController
{
    public function init()
    {
        parent::init();
        $this->view->pageTitle = pm_Locale::lmsg('cloudflarePageTitle');
    }

    public function indexAction()
    {
        if (!$this->_requireFeature(LicenseService::FEATURE_CLOUDFLARE_DNS, 'index', 'cloudflare')) {
            return;
        }

        $settingsRepository = new SettingsRepository();
        $settings = $settingsRepository->get();

        $this->view->hasToken = $settingsRepository->hasCloudflareToken();
        $this->view->syncMode = $settings['cloudflare_sync_mode'];
        $this->view->domains = (new DomainRepository())->findAll();
    }

    public function previewAction()
    {
        if (!$this->_requireFeature(LicenseService::FEATURE_CLOUDFLARE_DNS, 'index', 'cloudflare')) {
            return;
        }

        $domainId = (int) $this->_getParam('id');

        $context = $this->_loadDomainContext($domainId);
        if ($context === null) {
            return;
        }

        list($domain, $zone, $plans) = $context;

        $this->view->domain = $domain;
        $this->view->zone = $zone;
        $this->view->plans = $plans;
    }

    public function applyAction()
    {
        $this->_helper->viewRenderer->setNoRender();

        $domainId = (int) $this->_getParam('id');
        if (!$this->_requireFeature(LicenseService::FEATURE_CLOUDFLARE_DNS, 'index', 'cloudflare')) {
            return;
        }

        if (!$this->_requirePost('preview', 'cloudflare', array('id' => $domainId))) {
            return;
        }

        $context = $this->_loadDomainContext($domainId);
        if ($context === null) {
            return;
        }

        list($domain, $zone, $plans, $client) = $context;

        $cfService = new CloudflareDnsService($client);
        $results = $cfService->applyChanges($zone['id'], $plans);

        $dnsRecordRepository = new DnsRecordRepository();
        $dnsRecordRepository->savePlans($domain['id'], $results, 'cloudflare');

        $this->_status->addMessage('info', pm_Locale::lmsg('cloudflareChangesApplied'));
        $this->_redirectTo('preview', 'cloudflare', array('id' => $domainId));
    }

    /**
     * @return array{0:array,1:array,2:array,3:CloudflareClient}|null
     */
    private function _loadDomainContext($domainId)
    {
        $domainRepository = new DomainRepository();
        $domain = $domainRepository->findById($domainId);

        if (!$domain) {
            $this->_redirectTo('index');
            return null;
        }

        $settingsRepository = new SettingsRepository();
        $token = $settingsRepository->getCloudflareToken();

        if ($token === null) {
            $this->_status->addMessage('error', pm_Locale::lmsg('cloudflareTokenMissing'));
            $this->_redirectTo('index');
            return null;
        }

        $client = new CloudflareClient($token);
        $settings = $settingsRepository->get();
        if ($settings['cloudflare_sync_mode'] === 'all' && !(new LicenseService())->canUseFeature(LicenseService::FEATURE_CLOUDFLARE_SYNC_ALL)) {
            $settings['cloudflare_sync_mode'] = 'ses';
        }
        $cfService = new CloudflareDnsService($client);

        $zone = $cfService->detectOrCreateZone($domain['domain_name'], $settings['cloudflare_account_id']);
        if ($zone === null) {
            $this->_status->addMessage('error', pm_Locale::lmsg('cloudflareZoneNotFound'));
            $this->_redirectTo('index');
            return null;
        }

        $requiredRecords = array();
        $requiredRecordKeys = array();

        if ($settings['cloudflare_sync_mode'] === 'all') {
            foreach ((new \Library\Service\PleskDnsService())->getExistingRecords($domain['domain_name']) as $record) {
                $record->purpose = $record->purpose ?: 'all';
                $key = strtolower($record->type . '|' . $record->name . '|' . $record->value);
                if (isset($requiredRecordKeys[$key])) {
                    continue;
                }

                $requiredRecordKeys[$key] = true;
                $requiredRecords[] = $record;
            }
        } else {
            $dnsRecordRepository = new DnsRecordRepository();
            foreach ($dnsRecordRepository->findByDomainId($domain['id']) as $row) {
                if (!in_array($row['purpose'], array('dkim', 'spf', 'mail_from_mx', 'dmarc'), true)) {
                    continue;
                }

                $record = new DnsRecord($row['type'], $row['name'], $row['value'], $row['purpose']);
                $key = $this->_dnsRecordKey($record);
                if (isset($requiredRecordKeys[$key])) {
                    continue;
                }

                $requiredRecordKeys[$key] = true;
                $requiredRecords[] = $record;
            }

            foreach ((new AwsSesService())->getMailFromRecords($domain['domain_name'], $settings['mail_from_subdomain'], $domain['region']) as $record) {
                $key = $this->_dnsRecordKey($record);
                if (isset($requiredRecordKeys[$key])) {
                    continue;
                }

                $requiredRecordKeys[$key] = true;
                $requiredRecords[] = $record;
            }

            $dmarcRecord = (new AwsSesService())->getDmarcRecord($domain['domain_name'], $settings['default_dmarc_policy']);
            $key = $this->_dnsRecordKey($dmarcRecord);
            if (!isset($requiredRecordKeys[$key])) {
                $requiredRecordKeys[$key] = true;
                $requiredRecords[] = $dmarcRecord;
            }
        }

        $requiredRecords = $this->_uniqueDnsRecords($requiredRecords);
        $plans = $cfService->previewChanges($zone['id'], $domain['domain_name'], $requiredRecords, $settings['default_spf_policy']);

        return array($domain, $zone, $plans, $client);
    }

    private function _uniqueDnsRecords(array $records)
    {
        $unique = array();
        $seen = array();

        foreach ($records as $record) {
            $key = $this->_dnsRecordKey($record);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $record;
        }

        return $unique;
    }

    private function _dnsRecordKey(DnsRecord $record)
    {
        return strtolower(implode('|', array(
            strtoupper($record->type),
            rtrim($record->name, '.'),
            preg_replace('/\s+/', ' ', trim($record->value)),
        )));
    }
}
