<?php

namespace Library\Service\Dto;

class SesIdentityResult
{
    /** @var string */
    public $domain;

    /** @var string */
    public $verificationStatus;

    /** @var string[] */
    public $dkimTokens;

    public function __construct($domain, $verificationStatus, array $dkimTokens = array())
    {
        $this->domain = $domain;
        $this->verificationStatus = $verificationStatus;
        $this->dkimTokens = $dkimTokens;
    }
}
