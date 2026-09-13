<?php

namespace Library\Service;

use Library\Client\SmtpClient;
use Library\Service\Dto\MailTestResult;
use Library\Service\Dto\SmtpTestResult;

class SmtpTestService
{
    public function testCredentials($host, $port, $username, $password)
    {
        try {
            $client = new SmtpClient();
            $client->testLogin($host, (int) $port, $username, $password);

            return new SmtpTestResult(true, 'SMTP credentials are valid.');
        } catch (\Exception $e) {
            $error = $this->sanitizeSmtpError($e->getMessage());
            \pm_Log::err('SES Manager: SMTP credential test failed for host ' . $host . ': ' . $error);

            return new SmtpTestResult(false, 'SMTP authentication failed: ' . $error);
        }
    }

    public function sendTestMail($host, $port, $username, $password, $from, $to, $subject, $body)
    {
        try {
            $client = new SmtpClient();
            $response = $client->sendMail($host, (int) $port, $username, $password, $from, $to, $subject, $body);

            return new MailTestResult(true, 'Test mail sent.', $response);
        } catch (\Exception $e) {
            $error = $this->sanitizeSmtpError($e->getMessage());
            \pm_Log::err('SES Manager: test mail send failed for host ' . $host . ': ' . $error);

            return new MailTestResult(false, 'Failed to send test mail: ' . $error);
        }
    }

    private function sanitizeSmtpError($message)
    {
        $message = trim(preg_replace('/\s+/', ' ', (string) $message));

        if (preg_match('/User [`\']arn:aws:iam::\d{12}:user\/([^`\']+)[`\'].*[`\']ses:SendRawEmail[`\'].*resource [`\']arn:aws:ses:([^:]+):\d{12}:identity\/([^`\']+)[`\']/i', $message, $matches)) {
            return sprintf(
                'SMTP IAM user `%s` is missing `ses:SendRawEmail` permission for SES identity `%s` in `%s`.',
                $matches[1],
                $matches[3],
                $matches[2]
            );
        }

        $message = preg_replace('/`?arn:aws:iam::\d{12}:user\/([A-Za-z0-9+=,.@_-]+)`?/', 'IAM user `$1`', $message);
        $message = preg_replace('/`?arn:aws:ses:([a-z0-9-]+):\d{12}:identity\/([A-Za-z0-9_.@-]+)`?/', 'SES identity `$2` in `$1`', $message);
        $message = preg_replace('/[A-Z0-9]{16,}/', '[redacted]', $message);

        if ($message === '') {
            return 'Unknown SMTP error.';
        }

        return $message;
    }
}
