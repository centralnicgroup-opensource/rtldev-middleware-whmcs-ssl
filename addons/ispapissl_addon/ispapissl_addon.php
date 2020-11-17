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
        "name" => "ISPAPI SSL",
        "description" => "Quickly add and configure SSL Certificates",
        "author" => '<a href="https://www.hexonet.net/" target="_blank"><img style="max-width:100px" src="' . SSLHelper::getLogo() . '" alt="HEXONET" /></a>',
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
    $defaultCurrency = SSLHelper::getDefaultCurrency();
    $exchangeRates = APIHelper::getExchangeRates();

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
                if (in_array($currency, $exchangeRates['CURRENCYFROM'])) {
                    // Product currency is same as ISPAPI base currency
                    $exchangeKey = array_search($defaultCurrency, $exchangeRates['CURRENCYTO']);
                    if ($exchangeKey === false) {
                        continue;
                    }
                    $price = round($price * $exchangeRates['RATE'][$exchangeKey], 2);
                } else {
                    // Convert to ISPAPI base currency
                    $exchangeKey = array_search($currency, $exchangeRates['CURRENCYTO']);
                    if ($exchangeKey === false) {
                        continue;
                    }
                    $price = round($price / $exchangeRates['RATE'][$exchangeKey], 2);
                    if ($defaultCurrency != $exchangeRates['CURRENCYFROM'][$exchangeKey]) {
                        // Convert to WHMCS default currency
                        $exchangeKey = array_search($defaultCurrency, $exchangeRates['CURRENCYTO']);
                        $price = round($price * $exchangeRates['RATE'][$exchangeKey], 2);
                    }
                }
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
    $smarty->assign('logo', SSLHelper::getLogo());
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
            $smarty->assign('success', "Your product list has been updated successfully");
            $step = 3;
        } else {
            $smarty->assign('error', 'Please select an SSL Certificate');
        }
    } else {
        $step = 1;
    }

    if ($step == 2) {
        $smarty->assign('products', $products);
        $smarty->assign('currency', $defaultCurrency);
    }

    try {
        $smarty->display("step{$step}.tpl");
    } catch (Exception $e) {
        echo "ERROR - Unable to render template: {$e->getMessage()}";
    }
}
