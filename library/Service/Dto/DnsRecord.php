<?php

namespace Library\Service\Dto;

class DnsRecord
{
    /** @var string */
    public $type;

    /** @var string */
    public $name;

    /** @var string */
    public $value;

    /** @var string|null */
    public $purpose;

    public function __construct($type, $name, $value, $purpose = null)
    {
        $this->type = $type;
        $this->name = $name;
        $this->value = $value;
        $this->purpose = $purpose;
    }
}
