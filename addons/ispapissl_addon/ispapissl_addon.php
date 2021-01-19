<?php

require_once(__DIR__ . '/../../servers/ispapissl/vendor/autoload.php');

use HEXONET\WHMCS\ISPAPI\SSL\DBHelper;
use HEXONET\WHMCS\ISPAPI\SSL\SSLHelper;
use WHMCS\Module\Registrar\Ispapi\LoadRegistrars;

/**
 * Configuration of the addon module.
 * @return string[]
 */
function ispapissl_addon_config()
{
    return [
        "name" => "ISPAPI SSL",
        "description" => "Quickly add and configure SSL Certificates",
        "author" => '<a href="https://www.hexonet.net/" target="_blank"><img style="max-width:100px" src="' . SSLHelper::getLogo() . '" alt="HEXONET" /></a>',
        "language" => "english",
        "version" => "9.1.0"
    ];
}

/**
 * This function will be called with the activation of the add-on module.
 * @return string[]
 */
function ispapissl_addon_activate()
{
    return ['status' => 'success','description' => 'Installed'];
}

/**
 * This function will be called with the deactivation of the add-on module.
 * @return string[]
 */
function ispapissl_addon_deactivate()
{
    return ['status' => 'success','description' => 'Uninstalled'];
}

/**
 * Module interface functionality
 * @param $vars
 */
function ispapissl_addon_output($vars)
{
    global $templates_compiledir;

    if (!class_exists('WHMCS\Module\Registrar\Ispapi\LoadRegistrars')) {
        echo "The ISPAPI Registrar Module is required. Please install it and activate it.";
        return;
    }

    $registrars = new LoadRegistrars();
    if (empty($registrars->getLoadedRegistars())) {
        echo "The ISPAPI Registrar Module authentication failed! Please verify your registrar credentials and try again.";
        return;
    }

    $smarty = new Smarty();
    $smarty->setTemplateDir(__DIR__ . DIRECTORY_SEPARATOR . 'templates');
    $smarty->setCompileDir($templates_compiledir);
    $smarty->caching = false;
    $smarty->assign('lang', $vars['_lang']);

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$_POST['ProductGroup']) {
                $smarty->assign('error', 'Please select a product group');
            } elseif (count($_POST['SelectedCertificate']) == 0) {
                $smarty->assign('error', 'Please select at least one certificate');
            } else {
                SSLHelper::importProducts();
                $smarty->assign('success', count($_POST['SelectedCertificate']) . ' products have been imported');
            }
        }
        $smarty->assign('logo', SSLHelper::getLogo());
        $smarty->assign('productGroups', DBHelper::getProductGroups());
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
