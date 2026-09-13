<?php

namespace Library\Service;

use Library\Client\DnsResolverClient;

class SpfAnalyzerService
{
    /** @var DnsResolverClient */
    private $resolver;

    /** @var array */
    private $txtCache = array();

    public function __construct(?DnsResolverClient $resolver = null)
    {
        $this->resolver = $resolver ?: new DnsResolverClient();
    }

    public function analyze($domainName, $sesIncludeRequired = true)
    {
        $records = $this->findSpfRecords($domainName);
        $record = isset($records[0]) ? $records[0] : null;
        $lookupInfo = $record ? $this->countLookups($record, array(strtolower($domainName) => true)) : array('count' => 0, 'loops' => array());
        $policy = $record ? $this->extractPolicy($record) : null;
        $issues = array();

        if (empty($records)) {
            $issues[] = 'SPF record is missing.';
        }

        if (count($records) > 1) {
            $issues[] = 'Multiple SPF records found.';
        }

        if ($record && $sesIncludeRequired && stripos($record, 'include:amazonses.com') === false) {
            $issues[] = 'Amazon SES include is missing.';
        }

        if ($record && preg_match('/(?:^|\s)\+all(?:\s|$)/i', $record)) {
            $issues[] = 'Risky +all policy is used.';
        }

        if ($lookupInfo['count'] > 10) {
            $issues[] = 'SPF DNS lookup limit exceeded.';
        }

        if (!empty($lookupInfo['loops'])) {
            $issues[] = 'Recursive SPF include loop detected.';
        }

        $status = $this->statusFromIssues($records, $issues, $record, $lookupInfo['count']);

        return array(
            'domain' => $domainName,
            'status' => $status,
            'present' => !empty($records),
            'record_count' => count($records),
            'record' => $record,
            'policy' => $policy,
            'lookup_count' => $lookupInfo['count'],
            'lookup_limit' => 10,
            'ses_include' => $record ? stripos($record, 'include:amazonses.com') !== false : false,
            'risky_policy' => $record ? preg_match('/(?:^|\s)\+all(?:\s|$)/i', $record) === 1 : false,
            'loops' => array_values($lookupInfo['loops']),
            'issues' => $issues,
        );
    }

    private function findSpfRecords($domainName)
    {
        $records = array();
        foreach ($this->getTxtRecords($domainName) as $txt) {
            if (stripos(trim($txt), 'v=spf1') === 0) {
                $records[] = trim($txt);
            }
        }

        return $records;
    }

    private function getTxtRecords($domainName)
    {
        $key = strtolower($domainName);
        if (!array_key_exists($key, $this->txtCache)) {
            $this->txtCache[$key] = $this->resolver->getTxtRecords($domainName);
        }

        return $this->txtCache[$key];
    }

    private function countLookups($spfRecord, array $visited)
    {
        $count = 0;
        $loops = array();
        $terms = preg_split('/\s+/', trim($spfRecord));

        foreach ($terms as $term) {
            $term = trim($term);
            if ($term === '' || stripos($term, 'v=spf1') === 0) {
                continue;
            }

            $normalized = ltrim($term, '+-~?');
            if (preg_match('/^include:([^\/\s]+)/i', $normalized, $matches)) {
                $includeDomain = strtolower(rtrim($matches[1], '.'));
                $count++;

                if (isset($visited[$includeDomain])) {
                    $loops[] = $includeDomain;
                    continue;
                }

                $includedRecords = $this->findSpfRecords($includeDomain);
                if (!empty($includedRecords)) {
                    $nested = $this->countLookups($includedRecords[0], $visited + array($includeDomain => true));
                    $count += $nested['count'];
                    $loops = array_merge($loops, $nested['loops']);
                }

                continue;
            }

            if (preg_match('/^(a|mx|exists)(?::|$)/i', $normalized)) {
                $count++;
                continue;
            }

            if (preg_match('/^redirect=([^\/\s]+)/i', $normalized, $matches)) {
                $redirectDomain = strtolower(rtrim($matches[1], '.'));
                $count++;

                if (isset($visited[$redirectDomain])) {
                    $loops[] = $redirectDomain;
                    continue;
                }

                $redirectRecords = $this->findSpfRecords($redirectDomain);
                if (!empty($redirectRecords)) {
                    $nested = $this->countLookups($redirectRecords[0], $visited + array($redirectDomain => true));
                    $count += $nested['count'];
                    $loops = array_merge($loops, $nested['loops']);
                }
            }
        }

        return array('count' => $count, 'loops' => array_values(array_unique($loops)));
    }

    private function extractPolicy($spfRecord)
    {
        if (preg_match('/(?:^|\s)([+?~-]all)(?:\s|$)/i', $spfRecord, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function statusFromIssues(array $records, array $issues, $record, $lookupCount)
    {
        if (empty($records)) {
            return 'missing';
        }

        if (count($records) > 1 || $lookupCount > 10 || ($record && preg_match('/(?:^|\s)\+all(?:\s|$)/i', $record))) {
            return 'failed';
        }

        return empty($issues) ? 'pass' : 'warning';
    }
}
