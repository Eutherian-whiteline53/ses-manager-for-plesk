<?php

namespace Library\Service;

use Library\Repository\IpReputationAlertRepository;

class IpReputationAlertService
{
    const NOTIFICATION_ID = 'ses-manager-ip-reputation-critical';

    /** @var IpReputationAlertRepository */
    private $repository;

    public function __construct(?IpReputationAlertRepository $repository = null)
    {
        $this->repository = $repository ?: new IpReputationAlertRepository();
    }

    public function process(array $report)
    {
        $ip = isset($report['ip']) ? (string) $report['ip'] : '';
        $activeKeys = array();
        $notifications = array();

        foreach ($this->extractCriticalListings($report) as $listing) {
            $alertKey = $this->buildAlertKey($ip, $listing['provider']);
            $activeKeys[$alertKey] = true;
            $existing = $this->repository->findByKey($alertKey);
            $isNew = !$existing || $existing['status'] !== 'active';
            $shouldNotify = $isNew || !$this->repository->isInCooldown($existing);
            $message = $this->buildListingMessage($ip, $listing);
            $alertId = $this->repository->upsertActive($alertKey, $ip, $listing['provider'], $listing['severity'], $message, $shouldNotify);

            if ($isNew) {
                $this->repository->createEvent($alertId, 'listed', $listing['severity'], $message, $listing);
            } elseif ($shouldNotify) {
                $this->repository->createEvent($alertId, 'reminder', $listing['severity'], $message, $listing);
            }

            if ($shouldNotify) {
                $notifications[] = $message;
            }
        }

        foreach ($this->repository->findActive() as $alert) {
            if ($alert['ip_address'] !== $ip || isset($activeKeys[$alert['alert_key']])) {
                continue;
            }

            $message = 'Recovered: ' . $alert['provider'] . ' no longer lists ' . $ip . '.';
            $alertId = $this->repository->markRecovered($alert['alert_key'], $message);
            if ($alertId !== null) {
                $this->repository->createEvent($alertId, 'recovered', $alert['severity'], $message);
            }
        }

        $this->syncPleskNotification($notifications);

        return array(
            'notifications' => count($notifications),
            'activeAlerts' => count($this->repository->findActive()),
        );
    }

    private function extractCriticalListings(array $report)
    {
        $listings = array();
        if (empty($report['blacklist']['checks']) || !is_array($report['blacklist']['checks'])) {
            return $listings;
        }

        foreach ($report['blacklist']['checks'] as $check) {
            if (empty($check['listed']) || (isset($check['severity']) && $check['severity'] !== 'critical')) {
                continue;
            }

            $listings[] = array(
                'provider' => $check['provider'],
                'zone' => isset($check['zone']) ? $check['zone'] : '',
                'severity' => isset($check['severity']) ? $check['severity'] : 'critical',
                'response' => isset($check['response']) ? $check['response'] : '',
            );
        }

        return $listings;
    }

    private function buildAlertKey($ip, $provider)
    {
        return sha1(strtolower($ip . '|' . $provider));
    }

    private function buildListingMessage($ip, array $listing)
    {
        $response = $listing['response'] !== '' ? ' Response: ' . $listing['response'] . '.' : '';

        return 'Critical DNSBL listing: ' . $ip . ' is listed by ' . $listing['provider'] . '.' . $response;
    }

    private function syncPleskNotification(array $messages)
    {
        if (!method_exists('pm_Notification', 'create')) {
            return;
        }

        if (!empty($messages)) {
            \pm_Notification::create(self::NOTIFICATION_ID, implode(' ', $messages));
            return;
        }

        if (empty($this->repository->findActive()) && method_exists('pm_Notification', 'cancelAll')) {
            \pm_Notification::cancelAll(self::NOTIFICATION_ID);
        }
    }
}
