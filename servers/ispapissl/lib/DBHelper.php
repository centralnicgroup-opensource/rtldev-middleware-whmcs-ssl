<?php

namespace HEXONET\WHMCS\ISPAPI\SSL;

use Illuminate\Database\Capsule\Manager as DB;

class DBHelper
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

    public static function orderExists(int $serviceId)
    {
        return DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->where("module", "ispapissl")
            ->exists();
    }

    public static function getOrderId(int $serviceId)
    {
        return DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->where("module", "ispapissl")
            ->value('id');
    }

    public static function getOrder(int $serviceId, int $addonId)
    {
        return DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->where("addon_id", $addonId)
            ->where("module", "ispapissl")
            ->select(['id', 'remoteid', 'status'])
            ->first();
    }

    public static function createOrder(int $userId, int $serviceId, int $orderId, string $certClass)
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

    public static function updateOrder(int $serviceId, int $addonId, array $data)
    {
        DB::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->where("addon_id", $addonId)
            ->where("module", "ispapissl")
            ->update($data);
    }

    public static function updateHosting(int $serviceId, array $data)
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

    public static function getProductGroupId(string $productGroupName)
    {
        return DB::table('tblproductgroups')->where('name', $productGroupName)->value('id');
    }

    public static function getProductId(string $certificateClass, int $gid)
    {
        return DB::table('tblproducts')
            ->where('configoption1', $certificateClass)
            ->where('gid', $gid)
            ->value('id');
    }

    public static function getProductCurrencies(int $productId)
    {
        return DB::table('tblpricing')
            ->where('relid', $productId)
            ->where('type', 'product')
            ->pluck('currency');
    }

    public static function createProduct(string $productName, int $productGroupId, string $certificateClass)
    {
        return DB::table('tblproducts')->insertGetId([
            'type' => 'other',
            'gid' => $productGroupId,
            'name' => $productName,
            'paytype' => 'recurring',
            'autosetup' => 'payment',
            'servertype' => 'ispapissl',
            'configoption1' => $certificateClass,
            'tax' => 1
        ]);
    }

    public static function updatePricing(int $productId, int $currency, float $price)
    {
        DB::table('tblpricing')
            ->where('relid', $productId)
            ->where('currency', $currency)
            ->update(['annually' => $price]);
    }

    public static function createPricing(int $productId, int $currency, float $price)
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
            'monthly' => -1,
            'quarterly' => -1,
            'semiannually' => -1,
            'annually' => $price,
            'biennially' => -1,
            'triennially' => -1
        ]);
    }

    public static function getDefaultCurrency()
    {
        return DB::table('tblcurrencies')
            ->where('default', 1)
            ->value('code');
    }

    public static function getAdminLanguage($adminId)
    {
        return DB::table("tbladmins")
            ->where("id", $adminId)
            ->value('language');
    }

    public static function getUserLanguage($userId)
    {
        return DB::table("tblclients")
            ->where("id", $userId)
            ->value('language');
    }
}
