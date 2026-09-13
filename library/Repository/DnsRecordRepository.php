<?php

namespace Library\Repository;

use Library\Service\Dto\DnsChangeResult;

class DnsRecordRepository
{
    public function findByDomainId($domainId)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        return $db->fetchAll('SELECT * FROM ses_dns_records WHERE domain_id = ? ORDER BY purpose ASC, id ASC', array($domainId));
    }

    /**
     * @param DnsChangeResult[] $plans
     */
    public function savePlans($domainId, array $plans, $provider = 'plesk')
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $now = date('Y-m-d H:i:s');

        foreach ($plans as $plan) {
            $record = $plan->record;
            if ($record->purpose === null || $record->purpose === '') {
                \pm_Log::err('SES Manager: skipped DNS plan with empty purpose for ' . $record->name);
                continue;
            }

            $status = in_array($plan->status, array(DnsChangeResult::STATUS_SPF_MERGE, DnsChangeResult::STATUS_TXT_UPDATE), true) ? DnsChangeResult::STATUS_PLANNED : $plan->status;
            $status = $status === DnsChangeResult::STATUS_SKIPPED ? DnsChangeResult::STATUS_APPLIED : $status;

            $existing = $db->fetchRow(
                'SELECT id FROM ses_dns_records WHERE domain_id = ? AND type = ? AND purpose = ? AND name = ? AND provider = ?',
                array($domainId, $record->type, $record->purpose, $record->name, $provider)
            );

            $data = array(
                'domain_id' => $domainId,
                'type' => $record->type,
                'name' => $record->name,
                'value' => $record->value,
                'provider' => $provider,
                'purpose' => $record->purpose,
                'status' => $status,
                'last_error' => $status === DnsChangeResult::STATUS_FAILED ? $plan->message : null,
                'updated_at' => $now,
            );

            if ($existing) {
                $db->update('ses_dns_records', $data, $db->quoteInto('id = ?', $existing['id']));
                continue;
            }

            $data['created_at'] = $now;
            $db->insert('ses_dns_records', $data);
        }
    }

    public function updateStatus($id, $status, $lastError = null)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        $db->update('ses_dns_records', array(
            'status' => $status,
            'last_error' => $lastError,
            'updated_at' => date('Y-m-d H:i:s'),
        ), $db->quoteInto('id = ?', $id));
    }

    public function deleteRootSesSpf($domainId, $domainName)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        $db->delete('ses_dns_records', array(
            $db->quoteInto('domain_id = ?', $domainId),
            $db->quoteInto('purpose = ?', 'spf'),
            $db->quoteInto('name = ?', $domainName),
        ));
    }
}
