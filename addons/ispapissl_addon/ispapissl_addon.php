<?php

/**
 * ISPAPI SSL Addon for WHMCS
 *
 * SSL Certificates Registration using WHMCS & HEXONET
 *
 * For more information, please refer to the online documentation.
 * @see https://wiki.hexonet.net/wiki/WHMCS_Modules
 * @noinspection PhpUnused
 */

require_once(__DIR__ . '/../../servers/ispapissl/vendor/autoload.php');

use HEXONET\WHMCS\ISPAPI\SSL\DBHelper;
use HEXONET\WHMCS\ISPAPI\SSL\SSLHelper;
use WHMCS\Module\Registrar\Ispapi\Ispapi;

/**
 * Configuration of the addon module.
 * @return array<string, string>
 */
function ispapissl_addon_config(): array
{
    return [
        "name" => "ISPAPI SSL",
        "description" => "Quickly add and configure SSL Certificates",
        "author" => '<a href="https://www.hexonet.net/" target="_blank"><img style="max-width:100px" src="' . SSLHelper::getLogo() . '" alt="HEXONET" /></a>',
        "language" => "english",
        "version" => "9.2.3"
    ];
}

/**
 * This function will be called with the activation of the add-on module.
 * @return array<string, string>
 */
function ispapissl_addon_activate(): array
{
    return ['status' => 'success','description' => 'Installed'];
}

/**
 * This function will be called with the deactivation of the add-on module.
 * @return array<string, string>
 */
function ispapissl_addon_deactivate(): array
{
    return ['status' => 'success','description' => 'Uninstalled'];
}

/**
 * Module interface functionality
 * @param array<string, mixed> $vars
 */
function ispapissl_addon_output(array $vars): void
{
    global $templates_compiledir;

    if (!class_exists('\WHMCS\Module\Registrar\Ispapi\Ispapi')) {
        echo "The ISPAPI Registrar Module is required. Please install it and activate it.";
        return;
    }

    if (!method_exists('\WHMCS\Module\Registrar\Ispapi\Ispapi', 'checkAuth')) {
        echo "The ISPAPI Registrar Module is outdated. Please update it.";
        return;
    }

    if (!Ispapi::checkAuth()) {
        echo "The ISPAPI Registrar Module authentication failed! Please verify your registrar credentials and try again.";
        return;
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
            SSLHelper::importProducts();
            $smarty->assign('success', count($_POST['SelectedCertificate']) . ' products have been imported');
        }
        $smarty->assign('logo', SSLHelper::getLogo());
        $smarty->assign('products', SSLHelper::getProducts());
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
