<?php

namespace Library\Repository;

class IpReputationCheckRepository
{
    public function save(array $report)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $data = array(
            'ip_address' => $report['ip'],
            'ptr_hostname' => $report['reverseDns']['ptr'],
            'ptr_status' => $report['reverseDns']['status'],
            'forward_confirmed' => empty($report['reverseDns']['forwardConfirmed']) ? 0 : 1,
            'smtp_banner' => $report['smtpBanner']['banner'],
            'smtp_banner_status' => $report['smtpBanner']['status'],
            'helo_hostname' => $report['mailIdentity']['hostname'],
            'helo_status' => $report['mailIdentity']['status'],
            'score' => (int) $report['score'],
            'raw_payload' => json_encode($report),
            'checked_at' => date('Y-m-d H:i:s'),
        );

        $columns = $db->fetchCol('SHOW COLUMNS FROM ses_ip_reputation_checks');
        if (in_array('asn', $columns, true)) {
            $asn = isset($report['asn']) && is_array($report['asn']) ? $report['asn'] : array();
            $data['asn'] = isset($asn['asn']) ? $asn['asn'] : '';
            $data['asn_name'] = isset($asn['name']) ? $asn['name'] : '';
            $data['asn_provider'] = isset($asn['provider']) ? $asn['provider'] : '';
            $data['asn_country'] = isset($asn['country']) ? $asn['country'] : '';
            $data['asn_known_datacenter'] = empty($asn['knownDatacenter']) ? 0 : 1;
        }

        $db->insert('ses_ip_reputation_checks', $data);

        return (int) $db->lastInsertId();
    }

    public function findLatest($ipAddress)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $row = $db->fetchRow(
            'SELECT * FROM ses_ip_reputation_checks WHERE ip_address = ? ORDER BY checked_at DESC, id DESC LIMIT 1',
            array($ipAddress)
        );

        if (!$row || empty($row['raw_payload'])) {
            return null;
        }

        $decoded = json_decode($row['raw_payload'], true);

        return is_array($decoded) ? $decoded : null;
    }
}
