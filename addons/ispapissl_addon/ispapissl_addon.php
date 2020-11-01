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

    $pattern = '/PRICE_CLASS_SSLCERT_(.*_.*)_ANNUAL$/';
    $products = [];
    if (preg_grep($pattern, $user['RELATIONTYPE'])) {
        foreach (preg_grep($pattern, $user['RELATIONTYPE']) as $key => $val) {
            preg_match($pattern, $val, $matches);
            $certificate = $matches[1];

            $price = $user['RELATIONVALUE'][$key];
            $currencyKeys = array_keys(preg_grep('/PRICE_CLASS_SSLCERT_' . $certificate . '_CURRENCY$/', $user['RELATIONTYPE']));
            $currency = null;
            foreach ($currencyKeys as $key) {
                if (array_key_exists($key, $user['RELATIONVALUE'])) {
                    $currency = $user['RELATIONVALUE'][$key];
                }
            }

            $products[$certificate] = [
                'Name' => SSLHelper::getProductName($certificate),
                'Price' => $price,
                'NewPrice' => $price,
                'DefaultCurrency' => $currency
            ];
        }
    }

    $systemCurrencies = [];
    $currencies = localAPI('GetCurrencies', []);
    if ($currencies['result'] == 'success') {
        foreach ($currencies['currencies']['currency'] as $value) {
            $systemCurrencies[$value['id']] = $value['code'];
        }
    }

    $productGroupName = htmlspecialchars($_POST['selectedproductgroup']);

    $smarty = new Smarty();
    $smarty->setTemplateDir(__DIR__ . DIRECTORY_SEPARATOR . 'templates');
    $smarty->setCompileDir($templates_compiledir);
    $smarty->caching = false;

    $step = 2;
    $smarty->assign('product_groups', SSLHelper::getProductGroups());

    if (isset($_POST['loadcertificates'])) {
        $_SESSION['selectedproductgroup'] = $productGroupName;
        if (empty($productGroupName)) {
            $smarty->assign('error', 'Please select a product group');
            $step = 1;
        }
    } elseif (isset($_POST['calculateregprice'])) {
        $regPeriod = $_POST['RegistrationPeriod'];
        if (!empty($regPeriod) && $regPeriod == 2) {
            $products = SSLHelper::calculateRegistrationPrice($products, $regPeriod);
        }
    } elseif (isset($_POST['AddProfitMargin'])) {
        $profitMargin = $_POST['ProfitMargin'];
        $regPeriod = $_POST['RegistrationPeriod'];
        if (!empty($profitMargin)) {
            if (!empty($regPeriod) && $regPeriod == 2) {
                $products = SSLHelper::calculateProfitMargin(SSLHelper::calculateRegistrationPrice($products, $regPeriod), $profitMargin);
            } else {
                $products = SSLHelper::calculateProfitMargin($products, $profitMargin);
            }
        } elseif (!empty($regPeriod) && $regPeriod == 2) {
            $products = SSLHelper::calculateRegistrationPrice($products, $regPeriod);
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
        $smarty->assign('session-selected-product-group', $_SESSION['selectedproductgroup']);
        $smarty->assign('products', $products);
        $smarty->assign('configured_currencies_in_whmcs', $systemCurrencies);
    }

    try {
        $smarty->display("step{$step}.tpl");
    } catch (Exception $e) {
        echo "ERROR - Unable to render template: {$e->getMessage()}";
    }
}
