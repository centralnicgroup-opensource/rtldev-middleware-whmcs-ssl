<?php

namespace HEXONET\WHMCS\ISPAPI\SSL;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class DBHelper
{
    /**
     * Create SSL configuration e-mail template if required
     */
    public static function createEmailTemplateIfNotExisting(): void
    {
        $exists = DB::table('tblemailtemplates')
            ->where('name', '=', 'SSL Certificate Configuration Required')
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

    /**
     * Check if order exists based on the given Service ID
     * @param int $serviceId
     * @return bool
     */
    public static function orderExists(int $serviceId): bool
    {
        return DB::table('tblsslorders')
            ->where('serviceid', '=', $serviceId)
            ->where('module', '=', 'ispapissl')
            ->exists();
    }

    /**
     * Get the Order ID for the given Service ID
     * @param int $serviceId
     * @return int
     */
    public static function getOrderId(int $serviceId): int
    {
        return DB::table('tblsslorders')
            ->where('serviceid', '=', $serviceId)
            ->where('module', '=', 'ispapissl')
            ->value('id');
    }

    /**
     * Get the order for the given Service ID
     * @param int $serviceId
     * @param int $addonId
     * @return Builder|mixed|null
     */
    public static function getOrder(int $serviceId, int $addonId)
    {
        return DB::table('tblsslorders')
            ->where('serviceid', '=', $serviceId)
            ->where('addon_id', '=', $addonId)
            ->where('module', '=', 'ispapissl')
            ->select(['id', 'remoteid', 'status'])
            ->first();
    }

    /**
     * Create an SSL order
     * @param int $userId
     * @param int $serviceId
     * @param int $orderId
     * @param string $certClass
     * @return int
     */
    public static function createOrder(int $userId, int $serviceId, int $orderId, string $certClass): int
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

    /**
     * Update an SSL order
     * @param int $serviceId
     * @param int $addonId
     * @param array<string, mixed> $data
     */
    public static function updateOrder(int $serviceId, int $addonId, array $data): void
    {
        DB::table('tblsslorders')
            ->where('serviceid', '=', $serviceId)
            ->where('addon_id', '=', $addonId)
            ->where('module', '=', 'ispapissl')
            ->update($data);
    }

    /**
     * Update the hosting package information
     * @param int $serviceId
     * @param array<string, mixed> $data
     */
    public static function updateHosting(int $serviceId, array $data): void
    {
        DB::table('tblhosting')
            ->where('id', '=', $serviceId)
            ->update($data);
    }

    /**
     * Get a list of product groups
     * @return Collection<string>
     */
    public static function getProductGroups(): Collection
    {
        $productGroups = DB::table('tblproductgroups')->pluck('name');
        if (empty($productGroups)) {
            DB::table('tblproductgroups')->insert(['name' => 'SSL Certificates']);
            return DB::table('tblproductgroups')->pluck('name');
        }
        return $productGroups;
    }

    /**
     * Get the product group ID based on its name
     * @param string $productGroupName
     * @return int
     */
    public static function getProductGroupId(string $productGroupName): int
    {
        return DB::table('tblproductgroups')
            ->where('name', '=', $productGroupName)
            ->value('id');
    }

    /**
     * Get the product ID based on the certificate class
     * @param string $certificateClass
     * @return int|null
     */
    public static function getProductId(string $certificateClass)
    {
        return DB::table('tblproducts')
            ->where('configoption1', '=', $certificateClass)
            ->where('servertype', '=', 'ispapissl')
            ->value('id');
    }

    /**
     * Get the product based on the certificate class
     * @param string $certificateClass
     * @return Builder|mixed|null
     */
    public static function getProduct(string $certificateClass)
    {
        return DB::table('tblproducts')
            ->where('configoption1', '=', $certificateClass)
            ->where('servertype', '=', 'ispapissl')
            ->first();
    }

    /**
     * Get currencies for a given product ID
     * @param int $productId
     * @return Collection<int>
     */
    public static function getProductCurrencies(int $productId): Collection
    {
        return DB::table('tblpricing')
            ->where('relid', '=', $productId)
            ->where('type', '=', 'product')
            ->pluck('currency');
    }

    /**
     * Get product pricing for a specific currency
     * @param int $productId
     * @param int $currencyId
     * @return float
     */
    public static function getProductPricing(int $productId, int $currencyId): float
    {
        return DB::table('tblpricing')
            ->where('relid', '=', $productId)
            ->where('type', '=', 'product')
            ->where('currency', '=', $currencyId)
            ->value('annually');
    }

    /**
     * Create a product
     * @param string $productName
     * @param int $productGroupId
     * @param string $certificateClass
     * @param string $autoSetup
     * @return int
     */
    public static function createProduct(string $productName, int $productGroupId, string $certificateClass, string $autoSetup): int
    {
        return DB::table('tblproducts')
            ->insertGetId([
                'type' => 'other',
                'gid' => $productGroupId,
                'name' => $productName,
                'paytype' => 'recurring',
                'autosetup' => $autoSetup,
                'servertype' => 'ispapissl',
                'configoption1' => $certificateClass,
                'tax' => 1
            ]);
    }

    /**
     * Update a product
     * @param int $productId
     * @param string $autoSetup
     * @return int
     */
    public static function updateProduct(int $productId, string $autoSetup): int
    {
        return DB::table('tblproducts')
            ->where('id', '=', $productId)
            ->update(['autosetup' => $autoSetup]);
    }

    /**
     * Update product pricing
     * @param int $productId
     * @param int $currency
     * @param float $price
     */
    public static function updatePricing(int $productId, int $currency, float $price): void
    {
        DB::table('tblpricing')
            ->where('relid', '=', $productId)
            ->where('type', '=', 'product')
            ->where('currency', '=', $currency)
            ->update(['annually' => $price]);
    }

    /**
     * Create product pricing
     * @param int $productId
     * @param int $currency
     * @param float $price
     */
    public static function createPricing(int $productId, int $currency, float $price): void
    {
        DB::table('tblpricing')
            ->insert([
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

    /**
     * Get default system currency
     * @return Builder|mixed|null
     */
    public static function getDefaultCurrency()
    {
        return DB::table('tblcurrencies')
            ->where('default', '=', 1)
            ->first();
    }

    /**
     * Get the language for the given administrator
     * @param int $adminId
     * @return string
     */
    public static function getAdminLanguage(int $adminId): string
    {
        return DB::table("tbladmins")
            ->where("id", '=', $adminId)
            ->value('language');
    }

    /**
     * Get the language for the given user
     * @param int $userId
     * @return string
     */
    public static function getUserLanguage(int $userId): string
    {
        return DB::table("tblclients")
            ->where("id", '=', $userId)
            ->value('language');
    }
}
