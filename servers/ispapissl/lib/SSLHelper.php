<?php

namespace HEXONET\WHMCS\ISPAPI\SSL;

use Illuminate\Database\Capsule\Manager as DB;
use WHMCS\Module\Registrar\Ispapi\LoadRegistrars;

class SSLHelper
{
    public static function createEmailTemplateIfNotExisting()
    {
        $exists = DB::table('tblemailtemplates')
            ->where('name', 'SSL Certificate Configuration Required')
            ->exists();
        if (!$exists) {
            DB::table('tblemailtemplates')->insert([
                'type' => 'product',
                'name' => 'SSL Certificate Configuration Required',
                'subject' => 'SSL Certificate Configuration Required',
                'message' => '<p>Dear {\$client_name},</p><p>Thank you for your order for an SSL Certificate. Before you can use your certificate, it requires configuration which can be done at the URL below.</p><p>{\$ssl_configuration_link}</p><p>Instructions are provided throughout the process but if you experience any problems or have any questions, please open a ticket for assistance.</p><p>{\$signature}</p>'
            ]);
        }
    }

    public static function sendConfigurationEmail($serviceId, $sslOrderId)
    {
        global $CONFIG;
        $sslconfigurationlink = $CONFIG['SystemURL'] . '/configuressl.php?cert=' . md5($sslOrderId);

        $sslconfigurationlink = '<a href="' . $sslconfigurationlink . '">' . $sslconfigurationlink . '</a>';
        $postData = [
            'messagename' => 'SSL Certificate Configuration Required',
            'id' => $serviceId,
            'customvars' => base64_encode(serialize(["ssl_configuration_link" => $sslconfigurationlink])),
        ];
        localAPI('SendEmail', $postData);
    }

    public static function orderExists($serviceId)
    {
        return DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->exists();
    }

    public static function getOrderId($serviceId)
    {
        return DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->value('id');
    }

    public static function getOrder($serviceId)
    {
        return DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->select(['id', 'remoteid', 'status'])
            ->first();
    }

    public static function createOrder($userId, $serviceId, $orderId, $certClass)
    {
        return DB::table('tblsslorders')->insertGetId([
            'userid' => $userId,
            'serviceid' => $serviceId,
            'remoteid' => $orderId,
            'module' => 'ispapissl',
            'certtype' => $certClass,
            'status' => 'Awaiting Configuration'
        ]);
    }

    public static function updateOrder($serviceId, $data)
    {
        DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->update($data);
    }

    public static function updateHosting($serviceId, $data)
    {
        DB::table('tblhosting')
            ->where('id', $serviceId)
            ->update($data);
    }

    public static function getProductGroups()
    {
        $productGroups = DB::table('tblproductgroups')->pluck('name');
        if (empty($productGroups)) {
            DB::table('tblproductgroups')->insert(['name' => 'SSL Certificates']);
            return ['SSL Certificates'];
        }
        return $productGroups;
    }

    public static function getProductGroupId($productGroupName)
    {
        return DB::table('tblproductgroups')->where('name', $productGroupName)->value('id');
    }

    public static function getProductId($productName, $gid, $regPeriod)
    {
        return DB::table('tblproducts')
            ->where('name', $productName)
            ->where('gid', $gid)
            ->where('configoption3', $regPeriod)
            ->value('id');
    }

    public static function getProductCurrencies($productId)
    {
        return DB::table('tblpricing')
            ->where('relid', $productId)
            ->where('type', 'product')
            ->pluck('currency');
    }

    public static function createProduct($productName, $productGroupId, $serverType, $certificateClass, $registrar, $regPeriod)
    {
        return DB::table('tblproducts')->insertGetId([
            'type' => 'other',
            'gid' => $productGroupId,
            'name' => $productName,
            'paytype' => 'onetime',
            'autosetup' => 'payment',
            'servertype' => $serverType,
            'configoption1' => $certificateClass,
            'configoption2' => $registrar,
            'configoption3' => $regPeriod
        ]);
    }

    public static function updatePricing($productId, $currency, $price)
    {
        DB::table('tblpricing')
            ->where('relid', $productId)
            ->where('currency', $currency)
            ->update(['monthly' => $price]);
    }

    public static function createPricing($productId, $currency, $price)
    {
        DB::table('tblpricing')->insert([
            'type' => 'product',
            'currency' => $currency,
            'relid' => $productId,
            'msetupfee' => 0,
            'qsetupfee' => 0,
            'ssetupfee' => 0,
            'asetupfee' => 0,
            'bsetupfee' => 0,
            'tsetupfee' => 0,
            'monthly' => $price,
            'quarterly' => -1,
            'semiannually' => -1,
            'annually' => -1,
            'biennially' => -1,
            'triennially' => -1
        ]);
    }

    public static function parseDomain($domain)
    {
        if (count($domain) == 2) {
            $domain = implode('.', $domain);
        } else {
            $tld = array_pop($domain);
            $sld = array_pop($domain);
            $dom = array_pop($domain);

            if (preg_match('/^([a-z][a-z]|com|net|org|biz|info)$/i', $sld)) {
                $domain = $dom . '.' . $sld . '.' . $tld;
            } else {
                $domain = $sld . '.' . $tld;
            }
        }
        return $domain;
    }

    public static function formatArrayKeys(&$array)
    {
        $array = array_change_key_case($array, CASE_LOWER);
        $array = array_combine(array_map(function ($str) {
            return ucwords(str_replace("_", " ", $str));
        }, array_keys($array)), array_values($array));
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                self::formatArrayKeys($array[$key]);
            }
        }
    }

    public static function calculateProfitMargin($products, $profitMargin)
    {
        foreach ($products as $certificate => $price) {
            $newPrice = $price['Newprice'] + ($profitMargin / 100) * $price['Newprice'];
            $products[$certificate]['Newprice'] = number_format((float)$newPrice, 2, '.', '');
        }
        return $products;
    }

    public static function calculateRegistrationPrice($products, $regPeriod)
    {
        foreach ($products as $certificate => $price) {
            $newPrice = number_format((float) $regPeriod * $price['Price'], 2, '.', '');
            $products[$certificate]['Price'] = $newPrice;
            $products[$certificate]['Newprice'] = $newPrice;
        }
        return $products;
    }

    public static function importProducts()
    {
        $registrars = new LoadRegistrars();
        $_SESSION["ispapi_registrar"] = $registrars->getLoadedRegistars();

        $products = [];
        $currencies = [];
        foreach ($_POST as $key => $value) {
            if (preg_match("/(.*)_saleprice/", $key, $match)) {
                $products[$match[1]]['newprice'] = $value;
                $products[$match[1]]['certificateClass'] = strtoupper($match[1]);
                $products[$match[1]]['servertype'] = 'ispapissl';
                $products[$match[1]]['registrar'] = $_SESSION["ispapi_registrar"][0];
            } elseif (preg_match("/currency/", $key)) {
                $currencies[] = $value;
            }
        }

        $i = 0;
        self::formatArrayKeys($products);
        foreach ($products as $key => $val) {
            if (array_search($key, $_POST['checkboxcertificate']) === false) {
                unset($products[$key]);
            } else {
                $products[$key]['currency'] = $currencies[$i];
            }
            $i++;
        }

        $productGroupName = $_POST['SelectedProductGroup'];
        $productGroupId = self::getProductGroupId($productGroupName);
        $regPeriod = ($_POST['registrationperiod'] == 1) ? 1 : 2;
        foreach ($products as $productName => $price) {
            $productName .= " - {$regPeriod} Year";
            $productId = self::getProductId($productName, $productGroupId, $regPeriod);
            if (!$productId) {
                $productId = self::createProduct($productName, $productGroupId, $price['Servertype'], $price['Certificateclass'], $price['Registrar'], $regPeriod);
            } else {
                $currencies = self::getProductCurrencies($productId);
                if (in_array($price['Currency'], $currencies)) {
                    self::updatePricing($productId, $price['Currency'], $price['Newprice']);
                    continue;
                }
            }
            self::createPricing($productId, $price['Currency'], $price['Newprice']);
        }
    }
}
