<?php

namespace Library\Service\Dto;

class LicenseStatus
{
    public $plan;
    public $domainLimit;
    public $serverLimit;
    public $usedDomains;
    public $validUntil;
    public $status;
    public $features;
    public $upgradeUrl;
    public $cacheUntil;
    public $graceUntil;
    public $lastError;
    public $licenseSource;
    public $licenseMode;

    public function __construct(
        $plan,
        $domainLimit,
        $serverLimit,
        $usedDomains,
        ?string $validUntil = null,
        $status = 'free',
        array $features = array(),
        $upgradeUrl = null,
        ?string $cacheUntil = null,
        ?string $graceUntil = null,
        $lastError = null,
        $licenseSource = null,
        $licenseMode = null
    )
    {
        $this->plan = $plan;
        $this->domainLimit = $domainLimit;
        $this->serverLimit = $serverLimit;
        $this->usedDomains = $usedDomains;
        $this->validUntil = $validUntil;
        $this->status = $status;
        $this->features = $features;
        $this->upgradeUrl = $upgradeUrl;
        $this->cacheUntil = $cacheUntil;
        $this->graceUntil = $graceUntil;
        $this->lastError = $lastError;
        $this->licenseSource = $licenseSource;
        $this->licenseMode = $licenseMode;
    }

    public function isLimitReached()
    {
        return false;
    }

    public function hasFeature($featureKey)
    {
        return !empty($this->features[$featureKey]);
    }

    public function canUseFeature($featureKey)
    {
        return $this->hasFeature($featureKey);
    }

    public function isFree()
    {
        return $this->plan === 'free' || $this->plan === 'public';
    }

    public function isDevelopmentMode()
    {
        return $this->licenseMode === 'development';
    }

    public function isDevelopmentLicense()
    {
        return $this->licenseSource === 'development_test_code';
    }

    public function isInGracePeriod()
    {
        return $this->graceUntil !== null && strtotime($this->graceUntil) >= time();
    }
}
