<?php

namespace Library\Client;

class SmtpClient
{
    public function testLogin($host, $port, $username, $password, $timeout = 10)
    {
        $socket = $this->connectAndAuthenticate($host, $port, $username, $password, $timeout);
        $this->command($socket, 'QUIT', 221);
        fclose($socket);

        return true;
    }

    public function sendMail($host, $port, $username, $password, $from, $to, $subject, $body, $timeout = 10)
    {
        $from = $this->sanitizeAddress($from);
        $to = $this->sanitizeAddress($to);

        $socket = $this->connectAndAuthenticate($host, $port, $username, $password, $timeout);

        try {
            $this->command($socket, 'MAIL FROM:<' . $from . '>', 250);
            $this->command($socket, 'RCPT TO:<' . $to . '>', 250);
            $this->command($socket, 'DATA', 354);

            $message = $this->buildMessage($from, $to, $subject, $body);
            fwrite($socket, $message . "\r\n.\r\n");
            $response = $this->readResponse($socket);

            if ((int) substr($response, 0, 3) !== 250) {
                throw new \RuntimeException('Unexpected SMTP response: ' . trim($response));
            }

            $this->command($socket, 'QUIT', 221);

            return trim($response);
        } finally {
            fclose($socket);
        }
    }

    private function connectAndAuthenticate($host, $port, $username, $password, $timeout)
    {
        $errno = 0;
        $errstr = '';
        $transport = ((int) $port === 465) ? 'ssl://' . $host : $host;

        $socket = @fsockopen($transport, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            throw new \RuntimeException('SMTP connection failed: ' . $errstr);
        }

        stream_set_timeout($socket, $timeout);

        $this->expect($socket, 220);
        $this->command($socket, 'EHLO ' . $this->hostname(), 250);

        if ((int) $port !== 465) {
            $this->command($socket, 'STARTTLS', 220);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('SMTP STARTTLS negotiation failed');
            }

            $this->command($socket, 'EHLO ' . $this->hostname(), 250);
        }

        $this->command($socket, 'AUTH LOGIN', 334);
        $this->command($socket, base64_encode($username), 334);
        $this->command($socket, base64_encode($password), 235);

        return $socket;
    }

    private function buildMessage($from, $to, $subject, $body)
    {
        $subject = $this->sanitizeHeaderValue($subject);
        $from = $this->sanitizeHeaderValue($from);
        $to = $this->sanitizeHeaderValue($to);

        $headers = array(
            'Date: ' . date('r'),
            'From: ' . $from,
            'To: ' . $to,
            'Subject: ' . $subject,
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->hostname() . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        );

        $lines = array_merge($headers, array(''), explode("\n", $body));

        return implode("\r\n", $this->escapeDotLines($lines));
    }

    private function sanitizeAddress($value)
    {
        $value = trim($this->sanitizeHeaderValue($value));
        if ($value === '' || strpos($value, '<') !== false || strpos($value, '>') !== false) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        return $value;
    }

    private function sanitizeHeaderValue($value)
    {
        return str_replace(array("\r", "\n"), ' ', (string) $value);
    }

    private function escapeDotLines(array $lines)
    {
        return array_map(function ($line) {
            return (substr($line, 0, 1) === '.') ? '.' . $line : $line;
        }, $lines);
    }

    private function hostname()
    {
        $hostname = gethostname();

        return $hostname ?: 'localhost';
    }

    private function command($socket, $command, $expectedCode)
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expectedCode);
    }

    private function expect($socket, $expectedCode)
    {
        $response = $this->readResponse($socket);

        if ((int) substr($response, 0, 3) !== $expectedCode) {
            throw new \RuntimeException('Unexpected SMTP response: ' . trim($response));
        }
    }

    private function readResponse($socket)
    {
        $response = '';

        do {
            $line = fgets($socket, 515);

            if ($line === false) {
                throw new \RuntimeException('SMTP connection closed unexpectedly');
            }

            $response = $line;
        } while (isset($line[3]) && $line[3] === '-');

        return $response;
    }
}
