<?php

namespace Library\Service;

use Library\Client\DnsResolverClient;
use Library\Service\Dto\DmarcPolicy;
use Library\Service\Dto\DnsRecord;
use Library\Service\Dto\HealthReport;

class HealthCheckService
{
    /** @var DnsResolverClient */
    private $resolver;

    /** @var SpfAnalyzerService */
    private $spfAnalyzer;

    /** @var DkimAnalyzerService */
    private $dkimAnalyzer;

    /** @var DmarcAnalyzerService */
    private $dmarcAnalyzer;

    public function __construct(?DnsResolverClient $resolver = null)
    {
        $this->resolver = $resolver ?: new DnsResolverClient();
        $this->spfAnalyzer = new SpfAnalyzerService($this->resolver);
        $this->dkimAnalyzer = new DkimAnalyzerService($this->resolver);
        $this->dmarcAnalyzer = new DmarcAnalyzerService($this->resolver);
    }

    /**
     * @param DnsRecord[] $expectedDkimRecords
     */
    public function checkDomain($domainName, array $expectedDkimRecords, $sesVerificationStatus, $spfDomainName = null)
    {
        $report = new HealthReport($domainName);
        $mailFromDomainName = $spfDomainName ?: $domainName;

        $report->sesStatus = $this->mapSesStatus($sesVerificationStatus);
        $report->spfAnalysis = $this->spfAnalyzer->analyze($mailFromDomainName);
        $report->dkimAnalysis = $this->dkimAnalyzer->analyze($expectedDkimRecords);
        $report->dmarcAnalysis = $this->dmarcAnalyzer->analyze($domainName);
        $report->mailFromAnalysis = $this->checkMailFromMx($mailFromDomainName);

        $report->spfStatus = $report->spfAnalysis['status'];
        $report->dkimStatus = $report->dkimAnalysis['status'];
        $report->dmarcStatus = $report->dmarcAnalysis['status'];
        $report->mxStatus = $report->mailFromAnalysis['status'];

        return $report;
    }

    public function calculateScore(HealthReport $report)
    {
        $weights = array(
            'sesStatus' => 20,
            'spfStatus' => 15,
            'dkimStatus' => 20,
            'dmarcStatus' => 15,
            'mxStatus' => 15,
        );

        $statusFactors = array(
            'pass' => 1.0,
            'optional' => 1.0,
            'warning' => 0.5,
            'pending' => 0.5,
            'missing' => 0.0,
            'failed' => 0.0,
        );

        $score = 0.0;
        foreach ($weights as $field => $weight) {
            $status = $report->$field;
            $factor = isset($statusFactors[$status]) ? $statusFactors[$status] : 0.0;
            $score += $weight * $factor;
        }

        if ($this->hasDnsConflict($report)) {
            return (int) max(0, round($score));
        }

        $score += 15;

        return (int) round($score);
    }

    public function parseDmarc($txt)
    {
        if (stripos($txt, 'v=DMARC1') === false) {
            return new DmarcPolicy(false);
        }

        $policy = null;
        if (preg_match('/p=(none|quarantine|reject)/i', $txt, $matches)) {
            $policy = strtolower($matches[1]);
        }

        return new DmarcPolicy(true, $policy, $txt);
    }

    private function mapSesStatus($verificationStatus)
    {
        $status = strtolower((string) $verificationStatus);

        if (in_array($status, array('success', 'verified'), true)) {
            return 'pass';
        }

        if (in_array($status, array('pending', 'not_started'), true)) {
            return 'pending';
        }

        return 'failed';
    }

    private function checkMailFromMx($domainName)
    {
        $mxRecords = $this->resolver->getMxRecords($domainName);
        $hasSesFeedbackMx = false;

        foreach ($mxRecords as $mxRecord) {
            if (stripos($mxRecord, 'feedback-smtp.') !== false && stripos($mxRecord, '.amazonses.com') !== false) {
                $hasSesFeedbackMx = true;
                break;
            }
        }

        if (empty($mxRecords)) {
            $status = 'missing';
        } elseif ($hasSesFeedbackMx) {
            $status = 'pass';
        } else {
            $status = 'warning';
        }

        return array(
            'domain' => $domainName,
            'status' => $status,
            'present' => !empty($mxRecords),
            'mx_records' => $mxRecords,
            'ses_feedback_mx' => $hasSesFeedbackMx,
            'issues' => $status === 'pass' ? array() : array('Custom MAIL FROM MX record is missing or does not point to Amazon SES feedback SMTP.'),
        );
    }

    private function hasDnsConflict(HealthReport $report)
    {
        if (isset($report->dkimAnalysis['conflict_count']) && (int) $report->dkimAnalysis['conflict_count'] > 0) {
            return true;
        }

        return false;
    }
}
