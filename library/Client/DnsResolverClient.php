<?php

namespace Library\Client;

class DnsResolverClient
{
    /**
     * @return string[]
     */
    public function getTxtRecords($name)
    {
        return $this->queryRecords($name, DNS_TXT, 'txt');
    }

    /**
     * @return string[]
     */
    public function getCnameRecords($name)
    {
        return $this->queryRecords($name, DNS_CNAME, 'target');
    }

    /**
     * @return string[]
     */
    public function getMxRecords($name)
    {
        return $this->queryRecords($name, DNS_MX, 'target');
    }

    /**
     * @return string[]
     */
    public function getNsRecords($name)
    {
        return $this->queryRecords($name, DNS_NS, 'target');
    }

    private function queryRecords($name, $type, $field)
    {
        $digRecords = $this->queryAuthoritativeRecords($name, $type);
        if (!empty($digRecords)) {
            return $digRecords;
        }

        $records = @dns_get_record($name, $type);
        if ($records === false) {
            return array();
        }

        $values = array();
        foreach ($records as $record) {
            if (isset($record[$field])) {
                $values[] = is_array($record[$field]) ? implode('', $record[$field]) : $record[$field];
            }
        }

        return $values;
    }

    private function queryAuthoritativeRecords($name, $type)
    {
        if (!function_exists('exec')) {
            return array();
        }

        $typeName = $this->typeToDigName($type);
        if ($typeName === null) {
            return array();
        }

        foreach ($this->findAuthoritativeNameServers($name) as $nameServer) {
            $values = $this->dig($name, $typeName, $nameServer);
            if (!empty($values)) {
                return $values;
            }
        }

        return array();
    }

    private function findAuthoritativeNameServers($name)
    {
        $labels = explode('.', trim($name, '.'));
        $servers = array();

        for ($offset = 0; $offset < count($labels) - 1; $offset++) {
            $candidate = implode('.', array_slice($labels, $offset));
            $servers = $this->dig($candidate, 'NS', null);
            if (!empty($servers)) {
                break;
            }
        }

        $normalized = array();
        foreach ($servers as $server) {
            $server = rtrim(trim($server), '.');
            if ($server !== '') {
                $normalized[] = $server;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function dig($name, $typeName, $nameServer = null)
    {
        $command = 'dig +time=2 +tries=1 +short ' . escapeshellarg($name) . ' ' . escapeshellarg($typeName);
        if ($nameServer !== null && $nameServer !== '') {
            $command .= ' @' . escapeshellarg($nameServer);
        }

        exec($command . ' 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0) {
            return array();
        }

        $values = array();
        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($typeName === 'TXT') {
                $values[] = $this->parseDigTxtLine($line);
                continue;
            }

            $values[] = rtrim($line, '.');
        }

        return $values;
    }

    private function parseDigTxtLine($line)
    {
        if (preg_match_all('/"((?:\\\\.|[^"])*)"/', $line, $matches) && !empty($matches[1])) {
            $value = '';
            foreach ($matches[1] as $part) {
                $value .= stripcslashes($part);
            }

            return $value;
        }

        return trim($line, '"');
    }

    private function typeToDigName($type)
    {
        switch ($type) {
            case DNS_TXT:
                return 'TXT';
            case DNS_CNAME:
                return 'CNAME';
            case DNS_MX:
                return 'MX';
            case DNS_NS:
                return 'NS';
            default:
                return null;
        }
    }
}
