<?php

namespace Library\LongTask;

use Library\Repository\JobRepository;
use Library\Service\DomainVerificationService;
use Library\Service\LicenseService;

/**
 * Verifies a batch of Plesk domains one at a time via pm_LongTask_Manager.
 *
 * The task keeps one queued job row in sync while pm_LongTask_Manager
 * advances the selected domain list one item at a time.
 */
class BulkVerifyTask extends \pm_LongTask_Task
{
    public function getTitle()
    {
        return \pm_Locale::lmsg('bulkVerifyTaskTitle');
    }

    public function getDescription(): string
    {
        return $this->getTitle();
    }

    public function run()
    {
        $domains = (array) $this->getParam('domains', array());
        $jobId = (int) $this->getParam('jobId');
        $jobRepository = new JobRepository();
        $jobRepository->markRunning($jobId);

        $licenseService = new LicenseService();
        if (!$licenseService->canUseFeature(LicenseService::FEATURE_BULK_VERIFY)) {
            $jobRepository->markFinished($jobId, 'failed', 'Bulk verification is unavailable.');
            $this->updateProgress(100);
            return;
        }

        $filtered = $licenseService->filterRegisterableDomains($domains);
        $domains = $filtered['allowed'];
        $limitSkipped = $filtered['skipped'];

        $total = count($domains);
        if ($total === 0) {
            $message = empty($limitSkipped) ? 'No domains selected.' : 'Domain limit reached.';
            $jobRepository->markFinished($jobId, 'failed', $message);
            $this->updateProgress(100);
            return;
        }

        $failed = array();
        foreach ($limitSkipped as $domainName) {
            $failed[] = $domainName . ': limit_reached';
        }
        foreach ($domains as $index => $domain) {
            if (empty($domain['domainName']) || empty($domain['pleskDomainId'])) {
                $failed[] = 'invalid domain payload';
                continue;
            }

            try {
                $result = (new DomainVerificationService())->verifyDomain($domain['domainName'], (int) $domain['pleskDomainId']);
                if (!$result->success) {
                    $failed[] = $domain['domainName'] . ': ' . ($result->errorCode ?: 'failed');
                }
            } catch (\Exception $e) {
                $failed[] = $domain['domainName'] . ': exception';
                \pm_Log::err('SES Manager: bulk verify failed for ' . $domain['domainName'] . ': ' . $e->getMessage());
            }

            $this->setParam('index', $index + 1);
            $this->updateProgress((int) round((($index + 1) / $total) * 100));
        }

        if (empty($failed)) {
            $jobRepository->markFinished($jobId, 'success');
            return;
        }

        $jobRepository->markFinished($jobId, 'partial', implode('; ', array_slice($failed, 0, 5)));
    }
}
