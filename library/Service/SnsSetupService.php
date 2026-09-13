<?php

namespace Library\Service;

use Library\Client\AwsSesClassicClient;
use Library\Client\AwsSnsClient;
use Library\Repository\DomainRepository;
use Library\Repository\SettingsRepository;

class SnsSetupService
{
    public function configureAll($webhookUrl)
    {
        return $this->provisionAll($webhookUrl);
    }

    public function provisionAll($webhookUrl)
    {
        try {
            $contextResult = $this->ensureTopicConfigured($webhookUrl);
            if (!$contextResult['success']) {
                return array(
                    'success' => false,
                    'message' => $contextResult['message'],
                    'raw' => array('success' => false, 'message' => $contextResult['message'], 'rawEnabled' => null),
                    'domains' => array(),
                );
            }

            $context = $contextResult['context'];
            $rawResult = $this->ensureWebhookSubscription($context['snsClient'], $context['topicArn'], $webhookUrl);

            $domainResults = array();
            foreach ((new DomainRepository())->findAll() as $domain) {
                $domainResults[] = $this->configureIdentityTopics($domain['domain_name']);
            }

            $success = $rawResult['success'];
            foreach ($domainResults as $result) {
                $success = $success && $result['success'];
            }

            return array(
                'success' => $success,
                'message' => trim($contextResult['message'] . ' ' . $this->summarize($rawResult, $domainResults)),
                'raw' => $rawResult,
                'domains' => $domainResults,
            );
        } catch (\Exception $e) {
            $this->logError('SES Manager: SNS automatic setup failed: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'raw' => array('success' => false, 'message' => $e->getMessage(), 'rawEnabled' => null),
                'domains' => array(),
            );
        }
    }

    public function disableRawMessageDelivery($webhookUrl)
    {
        try {
            $context = $this->buildContext();
            if (!$context['enabled']) {
                return array('success' => false, 'message' => $context['message'], 'rawEnabled' => null);
            }

            return $this->disableRawForContext($context['snsClient'], $context['topicArn'], $webhookUrl);
        } catch (\Exception $e) {
            $this->logError('SES Manager: SNS raw delivery check failed: ' . $e->getMessage());
            return array('success' => false, 'message' => $e->getMessage(), 'rawEnabled' => null);
        }
    }

    public function configureIdentityTopics($domainName)
    {
        try {
            $context = $this->buildContext();
            if (!$context['enabled']) {
                return array('success' => true, 'domain' => $domainName, 'message' => 'SNS topic is not configured; skipped.');
            }

            $sesClient = new AwsSesClassicClient(
                $context['accessKey'],
                $context['secretKey'],
                $context['sesRegion']
            );

            $sesClient->setIdentityNotificationTopic($domainName, 'Bounce', $context['topicArn']);
            $sesClient->setIdentityNotificationTopic($domainName, 'Complaint', $context['topicArn']);

            return array('success' => true, 'domain' => $domainName, 'message' => 'Bounce and Complaint topics are configured.');
        } catch (\Exception $e) {
            $this->logError('SES Manager: failed to configure SNS feedback topics for ' . $domainName . ': ' . $e->getMessage());
            return array('success' => false, 'domain' => $domainName, 'message' => $e->getMessage());
        }
    }

    private function ensureTopicConfigured($webhookUrl)
    {
        $settingsRepository = new SettingsRepository();
        $settings = $settingsRepository->get();
        $credentials = $settingsRepository->getAwsCredentials();

        if ($credentials['accessKey'] === null || $credentials['secretKey'] === null) {
            return array('success' => false, 'message' => 'AWS credentials are missing.', 'context' => null);
        }

        $topicArn = trim((string) $settings['sns_topic_arn']);
        $topicRegion = $topicArn !== '' ? $this->extractTopicRegion($topicArn) : $credentials['region'];

        if ($topicRegion === '') {
            return array('success' => false, 'message' => 'Allowed SNS Topic ARN is invalid.', 'context' => null);
        }

        $snsClient = new AwsSnsClient($credentials['accessKey'], $credentials['secretKey'], $topicRegion);
        $message = 'Using configured SNS topic.';

        if ($topicArn === '') {
            $topicArn = $snsClient->createTopic($this->topicName($webhookUrl));
            if ($topicArn === '') {
                return array('success' => false, 'message' => 'Could not create SNS topic.', 'context' => null);
            }

            $settingsRepository->saveGeneralSettings(array('sns_topic_arn' => $topicArn));
            $message = 'SNS topic created and saved.';
        }

        return array(
            'success' => true,
            'message' => $message,
            'context' => array(
                'enabled' => true,
                'topicArn' => $topicArn,
                'topicRegion' => $topicRegion,
                'sesRegion' => $credentials['region'],
                'accessKey' => $credentials['accessKey'],
                'secretKey' => $credentials['secretKey'],
                'snsClient' => $snsClient,
            ),
        );
    }

    private function ensureWebhookSubscription(AwsSnsClient $client, $topicArn, $webhookUrl)
    {
        $subscription = $this->findWebhookSubscription($client, $topicArn, $webhookUrl);
        if ($subscription === null) {
            $subscriptionArn = $client->subscribeHttpEndpoint($topicArn, $webhookUrl);
            return array(
                'success' => true,
                'message' => $subscriptionArn !== ''
                    ? 'SNS webhook subscription request was sent; confirmation will be handled by the public webhook.'
                    : 'SNS webhook subscription request was sent.',
                'rawEnabled' => false,
                'pending' => true,
            );
        }

        $subscriptionArn = isset($subscription['SubscriptionArn']) ? $subscription['SubscriptionArn'] : '';
        if ($subscriptionArn === '' || $subscriptionArn === 'PendingConfirmation' || stripos($subscriptionArn, 'pending') !== false) {
            return array(
                'success' => true,
                'message' => 'SNS subscription is pending confirmation; the webhook will confirm it when AWS sends the confirmation request.',
                'rawEnabled' => null,
                'pending' => true,
            );
        }

        return $this->disableRawForContext($client, $topicArn, $webhookUrl);
    }

    private function disableRawForContext(AwsSnsClient $client, $topicArn, $webhookUrl)
    {
        $subscription = $this->findWebhookSubscription($client, $topicArn, $webhookUrl);
        if ($subscription === null) {
            return array('success' => false, 'message' => 'SNS subscription for this webhook URL was not found.', 'rawEnabled' => null);
        }

        $subscriptionArn = isset($subscription['SubscriptionArn']) ? $subscription['SubscriptionArn'] : '';
        if ($subscriptionArn === '' || $subscriptionArn === 'PendingConfirmation' || stripos($subscriptionArn, 'pending') !== false) {
            return array('success' => false, 'message' => 'SNS subscription is still pending confirmation.', 'rawEnabled' => null);
        }

        $attributes = $client->getSubscriptionAttributes($subscriptionArn);
        $rawEnabled = isset($attributes['RawMessageDelivery']) && strtolower((string) $attributes['RawMessageDelivery']) === 'true';

        if ($rawEnabled) {
            $client->setRawMessageDelivery($subscriptionArn, false);
            return array('success' => true, 'message' => 'Raw message delivery was enabled and has been disabled.', 'rawEnabled' => false);
        }

        return array('success' => true, 'message' => 'Raw message delivery is already disabled.', 'rawEnabled' => false);
    }

    private function buildContext()
    {
        $settingsRepository = new SettingsRepository();
        $settings = $settingsRepository->get();
        $credentials = $settingsRepository->getAwsCredentials();
        $topicArn = trim((string) $settings['sns_topic_arn']);

        if ($topicArn === '') {
            return array('enabled' => false, 'message' => 'Allowed SNS Topic ARN is not configured.');
        }

        if ($credentials['accessKey'] === null || $credentials['secretKey'] === null) {
            return array('enabled' => false, 'message' => 'AWS credentials are missing.');
        }

        $topicRegion = $this->extractTopicRegion($topicArn);
        if ($topicRegion === '') {
            return array('enabled' => false, 'message' => 'Allowed SNS Topic ARN is invalid.');
        }

        return array(
            'enabled' => true,
            'topicArn' => $topicArn,
            'topicRegion' => $topicRegion,
            'sesRegion' => $credentials['region'],
            'accessKey' => $credentials['accessKey'],
            'secretKey' => $credentials['secretKey'],
            'snsClient' => new AwsSnsClient($credentials['accessKey'], $credentials['secretKey'], $topicRegion),
        );
    }

    private function extractTopicRegion($topicArn)
    {
        $parts = explode(':', $topicArn);

        return count($parts) >= 4 && $parts[0] === 'arn' && $parts[2] === 'sns' ? $parts[3] : '';
    }

    private function findWebhookSubscription(AwsSnsClient $client, $topicArn, $webhookUrl)
    {
        $target = $this->normalizeUrl($webhookUrl);

        foreach ($client->listSubscriptionsByTopic($topicArn) as $subscription) {
            $protocol = isset($subscription['Protocol']) ? strtolower($subscription['Protocol']) : '';
            $endpoint = isset($subscription['Endpoint']) ? $subscription['Endpoint'] : '';

            if ($protocol !== 'https' && $protocol !== 'http') {
                continue;
            }

            if ($this->normalizeUrl($endpoint) === $target) {
                return $subscription;
            }
        }

        return null;
    }

    private function normalizeUrl($url)
    {
        return rtrim(strtolower(trim((string) $url)), '/');
    }

    private function topicName($webhookUrl)
    {
        return 'ses-manager-feedback-' . substr(sha1($webhookUrl), 0, 10);
    }

    private function summarize(array $rawResult, array $domainResults)
    {
        $successCount = 0;
        $failed = array();

        foreach ($domainResults as $result) {
            if ($result['success']) {
                $successCount++;
                continue;
            }

            $failed[] = $result['domain'];
        }

        $message = $rawResult['message'] . ' SES feedback topics configured for ' . $successCount . ' domain(s).';
        if (!empty($failed)) {
            $message .= ' Failed: ' . implode(', ', $failed) . '.';
        }

        return $message;
    }

    private function logError($message)
    {
        try {
            \pm_Log::err($message);
        } catch (\Exception $e) {
            error_log($message);
        }
    }
}
