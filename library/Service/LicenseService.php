<?php

namespace Library\Service;

use Library\Repository\DomainRepository;
use Library\Service\Dto\LicenseResult;
use Library\Service\Dto\LicenseStatus;

class LicenseService
{
    const FREE_DOMAIN_LIMIT = null;
    const PLAN_FREE = 'public';
    const PLAN_STARTER = 'public';
    const PLAN_PROFESSIONAL = 'public';
    const PLAN_AGENCY = 'public';
    const PLAN_PROVIDER = 'public';

    const FEATURE_SES_SETUP = 'ses_setup';
    const FEATURE_SES_CONNECTION = 'ses_connection';
    const FEATURE_SES_ACCOUNT_STATUS = 'ses_account_status';
    const FEATURE_MANUAL_IDENTITY_CREATE = 'manual_identity_create';
    const FEATURE_DNS_HEALTH = 'dns_health';
    const FEATURE_DELIVERABILITY_SCORE = 'deliverability_score';
    const FEATURE_SES_REPUTATION = 'ses_reputation';
    const FEATURE_DNS_AUTO_FIX = 'dns_auto_fix';
    const FEATURE_SPF_AUTO_FIX = 'spf_auto_fix';
    const FEATURE_DKIM_AUTO_FIX = 'dkim_auto_fix';
    const FEATURE_DMARC_AUTO_FIX = 'dmarc_auto_fix';
    const FEATURE_MAIL_FROM_AUTO_FIX = 'mail_from_auto_fix';
    const FEATURE_CLOUDFLARE_DNS = 'cloudflare_dns';
    const FEATURE_CLOUDFLARE_SYNC_ALL = 'cloudflare_sync_all';
    const FEATURE_AUTO_SES_ON_CREATE = 'auto_ses_on_create';
    const FEATURE_AUTO_DNS_SETUP = 'auto_dns_setup';
    const FEATURE_SNS_AUTOMATION = 'sns_automation';
    const FEATURE_BOUNCE_TRACKING = 'bounce_tracking';
    const FEATURE_COMPLAINT_TRACKING = 'complaint_tracking';
    const FEATURE_DELIVERY_TRACKING = 'delivery_tracking';
    const FEATURE_EVENT_VIEWER = 'event_viewer';
    const FEATURE_FEEDBACK_METRICS = 'feedback_metrics';
    const FEATURE_BULK_DOMAIN_ONBOARDING = 'bulk_domain_onboarding';
    const FEATURE_BULK_VERIFY = 'bulk_verify';
    const FEATURE_BULK_HEALTH_CHECK = 'bulk_health_check';
    const FEATURE_CSV_EXPORT = 'csv_export';
    const FEATURE_MAIL_HEALTH_REPORTS = 'mail_health_reports';
    const FEATURE_PLESK_NOTIFICATIONS = 'plesk_notifications';
    const FEATURE_RBL_MONITORING = 'rbl_monitoring';
    const FEATURE_MULTI_SERVER_DASHBOARD = 'multi_server_dashboard';
    const FEATURE_CENTRAL_LICENSE_MANAGEMENT = 'central_license_management';
    const FEATURE_WHITE_LABEL = 'white_label';

    /** @var DomainRepository */
    private $domainRepository;

    public function __construct(
        ?DomainRepository $domainRepository = null
    )
    {
        $this->domainRepository = $domainRepository ?: new DomainRepository();
    }

    public function getStatus()
    {
        $this->syncTrackedDomainsWithPlesk();

        return new LicenseStatus(
            self::PLAN_FREE,
            null,
            null,
            $this->domainRepository->countActive(),
            null,
            'public',
            $this->enabledFeatureMap(),
            null,
            null,
            null,
            null,
            'open_source',
            'public'
        );
    }

    public function hasFeature($featureKey)
    {
        return in_array($featureKey, $this->allFeatures(), true);
    }

    public function canUseFeature($featureKey)
    {
        return $this->hasFeature($featureKey);
    }

    public function requiredPlanForFeature($featureKey)
    {
        return null;
    }

    public function licenseMode()
    {
        return 'public';
    }

    public function isDevelopmentMode()
    {
        return false;
    }

    public function developmentCodes()
    {
        return array();
    }

    public function skuMap()
    {
        return array();
    }

    public function canRegisterDomain($domainName)
    {
        return true;
    }

    public function remainingDomainSlots()
    {
        return null;
    }

    public function filterRegisterableDomains(array $domains)
    {
        return array(
            'allowed' => $domains,
            'skipped' => array(),
            'limit' => null,
            'used' => $this->domainRepository->countActive(),
            'remaining' => null,
        );
    }

    public function syncTrackedDomainsWithPlesk($markAllWhenEmpty = false)
    {
        if (!class_exists('\pm_Domain')) {
            return 0;
        }

        try {
            $ids = array();
            foreach (\pm_Domain::getAllDomains() as $domain) {
                $ids[] = (int) $domain->getId();
            }

            if (empty($ids) && !$markAllWhenEmpty) {
                return 0;
            }

            return $this->domainRepository->markMissingPleskDomainsInactive($ids);
        } catch (\Exception $e) {
            \pm_Log::err('SES Manager: failed to sync tracked domains with Plesk: ' . $e->getMessage());
            return 0;
        }
    }

    public function activateLicense($licenseKey)
    {
        return new LicenseResult(true, 'Public build: licensing is disabled.');
    }

    public function registerFreeLicense()
    {
        return new LicenseResult(true, 'Public build: all features are enabled.');
    }

    public function refreshLicense()
    {
        return new LicenseResult(true, 'Public build: no license refresh is required.');
    }

    public function getUpgradeUrl()
    {
        return null;
    }

    public function featureRows()
    {
        $rows = array();

        foreach ($this->allFeatures() as $featureKey) {
            $rows[] = array(
                'key' => $featureKey,
                'label' => $this->humanizeFeatureKey($featureKey),
                'enabled' => true,
                'required_plan' => null,
            );
        }

        usort($rows, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return $rows;
    }

    public function featureGate($featureKey)
    {
        return array(
            'feature' => $featureKey,
            'enabled' => true,
            'required_plan' => null,
            'upgrade_url' => null,
        );
    }

    public function deactivateLicense()
    {
        return new LicenseResult(true, 'Public build: licensing is already disabled.');
    }

    private function enabledFeatureMap()
    {
        $map = array();
        foreach ($this->allFeatures() as $featureKey) {
            $map[$featureKey] = true;
        }

        return $map;
    }

    private function humanizeFeatureKey($featureKey)
    {
        return ucwords(str_replace('_', ' ', $featureKey));
    }

    private function allFeatures()
    {
        return array(
            self::FEATURE_SES_SETUP,
            self::FEATURE_SES_CONNECTION,
            self::FEATURE_SES_ACCOUNT_STATUS,
            self::FEATURE_MANUAL_IDENTITY_CREATE,
            self::FEATURE_DNS_HEALTH,
            self::FEATURE_DELIVERABILITY_SCORE,
            self::FEATURE_SES_REPUTATION,
            self::FEATURE_DNS_AUTO_FIX,
            self::FEATURE_SPF_AUTO_FIX,
            self::FEATURE_DKIM_AUTO_FIX,
            self::FEATURE_DMARC_AUTO_FIX,
            self::FEATURE_MAIL_FROM_AUTO_FIX,
            self::FEATURE_CLOUDFLARE_DNS,
            self::FEATURE_CLOUDFLARE_SYNC_ALL,
            self::FEATURE_AUTO_SES_ON_CREATE,
            self::FEATURE_AUTO_DNS_SETUP,
            self::FEATURE_SNS_AUTOMATION,
            self::FEATURE_BOUNCE_TRACKING,
            self::FEATURE_COMPLAINT_TRACKING,
            self::FEATURE_DELIVERY_TRACKING,
            self::FEATURE_EVENT_VIEWER,
            self::FEATURE_FEEDBACK_METRICS,
            self::FEATURE_BULK_DOMAIN_ONBOARDING,
            self::FEATURE_BULK_VERIFY,
            self::FEATURE_BULK_HEALTH_CHECK,
            self::FEATURE_CSV_EXPORT,
            self::FEATURE_MAIL_HEALTH_REPORTS,
            self::FEATURE_PLESK_NOTIFICATIONS,
            self::FEATURE_RBL_MONITORING,
            self::FEATURE_MULTI_SERVER_DASHBOARD,
            self::FEATURE_CENTRAL_LICENSE_MANAGEMENT,
            self::FEATURE_WHITE_LABEL
        );
    }
}
