<?php

namespace Library\Service;

use Library\Repository\EventRepository;
use Library\Repository\ReputationSnapshotRepository;
use Library\Repository\SettingsRepository;

class ReputationService
{
    /** @var SettingsRepository */
    private $settingsRepository;

    /** @var EventRepository */
    private $eventRepository;

    /** @var ReputationSnapshotRepository */
    private $snapshotRepository;

    public function __construct(
        ?SettingsRepository $settingsRepository = null,
        ?EventRepository $eventRepository = null,
        ?ReputationSnapshotRepository $snapshotRepository = null
    ) {
        $this->settingsRepository = $settingsRepository ?: new SettingsRepository();
        $this->eventRepository = $eventRepository ?: new EventRepository();
        $this->snapshotRepository = $snapshotRepository ?: new ReputationSnapshotRepository();
    }

    public function getSnapshot($forceRefresh = false)
    {
        $credentials = $this->settingsRepository->getAwsCredentials();
        if ($credentials['accessKey'] === null || $credentials['secretKey'] === null) {
            return array(
                'success' => false,
                'message' => \pm_Locale::lmsg('credentialsMissing'),
                'snapshot' => null,
            );
        }

        if (!$forceRefresh) {
            $latest = $this->snapshotRepository->findLatest($credentials['region']);
            if ($latest && strtotime($latest['checked_at']) >= time() - 900) {
                return array('success' => true, 'message' => '', 'snapshot' => $latest);
            }
        }

        try {
            $account = (new AwsSesService())->getAccount($credentials['accessKey'], $credentials['secretKey'], $credentials['region']);
        } catch (\Exception $e) {
            \pm_Log::err('SES Manager: failed to fetch SES account reputation: ' . $e->getMessage());

            return array(
                'success' => false,
                'message' => \pm_Locale::lmsg('reputationFetchFailed'),
                'snapshot' => $this->snapshotRepository->findLatest($credentials['region']),
            );
        }

        $sentLast24Hours = (int) round(isset($account['SendQuota']['SentLast24Hours']) ? $account['SendQuota']['SentLast24Hours'] : 0);
        $bounceCount = $this->eventRepository->countByTypeSince('bounce', date('Y-m-d H:i:s', time() - 86400));
        $complaintCount = $this->eventRepository->countByTypeSince('complaint', date('Y-m-d H:i:s', time() - 86400));
        $bounceRate = $sentLast24Hours > 0 ? ($bounceCount / $sentLast24Hours) * 100 : 0;
        $complaintRate = $sentLast24Hours > 0 ? ($complaintCount / $sentLast24Hours) * 100 : 0;

        $snapshot = array(
            'region' => $credentials['region'],
            'production_access_enabled' => empty($account['ProductionAccessEnabled']) ? 0 : 1,
            'sending_enabled' => empty($account['SendingEnabled']) ? 0 : 1,
            'enforcement_status' => isset($account['EnforcementStatus']) ? $account['EnforcementStatus'] : 'UNKNOWN',
            'max_24_hour_send' => (int) round(isset($account['SendQuota']['Max24HourSend']) ? $account['SendQuota']['Max24HourSend'] : 0),
            'sent_last_24_hours' => $sentLast24Hours,
            'max_send_rate' => isset($account['SendQuota']['MaxSendRate']) ? (float) $account['SendQuota']['MaxSendRate'] : 0,
            'bounce_rate' => round($bounceRate, 4),
            'complaint_rate' => round($complaintRate, 4),
            'health' => $this->calculateHealth($account, $bounceRate, $complaintRate),
            'source' => 'ses_events',
            'raw_payload' => json_encode($account),
            'checked_at' => date('Y-m-d H:i:s'),
        );

        $this->snapshotRepository->save($snapshot);

        return array('success' => true, 'message' => '', 'snapshot' => $snapshot);
    }

    private function calculateHealth(array $account, $bounceRate, $complaintRate)
    {
        $enforcement = strtoupper(isset($account['EnforcementStatus']) ? $account['EnforcementStatus'] : '');

        if (empty($account['SendingEnabled']) || $enforcement === 'SHUTDOWN' || $bounceRate >= 10 || $complaintRate >= 0.5) {
            return 'critical';
        }

        if ($enforcement === 'PROBATION' || $bounceRate >= 5 || $complaintRate >= 0.1) {
            return 'warning';
        }

        return 'excellent';
    }
}

