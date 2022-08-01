<?php

/**
 * CentralNic SSL Addon for WHMCS
 *
 * SSL Certificates Registration using WHMCS & HEXONET or CNR
 *
 * For more information, please refer to the online documentation.
 * @see https://centralnic-reseller.github.io/centralnic-reseller/docs/cnic/whmcs/whmcs-ssl/
 * @noinspection PhpUnused
 */

require_once(__DIR__ . '/../../servers/cnicssl/vendor/autoload.php');

use CNIC\WHMCS\SSL\APIHelper;
use CNIC\WHMCS\SSL\DBHelper;
use CNIC\WHMCS\SSL\SSLHelper;

/**
 * Configuration of the addon module.
 * @return array<string, mixed>
 */
function cnicssl_addon_config(): array
{
    return [
        "name" => "CNIC SSL",
        "description" => "Quickly add and configure SSL Certificates",
        "author" => '<a href="https://www.centralnicreseller.com/" target="_blank"><img style="max-width:100px" src="' . SSLHelper::getLogo() . '" alt="CentralNic Reseller" /></a>',
        "language" => "english",
        "version" => "11.0.0",
        "fields" => [
            "registrar" => [
                "FriendlyName" => "Registrar",
                "Type" => "radio",
                "Options" => "ISPAPI,CNIC",
                "Default" => "ISPAPI",
                "Description" => "Please note, the corresponding registrar module must be installed!"
            ],
        ]
    ];
}

/**
 * This function will be called with the activation of the add-on module.
 * @return array<string, string>
 */
function cnicssl_addon_activate(): array
{
    return ['status' => 'success', 'description' => 'Installed'];
}

/**
 * This function will be called with the deactivation of the add-on module.
 * @return array<string, string>
 */
function cnicssl_addon_deactivate(): array
{
    return ['status' => 'success', 'description' => 'Uninstalled'];
}

/**
 * This function will be called when upgrading the add-on module.
 * @param array<string, mixed> $vars
 * @return void
 */
function cnicssl_addon_upgrade($vars)
{
    DBHelper::processUpgradeSteps($vars['version']);
}

/**
 * Module interface functionality
 * @param array<string, mixed> $vars
 */
function cnicssl_addon_output(array $vars): void
{
    global $templates_compiledir;

    $registrarid = strtolower($vars["registrar"]);
    $label = "ISPAPI";
    $class = "\WHMCS\Module\Registrar\Ispapi\Ispapi";
    $fn = "checkAuth";
    $productregistrarid = "ISPAPI";
    if ($registrarid !== "ispapi") {
        $registrarid = "keysystems";
        $productregistrarid = "CNIC";
        $label = "CentralNic Reseller";
        $class = "\WHMCS\Module\Registrar\Keysystems\APIClient";
        $fn = null;
    }

    $registrar = new \WHMCS\Module\Registrar();
    if (
        !$registrar->load($registrarid)
        || !$registrar->isActivated()
    ) {
        // unable to load the registrar module
        echo "The " . $label . " Registrar Module is missing or not activated.";
        return;
    }

    if (!class_exists($class)) {
        echo "Class not found ${class}.";
        return;
        //$vendor = realpath(__DIR__ . '/../../registrars/keysystems/vendor/autoload.php');
        //if ($vendor && file_exists($vendor)) {
        //    require_once $vendor;
        //}
        //TODO should we implement checkAuth in the CentralNic Reseller module?
    }
    if (!is_null($fn)) {
        if (!method_exists($class, $fn)) {
            echo "The " . $label . " Registrar Module is outdated. Please upgrade.";
            return;
        }
        if (!$class::$fn()) {
            echo "The " . $label . " Registrar Module authentication failed! Please verify your registrar credentials and try again.";
            return;
        }
    }

    $smarty = new Smarty();
    $smarty->setTemplateDir(__DIR__ . DIRECTORY_SEPARATOR . 'templates');
    $smarty->setCompileDir($templates_compiledir);
    $smarty->setCaching(Smarty::CACHING_OFF);
    $smarty->assign('lang', $vars['_lang']);

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (@$_POST['ProductGroups']) {
                SSLHelper::importProductGroups();
            }
            $countImported = SSLHelper::importProducts($productregistrarid);
            $smarty->assign('success', $countImported . ' products have been imported');
        }
        $smarty->assign('logo', SSLHelper::getLogo());
        $smarty->assign('products', APIHelper::getProducts($productregistrarid));
        $smarty->assign('currency', DBHelper::getDefaultCurrency()->code);
    } catch (Exception $ex) {
        $smarty->assign('error', $ex->getMessage());
    }

    try {
        $smarty->display("import.tpl");
    } catch (Exception $e) {
        echo "ERROR - Unable to render template: {$e->getMessage()}";
    }
}
