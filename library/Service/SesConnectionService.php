<?php

namespace Library\Service;

use Library\Repository\SettingsRepository;

class SesConnectionService
{
    /** @var SettingsRepository */
    private $settingsRepository;

    /** @var ReputationService */
    private $reputationService;

    public function __construct(?SettingsRepository $settingsRepository = null, ?ReputationService $reputationService = null)
    {
        $this->settingsRepository = $settingsRepository ?: new SettingsRepository();
        $this->reputationService = $reputationService ?: new ReputationService($this->settingsRepository);
    }

    public function getStatus($forceRefresh = false)
    {
        $credentials = $this->settingsRepository->getAwsCredentials();
        $smtpConfigured = $this->settingsRepository->hasSmtpCredentials();
        $hasAwsCredentials = $credentials['accessKey'] !== null && $credentials['secretKey'] !== null;

        $status = array(
            'overall' => 'missing',
            'api' => 'missing',
            'account' => 'unknown',
            'sending' => 'unknown',
            'smtp' => $smtpConfigured ? 'configured' : 'missing',
            'region' => $credentials['region'],
            'productionAccess' => false,
            'sendingEnabled' => false,
            'snapshot' => null,
            'checkedAt' => '',
            'message' => \pm_Locale::lmsg('sesConnectionMissingCredentials'),
        );

        if (!$hasAwsCredentials) {
            return $status;
        }

        $reputation = $this->reputationService->getSnapshot($forceRefresh);
        if (!$reputation['success'] || !$reputation['snapshot']) {
            $status['api'] = 'failed';
            $status['overall'] = 'failed';
            $status['message'] = $reputation['message'] !== '' ? $reputation['message'] : \pm_Locale::lmsg('sesConnectionApiFailed');
            $status['snapshot'] = $reputation['snapshot'];
            $status['checkedAt'] = $reputation['snapshot'] && !empty($reputation['snapshot']['checked_at']) ? $reputation['snapshot']['checked_at'] : '';

            return $status;
        }

        $snapshot = $reputation['snapshot'];
        $productionAccess = !empty($snapshot['production_access_enabled']);
        $sendingEnabled = !empty($snapshot['sending_enabled']);

        $status['api'] = 'connected';
        $status['account'] = $productionAccess ? 'production' : 'sandbox';
        $status['sending'] = $sendingEnabled ? 'enabled' : 'disabled';
        $status['productionAccess'] = $productionAccess;
        $status['sendingEnabled'] = $sendingEnabled;
        $status['snapshot'] = $snapshot;
        $status['checkedAt'] = $snapshot['checked_at'];

        if (!$sendingEnabled) {
            $status['overall'] = 'warning';
            $status['message'] = \pm_Locale::lmsg('sesConnectionSendingDisabled');
        } elseif (!$productionAccess) {
            $status['overall'] = 'warning';
            $status['message'] = \pm_Locale::lmsg('sesConnectionSandbox');
        } elseif (!$smtpConfigured) {
            $status['overall'] = 'warning';
            $status['message'] = \pm_Locale::lmsg('sesConnectionSmtpMissing');
        } else {
            $status['overall'] = 'connected';
            $status['message'] = \pm_Locale::lmsg('sesConnectionConnected');
        }

        return $status;
    }
}
