<?php

namespace Library\Client;

class AwsSesClassicClient
{
    /** @var AwsQueryClient */
    private $client;

    public function __construct($accessKey, $secretKey, $region, $timeout = 10)
    {
        $this->client = new AwsQueryClient(
            'ses',
            $region,
            sprintf('email.%s.amazonaws.com', $region),
            $accessKey,
            $secretKey,
            $timeout
        );
    }

    public function setIdentityNotificationTopic($identity, $notificationType, $topicArn)
    {
        $this->client->post(array(
            'Action' => 'SetIdentityNotificationTopic',
            'Version' => '2010-12-01',
            'Identity' => $identity,
            'NotificationType' => $notificationType,
            'SnsTopic' => $topicArn,
        ));
    }

    public function getIdentityNotificationAttributes(array $identities)
    {
        $params = array(
            'Action' => 'GetIdentityNotificationAttributes',
            'Version' => '2010-12-01',
        );

        $index = 1;
        foreach ($identities as $identity) {
            $params['Identities.member.' . $index] = $identity;
            $index++;
        }

        return $this->client->post($params);
    }
}
