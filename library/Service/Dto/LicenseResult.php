<?php

namespace Library\Service\Dto;

class LicenseResult
{
    public $success;
    public $message;
    public $data;

    public function __construct($success, $message, array $data = array())
    {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
    }
}
