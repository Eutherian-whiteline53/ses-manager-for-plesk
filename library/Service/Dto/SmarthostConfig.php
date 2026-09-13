<?php

namespace Library\Service\Dto;

class SmarthostConfig
{
    public $host;
    public $port;
    public $username;
    public $encryption;

    public function __construct($host, $port, $username, $encryption = 'starttls')
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->encryption = $encryption;
    }
}
