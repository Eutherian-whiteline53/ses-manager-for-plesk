<?php

namespace Library\Service;

use Library\Repository\BlacklistCheckRepository;

class BlacklistService
{
    private $providers = array(
        array('provider' => 'Spamhaus ZEN', 'zone' => 'zen.spamhaus.org', 'severity' => 'critical'),
        array('provider' => 'Barracuda', 'zone' => 'b.barracudacentral.org', 'severity' => 'critical'),
        array('provider' => 'Spamcop', 'zone' => 'bl.spamcop.net', 'severity' => 'critical'),
        array('provider' => 'SORBS', 'zone' => 'dnsbl.sorbs.net', 'severity' => 'warning'),
        array('provider' => 'UCEPROTECT L1', 'zone' => 'dnsbl-1.uceprotect.net', 'severity' => 'warning'),
        array('provider' => 'UCEPROTECT L2', 'zone' => 'dnsbl-2.uceprotect.net', 'severity' => 'informational'),
        array('provider' => 'UCEPROTECT L3', 'zone' => 'dnsbl-3.uceprotect.net', 'severity' => 'informational'),
        array('provider' => 'Invaluement', 'zone' => 'dnsbl.invaluement.com', 'severity' => 'warning'),
        array('provider' => 'PSBL', 'zone' => 'psbl.surriel.com', 'severity' => 'warning'),
    );

    /** @var BlacklistCheckRepository */
    private $repository;

    public function __construct(?BlacklistCheckRepository $repository = null)
    {
        $this->repository = $repository ?: new BlacklistCheckRepository();
    }

    public function getServerIp()
    {
        $candidates = array();
        $hostnameIp = gethostbyname(gethostname());
        if ($hostnameIp !== gethostname()) {
            $candidates[] = $hostnameIp;
        }

        if (function_exists('shell_exec')) {
            $ipOutput = trim((string) @shell_exec('hostname -I 2>/dev/null'));
            foreach (preg_split('/\s+/', $ipOutput) as $ip) {
                $candidates[] = $ip;
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        return null;
    }

    public function check($ipAddress = null, $forceRefresh = false)
    {
        $ipAddress = $ipAddress ?: $this->getServerIp();
        if ($ipAddress === null || !filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return array('ip' => $ipAddress, 'checks' => array(), 'message' => \pm_Locale::lmsg('blacklistIpUnavailable'));
        }

        if (!$forceRefresh) {
            $latest = $this->repository->findLatestByIp($ipAddress);
            if (!empty($latest) && strtotime($latest[0]['checked_at']) >= time() - 21600) {
                return array('ip' => $ipAddress, 'checks' => $this->normalizeRows($latest), 'message' => '');
            }
        }

        $checks = array();
        $reverseIp = implode('.', array_reverse(explode('.', $ipAddress)));
        foreach ($this->providers as $provider) {
            $query = $reverseIp . '.' . $provider['zone'];
            $records = @dns_get_record($query, DNS_A);
            $response = !empty($records) ? $records[0]['ip'] : '';
            $listed = $this->isListedResponse($provider['provider'], $response);
            if ($provider['provider'] === 'Spamhaus ZEN' && $response === '127.255.255.254') {
                $response = 'Query blocked by resolver policy (127.255.255.254)';
            }
            $checks[] = array(
                'provider' => $provider['provider'],
                'zone' => $provider['zone'],
                'severity' => $provider['severity'],
                'listed' => $listed,
                'response' => $response,
            );
        }

        $this->repository->saveMany($ipAddress, $checks);

        return array('ip' => $ipAddress, 'checks' => $checks, 'message' => '');
    }

    private function normalizeRows(array $rows)
    {
        $checks = array();
        foreach ($rows as $row) {
            $checks[] = array(
                'provider' => $row['provider'],
                'zone' => isset($row['zone']) ? $row['zone'] : '',
                'severity' => isset($row['severity']) ? $row['severity'] : 'warning',
                'listed' => !empty($row['listed']),
                'response' => $row['response'],
            );
        }

        return $checks;
    }

    private function isListedResponse($provider, $response)
    {
        if ($response === '') {
            return false;
        }

        if ($provider === 'Spamhaus ZEN' && in_array($response, array('127.255.255.254', '127.255.255.255'), true)) {
            return false;
        }

        return true;
    }
}
