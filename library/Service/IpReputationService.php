<?php

namespace Library\Service;

use Library\Repository\IpReputationCheckRepository;

class IpReputationService
{
    /** @var BlacklistService */
    private $blacklistService;

    /** @var IpReputationCheckRepository */
    private $repository;

    public function __construct(?BlacklistService $blacklistService = null, ?IpReputationCheckRepository $repository = null)
    {
        $this->blacklistService = $blacklistService ?: new BlacklistService();
        $this->repository = $repository ?: new IpReputationCheckRepository();
    }

    public function check($forceRefresh = false)
    {
        $ip = $this->blacklistService->getServerIp();
        if ($ip === null) {
            return array(
                'ip' => '',
                'score' => 0,
                'status' => 'critical',
                'message' => \pm_Locale::lmsg('blacklistIpUnavailable'),
                'blacklist' => array('ip' => '', 'checks' => array(), 'message' => \pm_Locale::lmsg('blacklistIpUnavailable')),
                'reverseDns' => array('ptr' => '', 'status' => 'critical', 'forwardConfirmed' => false, 'message' => \pm_Locale::lmsg('blacklistIpUnavailable')),
                'smtpBanner' => array('banner' => '', 'status' => 'warning', 'message' => \pm_Locale::lmsg('smtpBannerUnavailable')),
                'mailIdentity' => array('hostname' => '', 'status' => 'warning', 'message' => \pm_Locale::lmsg('heloUnavailable')),
                'asn' => array('asn' => '', 'provider' => '', 'country' => '', 'knownDatacenter' => false, 'status' => 'unknown', 'note' => \pm_Locale::lmsg('asnLookupUnavailable')),
            );
        }

        if (!$forceRefresh) {
            $latest = $this->repository->findLatest($ip);
            if ($latest && !empty($latest['checkedAt']) && strtotime($latest['checkedAt']) >= time() - 21600) {
                return $latest;
            }
        }

        $blacklist = $this->blacklistService->check($ip, $forceRefresh);
        $reverseDns = (new ReverseDnsService())->check($ip);
        $smtpBanner = (new SmtpBannerService())->check($ip, $reverseDns['ptr']);
        $mailIdentity = (new MailServerIdentityService())->check($reverseDns['ptr']);
        $asn = (new AsnLookupService())->lookup($ip);

        $report = array(
            'ip' => $ip,
            'blacklist' => $blacklist,
            'reverseDns' => $reverseDns,
            'smtpBanner' => $smtpBanner,
            'mailIdentity' => $mailIdentity,
            'asn' => $asn,
            'score' => 0,
            'status' => 'unknown',
            'message' => '',
            'checkedAt' => date('Y-m-d H:i:s'),
        );
        $report['score'] = $this->calculateScore($report);
        $report['status'] = $this->statusFromScore($report['score'], $blacklist['checks']);
        $report['message'] = $this->messageFromStatus($report['status']);

        $this->repository->save($report);

        return $report;
    }

    private function calculateScore(array $report)
    {
        $score = 0;

        if ($report['ip'] !== '') {
            $score += 10;
        }
        if ($report['reverseDns']['ptr'] !== '') {
            $score += 15;
        }
        if (!empty($report['reverseDns']['forwardConfirmed'])) {
            $score += 10;
        }
        if ($report['smtpBanner']['status'] === 'pass') {
            $score += 10;
        }
        if ($report['mailIdentity']['status'] === 'pass') {
            $score += 10;
        }

        $hasCriticalListing = false;
        $hasWarningListing = false;
        $hasInformationalListing = false;
        foreach ($report['blacklist']['checks'] as $check) {
            if (empty($check['listed'])) {
                continue;
            }
            if ($check['severity'] === 'critical') {
                $hasCriticalListing = true;
            } elseif ($check['severity'] === 'warning') {
                $hasWarningListing = true;
            } else {
                $hasInformationalListing = true;
            }
        }

        if (!$hasCriticalListing) {
            $score += 25;
        }
        if (!$hasWarningListing) {
            $score += 10;
        }
        if (!$hasInformationalListing) {
            $score += 10;
        }

        return min(100, $score);
    }

    private function statusFromScore($score, array $checks)
    {
        foreach ($checks as $check) {
            if (!empty($check['listed']) && $check['severity'] === 'critical') {
                return 'critical';
            }
        }

        if ($score >= 85) {
            return 'excellent';
        }
        if ($score >= 65) {
            return 'warning';
        }

        return 'critical';
    }

    private function messageFromStatus($status)
    {
        if ($status === 'excellent') {
            return \pm_Locale::lmsg('ipReputationExcellent');
        }
        if ($status === 'warning') {
            return \pm_Locale::lmsg('ipReputationWarning');
        }

        return \pm_Locale::lmsg('ipReputationCritical');
    }
}
