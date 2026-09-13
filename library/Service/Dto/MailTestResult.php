<?php

namespace Library\Service\Dto;

class MailTestResult
{
    public $success;
    public $message;
    public $providerMessageId;

    public function __construct($success, $message, $providerMessageId = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->providerMessageId = $providerMessageId;
    }
}
