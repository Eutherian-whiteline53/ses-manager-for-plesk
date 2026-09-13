<?php

namespace Library\Service\Dto;

class SmtpTestResult
{
    /** @var bool */
    public $success;

    /** @var string */
    public $message;

    public function __construct($success, $message)
    {
        $this->success = $success;
        $this->message = $message;
    }
}
