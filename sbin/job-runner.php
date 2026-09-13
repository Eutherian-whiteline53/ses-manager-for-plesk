<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use Library\Service\AutoDomainJobRunner;

pm_Bootstrap::init();

(new AutoDomainJobRunner())->run(5);
