<?php

use HEXONET\WHMCS\ISPAPI\SSL\SSLHelper;
use WHMCS\Module\Registrar\Ispapi\Ispapi;
use WHMCS\Module\Registrar\Ispapi\LoadRegistrars;

session_start();

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
    return array('status' => 'success','description' => 'Installed');
}

/*
 * This function will be called with the deactivation of the add-on module.
*/
function ispapissl_addon_deactivate()
{
    return array('status' => 'success','description' => 'Uninstalled');
}

/*
 * Module interface functionality
 */
function ispapissl_addon_output()
{
    //load all the ISPAPI registrars
    $ispapi_registrars = new LoadRegistrars();
    $_SESSION["ispapi_registrar"] = $ispapi_registrars->getLoadedRegistars();

    if (empty($_SESSION["ispapi_registrar"])) {
        die("The ispapi registrar authentication failed! Please verify your registrar credentials and try again.");
    }

    //smarty template
    $smarty = new Smarty();
    $smarty->setCompileDir($GLOBALS['templates_compiledir']);
    $smarty->caching = false;

    //display all the product groups that user has
    $smarty->assign('product_groups', SSLHelper::GetProductGroups());

    //get the SSL certificates
    $command = array(
        "command" => "statususer"
    );
    $statususer_data = Ispapi::call($command);

    $pattern_for_SSL_certificate = "/PRICE_CLASS_SSLCERT_(.*_.*)_ANNUAL$/";
    $certificates_and_prices = array();
    if (preg_grep($pattern_for_SSL_certificate, $statususer_data["PROPERTY"]["RELATIONTYPE"])) {
        //get all the SSL Certificates
        $list_of_certificates = preg_grep($pattern_for_SSL_certificate, $statususer_data["PROPERTY"]["RELATIONTYPE"]);

        //get the prices of the SSL certificates
        foreach ($list_of_certificates as $key => $ssl_certificate) {
            //to match ispapi classes of ssl certificates
            preg_match($pattern_for_SSL_certificate, $ssl_certificate, $certificate);
            
            $ispapi_match_ssl_certificate = $certificate[1];
            //price of the ssl certificate
            $price = $statususer_data["PROPERTY"]["RELATIONVALUE"][$key];
            //collect certs and prices
            $certificates_and_prices[$ispapi_match_ssl_certificate]['Price'] = $price;
            //this 'newprice' = sale price and is modifiable by the user and this is the price that will be imported when it is changed/unchanged by user.
            $certificates_and_prices[$ispapi_match_ssl_certificate]['Newprice'] = $price;

            //default currency (at hexonet)
            $pattern_for_currency = "/PRICE_CLASS_SSLCERT_" . $certificate[1] . "_CURRENCY$/";
            $currency_match = preg_grep($pattern_for_currency, $statususer_data["PROPERTY"]["RELATIONTYPE"]);
            $currency_match_keys = array_keys($currency_match);

            $cert_currency = null;
            foreach ($currency_match_keys as $key) {
                if (array_key_exists($key, $statususer_data["PROPERTY"]["RELATIONVALUE"])) {
                    $cert_currency = $statususer_data["PROPERTY"]["RELATIONVALUE"][$key];
                    #$tld_register_renew_transfer_currency[$tld]['currency'] = $tld_currency;
                }
            }
            $certificates_and_prices[$ispapi_match_ssl_certificate]['Defaultcurrency'] = $cert_currency;
        }
    }

    //array keys(certificate class names) without underscoreupper and to lower_case
    array_keys_to_lowerCase($certificates_and_prices);

    //user currencies configured in whmcs
    $configured_currencies_in_whmcs = [];
    $currencies = localAPI('GetCurrencies', []);
    foreach ($currencies['currency'] as $value) {
        $configured_currencies_in_whmcs[$value["id"]] = $value["code"];
    }

    //which product group selected by the user
    $selected_product_group = htmlspecialchars($_POST['selectedproductgroup']);

    //for prices with profit margin
    $certificates_and_new_prices = array();

    //import (products) ssl certificates and prices
    if (isset($_POST['loadcertificates'])) {
        $_SESSION['selectedproductgroup'] = $selected_product_group;
        //check of the product group is selected by user if not show a error message
        if (empty($_SESSION['selectedproductgroup'])) {
            echo "<div class='errorbox'><strong><span class='title'>ERROR!</span></strong><br>Please select a product group</div><br>";
            $smarty->display(dirname(__FILE__) . '/templates/step1.tpl');
        } else {
            $smarty->assign('session-selected-product-group', $_SESSION["selectedproductgroup"]);
            $smarty->assign('certificates_and_prices', $certificates_and_prices);
            $smarty->assign('configured_currencies_in_whmcs', $configured_currencies_in_whmcs);
            $smarty->display(dirname(__FILE__) . '/templates/step2.tpl');
        }
    } elseif (isset($_POST['calculateregprice'])) {
        $reg_period = $_POST['registrationperiod'];

        $smarty->assign('session-selected-product-group', $_SESSION["selectedproductgroup"]);
        if (!empty($reg_period) && $reg_period == 2) {
            $certificates_and_new_prices = calculate_registration_price($certificates_and_prices, $reg_period);
            $smarty->assign('certificates_and_prices', $certificates_and_new_prices);
        } else {
            $smarty->assign('certificates_and_prices', $certificates_and_prices);
        }
        $smarty->assign('configured_currencies_in_whmcs', $configured_currencies_in_whmcs);
        $smarty->display(dirname(__FILE__) . '/templates/step2.tpl');
    } elseif (isset($_POST['addprofitmargin'])) {
        $profit_margin = $_POST['profitmargin'];
        $reg_period = $_POST['registrationperiod'];

        $smarty->assign('session-selected-product-group', $_SESSION["selectedproductgroup"]);
        if (!empty($profit_margin)) {
            if (!empty($reg_period) && $reg_period == 2) {
                //profit margin on 2Y reg price
                $certificates_and_new_prices = calculate_profitmargin(calculate_registration_price($certificates_and_prices, $reg_period), $profit_margin);
                $smarty->assign('certificates_and_prices', $certificates_and_new_prices);
            } else {
                //profit margin on 1Y reg price
                $certificates_and_new_prices = calculate_profitmargin($certificates_and_prices, $profit_margin);
                $smarty->assign('certificates_and_prices', $certificates_and_new_prices);
            }
        } else {
            //when clicked on empty profit margin but reg period is still set to 2Y
            if (!empty($reg_period) && $reg_period == 2) {
                 $certificates_and_new_prices = calculate_registration_price($certificates_and_prices, $reg_period);
                 $smarty->assign('certificates_and_prices', $certificates_and_new_prices);
            } else {
                //when clicked on empty profit margin and reg period is set to 1Y
                $smarty->assign('certificates_and_prices', $certificates_and_prices);
            }
        }
        $smarty->assign('configured_currencies_in_whmcs', $configured_currencies_in_whmcs);
        $smarty->display(dirname(__FILE__) . '/templates/step2.tpl');
    } elseif (isset($_POST['import'])) {
        //to collect new prices, certificates and (new) selected currency
        if (isset($_POST["checkboxcertificate"])) {
            import_button();
        }

        //take post values of sale prices - when user edit sale prices manually, should be displayed same after 'import'
        $certificate_match_pattern = "/(.*)_saleprice/";
        foreach ($_POST as $key => $value) {
            if (preg_match($certificate_match_pattern, $key, $match)) {
                $certificates_and_new_prices[$match[1]]['Newprice'] = $value;
            }
        }
        //to remove underscores in certificate names
        array_keys_to_lowerCase($certificates_and_new_prices);
        foreach ($certificates_and_prices as $key => $value) {
            if (array_key_exists($key, $certificates_and_new_prices)) {
                $certificates_and_prices[$key]['Newprice'] = $certificates_and_new_prices[$key]['Newprice'];
            }
        }

        $smarty->assign('certificates_and_prices', $certificates_and_prices);
        $smarty->assign('session-selected-product-group', $_SESSION["selectedproductgroup"]);
        $smarty->assign('configured_currencies_in_whmcs', $configured_currencies_in_whmcs);
        //to display checked items even after button click
        $smarty->assign('post-checkboxcertificate', $_POST['checkboxcertificate']);

        $smarty->display(dirname(__FILE__) . '/templates/step2.tpl');
    } else {
        $smarty->display(dirname(__FILE__) . '/templates/step1.tpl');
    }
}

/*
 * Helper functions
 */
function array_keys_to_lowerCase(&$array)
{
    $array = array_change_key_case($array, CASE_LOWER);
    $array = array_combine(array_map(function ($str) {
        return ucwords(str_replace("_", " ", $str));
    }, array_keys($array)), array_values($array));
    foreach ($array as $key => $val) {
        if (is_array($val)) {
            array_keys_to_lowerCase($array[$key]);
        }
    }
}
//calculate profit margin of the product price
function calculate_profitmargin($certificates_and_prices, $profit_margin)
{
    //prices with profit margin
    $certificates_and_new_prices = $certificates_and_prices;

    foreach ($certificates_and_new_prices as $certificate => $price_defaultcurrency) {
        $percentage_of_price = ($profit_margin / 100) * $price_defaultcurrency['Newprice'];
        $new_price = $price_defaultcurrency['Newprice'] + $percentage_of_price;
        $certificates_and_new_prices[$certificate]['Newprice'] = number_format((float)$new_price, 2, '.', '');
    }

    return $certificates_and_new_prices;
}
//calculate product price for the given period
function calculate_registration_price($certificates_and_prices, $reg_period)
{
    $certificates_and_new_prices = $certificates_and_prices;

    foreach ($certificates_and_new_prices as $certificate => $price_and_defaultcurrency) {
        $new_price = $reg_period * $price_and_defaultcurrency['Price'];
        $certificates_and_new_prices[$certificate]['Price'] = number_format((float)$new_price, 2, '.', '');
        $certificates_and_new_prices[$certificate]['Newprice'] = number_format((float)$new_price, 2, '.', '');
    }

    return $certificates_and_new_prices;
}

/*
 * Import selected SSL certificates/products
 */
function import_button()
{
    //prepare an array from POST values with certificates and the new price for importing

    //load all the ISPAPI registrars
    $ispapi_registrars = new LoadRegistrars();
    $_SESSION["ispapi_registrar"] = $ispapi_registrars->getLoadedRegistars();

    $selected_product_group = $_POST['SelectedProductGroup'];

    $certificate_match_pattern = "/(.*)_saleprice/";

    //POST values and checked items of ssl certificates are different. POST's are seperated by _ underscore where checked items not.
    $certificates_and_new_prices = []; //will have all the certificates which have new prices
    foreach ($_POST as $key => $value) {
        if (preg_match($certificate_match_pattern, $key, $match)) {
            $certificates_and_new_prices[$match[1]]['newprice'] = $value;
          //certificate class
            $certificates_and_new_prices[$match[1]]['certificateClass'] = strtoupper($match[1]);
          //for ssl server module
            $certificates_and_new_prices[$match[1]]['servertype'] = 'ispapissl';
          //registrar
            $certificates_and_new_prices[$match[1]]['registrar'] = $_SESSION["ispapi_registrar"][0];
        }
    }

    //for currency
    $currencies = [];
    $currency_pattern = "/currency/";
    foreach ($_POST as $key => $value) {
        if (preg_match($currency_pattern, $key)) {
            $currencies['currency'] = $value;
        }
    }
    //to merge each curreny value from currencies array certificates_and_new_prices
    $i = -1;
    foreach ($certificates_and_new_prices as $key => $value) {
        $i++;
        $certificates_and_new_prices[$key]['currency'] = $currencies['currency'][$i];
    }
    //POST value automatically replace space with _. therefore I have to call this:
    array_keys_to_lowerCase($certificates_and_new_prices);

    //import only checked certificates - unset/remove all other certificates from $certificates_and_new_prices
    foreach ($certificates_and_new_prices as $key => $val) {
        if (array_search($key, $_POST['checkboxcertificate']) === false) {
            unset($certificates_and_new_prices[$key]);
        }
    }
    //import certificates and new prices
    importproducts($certificates_and_new_prices, $selected_product_group);
}

/*
 * Save imported SSL certificates/products
 */
function importproducts($certificates_and_prices, $selected_product_group)
{
    //registration period 1Y or 2Y
    $reg_period = $_POST['registrationperiod'];

    if ((!empty($reg_period) && $reg_period == 1)) {
        //to retrieve data for (1y or 2y product) from DB
        $configoption3 = '1';
        //certificate name will contain the following addition
        $yeartext = ' - 1 Year';
    } else {
        $configoption3 = '2';
        $yeartext = ' - 2 Year';
    }
    //get the id of selected product group
    $product_group_id = SSLHelper::GetProductGroupId($selected_product_group);
    foreach ($certificates_and_prices as $ssl_certificate => $price) {
        $ssl_certificate = $ssl_certificate . $yeartext;
        $product_id = SSLHelper::GetProductId($ssl_certificate, $product_group_id, $configoption3);

        if (!$product_id) {
            $product_id = SSLHelper::CreateProduct($ssl_certificate, $product_group_id, $price['Servertype'], $price['Certificateclass'], $price['Registrar']);
        } else {
            //update
            //the product exists then with which currency - there is possibility to store price of a product with as many currency as possible (if currencies configured in WHMCS)
            $currencies = SSLHelper::GetProductCurrencies($product_id);

            //if the currency exists then update it with new price
            if (in_array($price['Currency'], $currencies)) {
                SSLHelper::UpdatePricing($product_id, $price['Currency'], $price['Newprice']);
                return;
            }
        }

        SSLHelper::CreatePricing($product_id, $price['Currency'], $price['Newprice']);
    }
}
