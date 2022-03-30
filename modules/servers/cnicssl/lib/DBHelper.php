<?php

namespace CNIC\WHMCS\SSL;

use Exception;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;
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
            ->where('module', '=', 'cnicssl')
            ->exists();
    }

    /**
     * Get the Order ID for the given Service ID
     * @param int $serviceId
     * @return int|null
     */
    public static function getOrderId(int $serviceId): ?int
    {
        return DB::table('tblsslorders')
            ->where('serviceid', '=', $serviceId)
            ->where('module', '=', 'cnicssl')
            ->value('id');
    }

    /**
     * Get the order for the given Service ID
     * @param int $serviceId
     * @param int $addonId
     * @return Builder|Model|object|null
     */
    public static function getOrder(int $serviceId, int $addonId)
    {
        return DB::table('tblsslorders')
            ->where('serviceid', '=', $serviceId)
            ->where('addon_id', '=', $addonId)
            ->where('module', '=', 'cnicssl')
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
            'module' => 'cnicssl',
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
            ->where('module', '=', 'cnicssl')
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
     * Get the product group ID based on its name
     * @param string $productGroupName
     * @return int|null
     */
    public static function getProductGroupId(string $productGroupName): ?int
    {
        return DB::table('tblproductgroups')
            ->where('name', 'LIKE', "%$productGroupName%")
            ->value('id');
    }

    /**
     * Get default product group for SSL products
     * @return int
     */
    public static function getDefaultProductGroup(): int
    {
        $productGroupId = DB::table('tblproducts')
            ->where('servertype', '=', "cnicssl")
            ->value('gid');
        if ($productGroupId) {
            return $productGroupId;
        }

        $ts = date('Y-m-d H:i:s');
        $groupOrder = DB::table('tblproductgroups')->max('order') + 1;

        return DB::table('tblproductgroups')
            ->insertGetId([
                'name' => 'SSL Certificates',
                'slug' => 'ssl-certificates',
                'orderfrmtpl' => 'supreme_comparison',
                'order' => $groupOrder,
                'created_at' => $ts,
                'updated_at' => $ts
            ]);
    }

    /**
     * Create product group based on provider info
     * @param array<string, mixed> $provider
     * @return int
     */
    public static function createProductGroup(array $provider): int
    {
        $ts = date('Y-m-d H:i:s');
        $groupOrder = DB::table('tblproductgroups')->max('order') + 1;

        $productGroupId = DB::table('tblproductgroups')
            ->insertGetId([
                'name' => $provider['name'] . ' SSL Certificates',
                'slug' => strtolower($provider['name'] . '-ssl'),
                'headline' => $provider['headline'],
                'tagline' => $provider['tagline'],
                'orderfrmtpl' => 'supreme_comparison',
                'order' => $groupOrder,
                'created_at' => $ts,
                'updated_at' => $ts
            ]);

        self::setProductGroupFeatures($productGroupId, $provider['features']);

        return $productGroupId;
    }

    /**
     * Update product group based on provider info
     * @param int $productGroupId
     * @param array<string, mixed> $provider
     */
    public static function updateProductGroup(int $productGroupId, array $provider): void
    {
        DB::table('tblproductgroups')
            ->where('id', '=', $productGroupId)
            ->update([
                'name' => $provider['name'] . ' SSL Certificates',
                'slug' => strtolower($provider['name'] . '-ssl'),
                'headline' => $provider['headline'],
                'tagline' => $provider['tagline'],
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        self::setProductGroupFeatures($productGroupId, $provider['features']);
    }

    /**
     * @param int $productGroupId
     * @param array<string, string> $features
     */
    private static function setProductGroupFeatures(int $productGroupId, array $features): void
    {
        $ts = date('Y-m-d H:i:s');
        $featureOrder = 1;

        DB::table('tblproduct_group_features')
            ->where('product_group_id', '=', $productGroupId)
            ->delete();

        foreach ($features as $feature) {
            DB::table('tblproduct_group_features')
                ->insert([
                    'product_group_id' => $productGroupId,
                    'feature' => $feature,
                    'order' => $featureOrder++,
                    'created_at' => $ts,
                    'updated_at' => $ts
                ]);
        }
    }

    /**
     * Get the product ID based on the certificate class
     * @param string $certificateClass
     * @return int|null
     */
    public static function getProductId(string $certificateClass): ?int
    {
        return DB::table('tblproducts')
            ->where('configoption1', '=', $certificateClass)
            ->where('servertype', '=', 'cnicssl')
            ->value('id');
    }

    /**
     * Get the product based on the certificate class
     * @param string $certificateClass
     * @return Builder|Model|object|null
     */
    public static function getProduct(string $certificateClass)
    {
        return DB::table('tblproducts')
            ->where('configoption1', '=', $certificateClass)
            ->whereIn('servertype', ['cnicssl', 'ispapissl'])
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
     * @param string $productDescription
     * @param int $productGroupId
     * @param string $certificateClass
     * @param string $autoSetup
     * @return int
     */
    public static function createProduct(string $productName, string $productDescription, int $productGroupId, string $certificateClass, string $autoSetup): int
    {
        return DB::table('tblproducts')
            ->insertGetId([
                'type' => 'other',
                'gid' => $productGroupId,
                'name' => $productName,
                'description' => $productDescription,
                'paytype' => 'recurring',
                'autosetup' => $autoSetup,
                'servertype' => 'cnicssl',
                'configoption1' => $certificateClass,
                'tax' => 1
            ]);
    }

    /**
     * Update a product
     * @param int $productId
     * @param string $productName
     * @param string $productDescription
     * @param int $productGroupId
     * @param string $autoSetup
     * @return int
     */
    public static function updateProduct(int $productId, string $productName, string $productDescription, int $productGroupId, string $autoSetup): int
    {
        if ($productDescription) {
            return DB::table('tblproducts')
                ->where('id', '=', $productId)
                ->update(
                    [
                        'gid' => $productGroupId,
                        'autosetup' => $autoSetup,
                        'name' => $productName,
                        'description' => $productDescription,
                        'servertype' => 'cnicssl'
                    ]
                );
        }
        return DB::table('tblproducts')
            ->where('id', '=', $productId)
            ->update([
                'autosetup' => $autoSetup,
                'servertype' => 'cnicssl'
            ]);
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
     * @return Builder|Model|object
     * @throws Exception
     */
    public static function getDefaultCurrency()
    {
        $defaultCurrency = DB::table('tblcurrencies')
            ->where('default', '=', 1)
            ->first();
        if ($defaultCurrency == null) {
            throw new Exception("Unable to determine default currency");
        }
        return $defaultCurrency;
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
