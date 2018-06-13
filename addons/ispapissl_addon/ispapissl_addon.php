<?php
use WHMCS\Database\Capsule;
session_start();

use ISPAPISSL\LoadRegistrars;
use ISPAPISSL\Helper;

require_once(dirname(__FILE__)."/../../servers/ispapissl/lib/LoadRegistrars.class.php");
require_once(dirname(__FILE__)."/../../servers/ispapissl/lib/Helper.class.php");


$module_version = "1.0";

function ispapissl_addon_config($params) {
    global $module_version;
    $configarray = array(
        "name" => "ISPAPI SSL Addon",
        "description" => "This addon allows you to quickly add and configure SSL Certificates",
        "version" => $module_version,
        "author" => "HEXONET",
        "language" => "english",
    );
    return $configarray;
}

function ispapissl_addon_activate() {
	return array('status'=>'success','description'=>'Installed');
}

function ispapissl_addon_deactivate() {
	return array('status'=>'success','description'=>'Uninstalled');
}

function ispapissl_addon_output($vars){

    // load all the ISPAPI registrars
    $ispapi_registrars = new LoadRegistrars();

    $_SESSION["ispapi_registrar"] = $ispapi_registrars->getLoadedRegistars();
    //TODO what do you think about this check?
    if(empty($_SESSION["ispapi_registrar"])){
        die("The ispapi registrar authentication failed! Please verify your registrar credentials and try again.");
    }

    //smarty template
    $smarty = new Smarty;
    $smarty->compile_dir = $GLOBALS['templates_compiledir'];
    $smarty->caching = false;

    // Display all the product groups that user has
    $product_groups = Helper::SQLCall("SELECT * FROM tblproductgroups", array(), "fetchall");
    // Check if user has any product groups. if not create one and display it
    if($product_groups){
        $smarty->assign('product_groups', $product_groups);
    }
    else{
        $insert_stmt = Helper::SQLCall("INSERT INTO tblproductgroups (name) VALUES ('SSL Certificates')", array(), "execute");

        $product_groups = Helper::SQLCall("SELECT * FROM tblproductgroups", array(), "fetchall");

        $smarty->assign('product_groups', $product_groups);
    }

    //get the SSL certificates
    $command = array(
        "command" => "statususer"
    );
    $statususer_data = Helper::APICall($_SESSION["ispapi_registrar"][0], $command);

    $pattern_for_SSL_certificate ="/PRICE_CLASS_SSLCERT_(.*_.*)_ANNUAL$/";
    $certificates_and_prices = array();
    if(preg_grep($pattern_for_SSL_certificate, $statususer_data["PROPERTY"]["RELATIONTYPE"])){
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
                $certificates_and_prices[$ispapi_match_ssl_certificate]['Price']= $price;
                //this 'newprice' is modifiable by the user and this is the price that will be imported when it is changed/unchanged by user.
                $certificates_and_prices[$ispapi_match_ssl_certificate]['Newprice']= $price;
        }
    }

    //array keys(certificate class names) without underscoreupper and to lower_case
    array_keys_to_lowerCase($certificates_and_prices);

    //user currencies configured in whmcs
    $configured_currencies_in_whmcs = [];

    $currencies = Helper::SQLCall("SELECT * FROM tblcurrencies", array(), "fetchall");

    foreach ($currencies as $key => $value) {
        $configured_currencies_in_whmcs[$value["id"]] = $value["code"];
    }

    //for cost currency - the default one
    //$currencies[0]['code'] always the default currecy
    foreach ($certificates_and_prices as $key => $value) {
        $certificates_and_prices[$key]['Defaultcurrency']= $currencies[0]['code'];
    }

    //which product group selected by the user
    $selected_product_group = htmlspecialchars($_POST['selectedproductgroup']);

    //for prices with profit margin
    $certificates_and_new_prices = array();

    //import (products) ssl certificates and prices
    if(isset($_POST['loadcertificates'])){
        $_SESSION['selectedproductgroup'] = $selected_product_group;
        //check of the product group is selected by user if not show a error message
        if(empty($_SESSION['selectedproductgroup'])){
            echo "<div class='errorbox'><strong><span class='title'>ERROR!</span></strong><br>Please select a product group</div><br>";
        }
        else{
            $smarty->assign('session-selected-product-group', $_SESSION["selectedproductgroup"]);
            $smarty->assign('certificates_and_prices', $certificates_and_prices);
            $smarty->assign('configured_currencies_in_whmcs', $configured_currencies_in_whmcs);
            $smarty->display(dirname(__FILE__).'/templates/step2.tpl');
        }

    } //end of if(isset($_POST['loadcertificates']))
    elseif(isset($_POST['addprofitmargin'])){
        $profit_margin = $_POST['profitmargin'];
        if(!empty($profit_margin)){

            //call a function to calculate profit margin
            $certificates_and_new_prices = calculate_profitmargin($certificates_and_prices, $profit_margin);

            $smarty->assign('session-selected-product-group', $_SESSION["selectedproductgroup"]);
            $smarty->assign('certificates_and_prices', $certificates_and_new_prices);
            $smarty->assign('configured_currencies_in_whmcs', $configured_currencies_in_whmcs);
            $smarty->display(dirname(__FILE__).'/templates/step2.tpl');

        }
        else{

            // $smarty->assign('selected_product_group', $selected_product_group);
            $smarty->assign('session-selected-product-group', $_SESSION["selectedproductgroup"]);
            $smarty->assign('certificates_and_prices', $certificates_and_prices);
            $smarty->assign('configured_currencies_in_whmcs', $configured_currencies_in_whmcs);
            $smarty->display(dirname(__FILE__).'/templates/step2.tpl');
        }

    }
    elseif(isset($_POST['import'])){

        $smarty->assign('session-selected-product-group', $_SESSION["selectedproductgroup"]);
        $smarty->assign('certificates_and_prices', $certificates_and_prices);
        $smarty->assign('configured_currencies_in_whmcs', $configured_currencies_in_whmcs);
        //to display checked items even after button click
        $smarty->assign('post-checkbox-certificate', $_POST['checkboxcertificate']);
        //to diplay success message
        $smarty->assign('post-import', $_POST['import']);

        $smarty->display(dirname(__FILE__).'/templates/step2.tpl');

        //to collect new prices, certificated and (new) currency
        import_button();

    }
    else{
        $smarty->display(dirname(__FILE__).'/templates/step1.tpl');
    }

}//end of ispapissl_addon_output()

//helping functions
function array_keys_to_lowerCase(&$array)
{
    $array=array_change_key_case($array,CASE_LOWER);
    $array=array_combine(array_map(function($str){return ucwords(str_replace("_"," ",$str));},array_keys($array)),array_values($array));
    foreach($array as $key=>$val)
    {
        if(is_array($val)) array_keys_to_lowerCase($array[$key]);
    }
}
//calculate profit margin of the product price
function calculate_profitmargin($certificates_and_prices, $profit_margin){

    //prices with profit margin
    $certificates_and_new_prices = array();
    $certificates_and_new_prices = $certificates_and_prices;

    foreach ($certificates_and_prices as $certificate => $price_defaultcurrency) {
        $percentage_of_price = ($profit_margin/100) * $price_defaultcurrency['price'];
        $new_price = $price_defaultcurrency['price'] + $percentage_of_price;
        $certificates_and_new_prices[$certificate]['newprice'] = $new_price;
    }

    return $certificates_and_new_prices;
}

// prepare an array from POST values with certificates and the new price for importing
function import_button(){

    // load all the ISPAPI registrars
    $ispapi_registrars = new LoadRegistrars();
    $_SESSION["ispapi_registrar"] = $ispapi_registrars->getLoadedRegistars();

// TODO : here already store account details and also entity

    $selected_product_group = $_POST['SelectedProductGroup'];
    //
    $certificate_match_pattern = "/(.*)_saleprice/";

    $certificates_and_new_prices = []; //will have all the certificates which have new prices
    foreach($_POST as $key=>$value){
        if(preg_match($certificate_match_pattern,$key,$match)){
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
    foreach($_POST as $key=>$value){
        if(preg_match($currency_pattern, $key)){
          $currencies['currency'] = $value;
        }
    }
    //to merge each curreny value from currencies array certificates_and_new_prices
    $i = -1;
    foreach($certificates_and_new_prices as $key=>$value){
        $i++;
        $certificates_and_new_prices[$key]['currency'] = $currencies['currency'][$i];
    }
    // POST value automatically replace space with _. therefore I have to call this function again
    array_keys_to_lowerCase($certificates_and_new_prices);

    // import only checked certificates - unset/remove all other certificates from $certificates_and_new_prices
    foreach($certificates_and_new_prices as $key => $val)
    {
        if(array_search($key, $_POST['checkbox-certificate']) === false)
        {
            unset($certificates_and_new_prices[$key]);
        }
    }
    //import certificates and new prices
    importproducts($certificates_and_new_prices, $selected_product_group);
}

function importproducts($certificates_and_prices, $selected_product_group) {

    //get the id of selected product group
    $product_group_id = Helper::SQLCall("SELECT id FROM tblproductgroups WHERE name=? LIMIT 1", array($selected_product_group), "fetch");

    foreach ($certificates_and_prices as $ssl_certificate => $price) {

        if($ssl_certificate == 'Comodo Essentialssl'){ //delete TODO
        // check if the product already exists under the selected product group
        $data_tblproducts = Helper::SQLCall("SELECT * FROM tblproducts WHERE name=? AND gid=?", array($ssl_certificate, $product_group_id['id']), "fetch");

        if(empty($data_tblproducts)){
            // insert the product if it does not exists
            $insert_stmt = Helper::SQLCall("INSERT INTO tblproducts (type, gid, name, paytype, autosetup, servertype, configoption1, configoption2, configoption3) VALUES ('other', ?, ?, 'recurring', 'payment', ?, ?, ?, '1')", array($product_group_id['id'], $ssl_certificate, $price['Servertype'], $price['Certificateclass'], $price['Registrar']), "execute");
            //ID of the inserted product to insert/update pricing (relid in tblpricing)
            //product_group_id = relid
            $product_id = Helper::SQLCall("SELECT id FROM tblproducts WHERE name=? AND gid=? LIMIT 1", array($ssl_certificate, $product_group_id['id']), "fetch");

            $insert_stmt = Helper::SQLCall("INSERT INTO tblpricing (type, currency, relid, msetupfee, qsetupfee, ssetupfee, asetupfee, bsetupfee, tsetupfee, monthly, quarterly, semiannually, annually, biennially, triennially) VALUES ('product', ?, ?, '0', '0', '0', '0', '0', '0', '-1', '-1', '-1', ?, '-1', '-1')", array($price['Currency'],$product_id['id'], $price['Newprice']), "execute");
        }else{//end of if(empty($data)){
            // the product exists then with which currency - there is possibility to store price of a product with as many currency as possible (if currencies configured in WHMCS)
            $data_tblpricing = Helper::SQLCall("SELECT * FROM tblpricing WHERE relid=?", array($data_tblproducts['id']), "fetchall");
            //if the currency exists in the $data_tblpricing then update it with new price
            if(in_array($price['Currency'], array_column($data_tblpricing, 'currency'))) { // search value in the array
                $update_stmt = Helper::SQLCall("UPDATE tblpricing SET annually=? WHERE relid=? AND currency=?", array($price['Newprice'], $data_tblproducts['id'], $price['Currency']), "execute");
            }else{
                //if the currency does not exists, then insert it with new price with same relid
                $insert_stmt = Helper::SQLCall("INSERT INTO tblpricing (type, currency, relid, msetupfee, qsetupfee, ssetupfee, asetupfee, bsetupfee, tsetupfee, monthly, quarterly, semiannually, annually, biennially, triennially) VALUES ('product', ?, ?, '0', '0', '0', '0', '0', '0', '-1', '-1', '-1', ?, '-1', '-1')", array($price['Currency'],$product_id['id'], $price['Newprice']), "execute");
            }
        }//end of else if(empty($data)){
        }//delete this - testing IF condition TODO
    }//end foreach
}


// TODO:
// 1. in the table tblproducts => insert autosetup: payment and configoption3: 1  --> DONE
//2.recheck the flow of my code - are there any unnecessary lines I wrote
//3.number of years are not saved until the user clicks on save button under 'Module settings' => I think I should save default year 1 from here -DONE
//4.get the registrar from DB and save it to configoptions. If registrar module is not configured? what should be done
//5.am not able to buy certificate (no calls going to API and coming response) unless I click on 'save' button under 'Module Settings' ==> warum? -RESOLVED
    // needed to save year and autosetup already here in my code 
//6. Keeping lib folder in ssl provisioning module is ok?
//7.add profit only selected certificates
?>
