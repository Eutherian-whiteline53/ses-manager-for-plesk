<?php

namespace Library\Repository;

class CloudflareZoneRepository
{
    public function findByDomain($domainName)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $row = $db->fetchRow('SELECT * FROM ses_cloudflare_zones WHERE domain_name = ?', array($domainName));

        return $row ?: null;
    }

    public function save($domainName, $zoneId, $zoneName)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $now = date('Y-m-d H:i:s');

        $existing = $this->findByDomain($domainName);
        if ($existing) {
            $db->update('ses_cloudflare_zones', array(
                'zone_id' => $zoneId,
                'zone_name' => $zoneName,
                'detected_at' => $now,
            ), $db->quoteInto('id = ?', $existing['id']));

            return;
        }

        $db->insert('ses_cloudflare_zones', array(
            'domain_name' => $domainName,
            'zone_id' => $zoneId,
            'zone_name' => $zoneName,
            'detected_at' => $now,
        ));
    }
}
