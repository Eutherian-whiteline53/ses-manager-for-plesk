<?php

namespace Library\Service;

use Library\Client\PleskMailClient;
use Library\Repository\SettingsRepository;
use Library\Service\Dto\SmarthostConfig;
use Library\Service\Dto\SmarthostResult;

class SmarthostService
{
    /** @var PleskMailClient */
    private $mailClient;

    /** @var SettingsRepository */
    private $settingsRepository;

    public function __construct(?PleskMailClient $mailClient = null, ?SettingsRepository $settingsRepository = null)
    {
        $this->mailClient = $mailClient ?: new PleskMailClient();
        $this->settingsRepository = $settingsRepository ?: new SettingsRepository();
    }

    public function previewConfig($region)
    {
        $smtpCredentials = $this->settingsRepository->getSmtpCredentials();

        return new SmarthostConfig(
            sprintf('[email-smtp.%s.amazonaws.com]', $region),
            587,
            $smtpCredentials['username'],
            'starttls'
        );
    }

    public function getCurrentConfig()
    {
        return $this->mailClient->getRelayConfig();
    }

    public function applyConfig(SmarthostConfig $config)
    {
        $smtpCredentials = $this->settingsRepository->getSmtpCredentials();

        if ($smtpCredentials['username'] === null || $smtpCredentials['password'] === null) {
            return new SmarthostResult(false, 'SES SMTP credentials are not configured. Run Setup first.');
        }

        try {
            $currentConfig = $this->mailClient->getRelayConfig();
            $this->settingsRepository->saveSmarthostSnapshot($currentConfig);

            $this->mailClient->setRelay(
                $config->host,
                $config->port,
                $smtpCredentials['username'],
                $smtpCredentials['password'],
                $config->encryption
            );

            $this->settingsRepository->saveGeneralSettings(array('smarthost_enabled' => 1));

            return new SmarthostResult(true, 'Smarthost configuration applied.');
        } catch (\Exception $e) {
            \pm_Log::err('SES Manager: failed to apply smarthost configuration: ' . $e->getMessage());

            return new SmarthostResult(false, 'Failed to apply smarthost configuration.');
        }
    }

    public function rollbackLastConfig()
    {
        $snapshot = $this->settingsRepository->getSmarthostSnapshot();

        if ($snapshot === null) {
            return new SmarthostResult(false, 'No previous mail configuration snapshot is available.');
        }

        try {
            if (empty($snapshot['enabled'])) {
                $this->mailClient->disableRelay();
            } else {
                $this->mailClient->setRelay(
                    $snapshot['host'],
                    $snapshot['port'],
                    $snapshot['username'],
                    '',
                    $snapshot['encryption']
                );
            }

            $this->settingsRepository->saveGeneralSettings(array(
                'smarthost_enabled' => empty($snapshot['enabled']) ? 0 : 1,
            ));
            $this->settingsRepository->clearSmarthostSnapshot();

            return new SmarthostResult(true, 'Previous mail configuration restored.');
        } catch (\Exception $e) {
            \pm_Log::err('SES Manager: failed to roll back smarthost configuration: ' . $e->getMessage());

            return new SmarthostResult(false, 'Failed to roll back smarthost configuration.');
        }
    }
}
