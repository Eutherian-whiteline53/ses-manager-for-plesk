<?php

namespace Library\Service\Dto;

class ConnectionResult
{
    /** @var bool */
    public $success;

    /** @var string */
    public $message;

    /** @var array */
    public $details;

    public function __construct($success, $message, array $details = array())
    {
        $this->success = $success;
        $this->message = $message;
        $this->details = $details;
    }
}
