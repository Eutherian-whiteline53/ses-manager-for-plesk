<?php

namespace Library\Service\Dto;

class VerificationResult
{
    const ERROR_CREDENTIALS_MISSING = 'credentials_missing';
    const ERROR_LIMIT_REACHED = 'limit_reached';
    const ERROR_IDENTITY_FAILED = 'identity_failed';

    /** @var bool */
    public $success;

    /** @var string|null */
    public $errorCode;

    /** @var int|null */
    public $domainId;

    public function __construct($success, $errorCode = null, $domainId = null)
    {
        $this->success = $success;
        $this->errorCode = $errorCode;
        $this->domainId = $domainId;
    }
}
