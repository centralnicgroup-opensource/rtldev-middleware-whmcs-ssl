<?php

// This is the bootstrap for PHPUnit testing.

if (!defined('WHMCS')) {
    define('WHMCS', true);
}

// Include the WHMCS module.
require_once __DIR__ . '/../servers/cnicssl/cnicssl.php';

/**
 * Mock logModuleCall function for testing purposes.
 *
 * Inside of WHMCS, this function provides logging of module calls for debugging
 * purposes. The module log is accessed via Utilities > Logs.
 *
 * @param string $module
 * @param string $action
 * @param string|array<int, mixed> $request
 * @param string|array<int, mixed> $response
 * @param string|array<int, mixed> $data
 * @param array $variablesToMask
 *
 * @return void|false
 */
function logModuleCall(
    $module,
    $action,
    $request,
    $response,
    $data = '',
    $variablesToMask = array()
) {
    // do nothing during tests
}
