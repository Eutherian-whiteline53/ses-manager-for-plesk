<?php

namespace Library\Repository;

class JobRepository
{
    public function create($jobType, array $payload)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $now = date('Y-m-d H:i:s');

        $db->insert('ses_jobs', array(
            'job_type' => $jobType,
            'status' => 'queued',
            'payload' => json_encode($payload),
            'attempts' => 0,
            'scheduled_at' => $now,
            'created_at' => $now,
        ));

        return (int) $db->lastInsertId();
    }

    public function createAutoDomainVerifyJob($domainName, $pleskDomainId, $sourceAction, $objectId)
    {
        $existing = $this->findActiveAutoDomainVerifyJob($domainName);
        if ($existing) {
            return (int) $existing['id'];
        }

        return $this->create('auto_domain_verify', array(
            'domainName' => $domainName,
            'pleskDomainId' => (int) $pleskDomainId,
            'sourceAction' => $sourceAction,
            'objectId' => (int) $objectId,
        ));
    }

    public function findActiveAutoDomainVerifyJob($domainName)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $rows = $db->fetchAll(
            "SELECT * FROM ses_jobs WHERE job_type = 'auto_domain_verify' AND status IN ('queued', 'running') ORDER BY id DESC"
        );

        foreach ($rows as $row) {
            $payload = $this->decodePayload($row);
            if (isset($payload['domainName']) && strcasecmp($payload['domainName'], $domainName) === 0) {
                return $row;
            }
        }

        return null;
    }

    public function findLatestAutoDomainVerifyJob($domainName)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $rows = $db->fetchAll(
            "SELECT * FROM ses_jobs WHERE job_type = 'auto_domain_verify' ORDER BY id DESC LIMIT 50"
        );

        foreach ($rows as $row) {
            $payload = $this->decodePayload($row);
            if (isset($payload['domainName']) && strcasecmp($payload['domainName'], $domainName) === 0) {
                return $row;
            }
        }

        return null;
    }

    public function findActiveByType($jobType)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        return $db->fetchRow(
            "SELECT * FROM ses_jobs WHERE job_type = ? AND status IN ('queued', 'running') ORDER BY id DESC",
            array($jobType)
        );
    }

    public function findRecent($limit = 20)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        return $db->fetchAll('SELECT * FROM ses_jobs ORDER BY id DESC LIMIT ' . (int) $limit);
    }

    public function findRecentByType($jobType, $limit = 10)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        return $db->fetchAll(
            'SELECT * FROM ses_jobs WHERE job_type = ? ORDER BY id DESC LIMIT ' . (int) $limit,
            array($jobType)
        );
    }

    public function findNextQueuedByType($jobType)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        return $db->fetchRow(
            "SELECT * FROM ses_jobs WHERE job_type = ? AND status = 'queued' AND scheduled_at <= ? ORDER BY id ASC",
            array($jobType, date('Y-m-d H:i:s'))
        );
    }

    public function decodePayload(array $job)
    {
        $payload = json_decode($job['payload'], true);

        return is_array($payload) ? $payload : array();
    }

    public function markRunning($id)
    {
        $this->updateStatus($id, 'running', array('started_at' => date('Y-m-d H:i:s')));
    }

    public function markFinished($id, $status, $lastError = null)
    {
        $this->updateStatus($id, $status, array('finished_at' => date('Y-m-d H:i:s'), 'last_error' => $lastError));
    }

    public function incrementAttempts($id)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $db->query('UPDATE ses_jobs SET attempts = attempts + 1 WHERE id = ?', array($id));
    }

    private function updateStatus($id, $status, array $extra = array())
    {
        $db = \pm_Bootstrap::getDbAdapter();

        $data = array_merge(array('status' => $status), $extra);
        $db->update('ses_jobs', $data, $db->quoteInto('id = ?', $id));
    }
}
