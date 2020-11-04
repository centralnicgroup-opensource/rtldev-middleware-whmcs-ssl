<?php

use HEXONET\WHMCS\ISPAPI\SSL\APIHelper;
use HEXONET\WHMCS\ISPAPI\SSL\SSLHelper;
use WHMCS\Module\Registrar\Ispapi\LoadRegistrars;

session_start();

require_once(__DIR__ . '/../../servers/ispapissl/lib/APIHelper.php');
require_once(__DIR__ . '/../../servers/ispapissl/lib/SSLHelper.php');

/*
 * Configuration of the addon module.
 */
function ispapissl_addon_config()
{
    return [
        "name" => "ISPAPI SSL Addon",
        "description" => "This addon allows you to quickly add and configure SSL Certificates",
        "author" => "HEXONET",
        "language" => "english",
        "version" => "8.0.1"
    ];
}

/*
 * This function will be called with the activation of the add-on module.
 */
function ispapissl_addon_activate()
{
    return ['status' => 'success','description' => 'Installed'];
}

/*
 * This function will be called with the deactivation of the add-on module.
*/
function ispapissl_addon_deactivate()
{
    return ['status' => 'success','description' => 'Uninstalled'];
}

/*
 * Module interface functionality
 */
function ispapissl_addon_output()
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

    $user = APIHelper::getUserStatus();

    $currencies = SSLHelper::getCurrencies();

    $pattern = '/PRICE_CLASS_SSLCERT_(.*_.*)_ANNUAL$/';
    $products = [];
    $certs = preg_grep($pattern, $user['RELATIONTYPE']);
    if (is_array($certs)) {
        foreach ($certs as $key => $val) {
            preg_match($pattern, $val, $matches);
            $productKey = $matches[1];

            $price = $user['RELATIONVALUE'][$key];
            $currencyKey = array_search("PRICE_CLASS_SSLCERT_{$productKey}_CURRENCY", $user['RELATIONTYPE']);
            if ($currencyKey === false) {
                continue;
            }
            $currency = $user['RELATIONVALUE'][$currencyKey];

            $arrayKey = array_search($currency, array_column($currencies, 'code'));
            if ($arrayKey === false) {
                //TODO convert currency via API
            } else {
                $price = round($price / $currencies[$arrayKey]['rate'], 2);
            }

            $products[$productKey] = [
                'Name' => SSLHelper::getProductName($productKey),
                'Price' => $price,
                'NewPrice' => $price
            ];
        }
    }

    $productGroupName = htmlspecialchars($_POST['selectedproductgroup']);

    $smarty = new Smarty();
    $smarty->setTemplateDir(__DIR__ . DIRECTORY_SEPARATOR . 'templates');
    $smarty->setCompileDir($templates_compiledir);
    $smarty->caching = false;

    $step = 2;
    $smarty->assign('productGroups', SSLHelper::getProductGroups());

    if (isset($_POST['loadcertificates'])) {
        $_SESSION['selectedproductgroup'] = $productGroupName;
        if (empty($productGroupName)) {
            $smarty->assign('error', 'Please select a product group');
            $step = 1;
        }
    } elseif (isset($_POST['AddProfitMargin'])) {
        $profitMargin = $_POST['ProfitMargin'];
        if (!empty($profitMargin)) {
            $products = SSLHelper::calculateProfitMargin($products, $profitMargin);
        }
    } elseif (isset($_POST['import'])) {
        if (isset($_POST['SelectedCertificate'])) {
            SSLHelper::importProducts();
            $step = 3;
        }
    } else {
        $step = 1;
    }

    if ($step == 2) {
        $smarty->assign('products', $products);
        $smarty->assign('currency', SSLHelper::getDefaultCurrency());
    }

    try {
        $smarty->display("step{$step}.tpl");
    } catch (Exception $e) {
        echo "ERROR - Unable to render template: {$e->getMessage()}";
    }
}
