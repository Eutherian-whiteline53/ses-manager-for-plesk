<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use Library\Repository\SettingsRepository;
use Library\Service\HealthCheckRunner;
use Library\Service\LicenseService;
use Library\Service\NotificationService;

pm_Bootstrap::init();

$settingsRepository = new SettingsRepository();
$settings = $settingsRepository->get();
$licenseService = new LicenseService();
$licenseService->syncTrackedDomainsWithPlesk(true);

$runner = new HealthCheckRunner();
$runner->refreshStaleDomains((int) $settings['cache_ttl_seconds']);

if ($licenseService->canUseFeature(LicenseService::FEATURE_PLESK_NOTIFICATIONS)) {
    (new NotificationService())->notifyCriticalDomains();
}
