<?php

namespace Library\Service;

use Library\Repository\SettingsRepository;

class SupportBundleService
{
    private $settingsRepository;

    public function __construct(?SettingsRepository $settingsRepository = null)
    {
        $this->settingsRepository = $settingsRepository ?: new SettingsRepository();
    }

    public function build()
    {
        return array(
            'generated_at' => gmdate('c'),
            'extension' => $this->extensionInfo(),
            'server' => $this->serverInfo(),
            'settings' => $this->maskedSettings(),
            'database' => $this->databaseSummary(),
            'installed_extensions' => $this->installedExtensions(),
            'recent_errors' => $this->recentErrors(),
        );
    }

    public function toJson(array $bundle)
    {
        return json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function extensionInfo()
    {
        $meta = $this->loadMeta();

        return array(
            'id' => isset($meta['id']) ? $meta['id'] : 'ses-manager',
            'name' => isset($meta['name']) ? $meta['name'] : 'SES Manager for Plesk',
            'version' => isset($meta['version']) ? $meta['version'] : null,
            'release' => isset($meta['release']) ? $meta['release'] : null,
            'vendor' => isset($meta['vendor']) ? $meta['vendor'] : null,
        );
    }

    private function serverInfo()
    {
        return array(
            'hostname' => gethostname(),
            'os' => php_uname(),
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'plesk_version' => $this->getPleskVersion(),
            'admin_php_version' => $this->runCommand('/opt/psa/admin/bin/php -v 2>&1 | head -1'),
            'server_addr_hash' => $this->hashValue(isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : ''),
        );
    }

    private function maskedSettings()
    {
        $settings = $this->settingsRepository->get();
        $masked = $settings;

        foreach (array(
            'aws_key_encrypted',
            'aws_secret_encrypted',
            'smtp_username_encrypted',
            'smtp_password_encrypted',
            'cloudflare_token_encrypted',
        ) as $secretField) {
            $masked[$secretField] = empty($settings[$secretField]) ? 'missing' : 'configured';
        }

        $masked['smarthost_snapshot'] = $this->maskSmarthostSnapshot(isset($settings['smarthost_snapshot']) ? $settings['smarthost_snapshot'] : null);

        return $masked;
    }

    private function databaseSummary()
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $summary = array();
        foreach ($this->tables() as $table) {
            $summary[$table] = array(
                'exists' => false,
                'rows' => 0,
            );

            try {
                if (!$db->fetchOne('SHOW TABLES LIKE ?', array($table))) {
                    continue;
                }

                $summary[$table]['exists'] = true;
                $summary[$table]['rows'] = (int) $db->fetchOne('SELECT COUNT(*) FROM ' . $table);
                $dateColumn = $this->dateColumnForTable($table);
                if ($dateColumn) {
                    $summary[$table]['oldest'] = $db->fetchOne('SELECT MIN(' . $dateColumn . ') FROM ' . $table);
                    $summary[$table]['newest'] = $db->fetchOne('SELECT MAX(' . $dateColumn . ') FROM ' . $table);
                }
            } catch (\Exception $e) {
                $summary[$table]['error'] = $e->getMessage();
            }
        }

        return $summary;
    }

    private function installedExtensions()
    {
        $lines = $this->firstCommandLines(array('/usr/local/psa/bin/extension --list 2>&1', 'plesk bin extension --list 2>&1'));
        $extensions = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, ' - ') === false) {
                continue;
            }

            list($id, $name) = explode(' - ', $line, 2);
            $extension = array(
                'id' => trim($id),
                'name' => trim($name),
            );
            $extensions[] = array_merge($extension, $this->extensionCatalogMeta($extension['id']));
        }

        return $extensions;
    }

    private function recentErrors()
    {
        $logDir = dirname(__DIR__, 2) . '/var/logs';
        $entries = array();

        foreach (glob($logDir . '/*') ?: array() as $path) {
            if (!is_file($path)) {
                continue;
            }

            $entries[] = array(
                'file' => basename($path),
                'size' => filesize($path),
                'modified_at' => date('c', filemtime($path)),
            );
        }

        return $entries;
    }

    private function loadMeta()
    {
        $path = dirname(__DIR__, 2) . '/meta.xml';
        if (!is_file($path)) {
            return array();
        }

        $xml = @simplexml_load_file($path);
        if (!$xml) {
            return array();
        }

        $data = array();
        foreach (array('id', 'name', 'version', 'release', 'vendor') as $field) {
            $data[$field] = (string) $xml->{$field};
        }

        return $data;
    }

    private function runCommand($command)
    {
        $lines = $this->runCommandLines($command);

        return trim(implode("\n", $lines));
    }

    private function firstCommandOutput(array $commands)
    {
        return trim(implode("\n", $this->firstCommandLines($commands)));
    }

    private function firstCommandLines(array $commands)
    {
        foreach ($commands as $command) {
            $lines = $this->runCommandLines($command);
            $output = trim(implode("\n", $lines));
            if ($output !== '' && stripos($output, 'not found') === false) {
                return $lines;
            }
        }

        return array();
    }

    private function runCommandLines($command)
    {
        $lines = array();
        @exec($command, $lines);

        return array_slice($lines, 0, 200);
    }

    private function getPleskVersion()
    {
        if (class_exists('\\pm_ProductInfo') && method_exists('\\pm_ProductInfo', 'getVersion')) {
            try {
                $version = \pm_ProductInfo::getVersion();
                if ($version) {
                    return $version;
                }
            } catch (\Exception $e) {
            }
        }

        $output = $this->firstCommandOutput(array('/usr/local/psa/bin/plesk version 2>&1', '/usr/sbin/plesk version 2>&1', 'plesk version 2>&1'));

        return stripos($output, 'must run as root') === false ? $output : null;
    }

    private function maskSmarthostSnapshot($snapshot)
    {
        if (empty($snapshot)) {
            return null;
        }

        $decoded = json_decode((string) $snapshot, true);
        if (!is_array($decoded)) {
            return 'configured';
        }

        foreach (array('username', 'user', 'login', 'password', 'pass') as $field) {
            if (array_key_exists($field, $decoded)) {
                $decoded[$field] = $decoded[$field] === '' || $decoded[$field] === null ? 'missing' : 'configured';
            }
        }

        return $decoded;
    }

    private function extensionCatalogMeta($extensionId)
    {
        $safeId = preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $extensionId);
        if ($safeId === '') {
            return array();
        }

        $metaPath = '/usr/local/psa/admin/share/modules/' . $safeId . '/meta.xml';
        if (!is_file($metaPath) || !is_readable($metaPath)) {
            return array();
        }

        $xml = @simplexml_load_file($metaPath);
        if (!$xml) {
            return array();
        }

        $meta = array();
        foreach (array('version', 'release', 'vendor') as $field) {
            if (isset($xml->{$field}) && (string) $xml->{$field} !== '') {
                $meta[$field] = (string) $xml->{$field};
            }
        }

        return $meta;
    }

    private function hashValue($value)
    {
        $value = trim((string) $value);

        return $value === '' ? null : 'sha256:' . hash('sha256', $value);
    }

    private function tables()
    {
        return array(
            'ses_settings',
            'ses_domains',
            'ses_dns_records',
            'ses_mail_tests',
            'ses_events',
            'ses_sns_confirmations',
            'ses_sns_webhook_attempts',
            'ses_jobs',
            'ses_cloudflare_zones',
            'ses_reputation_snapshots',
            'ses_blacklist_checks',
            'ses_ip_reputation_checks',
            'ses_reputation_alerts',
            'ses_ip_reputation_events',
        );
    }

    private function dateColumnForTable($table)
    {
        $map = array(
            'ses_domains' => 'created_at',
            'ses_dns_records' => 'created_at',
            'ses_mail_tests' => 'created_at',
            'ses_events' => 'created_at',
            'ses_sns_confirmations' => 'created_at',
            'ses_sns_webhook_attempts' => 'created_at',
            'ses_jobs' => 'created_at',
            'ses_reputation_snapshots' => 'checked_at',
            'ses_blacklist_checks' => 'checked_at',
            'ses_ip_reputation_checks' => 'checked_at',
            'ses_reputation_alerts' => 'created_at',
            'ses_ip_reputation_events' => 'created_at',
        );

        return isset($map[$table]) ? $map[$table] : null;
    }
}
