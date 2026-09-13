<?php

namespace Library\Service;

use Library\Repository\DomainRepository;
use Library\Repository\EventRepository;
use Library\Repository\SnsConfirmationRepository;
use Library\Repository\SettingsRepository;
use Library\Service\Dto\WebhookResult;

class WebhookService
{
    const MAX_PAYLOAD_BYTES = 131072;

    /** @var EventRepository */
    private $eventRepository;

    /** @var DomainRepository */
    private $domainRepository;

    /** @var SettingsRepository */
    private $settingsRepository;

    /** @var SnsConfirmationRepository */
    private $snsConfirmationRepository;

    /** @var string */
    private $lastVerificationError = '';

    public function __construct(?EventRepository $eventRepository = null, ?DomainRepository $domainRepository = null, ?SettingsRepository $settingsRepository = null, ?SnsConfirmationRepository $snsConfirmationRepository = null)
    {
        $this->eventRepository = $eventRepository ?: new EventRepository();
        $this->domainRepository = $domainRepository ?: new DomainRepository();
        $this->settingsRepository = $settingsRepository ?: new SettingsRepository();
        $this->snsConfirmationRepository = $snsConfirmationRepository ?: new SnsConfirmationRepository();
    }

    public function verifySnsSignature($payload)
    {
        $this->lastVerificationError = '';

        if (strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            $this->lastVerificationError = 'Payload is too large.';
            return false;
        }

        $message = json_decode($payload, true);
        if (!is_array($message) || empty($message['SigningCertURL']) || empty($message['Signature'])) {
            if (is_array($message) && isset($message['notificationType']) && isset($message['mail'])) {
                $this->lastVerificationError = 'SNS Raw message delivery is enabled. Disable Raw message delivery on the SNS HTTPS subscription so AWS sends a signed SNS envelope.';
                return false;
            }

            $this->lastVerificationError = 'Missing SigningCertURL or Signature.';
            return false;
        }

        if (!$this->isAllowedTopic($message)) {
            $this->lastVerificationError = 'TopicArn does not match Allowed SNS Topic ARN.';
            return false;
        }

        $certUrl = $message['SigningCertURL'];

        if (!$this->isTrustedAwsCertificateUrl($certUrl)) {
            $this->lastVerificationError = 'SigningCertURL is not trusted.';
            return false;
        }

        $cert = $this->fetchHttps($certUrl);
        if ($cert === false) {
            $this->lastVerificationError = 'Could not fetch SNS signing certificate.';
            return false;
        }

        $publicKey = openssl_pkey_get_public($cert);
        if ($publicKey === false) {
            $this->lastVerificationError = 'Could not read SNS signing certificate public key.';
            return false;
        }

        $canonicalString = $this->buildCanonicalString($message);
        $signature = base64_decode($message['Signature'], true);
        if ($signature === false) {
            $this->lastVerificationError = 'SNS signature is not valid base64.';
            return false;
        }

        $algorithm = (isset($message['SignatureVersion']) && $message['SignatureVersion'] === '2')
            ? OPENSSL_ALGO_SHA256
            : OPENSSL_ALGO_SHA1;

        $verified = openssl_verify($canonicalString, $signature, $publicKey, $algorithm) === 1;
        if (!$verified) {
            $this->lastVerificationError = 'OpenSSL signature verification failed.';
        }

        return $verified;
    }

    public function getLastVerificationError()
    {
        return $this->lastVerificationError;
    }

    public function handleSnsPayload($payload)
    {
        $message = json_decode($payload, true);
        if (!is_array($message) || !isset($message['Type'])) {
            return new WebhookResult(false, 'Invalid SNS payload.');
        }

        switch ($message['Type']) {
            case 'SubscriptionConfirmation':
                return $this->confirmSubscription($message);
            case 'Notification':
                return $this->handleNotification($message);
            case 'UnsubscribeConfirmation':
                return new WebhookResult(true, 'Unsubscribe confirmation acknowledged.');
            default:
                return new WebhookResult(false, 'Unsupported SNS message type.');
        }
    }

    private function confirmSubscription(array $message)
    {
        $confirmationId = $this->snsConfirmationRepository->saveRequest($message);

        if (empty($message['SubscribeURL'])) {
            $this->snsConfirmationRepository->markStatus($confirmationId, 'failed', 'Missing SubscribeURL.');
            return new WebhookResult(false, 'Missing SubscribeURL.');
        }

        if (!$this->isAllowedTopic($message)) {
            $this->snsConfirmationRepository->markStatus($confirmationId, 'failed', 'SNS topic is not allowed.');
            return new WebhookResult(false, 'SNS topic is not allowed.');
        }

        if (parse_url($message['SubscribeURL'], PHP_URL_SCHEME) !== 'https'
            || !$this->isTrustedAwsHost(parse_url($message['SubscribeURL'], PHP_URL_HOST))
        ) {
            $this->snsConfirmationRepository->markStatus($confirmationId, 'failed', 'Untrusted SubscribeURL host.');
            return new WebhookResult(false, 'Untrusted SubscribeURL host.');
        }

        $response = $this->fetchHttps($message['SubscribeURL']);
        if ($response === false) {
            $this->snsConfirmationRepository->markStatus($confirmationId, 'failed', 'Failed to confirm SNS subscription.');
            return new WebhookResult(false, 'Failed to confirm SNS subscription.');
        }

        $this->snsConfirmationRepository->markStatus($confirmationId, 'confirmed');

        return new WebhookResult(true, 'SNS subscription confirmed.');
    }

    private function handleNotification(array $message)
    {
        if (!isset($message['Message'])) {
            return new WebhookResult(false, 'Missing Message field.');
        }

        $notification = json_decode($message['Message'], true);
        if (!is_array($notification) || !isset($notification['notificationType'])) {
            return new WebhookResult(false, 'Invalid SES notification payload.');
        }

        $eventType = strtolower($notification['notificationType']);

        if ($eventType === 'amazonsnssubscriptionsucceeded') {
            return new WebhookResult(true, $this->buildSubscriptionSucceededMessage($notification));
        }

        if (!in_array($eventType, array('bounce', 'complaint', 'delivery'), true)) {
            return new WebhookResult(true, 'Ignored unsupported SES notification type: ' . $notification['notificationType'] . '.');
        }

        $feature = $this->featureForEventType($eventType);
        if (!(new LicenseService())->canUseFeature($feature)) {
            return new WebhookResult(true, 'Ignored ' . $eventType . ' notification because this event type is unavailable.');
        }

        $messageId = isset($notification['mail']['messageId']) ? $notification['mail']['messageId'] : (isset($message['MessageId']) ? $message['MessageId'] : '');
        $receivedAt = isset($message['Timestamp']) ? $this->toMysqlDateTime($message['Timestamp']) : date('Y-m-d H:i:s');
        $domainId = $this->resolveDomainId($notification);
        $bounceType = $this->extractBounceType($eventType, $notification);

        $stored = 0;
        foreach ($this->extractRecipients($eventType, $notification) as $recipient) {
            if ($this->eventRepository->exists($messageId, $eventType, $recipient)) {
                continue;
            }

            $this->eventRepository->create(array(
                'domain_id' => $domainId,
                'event_type' => $eventType,
                'bounce_type' => $bounceType,
                'recipient' => $recipient,
                'message_id' => $messageId,
                'raw_payload' => json_encode($notification),
                'received_at' => $receivedAt,
            ));
            $stored++;
        }

        return new WebhookResult(true, 'Processed ' . $stored . ' event(s).');
    }

    private function featureForEventType($eventType)
    {
        switch ($eventType) {
            case 'bounce':
                return LicenseService::FEATURE_BOUNCE_TRACKING;
            case 'complaint':
                return LicenseService::FEATURE_COMPLAINT_TRACKING;
            case 'delivery':
            default:
                return LicenseService::FEATURE_DELIVERY_TRACKING;
        }
    }

    private function buildSubscriptionSucceededMessage(array $notification)
    {
        if (!empty($notification['message'])) {
            return $notification['message'];
        }

        return 'SES notification topic subscription confirmed.';
    }

    private function extractRecipients($eventType, array $notification)
    {
        switch ($eventType) {
            case 'bounce':
                $recipients = array();
                foreach ((isset($notification['bounce']['bouncedRecipients']) ? $notification['bounce']['bouncedRecipients'] : array()) as $recipient) {
                    if (!empty($recipient['emailAddress'])) {
                        $recipients[] = $recipient['emailAddress'];
                    }
                }
                return $recipients;
            case 'complaint':
                $recipients = array();
                foreach ((isset($notification['complaint']['complainedRecipients']) ? $notification['complaint']['complainedRecipients'] : array()) as $recipient) {
                    if (!empty($recipient['emailAddress'])) {
                        $recipients[] = $recipient['emailAddress'];
                    }
                }
                return $recipients;
            case 'delivery':
                return isset($notification['delivery']['recipients']) ? $notification['delivery']['recipients'] : array();
            default:
                return array();
        }
    }

    private function extractBounceType($eventType, array $notification)
    {
        if ($eventType !== 'bounce' || !isset($notification['bounce']['bounceType'])) {
            return null;
        }

        return strtolower($notification['bounce']['bounceType']);
    }

    private function resolveDomainId(array $notification)
    {
        if (empty($notification['mail']['source'])) {
            return null;
        }

        $atPos = strrpos($notification['mail']['source'], '@');
        if ($atPos === false) {
            return null;
        }

        $domainName = substr($notification['mail']['source'], $atPos + 1);
        $domain = $this->domainRepository->findByName($domainName);

        return $domain ? (int) $domain['id'] : null;
    }

    private function buildCanonicalString(array $message)
    {
        if (isset($message['Type']) && $message['Type'] === 'Notification') {
            $fields = array('Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type');
        } else {
            $fields = array('Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type');
        }

        $canonical = '';
        foreach ($fields as $field) {
            if (!array_key_exists($field, $message)) {
                continue;
            }

            $canonical .= $field . "\n" . $message[$field] . "\n";
        }

        return $canonical;
    }

    private function toMysqlDateTime($timestamp)
    {
        try {
            $dt = new \DateTime($timestamp);
        } catch (\Exception $e) {
            return date('Y-m-d H:i:s');
        }

        return $dt->format('Y-m-d H:i:s');
    }

    private function isTrustedAwsHost($host)
    {
        if ($host === null) {
            return false;
        }

        return (bool) preg_match('/^sns\.[a-z0-9\-]+\.amazonaws\.com$/i', $host);
    }

    private function isTrustedAwsCertificateUrl($url)
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !$this->isTrustedAwsHost(parse_url($url, PHP_URL_HOST))) {
            return false;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        return (bool) preg_match('#^/SimpleNotificationService-[A-Za-z0-9]+\\.pem$#', $path);
    }

    private function isAllowedTopic(array $message)
    {
        $settings = $this->settingsRepository->get();
        $allowedTopic = trim((string) $settings['sns_topic_arn']);

        if ($allowedTopic === '') {
            return true;
        }

        return isset($message['TopicArn']) && hash_equals($allowedTopic, (string) $message['TopicArn']);
    }

    private function fetchHttps($url)
    {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 5,
                'max_redirects' => 0,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return false;
        }

        $headers = isset($http_response_header) ? $http_response_header : array();
        if (!empty($headers[0]) && !preg_match('#\\s200\\s#', $headers[0])) {
            return false;
        }

        return $response;
    }
}
