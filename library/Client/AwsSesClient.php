<?php

namespace Library\Client;

class AwsSesClient
{
    const SERVICE = 'ses';

    /** @var string */
    private $accessKey;

    /** @var string */
    private $secretKey;

    /** @var string */
    private $region;

    /** @var int */
    private $timeout;

    public function __construct($accessKey, $secretKey, $region, $timeout = 10)
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region;
        $this->timeout = $timeout;
    }

    public function listEmailIdentities()
    {
        return $this->request('GET', '/v2/email/identities');
    }

    public function getAccount()
    {
        return $this->request('GET', '/v2/email/account');
    }

    public function batchGetMetricData(array $queries)
    {
        return $this->request('POST', '/v2/email/metrics/batch', array(
            'Queries' => $queries,
        ));
    }

    public function createEmailIdentity($domain)
    {
        return $this->request('POST', '/v2/email/identities', array('EmailIdentity' => $domain));
    }

    public function getEmailIdentity($domain)
    {
        return $this->request('GET', '/v2/email/identities/' . rawurlencode($domain));
    }

    public function putEmailIdentityMailFromAttributes($domain, $mailFromDomain, $behaviorOnMxFailure = 'USE_DEFAULT_VALUE')
    {
        return $this->request('PUT', '/v2/email/identities/' . rawurlencode($domain) . '/mail-from', array(
            'MailFromDomain' => $mailFromDomain,
            'BehaviorOnMxFailure' => $behaviorOnMxFailure,
        ));
    }

    private function request($method, $path, ?array $body = null)
    {
        $host = sprintf('email.%s.amazonaws.com', $this->region);
        $payload = $body !== null ? json_encode($body) : '';

        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');
        $payloadHash = hash('sha256', $payload);

        $authorization = $this->buildAuthorizationHeader($method, $path, $host, $payloadHash, $amzDate, $dateStamp);

        $requestHeaders = array(
            'Host: ' . $host,
            'X-Amz-Date: ' . $amzDate,
            'X-Amz-Content-Sha256: ' . $payloadHash,
            'Authorization: ' . $authorization,
            'Content-Type: application/json',
        );

        $ch = curl_init('https://' . $host . $path);
        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ));

        if ($payload !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('AWS SES request failed: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($statusCode >= 400) {
            if (isset($decoded['message'])) {
                $message = $decoded['message'];
            } elseif (isset($decoded['Message'])) {
                $message = $decoded['Message'];
            } elseif (isset($decoded['__type'])) {
                $message = $decoded['__type'];
            } else {
                $message = 'AWS SES request failed with status ' . $statusCode;
            }
            throw new \RuntimeException($message, $statusCode);
        }

        return $decoded;
    }

    private function buildAuthorizationHeader($method, $path, $host, $payloadHash, $amzDate, $dateStamp)
    {
        $headers = array(
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        );
        ksort($headers);

        $canonicalHeaders = '';
        $signedHeadersList = array();
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
            $signedHeadersList[] = $name;
        }
        $signedHeaders = implode(';', $signedHeadersList);

        $canonicalRequest = implode("\n", array(
            $method,
            $path,
            '',
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ));

        $credentialScope = implode('/', array($dateStamp, $this->region, self::SERVICE, 'aws4_request'));

        $stringToSign = implode("\n", array(
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ));

        $signingKey = $this->getSignatureKey($dateStamp);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        return sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->accessKey,
            $credentialScope,
            $signedHeaders,
            $signature
        );
    }

    private function getSignatureKey($dateStamp)
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', self::SERVICE, $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
