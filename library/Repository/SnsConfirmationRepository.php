<?php

namespace Library\Repository;

class SnsConfirmationRepository
{
    public function saveRequest(array $message)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $now = date('Y-m-d H:i:s');
        $messageId = isset($message['MessageId']) ? (string) $message['MessageId'] : sha1((string) $message['SubscribeURL']);
        $existing = $db->fetchRow('SELECT id FROM ses_sns_confirmations WHERE message_id = ?', array($messageId));

        $data = array(
            'message_id' => $messageId,
            'topic_arn' => isset($message['TopicArn']) ? (string) $message['TopicArn'] : '',
            'subscribe_url' => isset($message['SubscribeURL']) ? (string) $message['SubscribeURL'] : '',
            'token' => isset($message['Token']) ? (string) $message['Token'] : '',
            'status' => 'pending',
            'received_at' => $now,
            'updated_at' => $now,
        );

        if ($existing) {
            $db->update('ses_sns_confirmations', $data, $db->quoteInto('id = ?', $existing['id']));
            return (int) $existing['id'];
        }

        $data['created_at'] = $now;
        $db->insert('ses_sns_confirmations', $data);

        return (int) $db->lastInsertId();
    }

    public function markStatus($id, $status, $lastError = null)
    {
        $data = array(
            'status' => $status,
            'last_error' => $lastError,
            'updated_at' => date('Y-m-d H:i:s'),
        );

        if ($status === 'confirmed') {
            $data['confirmed_at'] = date('Y-m-d H:i:s');
        }

        \pm_Bootstrap::getDbAdapter()->update('ses_sns_confirmations', $data, \pm_Bootstrap::getDbAdapter()->quoteInto('id = ?', $id));
    }

    public function findRecent($limit = 10)
    {
        return \pm_Bootstrap::getDbAdapter()->fetchAll(
            'SELECT * FROM ses_sns_confirmations ORDER BY received_at DESC, id DESC LIMIT ' . max(1, min(50, (int) $limit))
        );
    }
}
