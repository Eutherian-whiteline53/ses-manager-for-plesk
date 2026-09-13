<?php

namespace Library\Client;

class AwsQueryClient
{
    /** @var string */
    private $service;

    /** @var string */
    private $region;

    /** @var string */
    private $host;

    /** @var string */
    private $accessKey;

    /** @var string */
    private $secretKey;

    /** @var int */
    private $timeout;

    public function __construct($service, $region, $host, $accessKey, $secretKey, $timeout = 10)
    {
        $this->service = $service;
        $this->region = $region;
        $this->host = $host;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->timeout = $timeout;
    }

    public function post(array $params)
    {
        ksort($params);
        $payload = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $method = 'POST';
        $path = '/';

        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');
        $payloadHash = hash('sha256', $payload);

        $authorization = $this->buildAuthorizationHeader($method, $path, $payloadHash, $amzDate, $dateStamp);
        $headers = array(
            'Host: ' . $this->host,
            'X-Amz-Date: ' . $amzDate,
            'X-Amz-Content-Sha256: ' . $payloadHash,
            'Authorization: ' . $authorization,
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
        );

        $ch = curl_init('https://' . $this->host . $path);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ));

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('AWS ' . strtoupper($this->service) . ' request failed: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new \RuntimeException($this->extractErrorMessage($response, $statusCode), $statusCode);
        }

        return $this->parseXml($response);
    }

    private function buildAuthorizationHeader($method, $path, $payloadHash, $amzDate, $dateStamp)
    {
        $headers = array(
            'host' => $this->host,
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

        $credentialScope = implode('/', array($dateStamp, $this->region, $this->service, 'aws4_request'));
        $stringToSign = implode("\n", array(
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ));

        $signature = hash_hmac('sha256', $stringToSign, $this->getSignatureKey($dateStamp));

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
        $kService = hash_hmac('sha256', $this->service, $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    private function parseXml($response)
    {
        if (!function_exists('simplexml_load_string')) {
            return array('raw' => $response);
        }

        $xml = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            return array('raw' => $response);
        }

        return json_decode(json_encode($xml), true);
    }

    private function extractErrorMessage($response, $statusCode)
    {
        if (preg_match('/<Message>(.*?)<\/Message>/s', $response, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }

        if (preg_match('/<Code>(.*?)<\/Code>/s', $response, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }

        return 'AWS ' . strtoupper($this->service) . ' request failed with status ' . $statusCode;
    }
}
