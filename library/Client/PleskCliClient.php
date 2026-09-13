<?php

namespace Library\Client;

class PleskCliClient
{
    /** @var string */
    private $binary;

    public function __construct($binary = '/usr/local/psa/bin/dns')
    {
        $this->binary = $binary;
    }

    /**
     * @return array{type:string,host:string,value:string}[]
     */
    public function getRecords($domain)
    {
        $output = $this->execute(array('--info', $domain));

        return $this->parseRecords($output);
    }

    public function addRecord($domain, $type, $host, $value)
    {
        $this->execute($this->buildRecordArgs('--add', $domain, $type, $host, $value));
    }

    public function removeRecord($domain, $type, $host, $value)
    {
        $this->execute($this->buildRecordArgs('--del', $domain, $type, $host, $value));
    }

    private function buildRecordArgs($command, $domain, $type, $host, $value)
    {
        $type = strtoupper($type);
        $host = $this->normalizeHost($host);
        $args = array($command, $domain);

        if ($type === 'TXT') {
            return array_merge($args, array('-txt', $value, '-domain', $host));
        }

        if ($type === 'CNAME') {
            return array_merge($args, array('-cname', $host, '-canonical', $value));
        }

        if ($type === 'MX') {
            if (!preg_match('/^(\d+)\s+(.+)$/', trim($value), $matches)) {
                throw new \InvalidArgumentException('MX value must be formatted as "<priority> <mail exchanger>".');
            }

            return array_merge($args, array('-mx', $host, '-mailexchanger', trim($matches[2]), '-priority', $matches[1]));
        }

        if ($type === 'A') {
            return array_merge($args, array('-a', $host, '-ip', $value));
        }

        if ($type === 'AAAA') {
            return array_merge($args, array('-aaaa', $host, '-ip', $value));
        }

        if ($type === 'NS') {
            return array_merge($args, array('-ns', $host, '-nameserver', $value));
        }

        throw new \InvalidArgumentException('Unsupported DNS record type for Plesk DNS apply: ' . $type);
    }

    private function execute(array $args)
    {
        $command = escapeshellcmd($this->binary);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Plesk DNS CLI command failed.');
        }

        return implode("\n", $output);
    }

    private function parseRecords($output)
    {
        $records = array();

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'Type') === 0 || stripos($line, 'SUCCESS:') === 0) {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 3);
            if (count($parts) === 3) {
                $records[] = array(
                    'type' => strtoupper($parts[1]),
                    'host' => $parts[0],
                    'value' => $parts[2],
                );
            }
        }

        return $records;
    }

    private function normalizeHost($host)
    {
        $host = trim($host);

        return $host === '@' ? '' : $host;
    }
}
