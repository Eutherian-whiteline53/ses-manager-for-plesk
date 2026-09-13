<?php

namespace Library\Service\Dto;

class HealthReport
{
    /** @var string */
    public $domain;

    /** @var string pass|pending|failed */
    public $sesStatus = 'pending';

    /** @var string pass|warning|missing */
    public $spfStatus = 'missing';

    /** @var string pass|pending|missing */
    public $dkimStatus = 'missing';

    /** @var string pass|warning|missing */
    public $dmarcStatus = 'missing';

    /** @var string pass|optional|missing */
    public $mxStatus = 'missing';

    /** @var array */
    public $spfAnalysis = array();

    /** @var array */
    public $dkimAnalysis = array();

    /** @var array */
    public $dmarcAnalysis = array();

    /** @var array */
    public $mailFromAnalysis = array();

    public function __construct($domain)
    {
        $this->domain = $domain;
    }
}
