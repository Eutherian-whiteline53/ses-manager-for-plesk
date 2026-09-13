<?php

namespace Library\Repository;

class DomainRepository
{
    public function findAll()
    {
        $db = \pm_Bootstrap::getDbAdapter();

        return $db->fetchAll("SELECT * FROM ses_domains WHERE status = 'active' ORDER BY domain_name ASC");
    }

    public function findAllIncludingInactive()
    {
        $db = \pm_Bootstrap::getDbAdapter();

        return $db->fetchAll('SELECT * FROM ses_domains ORDER BY domain_name ASC');
    }

    public function countActive()
    {
        $db = \pm_Bootstrap::getDbAdapter();

        return (int) $db->fetchOne("SELECT COUNT(*) FROM ses_domains WHERE status = 'active'");
    }

    public function findByName($domainName)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $row = $db->fetchRow('SELECT * FROM ses_domains WHERE domain_name = ?', array($domainName));

        return $row ?: null;
    }

    public function findById($id)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $row = $db->fetchRow('SELECT * FROM ses_domains WHERE id = ?', array($id));

        return $row ?: null;
    }

    /**
     * @return int domain id
     */
    public function registerDomain($domainName, $pleskDomainId, $region)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $existing = $this->findByName($domainName);
        $now = date('Y-m-d H:i:s');

        if ($existing) {
            $db->update('ses_domains', array(
                'plesk_domain_id' => $pleskDomainId,
                'region' => $region,
                'status' => 'active',
                'updated_at' => $now,
            ), $db->quoteInto('id = ?', $existing['id']));

            return (int) $existing['id'];
        }

        $db->insert('ses_domains', array(
            'domain_name' => $domainName,
            'plesk_domain_id' => $pleskDomainId,
            'region' => $region,
            'status' => 'active',
            'verification_status' => 'not_started',
            'spf_status' => 'missing',
            'dkim_status' => 'missing',
            'dmarc_status' => 'missing',
            'mx_status' => 'missing',
            'score' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ));

        return (int) $db->lastInsertId();
    }

    public function markMissingPleskDomainsInactive(array $activePleskDomainIds)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $activePleskDomainIds = array_values(array_unique(array_filter(array_map('intval', $activePleskDomainIds))));
        $where = "status = 'active'";

        if (!empty($activePleskDomainIds)) {
            $where .= ' AND plesk_domain_id NOT IN (' . implode(',', $activePleskDomainIds) . ')';
        }

        return $db->update('ses_domains', array(
            'status' => 'inactive',
            'updated_at' => date('Y-m-d H:i:s'),
        ), $where);
    }

    public function updateHealth($domainId, array $statuses, $score)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $now = date('Y-m-d H:i:s');

        $data = array_merge($statuses, array(
            'score' => $score,
            'last_checked_at' => $now,
            'updated_at' => $now,
        ));

        $db->update('ses_domains', $data, $db->quoteInto('id = ?', $domainId));
    }

    public function updateHealthAnalysis($domainId, array $statuses, array $analysis, $score)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $columns = $db->fetchCol('SHOW COLUMNS FROM ses_domains');

        foreach (array('spf_analysis_json', 'dkim_analysis_json', 'dmarc_analysis_json', 'mail_from_analysis_json') as $column) {
            if (!in_array($column, $columns, true)) {
                $this->updateHealth($domainId, $statuses, $score);
                return;
            }
        }

        $statuses['spf_analysis_json'] = json_encode($analysis['spf']);
        $statuses['dkim_analysis_json'] = json_encode($analysis['dkim']);
        $statuses['dmarc_analysis_json'] = json_encode($analysis['dmarc']);
        $statuses['mail_from_analysis_json'] = json_encode($analysis['mail_from']);

        $this->updateHealth($domainId, $statuses, $score);
    }

    public function updateVerification($domainId, $verificationStatus, $sesIdentityArn = null)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        $data = array(
            'verification_status' => $verificationStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        );

        if ($sesIdentityArn !== null) {
            $data['ses_identity_arn'] = $sesIdentityArn;
        }

        $db->update('ses_domains', $data, $db->quoteInto('id = ?', $domainId));
    }
}
