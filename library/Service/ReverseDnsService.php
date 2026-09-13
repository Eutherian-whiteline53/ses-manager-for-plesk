<?php

namespace Library\Service;

class ReverseDnsService
{
    public function check($ipAddress)
    {
        $report = array(
            'ip' => $ipAddress,
            'ptr' => '',
            'status' => 'critical',
            'forwardConfirmed' => false,
            'message' => \pm_Locale::lmsg('reverseDnsMissing'),
        );

        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $report['message'] = \pm_Locale::lmsg('blacklistIpUnavailable');
            return $report;
        }

        $ptr = @gethostbyaddr($ipAddress);
        if ($ptr === false || $ptr === $ipAddress) {
            return $report;
        }

        $ptr = strtolower(rtrim($ptr, '.'));
        $report['ptr'] = $ptr;
        $forwardIps = @gethostbynamel($ptr);
        $report['forwardConfirmed'] = is_array($forwardIps) && in_array($ipAddress, $forwardIps, true);

        if ($this->isGenericHostname($ptr)) {
            $report['status'] = 'warning';
            $report['message'] = \pm_Locale::lmsg('reverseDnsGeneric');
            return $report;
        }

        if (!$report['forwardConfirmed']) {
            $report['status'] = 'warning';
            $report['message'] = \pm_Locale::lmsg('reverseDnsForwardMismatch');
            return $report;
        }

        $report['status'] = 'pass';
        $report['message'] = \pm_Locale::lmsg('reverseDnsPass');

        return $report;
    }

    private function isGenericHostname($hostname)
    {
        return (bool) preg_match('/(^|\.)localhost(\.|$)|localdomain|^ubuntu-|^vps-|^server-|^ip-|^static-|^host-/i', $hostname);
    }
}

