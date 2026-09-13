<?php

namespace Library\Service;

class SmtpBannerService
{
    public function check($ipAddress, $expectedHostname = '')
    {
        $report = array(
            'banner' => '',
            'status' => 'warning',
            'message' => \pm_Locale::lmsg('smtpBannerUnavailable'),
        );

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($ipAddress, 25, $errno, $errstr, 5);
        if (!$socket) {
            $report['message'] = \pm_Locale::lmsg('smtpBannerPortClosed');
            return $report;
        }

        stream_set_timeout($socket, 5);
        $banner = fgets($socket, 515);
        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        $banner = trim((string) $banner);
        $report['banner'] = $banner;

        if ($banner === '') {
            return $report;
        }

        if (preg_match('/localhost|localdomain|ubuntu-|vps-|server-/i', $banner)) {
            $report['status'] = 'warning';
            $report['message'] = \pm_Locale::lmsg('smtpBannerGeneric');
            return $report;
        }

        if ($expectedHostname !== '' && stripos($banner, $expectedHostname) === false) {
            $report['status'] = 'warning';
            $report['message'] = \pm_Locale::lmsg('smtpBannerMismatch');
            return $report;
        }

        $report['status'] = 'pass';
        $report['message'] = \pm_Locale::lmsg('smtpBannerPass');

        return $report;
    }
}

