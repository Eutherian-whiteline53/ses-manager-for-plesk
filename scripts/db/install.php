<?php

/** @var pm_Db_Adapter $db */
$db = pm_Bootstrap::getDbAdapter();

$db->query("
    CREATE TABLE IF NOT EXISTS ses_settings (
        id INT UNSIGNED NOT NULL,
        aws_key_encrypted TEXT NULL,
        aws_secret_encrypted TEXT NULL,
        smtp_username_encrypted TEXT NULL,
        smtp_password_encrypted TEXT NULL,
        cloudflare_token_encrypted TEXT NULL,
        cloudflare_account_id VARCHAR(128) NULL,
        default_region VARCHAR(32) NOT NULL DEFAULT 'eu-central-1',
        default_spf_policy VARCHAR(16) NOT NULL DEFAULT '~all',
        default_dmarc_policy VARCHAR(16) NOT NULL DEFAULT 'none',
        mail_from_subdomain VARCHAR(63) NOT NULL DEFAULT 'bounce',
        cloudflare_sync_mode VARCHAR(16) NOT NULL DEFAULT 'ses',
        auto_ses_on_create TINYINT UNSIGNED NOT NULL DEFAULT 0,
        setup_completed TINYINT UNSIGNED NOT NULL DEFAULT 0,
        smarthost_enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
        sns_topic_arn VARCHAR(512) NULL,
        smarthost_snapshot TEXT NULL,
        cache_ttl_seconds INT UNSIGNED NOT NULL DEFAULT 900,
        retention_days INT UNSIGNED NOT NULL DEFAULT 90,
        mail_test_retention_days INT UNSIGNED NOT NULL DEFAULT 90,
        event_retention_days INT UNSIGNED NOT NULL DEFAULT 180,
        webhook_attempt_retention_days INT UNSIGNED NOT NULL DEFAULT 30,
        job_retention_days INT UNSIGNED NOT NULL DEFAULT 30,
        reputation_retention_days INT UNSIGNED NOT NULL DEFAULT 90,
        ip_reputation_retention_days INT UNSIGNED NOT NULL DEFAULT 90,
        log_retention_days INT UNSIGNED NOT NULL DEFAULT 30,
        eula_version VARCHAR(32) NULL,
        eula_accepted_at DATETIME NULL,
        privacy_version VARCHAR(32) NULL,
        privacy_accepted_at DATETIME NULL,
        legal_accepted_ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$columns = $db->fetchCol('SHOW COLUMNS FROM ses_settings');
if (!in_array('mail_from_subdomain', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD mail_from_subdomain VARCHAR(63) NOT NULL DEFAULT 'bounce' AFTER default_dmarc_policy");
}
if (!in_array('cloudflare_sync_mode', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD cloudflare_sync_mode VARCHAR(16) NOT NULL DEFAULT 'ses' AFTER mail_from_subdomain");
}
if (!in_array('auto_ses_on_create', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD auto_ses_on_create TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER cloudflare_sync_mode");
}
if (!in_array('setup_completed', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD setup_completed TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER auto_ses_on_create");
}
if (!in_array('cloudflare_account_id', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD cloudflare_account_id VARCHAR(128) NULL AFTER cloudflare_token_encrypted");
}
if (!in_array('sns_topic_arn', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD sns_topic_arn VARCHAR(512) NULL AFTER smarthost_enabled");
}
if (!in_array('mail_test_retention_days', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD mail_test_retention_days INT UNSIGNED NOT NULL DEFAULT 90 AFTER retention_days");
}
if (!in_array('event_retention_days', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD event_retention_days INT UNSIGNED NOT NULL DEFAULT 180 AFTER mail_test_retention_days");
}
if (!in_array('webhook_attempt_retention_days', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD webhook_attempt_retention_days INT UNSIGNED NOT NULL DEFAULT 30 AFTER event_retention_days");
}
if (!in_array('job_retention_days', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD job_retention_days INT UNSIGNED NOT NULL DEFAULT 30 AFTER webhook_attempt_retention_days");
}
if (!in_array('reputation_retention_days', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD reputation_retention_days INT UNSIGNED NOT NULL DEFAULT 90 AFTER job_retention_days");
}
if (!in_array('ip_reputation_retention_days', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD ip_reputation_retention_days INT UNSIGNED NOT NULL DEFAULT 90 AFTER reputation_retention_days");
}
if (!in_array('log_retention_days', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD log_retention_days INT UNSIGNED NOT NULL DEFAULT 30 AFTER ip_reputation_retention_days");
}
if (!in_array('eula_version', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD eula_version VARCHAR(32) NULL AFTER log_retention_days");
}
if (!in_array('eula_accepted_at', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD eula_accepted_at DATETIME NULL AFTER eula_version");
}
if (!in_array('privacy_version', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD privacy_version VARCHAR(32) NULL AFTER eula_accepted_at");
}
if (!in_array('privacy_accepted_at', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD privacy_accepted_at DATETIME NULL AFTER privacy_version");
}
if (!in_array('legal_accepted_ip', $columns, true)) {
    $db->query("ALTER TABLE ses_settings ADD legal_accepted_ip VARCHAR(45) NULL AFTER privacy_accepted_at");
}

$db->query("
    INSERT IGNORE INTO ses_settings
        (id, default_region, default_spf_policy, default_dmarc_policy, smarthost_enabled, cache_ttl_seconds, retention_days, mail_test_retention_days, event_retention_days, webhook_attempt_retention_days, job_retention_days, reputation_retention_days, ip_reputation_retention_days, log_retention_days, created_at, updated_at)
    VALUES
        (1, 'eu-central-1', '~all', 'none', 0, 900, 90, 90, 180, 30, 30, 90, 90, 30, NOW(), NOW())
");

$db->query("
    CREATE TABLE IF NOT EXISTS ses_domains (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        domain_name VARCHAR(255) NOT NULL,
        plesk_domain_id INT UNSIGNED NOT NULL,
        ses_identity_arn VARCHAR(512) NULL,
        region VARCHAR(32) NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'active',
        verification_status VARCHAR(32) NOT NULL DEFAULT 'not_started',
        spf_status VARCHAR(32) NOT NULL DEFAULT 'missing',
        dkim_status VARCHAR(32) NOT NULL DEFAULT 'missing',
        dmarc_status VARCHAR(32) NOT NULL DEFAULT 'missing',
        mx_status VARCHAR(32) NOT NULL DEFAULT 'missing',
        spf_analysis_json MEDIUMTEXT NULL,
        dkim_analysis_json MEDIUMTEXT NULL,
        dmarc_analysis_json MEDIUMTEXT NULL,
        mail_from_analysis_json MEDIUMTEXT NULL,
        score INT UNSIGNED NOT NULL DEFAULT 0,
        last_checked_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_domain_name (domain_name),
        KEY idx_plesk_domain_id (plesk_domain_id),
        KEY idx_status (status),
        KEY idx_verification_status (verification_status),
        KEY idx_last_checked_at (last_checked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$domainColumns = $db->fetchCol('SHOW COLUMNS FROM ses_domains');
$domainIndexes = $db->fetchCol("SHOW INDEX FROM ses_domains WHERE Key_name = 'idx_status'");
if (empty($domainIndexes)) {
    $db->query("ALTER TABLE ses_domains ADD INDEX idx_status (status)");
}
if (!in_array('spf_analysis_json', $domainColumns, true)) {
    $db->query("ALTER TABLE ses_domains ADD spf_analysis_json MEDIUMTEXT NULL AFTER mx_status");
}
if (!in_array('dkim_analysis_json', $domainColumns, true)) {
    $db->query("ALTER TABLE ses_domains ADD dkim_analysis_json MEDIUMTEXT NULL AFTER spf_analysis_json");
}
if (!in_array('dmarc_analysis_json', $domainColumns, true)) {
    $db->query("ALTER TABLE ses_domains ADD dmarc_analysis_json MEDIUMTEXT NULL AFTER dkim_analysis_json");
}
if (!in_array('mail_from_analysis_json', $domainColumns, true)) {
    $db->query("ALTER TABLE ses_domains ADD mail_from_analysis_json MEDIUMTEXT NULL AFTER dmarc_analysis_json");
}

$db->query("
    CREATE TABLE IF NOT EXISTS ses_dns_records (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        domain_id INT UNSIGNED NOT NULL,
        type VARCHAR(16) NOT NULL,
        name VARCHAR(255) NOT NULL,
        value TEXT NOT NULL,
        provider VARCHAR(32) NOT NULL DEFAULT 'plesk',
        purpose VARCHAR(32) NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'planned',
        last_error TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_domain_id (domain_id),
        KEY idx_provider (provider),
        KEY idx_purpose (purpose),
        KEY idx_status (status),
        CONSTRAINT fk_ses_dns_records_domain FOREIGN KEY (domain_id) REFERENCES ses_domains (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->query("
    CREATE TABLE IF NOT EXISTS ses_mail_tests (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        domain_id INT UNSIGNED NULL,
        from_email VARCHAR(255) NOT NULL,
        to_email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        status VARCHAR(32) NOT NULL,
        provider_message_id VARCHAR(255) NULL,
        response TEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_domain_id (domain_id),
        KEY idx_status (status),
        KEY idx_created_at (created_at),
        CONSTRAINT fk_ses_mail_tests_domain FOREIGN KEY (domain_id) REFERENCES ses_domains (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->query("
    CREATE TABLE IF NOT EXISTS ses_events (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        domain_id INT UNSIGNED NULL,
        event_type VARCHAR(32) NOT NULL,
        bounce_type VARCHAR(32) NULL,
        recipient VARCHAR(255) NOT NULL,
        message_id VARCHAR(255) NOT NULL,
        raw_payload MEDIUMTEXT NOT NULL,
        received_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_domain_id (domain_id),
        KEY idx_event_type (event_type),
        KEY idx_recipient (recipient),
        KEY idx_message_id (message_id),
        KEY idx_created_at (created_at),
        CONSTRAINT fk_ses_events_domain FOREIGN KEY (domain_id) REFERENCES ses_domains (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->query("
    CREATE TABLE IF NOT EXISTS ses_sns_confirmations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        message_id VARCHAR(255) NOT NULL,
        topic_arn VARCHAR(512) NOT NULL,
        subscribe_url TEXT NOT NULL,
        token TEXT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'pending',
        last_error TEXT NULL,
        received_at DATETIME NOT NULL,
        confirmed_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_message_id (message_id),
        KEY idx_topic_arn (topic_arn(191)),
        KEY idx_status (status),
        KEY idx_received_at (received_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->query("
    CREATE TABLE IF NOT EXISTS ses_sns_webhook_attempts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        message_id VARCHAR(255) NOT NULL DEFAULT '',
        message_type VARCHAR(64) NOT NULL DEFAULT '',
        topic_arn VARCHAR(512) NOT NULL DEFAULT '',
        status VARCHAR(32) NOT NULL DEFAULT 'failed',
        reason TEXT NULL,
        http_status INT UNSIGNED NOT NULL DEFAULT 0,
        raw_payload MEDIUMTEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_message_id (message_id),
        KEY idx_message_type (message_type),
        KEY idx_status (status),
        KEY idx_created_at (created_at),
        KEY idx_topic_arn (topic_arn(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->query("
    CREATE TABLE IF NOT EXISTS ses_jobs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        job_type VARCHAR(64) NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'queued',
        payload MEDIUMTEXT NULL,
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        last_error TEXT NULL,
        scheduled_at DATETIME NOT NULL,
        started_at DATETIME NULL,
        finished_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_job_type (job_type),
        KEY idx_status (status),
        KEY idx_scheduled_at (scheduled_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->query("
    CREATE TABLE IF NOT EXISTS ses_cloudflare_zones (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        domain_name VARCHAR(255) NOT NULL,
        zone_id VARCHAR(128) NOT NULL,
        zone_name VARCHAR(255) NOT NULL,
        detected_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_domain_name (domain_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->query("
    CREATE TABLE IF NOT EXISTS ses_reputation_snapshots (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        region VARCHAR(32) NOT NULL,
        production_access_enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
        sending_enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
        enforcement_status VARCHAR(64) NULL,
        max_24_hour_send INT UNSIGNED NOT NULL DEFAULT 0,
        sent_last_24_hours INT UNSIGNED NOT NULL DEFAULT 0,
        max_send_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
        bounce_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
        complaint_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
        health VARCHAR(32) NOT NULL DEFAULT 'unknown',
        source VARCHAR(32) NOT NULL DEFAULT 'ses_events',
        raw_payload MEDIUMTEXT NULL,
        checked_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_region_checked_at (region, checked_at),
        KEY idx_health (health)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->query("
    CREATE TABLE IF NOT EXISTS ses_blacklist_checks (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ip_address VARCHAR(64) NOT NULL,
        provider VARCHAR(64) NOT NULL,
        zone VARCHAR(255) NOT NULL DEFAULT '',
        severity VARCHAR(32) NOT NULL DEFAULT 'warning',
        listed TINYINT UNSIGNED NOT NULL DEFAULT 0,
        response TEXT NULL,
        checked_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_ip_checked_at (ip_address, checked_at),
        KEY idx_provider (provider),
        KEY idx_listed (listed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$blacklistColumns = $db->fetchCol('SHOW COLUMNS FROM ses_blacklist_checks');
if (!in_array('zone', $blacklistColumns, true)) {
    $db->query("ALTER TABLE ses_blacklist_checks ADD zone VARCHAR(255) NOT NULL DEFAULT '' AFTER provider");
}
if (!in_array('severity', $blacklistColumns, true)) {
    $db->query("ALTER TABLE ses_blacklist_checks ADD severity VARCHAR(32) NOT NULL DEFAULT 'warning' AFTER zone");
}

$db->query("
    CREATE TABLE IF NOT EXISTS ses_ip_reputation_checks (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ip_address VARCHAR(64) NOT NULL,
        ptr_hostname VARCHAR(255) NULL,
        ptr_status VARCHAR(32) NOT NULL DEFAULT 'unknown',
        forward_confirmed TINYINT UNSIGNED NOT NULL DEFAULT 0,
        smtp_banner TEXT NULL,
        smtp_banner_status VARCHAR(32) NOT NULL DEFAULT 'unknown',
        helo_hostname VARCHAR(255) NULL,
        helo_status VARCHAR(32) NOT NULL DEFAULT 'unknown',
        asn VARCHAR(32) NULL,
        asn_name VARCHAR(255) NULL,
        asn_provider VARCHAR(128) NULL,
        asn_country VARCHAR(8) NULL,
        asn_known_datacenter TINYINT UNSIGNED NOT NULL DEFAULT 0,
        score INT UNSIGNED NOT NULL DEFAULT 0,
        raw_payload MEDIUMTEXT NULL,
        checked_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_ip_checked_at (ip_address, checked_at),
        KEY idx_score (score),
        KEY idx_ptr_status (ptr_status),
        KEY idx_smtp_banner_status (smtp_banner_status),
        KEY idx_helo_status (helo_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$ipReputationColumns = $db->fetchCol('SHOW COLUMNS FROM ses_ip_reputation_checks');
if (!in_array('asn', $ipReputationColumns, true)) {
    $db->query("ALTER TABLE ses_ip_reputation_checks ADD asn VARCHAR(32) NULL AFTER helo_status");
}
if (!in_array('asn_name', $ipReputationColumns, true)) {
    $db->query("ALTER TABLE ses_ip_reputation_checks ADD asn_name VARCHAR(255) NULL AFTER asn");
}
if (!in_array('asn_provider', $ipReputationColumns, true)) {
    $db->query("ALTER TABLE ses_ip_reputation_checks ADD asn_provider VARCHAR(128) NULL AFTER asn_name");
}
if (!in_array('asn_country', $ipReputationColumns, true)) {
    $db->query("ALTER TABLE ses_ip_reputation_checks ADD asn_country VARCHAR(8) NULL AFTER asn_provider");
}
if (!in_array('asn_known_datacenter', $ipReputationColumns, true)) {
    $db->query("ALTER TABLE ses_ip_reputation_checks ADD asn_known_datacenter TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER asn_country");
}

$db->query("
    CREATE TABLE IF NOT EXISTS ses_reputation_alerts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        alert_key CHAR(40) NOT NULL,
        ip_address VARCHAR(64) NOT NULL,
        provider VARCHAR(64) NOT NULL,
        severity VARCHAR(32) NOT NULL DEFAULT 'critical',
        status VARCHAR(32) NOT NULL DEFAULT 'active',
        message TEXT NOT NULL,
        first_seen_at DATETIME NOT NULL,
        last_seen_at DATETIME NOT NULL,
        last_alerted_at DATETIME NULL,
        cooldown_until DATETIME NULL,
        recovered_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_alert_key (alert_key),
        KEY idx_status (status),
        KEY idx_ip_provider (ip_address, provider),
        KEY idx_cooldown_until (cooldown_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->query("
    CREATE TABLE IF NOT EXISTS ses_ip_reputation_events (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        alert_id INT UNSIGNED NULL,
        event_type VARCHAR(32) NOT NULL,
        severity VARCHAR(32) NOT NULL DEFAULT 'critical',
        message TEXT NOT NULL,
        payload MEDIUMTEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_alert_id (alert_id),
        KEY idx_event_type (event_type),
        KEY idx_created_at (created_at),
        CONSTRAINT fk_ses_ip_reputation_events_alert FOREIGN KEY (alert_id) REFERENCES ses_reputation_alerts (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
