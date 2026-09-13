<?php

namespace Library\Service;

use Library\Client\CloudflareClient;
use Library\Repository\DnsRecordRepository;
use Library\Repository\DomainRepository;
use Library\Repository\SettingsRepository;
use Library\Service\Dto\DnsRecord;
use Library\Service\Dto\VerificationResult;

class DomainVerificationService
{
    /**
     * Creates a SES email identity for the given Plesk domain and plans the
     * required DKIM/SPF DNS records. Shared by DomainsController and the
     * bulk verify long task.
     */
    public function verifyDomain($domainName, $pleskDomainId)
    {
        $settingsRepository = new SettingsRepository();
        $credentials = $settingsRepository->getAwsCredentials();
        $settings = $settingsRepository->get();

        if ($credentials['accessKey'] === null || $credentials['secretKey'] === null) {
            return new VerificationResult(false, VerificationResult::ERROR_CREDENTIALS_MISSING);
        }

        $licenseService = new LicenseService();
        if (!$licenseService->canRegisterDomain($domainName)) {
            return new VerificationResult(false, VerificationResult::ERROR_LIMIT_REACHED);
        }

        $awsService = new AwsSesService();

        try {
            $identity = $awsService->createEmailIdentity($credentials['accessKey'], $credentials['secretKey'], $credentials['region'], $domainName);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'already exist') !== false) {
                try {
                    $identity = $awsService->getIdentityStatus($credentials['accessKey'], $credentials['secretKey'], $credentials['region'], $domainName);
                } catch (\Exception $e2) {
                    \pm_Log::err('SES Manager: failed to create SES identity for ' . $domainName . ': ' . $e2->getMessage());
                    return new VerificationResult(false, VerificationResult::ERROR_IDENTITY_FAILED);
                }
            } else {
                \pm_Log::err('SES Manager: failed to create SES identity for ' . $domainName . ': ' . $e->getMessage());
                return new VerificationResult(false, VerificationResult::ERROR_IDENTITY_FAILED);
            }
        }

        $domainRepository = new DomainRepository();
        $domainId = $domainRepository->registerDomain($domainName, $pleskDomainId, $credentials['region']);
        $domainRepository->updateVerification($domainId, strtolower($identity->verificationStatus));

        $mailFromDomain = $awsService->buildMailFromDomain($domainName, $settings['mail_from_subdomain']);
        try {
            $awsService->configureMailFromDomain($credentials['accessKey'], $credentials['secretKey'], $credentials['region'], $domainName, $mailFromDomain);
        } catch (\Exception $e) {
            \pm_Log::err('SES Manager: failed to configure custom MAIL FROM for ' . $domainName . ': ' . $e->getMessage());
        }

        $dnsService = new PleskDnsService();
        $plans = $dnsService->planChanges($domainName, $this->buildRequiredRecords($awsService, $identity, $domainName, $settings, $credentials['region']), $settings['default_spf_policy']);

        $dnsRecordRepository = new DnsRecordRepository();
        $dnsRecordRepository->savePlans($domainId, $plans);

        return new VerificationResult(true, null, $domainId);
    }

    public function verifyAndApplyDomain($domainName, $pleskDomainId)
    {
        $settingsRepository = new SettingsRepository();
        $credentials = $settingsRepository->getAwsCredentials();
        $settings = $settingsRepository->get();

        if ($credentials['accessKey'] === null || $credentials['secretKey'] === null) {
            return array('status' => 'failed', 'message' => 'AWS credentials are missing.', 'domainId' => null);
        }

        $licenseService = new LicenseService();
        if (!$licenseService->canRegisterDomain($domainName)) {
            return array('status' => 'failed', 'message' => 'Domain limit reached.', 'domainId' => null);
        }

        $awsService = new AwsSesService();
        $identity = $this->createOrFetchIdentity($awsService, $credentials, $domainName);
        if ($identity === null) {
            return array('status' => 'failed', 'message' => 'Could not create or read SES identity.', 'domainId' => null);
        }

        $domainRepository = new DomainRepository();
        $domainId = $domainRepository->registerDomain($domainName, $pleskDomainId, $credentials['region']);
        $domainRepository->updateVerification($domainId, strtolower($identity->verificationStatus));

        $mailFromDomain = $awsService->buildMailFromDomain($domainName, $settings['mail_from_subdomain']);
        try {
            $awsService->configureMailFromDomain($credentials['accessKey'], $credentials['secretKey'], $credentials['region'], $domainName, $mailFromDomain);
        } catch (\Exception $e) {
            \pm_Log::err('SES Manager: failed to configure custom MAIL FROM for ' . $domainName . ': ' . $e->getMessage());
        }

        $requiredRecords = $this->buildRequiredRecords($awsService, $identity, $domainName, $settings, $credentials['region']);

        $dnsRecordRepository = new DnsRecordRepository();
        $messages = array('identity created or already exists');
        $partial = false;
        $dnsProvider = (new DnsProviderDetector())->detect($domainName);
        $licenseService = new LicenseService();

        if (!$licenseService->canUseFeature(LicenseService::FEATURE_AUTO_DNS_SETUP)) {
            $this->savePleskDnsPlans($domainName, $domainId, $requiredRecords, $settings, $dnsRecordRepository);

            return array(
                'status' => 'partial',
                'message' => 'identity created or already exists; automatic dns setup is unavailable, dns changes planned only',
                'domainId' => $domainId,
            );
        }

        if ($dnsProvider === DnsProviderDetector::PROVIDER_CLOUDFLARE) {
            $cloudflareToken = $settingsRepository->getCloudflareToken();
            if (!$licenseService->canUseFeature(LicenseService::FEATURE_CLOUDFLARE_DNS)) {
                $partial = true;
                $messages[] = 'cloudflare nameservers detected but cloudflare dns automation is unavailable';
                $this->savePleskDnsPlans($domainName, $domainId, $requiredRecords, $settings, $dnsRecordRepository);
            } elseif ($cloudflareToken === null) {
                $partial = true;
                $messages[] = 'cloudflare nameservers detected but token is missing';
                $this->savePleskDnsPlans($domainName, $domainId, $requiredRecords, $settings, $dnsRecordRepository);
            } else {
                $cloudflareResult = $this->applyCloudflareRecords($cloudflareToken, $settings['cloudflare_account_id'], $domainName, $domainId, $requiredRecords, $settings['default_spf_policy'], $dnsRecordRepository);
                $messages[] = $cloudflareResult['message'];
                $partial = $partial || $cloudflareResult['partial'];
            }
        } elseif ($dnsProvider === DnsProviderDetector::PROVIDER_PLESK) {
            $pleskResults = $this->applyPleskRecords($domainName, $domainId, $requiredRecords, $settings, $dnsRecordRepository);
            $messages[] = 'plesk dns processed';
            $partial = $partial || $this->hasDnsProblems($pleskResults);
        } else {
            $this->savePleskDnsPlans($domainName, $domainId, $requiredRecords, $settings, $dnsRecordRepository);
            $messages[] = 'unknown nameservers, dns changes planned only';
            $partial = true;
        }

        if (trim((string) $settings['sns_topic_arn']) !== '' && $licenseService->canUseFeature(LicenseService::FEATURE_SNS_AUTOMATION)) {
            $snsResult = (new SnsSetupService())->configureIdentityTopics($domainName);
            $messages[] = 'sns feedback topics: ' . $snsResult['message'];
            $partial = $partial || !$snsResult['success'];
        } elseif (trim((string) $settings['sns_topic_arn']) !== '') {
            $messages[] = 'sns feedback topics skipped because automation is unavailable';
            $partial = true;
        }

        return array(
            'status' => $partial ? 'partial' : 'success',
            'message' => implode('; ', $messages),
            'domainId' => $domainId,
        );
    }

    private function createOrFetchIdentity(AwsSesService $awsService, array $credentials, $domainName)
    {
        try {
            return $awsService->createEmailIdentity($credentials['accessKey'], $credentials['secretKey'], $credentials['region'], $domainName);
        } catch (\Exception $e) {
            try {
                return $awsService->getIdentityStatus($credentials['accessKey'], $credentials['secretKey'], $credentials['region'], $domainName);
            } catch (\Exception $e2) {
                \pm_Log::err('SES Manager: failed to create or fetch SES identity for ' . $domainName . ': create=' . $e->getMessage() . '; get=' . $e2->getMessage());
                return null;
            }
        }
    }

    private function buildRequiredRecords(AwsSesService $awsService, $identity, $domainName, array $settings, $region)
    {
        return array_merge(
            $awsService->getDkimRecords($identity),
            $awsService->getMailFromRecords($domainName, $settings['mail_from_subdomain'], $region),
            array($awsService->getDmarcRecord($domainName, $settings['default_dmarc_policy']))
        );
    }

    private function applyPleskRecords($domainName, $domainId, array $requiredRecords, array $settings, DnsRecordRepository $dnsRecordRepository)
    {
        $dnsService = new PleskDnsService();
        $plans = $dnsService->planChanges($domainName, $requiredRecords, $settings['default_spf_policy']);
        $results = $dnsService->apply($domainName, $plans);
        $dnsRecordRepository->savePlans($domainId, $results, 'plesk');

        return $results;
    }

    private function savePleskDnsPlans($domainName, $domainId, array $requiredRecords, array $settings, DnsRecordRepository $dnsRecordRepository)
    {
        $plans = (new PleskDnsService())->planChanges($domainName, $requiredRecords, $settings['default_spf_policy']);
        $dnsRecordRepository->savePlans($domainId, $plans, 'plesk');
    }

    private function applyCloudflareRecords($token, $accountId, $domainName, $domainId, array $requiredRecords, $defaultSpfPolicy, DnsRecordRepository $dnsRecordRepository)
    {
        try {
            $client = new CloudflareClient($token);
            $service = new CloudflareDnsService($client);
            $zone = $service->detectOrCreateZone($domainName, $accountId);

            if ($zone === null) {
                return array('partial' => true, 'message' => 'cloudflare zone not found');
            }

            $plans = $service->previewChanges($zone['id'], $domainName, $requiredRecords, $defaultSpfPolicy);
            $results = $service->applyChanges($zone['id'], $plans);
            $dnsRecordRepository->savePlans($domainId, $results, 'cloudflare');

            return array(
                'partial' => $this->hasDnsProblems($results),
                'message' => 'cloudflare dns processed',
            );
        } catch (\Exception $e) {
            \pm_Log::err('SES Manager: auto Cloudflare sync failed for ' . $domainName . ': ' . $e->getMessage());
            return array('partial' => true, 'message' => 'cloudflare failed');
        }
    }

    private function hasDnsProblems(array $results)
    {
        foreach ($results as $result) {
            if (in_array($result->status, array('failed', 'conflict'), true)) {
                return true;
            }
        }

        return false;
    }
}
