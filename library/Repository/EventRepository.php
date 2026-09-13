<?php

namespace Library\Repository;

class EventRepository
{
    public function exists($messageId, $eventType, $recipient)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        $id = $db->fetchOne(
            'SELECT id FROM ses_events WHERE message_id = ? AND event_type = ? AND recipient = ?',
            array($messageId, $eventType, $recipient)
        );

        return $id !== false;
    }

    public function create(array $data)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $data['created_at'] = date('Y-m-d H:i:s');

        $db->insert('ses_events', $data);

        return (int) $db->lastInsertId();
    }

    public function findRecent($limit = 50, array $filters = array())
    {
        $db = \pm_Bootstrap::getDbAdapter();

        $where = array();
        $params = array();

        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = ?';
            $params[] = $filters['event_type'];
        }

        if (!empty($filters['domain_id'])) {
            $where[] = 'domain_id = ?';
            $params[] = $filters['domain_id'];
        }

        $sql = 'SELECT * FROM ses_events';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY received_at DESC LIMIT ' . (int) $limit;

        return $db->fetchAll($sql, $params);
    }

    public function countByTypeSince($eventType, $since)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        return (int) $db->fetchOne(
            'SELECT COUNT(*) FROM ses_events WHERE event_type = ? AND received_at >= ?',
            array($eventType, $since)
        );
    }
}
