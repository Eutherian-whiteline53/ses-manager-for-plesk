<?php

namespace Library\Service;

use Library\Client\DnsResolverClient;

class DkimAnalyzerService
{
    /** @var DnsResolverClient */
    private $resolver;

    public function __construct(?DnsResolverClient $resolver = null)
    {
        $this->resolver = $resolver ?: new DnsResolverClient();
    }

    public function analyze(array $expectedDkimRecords)
    {
        $records = array();
        $matched = 0;
        $found = 0;
        $conflicts = 0;

        foreach ($expectedDkimRecords as $record) {
            $values = $this->resolver->getCnameRecords($record->name);
            $expected = rtrim(strtolower($record->value), '.');
            $isMatched = false;
            $hasConflict = false;

            if (!empty($values)) {
                $found++;
            }

            foreach ($values as $value) {
                $actual = rtrim(strtolower($value), '.');
                if ($actual === $expected) {
                    $isMatched = true;
                    continue;
                }

                $hasConflict = true;
            }

            if ($isMatched) {
                $matched++;
            }

            if ($hasConflict) {
                $conflicts++;
            }

            $records[] = array(
                'name' => $record->name,
                'expected' => $record->value,
                'actual' => $values,
                'status' => $isMatched ? 'pass' : (!empty($values) ? 'conflict' : 'missing'),
            );
        }

        $expectedCount = count($expectedDkimRecords);
        if ($expectedCount === 0) {
            $status = 'missing';
        } elseif ($matched === $expectedCount) {
            $status = 'pass';
        } elseif ($conflicts > 0) {
            $status = 'failed';
        } else {
            $status = $found > 0 ? 'pending' : 'missing';
        }

        return array(
            'status' => $status,
            'expected_count' => $expectedCount,
            'matched_count' => $matched,
            'found_count' => $found,
            'conflict_count' => $conflicts,
            'records' => $records,
            'issues' => $this->buildIssues($expectedCount, $matched, $conflicts),
        );
    }

    private function buildIssues($expectedCount, $matched, $conflicts)
    {
        $issues = array();
        if ($expectedCount === 0) {
            $issues[] = 'Expected SES DKIM records are not planned yet.';
        } elseif ($matched < $expectedCount) {
            $issues[] = 'Not all expected SES DKIM CNAME records resolve correctly.';
        }

        if ($conflicts > 0) {
            $issues[] = 'One or more DKIM CNAME records point to unexpected targets.';
        }

        return $issues;
    }
}
