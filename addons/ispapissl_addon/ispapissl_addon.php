<?php

use HEXONET\WHMCS\ISPAPI\SSL\APIHelper;
use HEXONET\WHMCS\ISPAPI\SSL\DBHelper;
use HEXONET\WHMCS\ISPAPI\SSL\SSLHelper;
use WHMCS\Module\Registrar\Ispapi\LoadRegistrars;

require_once(__DIR__ . '/../../servers/ispapissl/lib/APIHelper.php');
require_once(__DIR__ . '/../../servers/ispapissl/lib/DBHelper.php');
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

    $smarty = new Smarty();
    $smarty->setTemplateDir(__DIR__ . DIRECTORY_SEPARATOR . 'templates');
    $smarty->setCompileDir($templates_compiledir);
    $smarty->caching = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$_POST['ProductGroup']) {
            $smarty->assign('error', 'Please select a product group');
        } elseif (count($_POST['SelectedCertificate']) == 0) {
            $smarty->assign('error', 'Please select at least one certificate');
        } else {
            try {
                SSLHelper::importProducts();
                $smarty->assign('success', count($_POST['SelectedCertificate']) . ' products have been imported');
            } catch (Exception $ex) {
                $smarty->assign('error', $ex->getMessage());
            }
        }
    }

    $user = APIHelper::getUserStatus();
    $currencies = SSLHelper::getCurrencies();
    $defaultCurrency = DBHelper::getDefaultCurrency();
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
                    $exchangeKey = array_search($defaultCurrency->code, $exchangeRates['CURRENCYTO']);
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
                    if ($defaultCurrency->code != $exchangeRates['CURRENCYFROM'][$exchangeKey]) {
                        // Convert to WHMCS default currency
                        $exchangeKey = array_search($defaultCurrency->code, $exchangeRates['CURRENCYTO']);
                        $price = round($price * $exchangeRates['RATE'][$exchangeKey], 2);
                    }
                }
            } else {
                $price = round($price / $currencies[$arrayKey]['rate'], 2);
            }

            $products[$productKey] = [
                'id' => 0,
                'Name' => SSLHelper::getProductName($productKey),
                'Cost' => $price,
                'Margin' => 0,
                'AutoSetup' => false
            ];

            $existingProduct = DBHelper::getProduct($productKey);
            if ($existingProduct) {
                $products[$productKey]['id'] = $existingProduct->id;
                $products[$productKey]['AutoSetup'] = $existingProduct->autosetup ? true : false;
                $products[$productKey]['Price'] = DBHelper::getProductPricing($existingProduct->id, $defaultCurrency->id);
                $products[$productKey]['Margin'] = round(((($products[$productKey]['Price'] / $products[$productKey]['Cost']) * 100) - 100), 2);
            }
        }
    }

    $smarty->assign('logo', SSLHelper::getLogo());
    $smarty->assign('productGroups', DBHelper::getProductGroups());
    $smarty->assign('products', $products);
    $smarty->assign('currency', $defaultCurrency->code);

    try {
        $smarty->display("import.tpl");
    } catch (Exception $e) {
        echo "ERROR - Unable to render template: {$e->getMessage()}";
    }
}
