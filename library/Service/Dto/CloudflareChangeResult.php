<?php

namespace Library\Service\Dto;

class CloudflareChangeResult
{
    /** @var DnsRecord */
    public $record;

    /** @var string */
    public $status;

    /** @var string */
    public $message;

    /** @var string|null */
    public $recordId;

    /** @var string|null */
    public $previousValue;

    public function __construct(DnsRecord $record, $status, $message = '', $recordId = null)
    {
        $this->record = $record;
        $this->status = $status;
        $this->message = $message;
        $this->recordId = $recordId;
    }
}
