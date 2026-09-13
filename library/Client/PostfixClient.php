<?php

namespace Library\Client;

class PostfixClient
{
    /** @var string */
    private $binary;

    public function __construct($binary = null)
    {
        if ($binary !== null) {
            $this->binary = $binary;
            return;
        }

        foreach (array('/usr/sbin/postconf', '/usr/local/sbin/postconf', 'postconf') as $candidate) {
            if ($candidate === 'postconf' || is_executable($candidate)) {
                $this->binary = $candidate;
                return;
            }
        }

        $this->binary = 'postconf';
    }

    public function getConfig(array $keys)
    {
        $command = escapeshellcmd($this->binary);
        foreach ($keys as $key) {
            if (!preg_match('/^[a-z0-9_]+$/', $key)) {
                throw new \InvalidArgumentException('Invalid postconf key.');
            }
            $command .= ' ' . escapeshellarg($key);
        }

        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            return array();
        }

        $config = array();
        foreach ($output as $line) {
            if (preg_match('/^([a-z0-9_]+)\s*=\s*(.*)$/i', $line, $matches)) {
                $config[strtolower($matches[1])] = trim($matches[2]);
            }
        }

        return $config;
    }
}
