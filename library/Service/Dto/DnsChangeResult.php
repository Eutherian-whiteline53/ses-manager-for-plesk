<?php

namespace Library\Service\Dto;

class DnsChangeResult
{
    const STATUS_PLANNED = 'planned';
    const STATUS_SPF_MERGE = 'spf_merge';
    const STATUS_TXT_UPDATE = 'txt_update';
    const STATUS_SKIPPED = 'skipped';
    const STATUS_CONFLICT = 'conflict';
    const STATUS_APPLIED = 'applied';
    const STATUS_FAILED = 'failed';

    /** @var DnsRecord */
    public $record;

    /** @var string */
    public $status;

    /** @var string */
    public $message;

    /**
     * SPF merge için, üzerine yazılacak eski TXT değeri.
     *
     * @var string|null
     */
    public $previousValue;

    public function __construct(DnsRecord $record, $status, $message = '')
    {
        $this->record = $record;
        $this->status = $status;
        $this->message = $message;
    }
}
