<?php

namespace Library\Repository;

class SettingsRepository
{
    const EULA_VERSION = '2026-06-17';
    const PRIVACY_VERSION = '2026-06-16';

    public function get()
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $row = $db->fetchRow('SELECT * FROM ses_settings WHERE id = 1');

        return $row ? array_merge($this->defaults(), $row) : $this->defaults();
    }

    public function getAwsCredentials()
    {
        $row = $this->get();

        return array(
            'accessKey' => $row['aws_key_encrypted'] ? \pm_Crypt::decrypt($row['aws_key_encrypted']) : null,
            'secretKey' => $row['aws_secret_encrypted'] ? \pm_Crypt::decrypt($row['aws_secret_encrypted']) : null,
            'region' => $row['default_region'],
        );
    }

    public function getSmtpCredentials()
    {
        $row = $this->get();

        return array(
            'username' => $row['smtp_username_encrypted'] ? \pm_Crypt::decrypt($row['smtp_username_encrypted']) : null,
            'password' => $row['smtp_password_encrypted'] ? \pm_Crypt::decrypt($row['smtp_password_encrypted']) : null,
        );
    }

    public function saveAwsCredentials($accessKey, $secretKey, $region)
    {
        $this->upsert(array(
            'aws_key_encrypted' => \pm_Crypt::encrypt($accessKey),
            'aws_secret_encrypted' => \pm_Crypt::encrypt($secretKey),
            'default_region' => $region,
        ));
    }

    public function saveDefaultRegion($region)
    {
        $this->upsert(array(
            'default_region' => $region,
        ));
    }

    public function saveSmtpCredentials($username, $password)
    {
        $this->upsert(array(
            'smtp_username_encrypted' => \pm_Crypt::encrypt($username),
            'smtp_password_encrypted' => \pm_Crypt::encrypt($password),
        ));
    }

    public function getCloudflareToken()
    {
        $row = $this->get();

        return $row['cloudflare_token_encrypted'] ? \pm_Crypt::decrypt($row['cloudflare_token_encrypted']) : null;
    }

    public function saveCloudflareToken($token)
    {
        $this->upsert(array(
            'cloudflare_token_encrypted' => \pm_Crypt::encrypt($token),
        ));
    }

    public function hasCloudflareToken()
    {
        $row = $this->get();

        return !empty($row['cloudflare_token_encrypted']);
    }

    public function saveGeneralSettings(array $settings)
    {
        $allowed = array('default_spf_policy', 'default_dmarc_policy', 'mail_from_subdomain', 'auto_ses_on_create', 'setup_completed', 'smarthost_enabled', 'sns_topic_arn', 'cache_ttl_seconds', 'retention_days');

        $this->upsert(array_intersect_key($settings, array_flip($allowed)));
    }

    public function saveAdvancedSettings(array $settings)
    {
        $allowed = array(
            'mail_test_retention_days',
            'event_retention_days',
            'webhook_attempt_retention_days',
            'job_retention_days',
            'reputation_retention_days',
            'ip_reputation_retention_days',
            'log_retention_days',
        );

        $data = array_intersect_key($settings, array_flip($allowed));
        foreach ($data as $key => $value) {
            $data[$key] = max(1, (int) $value);
        }

        $this->upsert($data);
    }

    public function setSetupCompleted($completed)
    {
        $this->upsert(array(
            'setup_completed' => $completed ? 1 : 0,
        ));
    }

    public function hasAcceptedLegal()
    {
        $row = $this->get();

        return $row['eula_version'] === self::EULA_VERSION
            && !empty($row['eula_accepted_at'])
            && $row['privacy_version'] === self::PRIVACY_VERSION
            && !empty($row['privacy_accepted_at']);
    }

    public function saveLegalAcceptance($ipAddress = null)
    {
        $now = date('Y-m-d H:i:s');
        $this->upsert(array(
            'eula_version' => self::EULA_VERSION,
            'eula_accepted_at' => $now,
            'privacy_version' => self::PRIVACY_VERSION,
            'privacy_accepted_at' => $now,
            'legal_accepted_ip' => $ipAddress ? substr((string) $ipAddress, 0, 45) : null,
        ));
    }

    public function saveCloudflareSettings($syncMode, $accountId = '')
    {
        $accountId = trim((string) $accountId);
        $this->upsert(array(
            'cloudflare_sync_mode' => $syncMode === 'all' ? 'all' : 'ses',
            'cloudflare_account_id' => $accountId !== '' ? $accountId : null,
        ));
    }

    public function saveSmarthostSnapshot(array $config)
    {
        $this->upsert(array(
            'smarthost_snapshot' => json_encode($config),
        ));
    }

    public function getSmarthostSnapshot()
    {
        $row = $this->get();

        if (empty($row['smarthost_snapshot'])) {
            return null;
        }

        $decoded = json_decode($row['smarthost_snapshot'], true);

        return is_array($decoded) ? $decoded : null;
    }

    public function clearSmarthostSnapshot()
    {
        $this->upsert(array(
            'smarthost_snapshot' => null,
        ));
    }

    public function hasAwsCredentials()
    {
        $row = $this->get();

        return !empty($row['aws_key_encrypted']) && !empty($row['aws_secret_encrypted']);
    }

    public function hasSmtpCredentials()
    {
        $row = $this->get();

        return !empty($row['smtp_username_encrypted']) && !empty($row['smtp_password_encrypted']);
    }

    public function hasAwsAccessKey()
    {
        $row = $this->get();

        return !empty($row['aws_key_encrypted']);
    }

    public function hasAwsSecretKey()
    {
        $row = $this->get();

        return !empty($row['aws_secret_encrypted']);
    }

    public function hasSmtpUsername()
    {
        $row = $this->get();

        return !empty($row['smtp_username_encrypted']);
    }

    public function hasSmtpPassword()
    {
        $row = $this->get();

        return !empty($row['smtp_password_encrypted']);
    }

    private function upsert(array $data)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $data['updated_at'] = date('Y-m-d H:i:s');

        $exists = $db->fetchOne('SELECT id FROM ses_settings WHERE id = 1');
        if ($exists) {
            $db->update('ses_settings', $data, 'id = 1');
            return;
        }

        $data['id'] = 1;
        $data['created_at'] = $data['updated_at'];
        $db->insert('ses_settings', $data);
    }

    private function defaults()
    {
        return array(
            'id' => 1,
            'aws_key_encrypted' => null,
            'aws_secret_encrypted' => null,
            'smtp_username_encrypted' => null,
            'smtp_password_encrypted' => null,
            'cloudflare_token_encrypted' => null,
            'cloudflare_account_id' => null,
            'default_region' => 'eu-central-1',
            'default_spf_policy' => '~all',
            'default_dmarc_policy' => 'none',
            'mail_from_subdomain' => 'bounce',
            'cloudflare_sync_mode' => 'ses',
            'auto_ses_on_create' => 0,
            'setup_completed' => 0,
            'smarthost_enabled' => 0,
            'sns_topic_arn' => null,
            'smarthost_snapshot' => null,
            'cache_ttl_seconds' => 900,
            'retention_days' => 90,
            'mail_test_retention_days' => 90,
            'event_retention_days' => 180,
            'webhook_attempt_retention_days' => 30,
            'job_retention_days' => 30,
            'reputation_retention_days' => 90,
            'ip_reputation_retention_days' => 90,
            'log_retention_days' => 30,
            'eula_version' => null,
            'eula_accepted_at' => null,
            'privacy_version' => null,
            'privacy_accepted_at' => null,
            'legal_accepted_ip' => null,
        );
    }
}
