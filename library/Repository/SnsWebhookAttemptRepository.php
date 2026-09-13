<?php

namespace Library\Repository;

class SnsWebhookAttemptRepository
{
    public function createFromPayload($payload, $status, $reason, $httpStatus)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $message = json_decode($payload, true);
        $now = date('Y-m-d H:i:s');

        $db->insert('ses_sns_webhook_attempts', array(
            'message_id' => is_array($message) && isset($message['MessageId']) ? (string) $message['MessageId'] : '',
            'message_type' => is_array($message) && isset($message['Type']) ? (string) $message['Type'] : '',
            'topic_arn' => is_array($message) && isset($message['TopicArn']) ? (string) $message['TopicArn'] : '',
            'status' => $status,
            'reason' => $reason,
            'http_status' => (int) $httpStatus,
            'raw_payload' => $payload,
            'created_at' => $now,
        ));
    }

    public function findRecent($limit = 20)
    {
        return \pm_Bootstrap::getDbAdapter()->fetchAll(
            'SELECT * FROM ses_sns_webhook_attempts ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(100, (int) $limit))
        );
    }
}
