<?php

namespace Library\Service\Dto;

class DmarcPolicy
{
    /** @var bool */
    public $exists;

    /** @var string|null none|quarantine|reject */
    public $policy;

    /** @var string|null */
    public $raw;

    public function __construct($exists, ?string $policy = null, ?string $raw = null)
    {
        $this->exists = $exists;
        $this->policy = $policy;
        $this->raw = $raw;
    }
}
