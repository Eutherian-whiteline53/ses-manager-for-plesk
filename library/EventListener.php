<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use Library\Repository\JobRepository;
use Library\Repository\SettingsRepository;
use Library\Service\LicenseService;

if (!interface_exists('EventListener')) {
    interface EventListener
    {
        public function filterActions();

        public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues);
    }
}

class Modules_SesManager_EventListener implements EventListener
{
    public function filterActions()
    {
        return array('domain_create', 'site_create');
    }

    public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues)
    {
        $settings = (new SettingsRepository())->get();
        if (empty($settings['auto_ses_on_create'])) {
            return;
        }

        if (!(new LicenseService())->canUseFeature(LicenseService::FEATURE_AUTO_SES_ON_CREATE)) {
            \pm_Log::info('SES Manager: auto SES skipped, feature is unavailable.');
            return;
        }

        $domain = $this->resolveDomain((int) $objectId, (array) $newValues);
        if ($domain === null) {
            \pm_Log::warn('SES Manager: auto SES skipped, could not resolve Plesk domain for event ' . $action . ' object ' . $objectId);
            return;
        }

        (new JobRepository())->createAutoDomainVerifyJob($domain['name'], $domain['id'], $action, (int) $objectId);
    }

    private function resolveDomain($objectId, array $newValues)
    {
        $domain = \pm_Domain::getByDomainId($objectId);
        if ($domain) {
            return array('id' => (int) $domain->getId(), 'name' => $domain->getName());
        }

        $candidateNames = array('domain_name', 'name', 'newDomainName', 'domainName');
        foreach ($candidateNames as $key) {
            if (empty($newValues[$key])) {
                continue;
            }

            $resolved = $this->resolveDomainByName($newValues[$key]);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveDomainByName($domainName)
    {
        foreach (\pm_Domain::getAllDomains() as $domain) {
            if (strcasecmp($domain->getName(), $domainName) === 0) {
                return array('id' => (int) $domain->getId(), 'name' => $domain->getName());
            }
        }

        return null;
    }
}

return new Modules_SesManager_EventListener();
