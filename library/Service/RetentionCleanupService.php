<?php

namespace Library\Service;

use Library\Repository\SettingsRepository;

class RetentionCleanupService
{
    private $settingsRepository;

    public function __construct(?SettingsRepository $settingsRepository = null)
    {
        $this->settingsRepository = $settingsRepository ?: new SettingsRepository();
    }

    public function cleanup()
    {
        $settings = $this->settingsRepository->get();
        $db = \pm_Bootstrap::getDbAdapter();
        $summary = array();

        $summary['mail_tests'] = $this->deleteOlderThan($db, 'ses_mail_tests', 'created_at', $settings['mail_test_retention_days']);
        $summary['events'] = $this->deleteOlderThan($db, 'ses_events', 'created_at', $settings['event_retention_days']);
        $summary['sns_confirmations'] = $this->deleteOlderThan($db, 'ses_sns_confirmations', 'created_at', $settings['webhook_attempt_retention_days']);
        $summary['sns_webhook_attempts'] = $this->deleteOlderThan($db, 'ses_sns_webhook_attempts', 'created_at', $settings['webhook_attempt_retention_days']);
        $summary['jobs'] = $this->deleteOlderThan($db, 'ses_jobs', 'created_at', $settings['job_retention_days'], "status NOT IN ('queued', 'running')");
        $summary['reputation_snapshots'] = $this->deleteOlderThan($db, 'ses_reputation_snapshots', 'checked_at', $settings['reputation_retention_days']);
        $summary['blacklist_checks'] = $this->deleteOlderThan($db, 'ses_blacklist_checks', 'checked_at', $settings['ip_reputation_retention_days']);
        $summary['ip_reputation_checks'] = $this->deleteOlderThan($db, 'ses_ip_reputation_checks', 'checked_at', $settings['ip_reputation_retention_days']);
        $summary['ip_reputation_events'] = $this->deleteOlderThan($db, 'ses_ip_reputation_events', 'created_at', $settings['ip_reputation_retention_days']);
        $summary['log_files'] = $this->cleanupLogFiles($settings['log_retention_days']);

        return $summary;
    }

    private function deleteOlderThan($db, $table, $column, $days, $extraWhere = null)
    {
        if (!$this->tableExists($db, $table)) {
            return 0;
        }

        $cutoff = date('Y-m-d H:i:s', time() - max(1, (int) $days) * 86400);
        $where = array($db->quoteInto($column . ' < ?', $cutoff));
        if ($extraWhere) {
            $where[] = $extraWhere;
        }

        return (int) $db->delete($table, $where);
    }

    private function tableExists($db, $table)
    {
        try {
            return (bool) $db->fetchOne('SHOW TABLES LIKE ?', array($table));
        } catch (\Exception $e) {
            return false;
        }
    }

    private function cleanupLogFiles($days)
    {
        $logDir = dirname(__DIR__, 2) . '/var/logs';
        if (!is_dir($logDir)) {
            return 0;
        }

        $deleted = 0;
        $cutoff = time() - max(1, (int) $days) * 86400;
        foreach (glob($logDir . '/*') ?: array() as $path) {
            if (!is_file($path)) {
                continue;
            }
            if (@filemtime($path) !== false && @filemtime($path) < $cutoff && @unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
