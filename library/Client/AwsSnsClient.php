<?php

namespace Library\Client;

class AwsSnsClient
{
    /** @var AwsQueryClient */
    private $client;

    public function __construct($accessKey, $secretKey, $region, $timeout = 10)
    {
        $this->client = new AwsQueryClient(
            'sns',
            $region,
            sprintf('sns.%s.amazonaws.com', $region),
            $accessKey,
            $secretKey,
            $timeout
        );
    }

    public function listSubscriptionsByTopic($topicArn)
    {
        $response = $this->client->post(array(
            'Action' => 'ListSubscriptionsByTopic',
            'Version' => '2010-03-31',
            'TopicArn' => $topicArn,
        ));

        $members = isset($response['ListSubscriptionsByTopicResult']['Subscriptions']['member'])
            ? $response['ListSubscriptionsByTopicResult']['Subscriptions']['member']
            : array();

        if (empty($members)) {
            return array();
        }

        if (isset($members['SubscriptionArn'])) {
            $members = array($members);
        }

        return $members;
    }

    public function createTopic($name)
    {
        $response = $this->client->post(array(
            'Action' => 'CreateTopic',
            'Version' => '2010-03-31',
            'Name' => $name,
        ));

        return isset($response['CreateTopicResult']['TopicArn'])
            ? $response['CreateTopicResult']['TopicArn']
            : '';
    }

    public function subscribeHttpEndpoint($topicArn, $webhookUrl)
    {
        $protocol = stripos($webhookUrl, 'https://') === 0 ? 'https' : 'http';
        $response = $this->client->post(array(
            'Action' => 'Subscribe',
            'Version' => '2010-03-31',
            'TopicArn' => $topicArn,
            'Protocol' => $protocol,
            'Endpoint' => $webhookUrl,
            'ReturnSubscriptionArn' => 'true',
            'Attributes.entry.1.key' => 'RawMessageDelivery',
            'Attributes.entry.1.value' => 'false',
        ));

        return isset($response['SubscribeResult']['SubscriptionArn'])
            ? $response['SubscribeResult']['SubscriptionArn']
            : '';
    }

    public function getSubscriptionAttributes($subscriptionArn)
    {
        $response = $this->client->post(array(
            'Action' => 'GetSubscriptionAttributes',
            'Version' => '2010-03-31',
            'SubscriptionArn' => $subscriptionArn,
        ));

        $entries = isset($response['GetSubscriptionAttributesResult']['Attributes']['entry'])
            ? $response['GetSubscriptionAttributesResult']['Attributes']['entry']
            : array();

        if (empty($entries)) {
            return array();
        }

        if (isset($entries['key'])) {
            $entries = array($entries);
        }

        $attributes = array();
        foreach ($entries as $entry) {
            if (isset($entry['key'])) {
                $attributes[$entry['key']] = isset($entry['value']) ? $entry['value'] : '';
            }
        }

        return $attributes;
    }

    public function setRawMessageDelivery($subscriptionArn, $enabled)
    {
        $this->client->post(array(
            'Action' => 'SetSubscriptionAttributes',
            'Version' => '2010-03-31',
            'SubscriptionArn' => $subscriptionArn,
            'AttributeName' => 'RawMessageDelivery',
            'AttributeValue' => $enabled ? 'true' : 'false',
        ));
    }
}
