<?php

namespace Library\Client;

class CloudflareClient
{
    const BASE_URL = 'https://api.cloudflare.com/client/v4';

    /** @var string */
    private $apiToken;

    /** @var int */
    private $timeout;

    public function __construct($apiToken, $timeout = 10)
    {
        $this->apiToken = $apiToken;
        $this->timeout = $timeout;
    }

    /**
     * @return array{id:string,name:string}|null
     */
    public function findZoneForDomain($domain)
    {
        $labels = explode('.', $domain);

        while (count($labels) >= 2) {
            $candidate = implode('.', $labels);
            $response = $this->request('GET', '/zones', array('name' => $candidate));

            if (!empty($response['result'][0]['id'])) {
                return array(
                    'id' => $response['result'][0]['id'],
                    'name' => $response['result'][0]['name'],
                );
            }

            array_shift($labels);
        }

        return null;
    }

    /**
     * @return array[]
     */
    public function listAccounts()
    {
        $response = $this->request('GET', '/accounts');

        return isset($response['result']) ? $response['result'] : array();
    }

    /**
     * @return array{id:string,name:string}
     */
    public function createZone($domain, $accountId)
    {
        $response = $this->request('POST', '/zones', null, array(
            'name' => $domain,
            'account' => array('id' => $accountId),
            'type' => 'full',
        ));

        return array(
            'id' => $response['result']['id'],
            'name' => $response['result']['name'],
        );
    }

    /**
     * @return array[] Cloudflare DNS record resources
     */
    public function listDnsRecords($zoneId, $type = null, $name = null)
    {
        $query = array();
        if ($type !== null) {
            $query['type'] = $type;
        }
        if ($name !== null) {
            $query['name'] = $name;
        }

        $response = $this->request('GET', '/zones/' . rawurlencode($zoneId) . '/dns_records', $query);

        return isset($response['result']) ? $response['result'] : array();
    }

    public function createDnsRecord($zoneId, $type, $name, $content, $ttl = 3600)
    {
        return $this->request('POST', '/zones/' . rawurlencode($zoneId) . '/dns_records', null, $this->buildDnsRecordPayload($type, $name, $content, $ttl));
    }

    public function updateDnsRecord($zoneId, $recordId, $type, $name, $content, $ttl = 3600)
    {
        return $this->request('PUT', '/zones/' . rawurlencode($zoneId) . '/dns_records/' . rawurlencode($recordId), null, $this->buildDnsRecordPayload($type, $name, $content, $ttl));
    }

    private function buildDnsRecordPayload($type, $name, $content, $ttl)
    {
        $payload = array(
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
        );

        if (strtoupper($type) === 'MX' && preg_match('/^(\d+)\s+(.+)$/', trim($content), $matches)) {
            $payload['priority'] = (int) $matches[1];
            $payload['content'] = trim($matches[2]);
        }

        return $payload;
    }

    public function deleteDnsRecord($zoneId, $recordId)
    {
        return $this->request('DELETE', '/zones/' . rawurlencode($zoneId) . '/dns_records/' . rawurlencode($recordId));
    }

    private function request($method, $path, ?array $query = null, ?array $body = null)
    {
        $url = self::BASE_URL . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $headers = array(
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ));

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Cloudflare API request failed: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($statusCode >= 400 || empty($decoded['success'])) {
            $message = 'Cloudflare API request failed with status ' . $statusCode;
            if (!empty($decoded['errors'][0]['message'])) {
                $message = $decoded['errors'][0]['message'];
            }

            throw new \RuntimeException($message, $statusCode);
        }

        return $decoded;
    }
}
