<?php

// This is the bootstrap for PHPStan.

$whmcsPath = realpath(__DIR__ . '/../../whmcs');
$configFile = __DIR__ . '/phpstan.config.php';
if (file_exists($configFile)) {
    include($configFile);
}

$files = [
    '/vendor/autoload.php',
    '/includes/functions.php',
    '/modules/registrars/ispapi/lib/Ispapi.php',
    '/modules/registrars/keysystems/lib/APIClient.php'
];
foreach ($files as $file) {
    if (file_exists($whmcsPath . $file)) {
        require_once($whmcsPath . $file);
    }
}


//stream_wrapper_restore('phar');
