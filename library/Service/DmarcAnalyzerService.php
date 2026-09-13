<?php

namespace Library\Service;

use Library\Client\DnsResolverClient;

class DmarcAnalyzerService
{
    /** @var DnsResolverClient */
    private $resolver;

    public function __construct(?DnsResolverClient $resolver = null)
    {
        $this->resolver = $resolver ?: new DnsResolverClient();
    }

    public function analyze($domainName)
    {
        $records = $this->findDmarcRecords($domainName);
        $record = isset($records[0]) ? $records[0] : null;
        $tags = $record ? $this->parseTags($record) : array();
        $issues = array();

        if (empty($records)) {
            $issues[] = 'DMARC record is missing.';
        }

        if (count($records) > 1) {
            $issues[] = 'Multiple DMARC records found.';
        }

        if ($record && (!isset($tags['p']) || !in_array(strtolower($tags['p']), array('none', 'quarantine', 'reject'), true))) {
            $issues[] = 'DMARC policy is missing or invalid.';
        }

        if ($record && isset($tags['p']) && strtolower($tags['p']) === 'none') {
            $issues[] = 'DMARC policy is monitoring only.';
        }

        if ($record && empty($tags['rua'])) {
            $issues[] = 'Aggregate reporting is not enabled.';
        }

        if ($record && isset($tags['pct']) && (!$this->isIntegerInRange($tags['pct'], 0, 100))) {
            $issues[] = 'DMARC pct value is invalid.';
        }

        $policy = isset($tags['p']) ? strtolower($tags['p']) : null;
        $status = $this->statusFromIssues($records, $policy, $issues);

        return array(
            'domain' => $domainName,
            'status' => $status,
            'present' => !empty($records),
            'record_count' => count($records),
            'record' => $record,
            'policy' => $policy,
            'subdomain_policy' => isset($tags['sp']) ? strtolower($tags['sp']) : null,
            'adkim' => isset($tags['adkim']) ? strtolower($tags['adkim']) : 'r',
            'aspf' => isset($tags['aspf']) ? strtolower($tags['aspf']) : 'r',
            'pct' => isset($tags['pct']) ? (int) $tags['pct'] : 100,
            'rua_enabled' => !empty($tags['rua']),
            'ruf_enabled' => !empty($tags['ruf']),
            'issues' => $issues,
        );
    }

    private function findDmarcRecords($domainName)
    {
        $records = array();
        foreach ($this->resolver->getTxtRecords('_dmarc.' . $domainName) as $txt) {
            if (stripos(trim($txt), 'v=DMARC1') === 0) {
                $records[] = trim($txt);
            }
        }

        return $records;
    }

    private function parseTags($record)
    {
        $tags = array();
        foreach (explode(';', $record) as $part) {
            $part = trim($part);
            if (strpos($part, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $part, 2);
            $tags[strtolower(trim($key))] = trim($value);
        }

        return $tags;
    }

    private function isIntegerInRange($value, $min, $max)
    {
        if (!preg_match('/^\d+$/', (string) $value)) {
            return false;
        }

        $intValue = (int) $value;
        return $intValue >= $min && $intValue <= $max;
    }

    private function statusFromIssues(array $records, $policy, array $issues)
    {
        if (empty($records)) {
            return 'missing';
        }

        if (count($records) > 1 || $policy === null) {
            return 'failed';
        }

        if ($policy === 'none') {
            return 'warning';
        }

        return empty($issues) ? 'pass' : 'warning';
    }
}
