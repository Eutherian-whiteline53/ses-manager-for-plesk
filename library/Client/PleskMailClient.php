<?php

namespace Library\Client;

class PleskMailClient
{
    private $binary;

    public function __construct($binary = '/usr/local/psa/bin/mailserver')
    {
        $this->binary = $binary;
    }

    public function getRelayConfig()
    {
        $output = $this->executeSystem('postconf relayhost smtp_sasl_auth_enable smtp_tls_security_level');

        return $this->parseRelayConfig($output);
    }

    public function setRelay($host, $port, $username, $password, $encryption)
    {
        $this->execute(array(
            '--configure-smarthost',
            '-smarthost-host', $host,
            '-smarthost-port', (string) $port,
            '-smarthost-user', $username,
        ), array('PSA_PASSWORD' => $password));

        $this->execute(array('--enable-smarthost'));
    }

    public function disableRelay()
    {
        $this->execute(array('--disable-smarthost'));
    }

    private function execute(array $args, array $env = array())
    {
        $command = escapeshellcmd($this->binary);

        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        foreach ($env as $name => $value) {
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
                throw new \InvalidArgumentException('Invalid environment variable name.');
            }

            $command = $name . '=' . escapeshellarg($value) . ' ' . $command;
        }

        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Plesk mailserver CLI command failed: ' . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function executeSystem($command)
    {
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('System command failed: ' . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function parseRelayConfig($output)
    {
        $config = array(
            'host' => '',
            'port' => 587,
            'username' => '',
            'encryption' => 'none',
            'enabled' => false,
        );

        foreach (explode("\n", $output) as $line) {
            if (!preg_match('/^\s*([A-Za-z0-9_.-]+)\s*[:=]\s*(.*)$/', trim($line), $matches)) {
                continue;
            }

            $key = strtolower(str_replace('-', '_', $matches[1]));
            $value = trim($matches[2]);

            switch ($key) {
                case 'relayhost':
                    $config['enabled'] = $value !== '';
                    if (preg_match('/^\[?([^\]\:]+)\]?(?::(\d+))?$/', $value, $relayMatches)) {
                        $config['host'] = $relayMatches[1];
                        if (!empty($relayMatches[2])) {
                            $config['port'] = (int) $relayMatches[2];
                        }
                    } else {
                        $config['host'] = $value;
                    }
                    break;
                case 'smtp_sasl_auth_enable':
                    $config['auth_enabled'] = strtolower($value) === 'yes';
                    break;
                case 'smtp_tls_security_level':
                    $config['encryption'] = strtolower($value) === 'may' ? 'starttls' : $value;
                    break;
                case 'relay-host':
                case 'host':
                    $config['host'] = $value;
                    break;
                case 'relay-port':
                case 'port':
                    $config['port'] = (int) $value;
                    break;
                case 'relay-user':
                case 'username':
                    $config['username'] = $value;
                    break;
                case 'relay-encryption':
                case 'encryption':
                    $config['encryption'] = $value;
                    break;
                case 'relay-auth':
                    $config['enabled'] = strtolower($value) === 'on';
                    break;
            }
        }

        return $config;
    }
}
