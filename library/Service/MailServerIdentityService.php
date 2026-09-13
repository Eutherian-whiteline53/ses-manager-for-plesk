<?php

namespace Library\Service;

use Library\Client\PostfixClient;

class MailServerIdentityService
{
    /** @var PostfixClient */
    private $postfixClient;

    public function __construct(?PostfixClient $postfixClient = null)
    {
        $this->postfixClient = $postfixClient ?: new PostfixClient();
    }

    public function check($expectedHostname = '')
    {
        $config = $this->postfixClient->getConfig(array('myhostname', 'smtp_helo_name', 'myorigin'));
        $hostname = isset($config['smtp_helo_name']) && $config['smtp_helo_name'] !== ''
            ? $config['smtp_helo_name']
            : (isset($config['myhostname']) ? $config['myhostname'] : '');
        if ($hostname === '$myhostname' && !empty($config['myhostname'])) {
            $hostname = $config['myhostname'];
        }
        $hostname = strtolower(trim($hostname));

        $report = array(
            'hostname' => $hostname,
            'myhostname' => isset($config['myhostname']) ? $config['myhostname'] : '',
            'smtpHeloName' => isset($config['smtp_helo_name']) ? $config['smtp_helo_name'] : '',
            'myorigin' => isset($config['myorigin']) ? $config['myorigin'] : '',
            'status' => 'warning',
            'message' => \pm_Locale::lmsg('heloUnavailable'),
        );

        if ($hostname === '') {
            return $report;
        }

        if (preg_match('/localhost|localdomain|^ubuntu-|^vps-|^server-/i', $hostname)) {
            $report['message'] = \pm_Locale::lmsg('heloGeneric');
            return $report;
        }

        if ($expectedHostname !== '' && strcasecmp($hostname, $expectedHostname) !== 0) {
            $report['message'] = \pm_Locale::lmsg('heloMismatch');
            return $report;
        }

        $report['status'] = 'pass';
        $report['message'] = \pm_Locale::lmsg('heloPass');

        return $report;
    }
}
