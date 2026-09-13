<?php

namespace Library\Repository;

class IpReputationAlertRepository
{
    public function findByKey($alertKey)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $row = $db->fetchRow('SELECT * FROM ses_reputation_alerts WHERE alert_key = ?', array($alertKey));

        return $row ?: null;
    }

    public function upsertActive($alertKey, $ipAddress, $provider, $severity, $message, $shouldNotify)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $now = date('Y-m-d H:i:s');
        $existing = $this->findByKey($alertKey);
        $data = array(
            'alert_key' => $alertKey,
            'ip_address' => $ipAddress,
            'provider' => $provider,
            'severity' => $severity,
            'status' => 'active',
            'message' => $message,
            'last_seen_at' => $now,
            'updated_at' => $now,
        );

        if ($shouldNotify) {
            $data['last_alerted_at'] = $now;
            $data['cooldown_until'] = date('Y-m-d H:i:s', time() + 86400);
        }

        if ($existing) {
            $db->update('ses_reputation_alerts', $data, $db->quoteInto('id = ?', $existing['id']));
            return (int) $existing['id'];
        }

        $data['first_seen_at'] = $now;
        $data['created_at'] = $now;
        $db->insert('ses_reputation_alerts', $data);

        return (int) $db->lastInsertId();
    }

    public function markRecovered($alertKey, $message)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $existing = $this->findByKey($alertKey);
        if (!$existing || $existing['status'] !== 'active') {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $db->update('ses_reputation_alerts', array(
            'status' => 'recovered',
            'message' => $message,
            'recovered_at' => $now,
            'updated_at' => $now,
        ), $db->quoteInto('id = ?', $existing['id']));

        return (int) $existing['id'];
    }

    public function createEvent($alertId, $eventType, $severity, $message, array $payload = array())
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $db->insert('ses_ip_reputation_events', array(
            'alert_id' => $alertId,
            'event_type' => $eventType,
            'severity' => $severity,
            'message' => $message,
            'payload' => json_encode($payload),
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function findActive()
    {
        return \pm_Bootstrap::getDbAdapter()->fetchAll(
            'SELECT * FROM ses_reputation_alerts WHERE status = ? ORDER BY severity ASC, last_seen_at DESC',
            array('active')
        );
    }

    public function findRecentEvents($limit = 20)
    {
        $limit = max(1, min(100, (int) $limit));

        return \pm_Bootstrap::getDbAdapter()->fetchAll(
            'SELECT e.*, a.ip_address, a.provider
             FROM ses_ip_reputation_events e
             LEFT JOIN ses_reputation_alerts a ON a.id = e.alert_id
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT ' . $limit
        );
    }

    public function isInCooldown(array $alert)
    {
        return !empty($alert['cooldown_until']) && strtotime($alert['cooldown_until']) > time();
    }
}
