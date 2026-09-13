<?php

namespace Library\Repository;

class BlacklistCheckRepository
{
    public function saveMany($ipAddress, array $checks)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $now = date('Y-m-d H:i:s');

        foreach ($checks as $check) {
            $db->insert('ses_blacklist_checks', array(
                'ip_address' => $ipAddress,
                'provider' => $check['provider'],
                'zone' => isset($check['zone']) ? $check['zone'] : '',
                'severity' => isset($check['severity']) ? $check['severity'] : 'warning',
                'listed' => empty($check['listed']) ? 0 : 1,
                'response' => $check['response'],
                'checked_at' => $now,
            ));
        }
    }

    public function findLatestByIp($ipAddress)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        return $db->fetchAll(
            'SELECT c.* FROM ses_blacklist_checks c
             INNER JOIN (
                SELECT provider, MAX(checked_at) checked_at
                FROM ses_blacklist_checks
                WHERE ip_address = ?
                GROUP BY provider
             ) latest ON latest.provider = c.provider AND latest.checked_at = c.checked_at
             WHERE c.ip_address = ?
             ORDER BY c.provider ASC',
            array($ipAddress, $ipAddress)
        );
    }
}
