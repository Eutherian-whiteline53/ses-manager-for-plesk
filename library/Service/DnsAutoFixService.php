<?php

namespace Library\Service;

use Library\Client\CloudflareClient;
use Library\Repository\DnsRecordRepository;
use Library\Repository\DomainRepository;
use Library\Repository\SettingsRepository;
use Library\Service\Dto\DnsRecord;

class DnsAutoFixService
{
    /** @var DomainRepository */
    private $domainRepository;

    /** @var DnsRecordRepository */
    private $dnsRecordRepository;

    /** @var SettingsRepository */
    private $settingsRepository;

    public function __construct(
        ?DomainRepository $domainRepository = null,
        ?DnsRecordRepository $dnsRecordRepository = null,
        ?SettingsRepository $settingsRepository = null
    ) {
        $this->domainRepository = $domainRepository ?: new DomainRepository();
        $this->dnsRecordRepository = $dnsRecordRepository ?: new DnsRecordRepository();
        $this->settingsRepository = $settingsRepository ?: new SettingsRepository();
    }

    public function fix($domainId, $purpose)
    {
        $domain = $this->domainRepository->findById($domainId);
        if (!$domain) {
            return array('success' => false, 'message' => \pm_Locale::lmsg('domainNotFound'));
        }

        $purpose = strtolower((string) $purpose);
        $records = $this->loadRecords($domain, $purpose);
        if (empty($records)) {
            return array('success' => false, 'message' => \pm_Locale::lmsg('dnsFixNoRecords'));
        }

        $settings = $this->settingsRepository->get();
        $provider = (new DnsProviderDetector())->detect($domain['domain_name']);

        if ($provider === DnsProviderDetector::PROVIDER_CLOUDFLARE) {
            return $this->fixCloudflare($domain, $records, $settings);
        }

        if ($provider === DnsProviderDetector::PROVIDER_UNKNOWN) {
            $plans = (new PleskDnsService())->planChanges($domain['domain_name'], $records, $settings['default_spf_policy']);
            $this->dnsRecordRepository->savePlans($domain['id'], $plans, 'plesk');

            return array('success' => false, 'message' => \pm_Locale::lmsg('dnsFixUnknownProvider'));
        }

        $plans = (new PleskDnsService())->planChanges($domain['domain_name'], $records, $settings['default_spf_policy']);
        $results = (new PleskDnsService())->apply($domain['domain_name'], $plans);
        $this->dnsRecordRepository->savePlans($domain['id'], $results, 'plesk');
        (new HealthCheckRunner())->refreshDomain($domain);

        return array('success' => !$this->hasProblems($results), 'message' => \pm_Locale::lmsg('dnsFixApplied'));
    }

    private function fixCloudflare(array $domain, array $records, array $settings)
    {
        $token = $this->settingsRepository->getCloudflareToken();
        if ($token === null) {
            return array('success' => false, 'message' => \pm_Locale::lmsg('cloudflareTokenMissing'));
        }

        try {
            $service = new CloudflareDnsService(new CloudflareClient($token));
            $zone = $service->detectOrCreateZone($domain['domain_name'], $settings['cloudflare_account_id']);
            if ($zone === null) {
                return array('success' => false, 'message' => \pm_Locale::lmsg('cloudflareZoneNotFound'));
            }

            $plans = $service->previewChanges($zone['id'], $domain['domain_name'], $records, $settings['default_spf_policy']);
            $results = $service->applyChanges($zone['id'], $plans);
            $this->dnsRecordRepository->savePlans($domain['id'], $results, 'cloudflare');
            (new HealthCheckRunner())->refreshDomain($domain);

            return array('success' => !$this->hasProblems($results), 'message' => \pm_Locale::lmsg('dnsFixApplied'));
        } catch (\Exception $e) {
            \pm_Log::err('SES Manager: DNS auto fix Cloudflare failed for ' . $domain['domain_name'] . ': ' . $e->getMessage());

            return array('success' => false, 'message' => \pm_Locale::lmsg('dnsFixFailed'));
        }
    }

    private function loadRecords(array $domain, $purpose)
    {
        $wanted = $this->wantedPurposes($purpose);
        $records = array();
        $seen = array();

        foreach ($this->dnsRecordRepository->findByDomainId($domain['id']) as $row) {
            if (!in_array($row['purpose'], $wanted, true)) {
                continue;
            }

            $record = new DnsRecord($row['type'], $row['name'], $row['value'], $row['purpose']);
            $key = strtolower($record->type . '|' . $record->name . '|' . preg_replace('/\s+/', ' ', trim($record->value)));
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $records[] = $record;
        }

        return $records;
    }

    private function wantedPurposes($purpose)
    {
        if ($purpose === 'all') {
            return array('dkim', 'spf', 'dmarc', 'mail_from_mx');
        }

        if ($purpose === 'mx') {
            return array('mail_from_mx');
        }

        return array($purpose);
    }

    private function hasProblems(array $results)
    {
        foreach ($results as $result) {
            if (in_array($result->status, array('failed', 'conflict'), true)) {
                return true;
            }
        }

        return false;
    }
}

