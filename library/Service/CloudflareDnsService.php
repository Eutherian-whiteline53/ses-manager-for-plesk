<?php

namespace Library\Service;

use Library\Client\CloudflareClient;
use Library\Repository\CloudflareZoneRepository;
use Library\Service\Dto\CloudflareChangeResult;
use Library\Service\Dto\DnsChangeResult;
use Library\Service\Dto\DnsRecord;

class CloudflareDnsService
{
    /** @var CloudflareClient */
    private $client;

    /** @var CloudflareZoneRepository */
    private $zoneRepository;

    public function __construct(CloudflareClient $client, ?CloudflareZoneRepository $zoneRepository = null)
    {
        $this->client = $client;
        $this->zoneRepository = $zoneRepository ?: new CloudflareZoneRepository();
    }

    /**
     * @return array{id:string,name:string}|null
     */
    public function detectZone($domain)
    {
        $cached = $this->zoneRepository->findByDomain($domain);
        if ($cached) {
            return array('id' => $cached['zone_id'], 'name' => $cached['zone_name']);
        }

        $zone = $this->client->findZoneForDomain($domain);
        if ($zone === null) {
            return null;
        }

        $this->zoneRepository->save($domain, $zone['id'], $zone['name']);

        return $zone;
    }

    /**
     * @return array{id:string,name:string}|null
     */
    public function detectOrCreateZone($domain, $accountId = null)
    {
        $zone = $this->detectZone($domain);
        if ($zone !== null) {
            return $zone;
        }

        $accountId = trim((string) $accountId);
        if ($accountId === '') {
            $accounts = $this->client->listAccounts();
            if (empty($accounts[0]['id'])) {
                return null;
            }
            $accountId = $accounts[0]['id'];
        }

        if ($accountId === '') {
            return null;
        }

        $zone = $this->client->createZone($domain, $accountId);
        $this->zoneRepository->save($domain, $zone['id'], $zone['name']);

        return $zone;
    }

    /**
     * @param DnsRecord[] $requiredRecords
     * @return CloudflareChangeResult[]
     */
    public function previewChanges($zoneId, $domain, array $requiredRecords, $defaultSpfPolicy = '~all')
    {
        $plans = array();

        foreach ($requiredRecords as $record) {
            if ($record->purpose === 'spf') {
                $plans[] = $this->planSpfRecord($zoneId, $domain, $record, $defaultSpfPolicy);
                continue;
            }

            if ($record->purpose === 'dmarc') {
                $plans[] = $this->planDmarcRecord($zoneId, $domain, $record);
                continue;
            }

            $plans[] = $this->planRecord($zoneId, $record);
        }

        return $plans;
    }

    /**
     * @param CloudflareChangeResult[] $plans
     * @return CloudflareChangeResult[]
     */
    public function applyChanges($zoneId, array $plans)
    {
        $results = array();

        foreach ($plans as $plan) {
            if (!in_array($plan->status, array(DnsChangeResult::STATUS_PLANNED, DnsChangeResult::STATUS_SPF_MERGE, DnsChangeResult::STATUS_TXT_UPDATE), true)) {
                $results[] = $plan;
                continue;
            }

            try {
                if (in_array($plan->status, array(DnsChangeResult::STATUS_SPF_MERGE, DnsChangeResult::STATUS_TXT_UPDATE), true) && $plan->recordId !== null) {
                    $this->client->updateDnsRecord($zoneId, $plan->recordId, $plan->record->type, $plan->record->name, $plan->record->value);
                } else {
                    $this->client->createDnsRecord($zoneId, $plan->record->type, $plan->record->name, $plan->record->value);
                }

                $results[] = new CloudflareChangeResult($plan->record, DnsChangeResult::STATUS_APPLIED, 'Record applied.');
            } catch (\Exception $e) {
                \pm_Log::err('SES Manager: failed to apply Cloudflare DNS record for ' . $plan->record->name);
                $results[] = new CloudflareChangeResult($plan->record, DnsChangeResult::STATUS_FAILED, 'Failed to apply DNS record.');
            }
        }

        return $results;
    }

    private function planRecord($zoneId, DnsRecord $record)
    {
        $existing = $this->client->listDnsRecords($zoneId, $record->type, $record->name);

        if (empty($existing)) {
            return new CloudflareChangeResult($record, DnsChangeResult::STATUS_PLANNED, 'Record will be added.');
        }

        foreach ($existing as $existingRecord) {
            if ($this->normalizeValue($this->existingRecordValue($existingRecord)) === $this->normalizeValue($record->value)) {
                return new CloudflareChangeResult($record, DnsChangeResult::STATUS_SKIPPED, 'Record already exists with the expected value.', $existingRecord['id']);
            }
        }

        return new CloudflareChangeResult($record, DnsChangeResult::STATUS_CONFLICT, 'An existing ' . $record->type . ' record has a different value and was not changed.', $existing[0]['id']);
    }

    private function planSpfRecord($zoneId, $domain, DnsRecord $record, $defaultSpfPolicy)
    {
        $existing = $this->client->listDnsRecords($zoneId, 'TXT', $record->name);

        $spfRecords = array();
        foreach ($existing as $existingRecord) {
            if (stripos($existingRecord['content'], 'v=spf1') === 0) {
                $spfRecords[] = $existingRecord;
            }
        }

        $requiredInclude = $this->extractRequiredSpfInclude($record->value);

        if (count($spfRecords) > 1) {
            return new CloudflareChangeResult($record, DnsChangeResult::STATUS_CONFLICT, 'Multiple SPF (v=spf1) TXT records found. Resolve manually before applying.');
        }

        $pleskDnsService = new PleskDnsService();

        if (empty($spfRecords)) {
            $merged = $pleskDnsService->mergeSpf('', $requiredInclude, $defaultSpfPolicy);
            $newRecord = new DnsRecord('TXT', $record->name, $merged, 'spf');

            return new CloudflareChangeResult($newRecord, DnsChangeResult::STATUS_PLANNED, 'SPF record will be created.');
        }

        $existingSpf = $spfRecords[0];

        if (strpos($existingSpf['content'], 'include:' . $requiredInclude) !== false) {
            $existingDnsRecord = new DnsRecord('TXT', $record->name, $existingSpf['content'], 'spf');

            return new CloudflareChangeResult($existingDnsRecord, DnsChangeResult::STATUS_SKIPPED, 'SPF record already includes ' . $requiredInclude . '.', $existingSpf['id']);
        }

        $merged = $pleskDnsService->mergeSpf($existingSpf['content'], $requiredInclude, $defaultSpfPolicy);
        $mergedRecord = new DnsRecord('TXT', $record->name, $merged, 'spf');
        $changeResult = new CloudflareChangeResult($mergedRecord, DnsChangeResult::STATUS_SPF_MERGE, 'SPF record will be merged: ' . $existingSpf['content'] . ' -> ' . $merged, $existingSpf['id']);
        $changeResult->previousValue = $existingSpf['content'];

        return $changeResult;
    }

    private function planDmarcRecord($zoneId, $domain, DnsRecord $record)
    {
        $existing = $this->client->listDnsRecords($zoneId, 'TXT', $record->name);

        $dmarcRecords = array();
        foreach ($existing as $existingRecord) {
            if (stripos($existingRecord['content'], 'v=DMARC1') === 0) {
                $dmarcRecords[] = $existingRecord;
            }
        }

        $pleskDnsService = new PleskDnsService();
        $desiredValue = $pleskDnsService->mergeDmarc('', $domain, $record->value);

        if (empty($dmarcRecords)) {
            return new CloudflareChangeResult(new DnsRecord('TXT', $record->name, $desiredValue, 'dmarc'), DnsChangeResult::STATUS_PLANNED, 'DMARC record will be created.');
        }

        if (count($dmarcRecords) > 1) {
            return new CloudflareChangeResult($record, DnsChangeResult::STATUS_CONFLICT, 'Multiple DMARC TXT records found. Resolve manually before applying.', $dmarcRecords[0]['id']);
        }

        $existingDmarc = $dmarcRecords[0];
        $merged = $pleskDnsService->mergeDmarc($existingDmarc['content'], $domain, $record->value);

        if ($this->normalizeValue($existingDmarc['content']) === $this->normalizeValue($merged)) {
            return new CloudflareChangeResult(new DnsRecord('TXT', $record->name, $existingDmarc['content'], 'dmarc'), DnsChangeResult::STATUS_SKIPPED, 'DMARC record already has the expected value.', $existingDmarc['id']);
        }

        $mergedRecord = new DnsRecord('TXT', $record->name, $merged, 'dmarc');
        $changeResult = new CloudflareChangeResult($mergedRecord, DnsChangeResult::STATUS_TXT_UPDATE, 'DMARC record will be updated: ' . $existingDmarc['content'] . ' -> ' . $merged, $existingDmarc['id']);
        $changeResult->previousValue = $existingDmarc['content'];

        return $changeResult;
    }

    private function normalizeValue($value)
    {
        return strtolower(trim($value, " \t\n\r\0\x0B.\""));
    }

    private function existingRecordValue(array $record)
    {
        if (isset($record['type']) && strtoupper($record['type']) === 'MX' && isset($record['priority'])) {
            return $record['priority'] . ' ' . $record['content'];
        }

        return $record['content'];
    }

    private function extractRequiredSpfInclude($value)
    {
        $value = trim($value);

        if (preg_match('/(?:^|\s)include:([^\s]+)/i', $value, $matches)) {
            return $matches[1];
        }

        return preg_replace('/^include:/i', '', $value);
    }
}
