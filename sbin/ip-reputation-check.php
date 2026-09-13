<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use Library\Service\IpReputationService;
use Library\Service\IpReputationAlertService;
use Library\Service\LicenseService;

pm_Bootstrap::init();

if (!(new LicenseService())->canUseFeature(LicenseService::FEATURE_RBL_MONITORING)) {
    echo "SES Manager: IP reputation monitoring is unavailable.\n";
    return;
}

$report = (new IpReputationService())->check(true);
(new IpReputationAlertService())->process($report);
