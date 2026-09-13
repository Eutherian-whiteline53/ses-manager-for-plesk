<?php

namespace Library\Service;

class AsnLookupService
{
    private $knownDatacenterProviders = array(
        'amazon' => 'AWS',
        'aws' => 'AWS',
        'digitalocean' => 'DigitalOcean',
        'google' => 'Google Cloud',
        'hetzner' => 'Hetzner',
        'linode' => 'Linode',
        'microsoft' => 'Microsoft Azure',
        'ovh' => 'OVH',
        'scaleway' => 'Scaleway',
        'vultr' => 'Vultr',
    );

    public function lookup($ipAddress)
    {
        $empty = array(
            'asn' => '',
            'prefix' => '',
            'country' => '',
            'registry' => '',
            'allocated' => '',
            'provider' => '',
            'name' => '',
            'knownDatacenter' => false,
            'source' => 'team-cymru-dns',
            'status' => 'unknown',
            'note' => \pm_Locale::lmsg('asnLookupUnavailable'),
        );

        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $empty;
        }

        $origin = $this->queryTxt($this->reverseIp($ipAddress) . '.origin.asn.cymru.com');
        if ($origin === '') {
            return $empty;
        }

        $originParts = $this->parseCymruParts($origin);
        $asn = isset($originParts[0]) ? trim($originParts[0]) : '';
        $prefix = isset($originParts[1]) ? trim($originParts[1]) : '';
        $country = isset($originParts[2]) ? trim($originParts[2]) : '';
        $registry = isset($originParts[3]) ? trim($originParts[3]) : '';
        $allocated = isset($originParts[4]) ? trim($originParts[4]) : '';
        $name = '';

        if ($asn !== '') {
            $nameRecord = $this->queryTxt('AS' . $asn . '.asn.cymru.com');
            $nameParts = $this->parseCymruParts($nameRecord);
            $name = isset($nameParts[4]) ? trim($nameParts[4]) : '';
        }

        $provider = $this->detectProvider($name);

        return array(
            'asn' => $asn === '' ? '' : 'AS' . $asn,
            'prefix' => $prefix,
            'country' => $country,
            'registry' => $registry,
            'allocated' => $allocated,
            'provider' => $provider !== '' ? $provider : $name,
            'name' => $name,
            'knownDatacenter' => $provider !== '',
            'source' => 'team-cymru-dns',
            'status' => $asn === '' ? 'unknown' : 'pass',
            'note' => $provider !== '' ? \pm_Locale::lmsg('asnDatacenterNote') : \pm_Locale::lmsg('asnNeutralNote'),
        );
    }

    private function queryTxt($name)
    {
        $records = @dns_get_record($name, DNS_TXT);
        if ($records === false || empty($records)) {
            return '';
        }

        foreach ($records as $record) {
            if (isset($record['txt'])) {
                return is_array($record['txt']) ? implode('', $record['txt']) : $record['txt'];
            }
        }

        return '';
    }

    private function reverseIp($ipAddress)
    {
        return implode('.', array_reverse(explode('.', $ipAddress)));
    }

    private function parseCymruParts($record)
    {
        $parts = array();
        foreach (explode('|', $record) as $part) {
            $parts[] = trim($part);
        }

        return $parts;
    }

    private function detectProvider($name)
    {
        $normalized = strtolower($name);
        foreach ($this->knownDatacenterProviders as $needle => $provider) {
            if (strpos($normalized, $needle) !== false) {
                return $provider;
            }
        }

        return '';
    }
}
