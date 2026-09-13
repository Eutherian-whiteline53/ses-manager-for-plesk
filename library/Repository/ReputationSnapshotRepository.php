<?php

namespace Library\Repository;

class ReputationSnapshotRepository
{
    public function save(array $snapshot)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $db->insert('ses_reputation_snapshots', $snapshot);

        return (int) $db->lastInsertId();
    }

    public function findLatest($region)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $row = $db->fetchRow(
            'SELECT * FROM ses_reputation_snapshots WHERE region = ? ORDER BY checked_at DESC, id DESC LIMIT 1',
            array($region)
        );

        return $row ?: null;
    }
}

