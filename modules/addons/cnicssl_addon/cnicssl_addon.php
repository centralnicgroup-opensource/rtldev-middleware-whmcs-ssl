<?php

/**
 * CentralNic SSL Addon for WHMCS
 *
 * SSL Certificates Registration using WHMCS & HEXONET or RRPproxy
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
        "author" => '<a href="https://www.centralnicgroup.com/" target="_blank"><img style="max-width:100px" src="' . SSLHelper::getLogo() . '" alt="CentralNic" /></a>',
        "language" => "english",
        "version" => "11.0.0",
        "fields" => [
            "registrar" => [
                "FriendlyName" => "Registrar",
                "Type" => "radio",
                "Options" => "ISPAPI,RRPproxy",
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
 * Module interface functionality
 * @param array<string, mixed> $vars
 */
function cnicssl_addon_output(array $vars): void
{
    global $templates_compiledir;

    if ($vars["registrar"] === "RRPproxy") {
        if (!class_exists('\WHMCS\Module\Registrar\RRPproxy\APIClient')) {
            $vendor = realpath(__DIR__ . '/../../registrars/keysystems/vendor/autoload.php');
            if ($vendor && file_exists($vendor)) {
                require_once $vendor;
            } else {
                echo "The RRPproxy Registrar Module is required. Please install it and activate it.";
                return;
            }
        }
        //TODO should we implement checkAuth in the RRPproxy module?
    } else {
        $className = "\\WHMCS\\Module\\Registrar\\Ispapi\\Ispapi";
        if (!class_exists($className)) {
            echo "The ISPAPI Registrar Module is required. Please install it and activate it.";
            return;
        }
        if ((new $className())->checkAuth() instanceof $className) {
            echo "The ISPAPI Registrar Module is outdated. Please update it.";
            return;
        }
        if (!$className::checkAuth()) {
            echo "The ISPAPI Registrar Module authentication failed! Please verify your registrar credentials and try again.";
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
            SSLHelper::importProducts($vars["registrar"]);
            $smarty->assign('success', count($_POST['SelectedCertificate']) . ' products have been imported');
        }
        $smarty->assign('logo', SSLHelper::getLogo());
        $smarty->assign('products', APIHelper::getProducts($vars["registrar"]));
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
