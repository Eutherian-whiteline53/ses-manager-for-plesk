<?php

namespace Library\Service\Dto;

class SmarthostResult
{
    public $success;
    public $message;

    public function __construct($success, $message)
    {
        $this->success = $success;
        $this->message = $message;
    }
}
