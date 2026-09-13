<?php

namespace Library\Service;

use Library\Repository\DnsRecordRepository;
use Library\Repository\DomainRepository;
use Library\Repository\SettingsRepository;
use Library\Service\Dto\DnsRecord;

class HealthCheckRunner
{
    /** @var HealthCheckService */
    private $healthCheckService;

    /** @var DomainRepository */
    private $domainRepository;

    /** @var DnsRecordRepository */
    private $dnsRecordRepository;

    /** @var SettingsRepository */
    private $settingsRepository;

    public function __construct(
        ?HealthCheckService $healthCheckService = null,
        ?DomainRepository $domainRepository = null,
        ?DnsRecordRepository $dnsRecordRepository = null,
        ?SettingsRepository $settingsRepository = null
    ) {
        $this->healthCheckService = $healthCheckService ?: new HealthCheckService();
        $this->domainRepository = $domainRepository ?: new DomainRepository();
        $this->dnsRecordRepository = $dnsRecordRepository ?: new DnsRecordRepository();
        $this->settingsRepository = $settingsRepository ?: new SettingsRepository();
    }

    public function refreshDomain(array $domain)
    {
        $domain = $this->refreshSesIdentityStatus($domain);

        $expectedDkimRecords = array();
        foreach ($this->dnsRecordRepository->findByDomainId($domain['id']) as $row) {
            if ($row['purpose'] === 'dkim') {
                $expectedDkimRecords[] = new DnsRecord($row['type'], $row['name'], $row['value'], $row['purpose']);
            }
        }

        $settings = $this->settingsRepository->get();
        $spfDomainName = $this->buildMailFromDomain($domain['domain_name'], $settings['mail_from_subdomain']);
        $report = $this->healthCheckService->checkDomain($domain['domain_name'], $expectedDkimRecords, $domain['verification_status'], $spfDomainName);
        $score = $this->healthCheckService->calculateScore($report);

        $this->domainRepository->updateHealthAnalysis($domain['id'], array(
            'spf_status' => $report->spfStatus,
            'dkim_status' => $report->dkimStatus,
            'dmarc_status' => $report->dmarcStatus,
            'mx_status' => $report->mxStatus,
        ), array(
            'spf' => $report->spfAnalysis,
            'dkim' => $report->dkimAnalysis,
            'dmarc' => $report->dmarcAnalysis,
            'mail_from' => $report->mailFromAnalysis,
        ), $score);

        return $report;
    }

    private function refreshSesIdentityStatus(array $domain)
    {
        $credentials = $this->settingsRepository->getAwsCredentials();

        if ($credentials['accessKey'] === null || $credentials['secretKey'] === null) {
            return $domain;
        }

        try {
            $identity = (new AwsSesService())->getIdentityStatus(
                $credentials['accessKey'],
                $credentials['secretKey'],
                $domain['region'] ?: $credentials['region'],
                $domain['domain_name']
            );

            $verificationStatus = strtolower($identity->verificationStatus);
            $this->domainRepository->updateVerification($domain['id'], $verificationStatus);
            $domain['verification_status'] = $verificationStatus;
        } catch (\Exception $e) {
            \pm_Log::err('SES Manager: failed to refresh SES identity status for ' . $domain['domain_name'] . ': ' . $e->getMessage());
        }

        return $domain;
    }

    private function buildMailFromDomain($domainName, $mailFromSubdomain)
    {
        $mailFromSubdomain = trim(strtolower($mailFromSubdomain), ". \t\n\r\0\x0B");

        return ($mailFromSubdomain === '' ? 'bounce' : $mailFromSubdomain) . '.' . $domainName;
    }

    /**
     * Cache TTL süresi dolmuş domainleri yeniler, kaç domain yenilendiğini döner.
     */
    public function refreshStaleDomains($cacheTtlSeconds)
    {
        $now = time();
        $refreshed = 0;

        foreach ($this->domainRepository->findAll() as $domain) {
            $lastChecked = $domain['last_checked_at'] ? strtotime($domain['last_checked_at']) : null;

            if ($lastChecked !== null && ($now - $lastChecked) < $cacheTtlSeconds) {
                continue;
            }

            $this->refreshDomain($domain);
            $refreshed++;
        }

        return $refreshed;
    }
}
