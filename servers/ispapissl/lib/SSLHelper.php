<?php

namespace HEXONET\WHMCS\ISPAPI\SSL;

use Illuminate\Database\Capsule\Manager as DB;

class SSLHelper
{
    public static function CreateEmailTemplateIfNotExisting()
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

    public static function OrderExists($serviceId)
    {
        return DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->exists();
    }

    public static function GetOrderId($serviceId)
    {
        return DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->value('id');
    }

    public static function GetOrder($serviceId)
    {
        return DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->select(['id', 'remoteid', 'status'])
            ->first();
    }

    public static function CreateOrder($userId, $serviceId, $orderId, $certClass)
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

    public static function UpdateOrder($serviceId, $data)
    {
        DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->update($data);
    }

    public static function UpdateHosting($serviceId, $data)
    {
        DB::table('tblhosting')
            ->where('id', $serviceId)
            ->update($data);
    }

    public static function GetProductGroups()
    {
        $productGroups = DB::table('tblproductgroups')->pluck('name');
        if (empty($productGroups)) {
            DB::table('tblproductgroups')->insert(['name' => 'SSL Certificates']);
            return ['SSL Certificates'];
        }
        return $productGroups;
    }

    public static function GetProductGroupId($productGroupName)
    {
        return DB::table('tblproductgroups')->where('name', $productGroupName)->value('id');
    }

    public static function GetProductId($productName, $gid, $regPeriod)
    {
        return DB::table('tblproducts')
            ->where('name', $productName)
            ->where('gid', $gid)
            ->where('configoption3', $regPeriod)
            ->value('id');
    }

    public static function GetProductCurrencies($productId)
    {
        return DB::table('tblpricing')
            ->where('relid', $productId)
            ->where('type', 'product')
            ->pluck('currency');
    }

    public static function CreateProduct($productName, $productGroupId, $serverType, $certificateClass, $registrar, $regPeriod)
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
    
    public static function UpdatePricing($productId, $currency, $price)
    {
        DB::table('tblpricing')
            ->where('relid', $productId)
            ->where('currency', $currency)
            ->update(['monthly' => $price]);
    }

    public static function CreatePricing($productId, $currency, $price)
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
}
