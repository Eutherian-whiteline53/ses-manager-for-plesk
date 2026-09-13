<?php

namespace Library\Service;

use Library\Client\AwsSesClient;
use Library\Service\Dto\ConnectionResult;
use Library\Service\Dto\DnsRecord;
use Library\Service\Dto\SesIdentityResult;

class AwsSesService
{
    public function testConnection($accessKey, $secretKey, $region)
    {
        try {
            $client = new AwsSesClient($accessKey, $secretKey, $region);
            $client->getAccount();

            return new ConnectionResult(true, \pm_Locale::lmsg('awsCredentialsConfigured'));
        } catch (\Exception $e) {
            \pm_Log::err('SES Manager: AWS SES connection test failed for region ' . $region . ': ' . $e->getMessage());

            return new ConnectionResult(false, \pm_Locale::lmsg('sesConnectionApiFailed'));
        }
    }

    public function getAccount($accessKey, $secretKey, $region)
    {
        $client = new AwsSesClient($accessKey, $secretKey, $region);

        return $client->getAccount();
    }

    public function createEmailIdentity($accessKey, $secretKey, $region, $domain)
    {
        $client = new AwsSesClient($accessKey, $secretKey, $region);
        $response = $client->createEmailIdentity($domain);

        return $this->buildIdentityResult($domain, $response);
    }

    public function getIdentityStatus($accessKey, $secretKey, $region, $domain)
    {
        $client = new AwsSesClient($accessKey, $secretKey, $region);
        $response = $client->getEmailIdentity($domain);

        return $this->buildIdentityResult($domain, $response);
    }

    public function configureMailFromDomain($accessKey, $secretKey, $region, $domain, $mailFromDomain)
    {
        $client = new AwsSesClient($accessKey, $secretKey, $region);
        $client->putEmailIdentityMailFromAttributes($domain, $mailFromDomain, 'USE_DEFAULT_VALUE');
    }

    /**
     * @return DnsRecord[]
     */
    public function getDkimRecords(SesIdentityResult $identity)
    {
        $records = array();

        foreach ($identity->dkimTokens as $token) {
            $records[] = new DnsRecord(
                'CNAME',
                sprintf('%s._domainkey.%s', $token, $identity->domain),
                sprintf('%s.dkim.amazonses.com', $token),
                'dkim'
            );
        }

        return $records;
    }

    /**
     * @return DnsRecord[]
     */
    public function getMailFromRecords($domain, $mailFromSubdomain, $region)
    {
        $mailFromDomain = $this->buildMailFromDomain($domain, $mailFromSubdomain);

        return array(
            new DnsRecord('MX', $mailFromDomain, sprintf('10 feedback-smtp.%s.amazonses.com', $region), 'mail_from_mx'),
            new DnsRecord('TXT', $mailFromDomain, 'include:amazonses.com', 'spf'),
        );
    }

    public function getDmarcRecord($domain, $policy)
    {
        $policy = in_array($policy, array('none', 'quarantine', 'reject'), true) ? $policy : 'none';

        return new DnsRecord('TXT', '_dmarc.' . $domain, 'v=DMARC1; p=' . $policy . '; rua=mailto:dmarc@' . $domain . '; pct=100', 'dmarc');
    }

    public function buildMailFromDomain($domain, $mailFromSubdomain)
    {
        $mailFromSubdomain = trim(strtolower($mailFromSubdomain), ". \t\n\r\0\x0B");

        if ($mailFromSubdomain === '') {
            $mailFromSubdomain = 'bounce';
        }

        return $mailFromSubdomain . '.' . $domain;
    }

    private function buildIdentityResult($domain, array $response)
    {
        $tokens = array();
        if (isset($response['DkimAttributes']['Tokens'])) {
            $tokens = $response['DkimAttributes']['Tokens'];
        }

        $status = isset($response['VerificationStatus']) ? $response['VerificationStatus'] : 'PENDING';

        return new SesIdentityResult($domain, $status, $tokens);
    }
}
