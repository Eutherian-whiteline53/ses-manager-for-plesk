<?php

if (version_compare(pm_ProductInfo::getVersion(), '18.0.0', '<')) {
    echo 'SES Manager requires Plesk 18.0.0 or later.';
    exit(1);
}

if (!extension_loaded('curl')) {
    echo 'SES Manager requires the PHP curl extension.';
    exit(1);
}

if (!extension_loaded('openssl')) {
    echo 'SES Manager requires the PHP openssl extension.';
    exit(1);
}
