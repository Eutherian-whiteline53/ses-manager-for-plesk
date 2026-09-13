<?php

namespace Library\Service;

use Library\Client\PleskCliClient;
use Library\Service\Dto\DnsChangeResult;
use Library\Service\Dto\DnsRecord;

class PleskDnsService
{
    /** @var PleskCliClient */
    private $cli;

    public function __construct(?PleskCliClient $cli = null)
    {
        $this->cli = $cli ?: new PleskCliClient();
    }

    /**
     * @return DnsRecord[]
     */
    public function getExistingRecords($domain)
    {
        $records = array();

        foreach ($this->cli->getRecords($domain) as $row) {
            $records[] = new DnsRecord($row['type'], $row['host'], $row['value']);
        }

        return $records;
    }

    /**
     * @param DnsRecord[] $requiredRecords
     * @return DnsChangeResult[]
     */
    public function planChanges($domain, array $requiredRecords, $defaultSpfPolicy = '~all')
    {
        $existing = $this->getExistingRecords($domain);
        $plans = array();

        foreach ($requiredRecords as $record) {
            if ($record->purpose === 'spf') {
                $plans[] = $this->planSpfRecord($domain, $record, $existing, $defaultSpfPolicy);
                continue;
            }

            if ($record->purpose === 'dmarc') {
                $plans[] = $this->planDmarcRecord($domain, $record, $existing);
                continue;
            }

            $plans[] = $this->planRecord($domain, $record, $existing);
        }

        return $plans;
    }

    /**
     * @param DnsChangeResult[] $plans
     * @return DnsChangeResult[]
     */
    public function apply($domain, array $plans)
    {
        $results = array();

        foreach ($plans as $plan) {
            if (!in_array($plan->status, array(DnsChangeResult::STATUS_PLANNED, DnsChangeResult::STATUS_SPF_MERGE, DnsChangeResult::STATUS_TXT_UPDATE), true)) {
                $results[] = $plan;
                continue;
            }

            try {
                if (in_array($plan->status, array(DnsChangeResult::STATUS_SPF_MERGE, DnsChangeResult::STATUS_TXT_UPDATE), true)) {
                    $this->cli->removeRecord($domain, 'TXT', $this->relativeName($domain, $plan->record->name), $plan->previousValue);
                }

                $this->cli->addRecord($domain, $plan->record->type, $this->relativeName($domain, $plan->record->name), $plan->record->value);

                $results[] = new DnsChangeResult($plan->record, DnsChangeResult::STATUS_APPLIED, 'Record applied.');
            } catch (\Exception $e) {
                \pm_Log::err('SES Manager: failed to apply DNS record for ' . $domain);
                $results[] = new DnsChangeResult($plan->record, DnsChangeResult::STATUS_FAILED, 'Failed to apply DNS record.');
            }
        }

        return $results;
    }

    /**
     * SPF kayıtlarını overwrite etmeden merge eder.
     *
     * Örnek:
     *   Mevcut:   v=spf1 include:_spf.google.com ~all
     *   Gerekli:  include:amazonses.com
     *   Sonuç:    v=spf1 include:_spf.google.com include:amazonses.com ~all
     */
    public function mergeSpf($currentValue, $requiredInclude, $defaultPolicy = '~all')
    {
        $currentValue = trim($currentValue);

        if ($currentValue === '') {
            return sprintf('v=spf1 include:%s %s', $requiredInclude, $defaultPolicy);
        }

        if (strpos($currentValue, 'include:' . $requiredInclude) !== false) {
            return $currentValue;
        }

        if (preg_match('/\s([+\-~?]all)\s*$/', $currentValue, $matches)) {
            $allMechanism = $matches[1];
            $withoutAll = substr($currentValue, 0, -strlen($allMechanism));

            return rtrim($withoutAll) . ' include:' . $requiredInclude . ' ' . $allMechanism;
        }

        return $currentValue . ' include:' . $requiredInclude;
    }

    private function planRecord($domain, DnsRecord $record, array $existing)
    {
        foreach ($existing as $existingRecord) {
            if (strtoupper($existingRecord->type) !== strtoupper($record->type)) {
                continue;
            }

            if ($this->normalizeName($domain, $existingRecord->name) !== $this->normalizeName($domain, $record->name)) {
                continue;
            }

            if ($this->normalizeValue($existingRecord->value) === $this->normalizeValue($record->value)) {
                return new DnsChangeResult($record, DnsChangeResult::STATUS_SKIPPED, 'Record already exists with the expected value.');
            }

            return new DnsChangeResult($record, DnsChangeResult::STATUS_CONFLICT, 'An existing ' . $record->type . ' record has a different value and was not changed.');
        }

        return new DnsChangeResult($record, DnsChangeResult::STATUS_PLANNED, 'Record will be added.');
    }

    private function planSpfRecord($domain, DnsRecord $record, array $existing, $defaultSpfPolicy)
    {
        $spfRecords = array();
        foreach ($existing as $existingRecord) {
            if (strtoupper($existingRecord->type) === 'TXT'
                && $this->normalizeName($domain, $existingRecord->name) === $this->normalizeName($domain, $record->name)
                && stripos($existingRecord->value, 'v=spf1') === 0
            ) {
                $spfRecords[] = $existingRecord;
            }
        }

        $requiredInclude = $this->extractRequiredSpfInclude($record->value);

        if (count($spfRecords) > 1) {
            return new DnsChangeResult($record, DnsChangeResult::STATUS_CONFLICT, 'Multiple SPF (v=spf1) TXT records found. Resolve manually before applying.');
        }

        if (empty($spfRecords)) {
            $merged = $this->mergeSpf('', $requiredInclude, $defaultSpfPolicy);
            $newRecord = new DnsRecord('TXT', $record->name, $merged, 'spf');

            return new DnsChangeResult($newRecord, DnsChangeResult::STATUS_PLANNED, 'SPF record will be created.');
        }

        $existingSpf = $spfRecords[0];

        if (strpos($existingSpf->value, 'include:' . $requiredInclude) !== false) {
            $existingDnsRecord = new DnsRecord('TXT', $record->name, $existingSpf->value, 'spf');

            return new DnsChangeResult($existingDnsRecord, DnsChangeResult::STATUS_SKIPPED, 'SPF record already includes ' . $requiredInclude . '.');
        }

        $merged = $this->mergeSpf($existingSpf->value, $requiredInclude, $defaultSpfPolicy);
        $mergedRecord = new DnsRecord('TXT', $existingSpf->name, $merged, 'spf');
        $changeResult = new DnsChangeResult($mergedRecord, DnsChangeResult::STATUS_SPF_MERGE, 'SPF record will be merged: ' . $existingSpf->value . ' -> ' . $merged);
        $changeResult->previousValue = $existingSpf->value;

        return $changeResult;
    }

    private function planDmarcRecord($domain, DnsRecord $record, array $existing)
    {
        $dmarcRecords = array();
        foreach ($existing as $existingRecord) {
            if (strtoupper($existingRecord->type) === 'TXT'
                && $this->normalizeName($domain, $existingRecord->name) === $this->normalizeName($domain, $record->name)
                && stripos($existingRecord->value, 'v=DMARC1') === 0
            ) {
                $dmarcRecords[] = $existingRecord;
            }
        }

        $desiredValue = $this->mergeDmarc('', $domain, $record->value);
        if (empty($dmarcRecords)) {
            return new DnsChangeResult(new DnsRecord('TXT', $record->name, $desiredValue, 'dmarc'), DnsChangeResult::STATUS_PLANNED, 'DMARC record will be created.');
        }

        if (count($dmarcRecords) > 1) {
            return new DnsChangeResult($record, DnsChangeResult::STATUS_CONFLICT, 'Multiple DMARC TXT records found. Resolve manually before applying.');
        }

        $existingDmarc = $dmarcRecords[0];
        $merged = $this->mergeDmarc($existingDmarc->value, $domain, $record->value);

        if ($this->normalizeValue($existingDmarc->value) === $this->normalizeValue($merged)) {
            return new DnsChangeResult(new DnsRecord('TXT', $existingDmarc->name, $existingDmarc->value, 'dmarc'), DnsChangeResult::STATUS_SKIPPED, 'DMARC record already has the expected value.');
        }

        $mergedRecord = new DnsRecord('TXT', $existingDmarc->name, $merged, 'dmarc');
        $changeResult = new DnsChangeResult($mergedRecord, DnsChangeResult::STATUS_TXT_UPDATE, 'DMARC record will be updated: ' . $existingDmarc->value . ' -> ' . $merged);
        $changeResult->previousValue = $existingDmarc->value;

        return $changeResult;
    }

    public function mergeDmarc($currentValue, $domain, $fallbackValue)
    {
        $tags = $this->parseDmarcTags($currentValue !== '' ? $currentValue : $fallbackValue);
        $fallbackTags = $this->parseDmarcTags($fallbackValue);

        $policy = isset($tags['p']) && in_array(strtolower($tags['p']), array('none', 'quarantine', 'reject'), true)
            ? strtolower($tags['p'])
            : (isset($fallbackTags['p']) ? strtolower($fallbackTags['p']) : 'none');

        $tags['v'] = 'DMARC1';
        $tags['p'] = $policy;

        if (empty($tags['rua'])) {
            $tags['rua'] = 'mailto:dmarc@' . $domain;
        }

        if (empty($tags['pct'])) {
            $tags['pct'] = '100';
        }

        $orderedKeys = array('v', 'p', 'sp', 'adkim', 'aspf', 'rua', 'ruf', 'pct', 'fo');
        $parts = array();
        foreach ($orderedKeys as $key) {
            if (isset($tags[$key]) && $tags[$key] !== '') {
                $parts[] = $key . '=' . $tags[$key];
            }
        }

        foreach ($tags as $key => $value) {
            if (in_array($key, $orderedKeys, true) || $value === '') {
                continue;
            }

            $parts[] = $key . '=' . $value;
        }

        return implode('; ', $parts);
    }

    private function parseDmarcTags($record)
    {
        $tags = array();
        foreach (explode(';', (string) $record) as $part) {
            $part = trim($part);
            if (strpos($part, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $part, 2);
            $tags[strtolower(trim($key))] = trim($value);
        }

        return $tags;
    }

    private function normalizeName($domain, $name)
    {
        $name = rtrim($name, '.');

        if ($name === '' || $name === '@' || strcasecmp($name, $domain) === 0) {
            return strtolower($domain);
        }

        if (strcasecmp(substr($name, -strlen('.' . $domain)), '.' . $domain) === 0) {
            return strtolower($name);
        }

        return strtolower($name . '.' . $domain);
    }

    private function relativeName($domain, $name)
    {
        $normalized = $this->normalizeName($domain, $name);

        if ($normalized === strtolower($domain)) {
            return '';
        }

        return substr($normalized, 0, -strlen('.' . $domain));
    }

    private function normalizeValue($value)
    {
        return strtolower(trim($value, " \t\n\r\0\x0B."));
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
