<?php

namespace Library\Service;

use Library\Repository\JobRepository;

class AutoDomainJobRunner
{
    /** @var JobRepository */
    private $jobRepository;

    public function __construct(?JobRepository $jobRepository = null)
    {
        $this->jobRepository = $jobRepository ?: new JobRepository();
    }

    public function run($limit = 5)
    {
        $processed = 0;

        while ($processed < $limit) {
            $job = $this->jobRepository->findNextQueuedByType('auto_domain_verify');
            if (!$job) {
                break;
            }

            $this->processJob($job);
            $processed++;
        }

        return $processed;
    }

    private function processJob(array $job)
    {
        $payload = $this->jobRepository->decodePayload($job);
        $domainName = isset($payload['domainName']) ? $payload['domainName'] : null;
        $pleskDomainId = isset($payload['pleskDomainId']) ? (int) $payload['pleskDomainId'] : 0;

        $this->jobRepository->markRunning($job['id']);
        $this->jobRepository->incrementAttempts($job['id']);

        if ($domainName === null || $pleskDomainId <= 0) {
            $this->jobRepository->markFinished($job['id'], 'failed', 'Invalid job payload.');
            return;
        }

        $licenseService = new LicenseService();
        if (!$licenseService->canUseFeature(LicenseService::FEATURE_AUTO_SES_ON_CREATE)) {
            $this->jobRepository->markFinished($job['id'], 'failed', 'Automatic SES integration is unavailable.');
            return;
        }

        try {
            $result = (new DomainVerificationService())->verifyAndApplyDomain($domainName, $pleskDomainId);
            $status = $result['status'] === 'success' ? 'success' : ($result['status'] === 'partial' ? 'partial' : 'failed');
            $this->jobRepository->markFinished($job['id'], $status, $result['message']);
        } catch (\Exception $e) {
            \pm_Log::err('SES Manager: auto domain verify job failed for ' . $domainName . ': ' . $e->getMessage());
            $this->jobRepository->markFinished($job['id'], 'failed', 'Unexpected automation error.');
        }
    }
}
