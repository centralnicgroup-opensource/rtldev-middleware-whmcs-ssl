<?php

namespace HEXONET\WHMCS\ISPAPI\SSL;

use Exception;

class SSLHelper
{
    /**
     * Send SSL configuration e-mail to the customer
     * @param int $serviceId
     * @param int $sslOrderId
     */
    public static function sendConfigurationEmail(int $serviceId, int $sslOrderId): void
    {
        global $CONFIG;
        $sslconfigurationlink = $CONFIG['SystemURL'] . '/configuressl.php?cert=' . md5((string)$sslOrderId);

        $sslconfigurationlink = '<a href="' . $sslconfigurationlink . '">' . $sslconfigurationlink . '</a>';
        $postData = [
            'messagename' => 'SSL Certificate Configuration Required',
            'id' => $serviceId,
            'customvars' => base64_encode(serialize(["ssl_configuration_link" => $sslconfigurationlink])),
        ];
        localAPI('SendEmail', $postData);
    }

    /**
     * Get available SSL products from HEXONET
     * @return array<int|string, array<string, mixed>>
     * @throws Exception
     */
    public static function getProducts(): array
    {
        $user = APIHelper::getUserStatus();
        $currencies = self::getCurrencies();
        $defaultCurrency = DBHelper::getDefaultCurrency();
        $exchangeRates = APIHelper::getExchangeRates();

        $pattern = '/PRICE_CLASS_SSLCERT_(.*_.*)_ANNUAL1?$/';
        $products = [];
        $certs = preg_grep($pattern, $user['RELATIONTYPE']);
        if (!is_array($certs)) {
            return $products;
        }

        $file = file_get_contents(__DIR__ . '/../../../addons/ispapissl_addon/products.json');
        if ($file === false) {
            return $products;
        }
        $json = json_decode($file, true);
        $defs = array_column($json['certificates'], 'class');

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

            $defKey = array_search($productKey, $defs);
            if (!$defKey) {
                continue;
            }

            $products[$productKey] = [
                'id' => 0,
                'Provider' => $json['certificates'][$defKey]['provider'],
                'Name' => $json['certificates'][$defKey]['name'],
                'Cost' => number_format($price, 2),
                'Price' => 0,
                'Margin' => 0,
                'AutoSetup' => false
            ];

            $existingProduct = DBHelper::getProduct($productKey);
            if ($existingProduct) {
                $products[$productKey]['id'] = $existingProduct->id;
                $products[$productKey]['AutoSetup'] = (bool)$existingProduct->autosetup;
                $products[$productKey]['Price'] = DBHelper::getProductPricing($existingProduct->id, $defaultCurrency->id);
                $products[$productKey]['Margin'] = round(((($products[$productKey]['Price'] / $products[$productKey]['Cost']) * 100) - 100), 2);
            }
        }
        return $products;
    }

    /**
     * Import the SSL products
     * @throws Exception
     */
    public static function importProducts(): void
    {
        $currencies = self::getCurrencies();
        $json = self::loadDefinitionFile();
        $providers = array_column($json['providers'], 'name');
        $certs = array_column($json['certificates'], 'class');

        foreach ($_POST['SelectedCertificate'] as $certificateClass => $val) {
            $key = array_search($certificateClass, $certs);
            $product = $json['certificates'][$key];
            $productName = $product['name'];
            $existingProduct = DBHelper::getProduct($certificateClass);

            $productDescription = '';
            if ($_POST['ProductDescriptions']) {
                $providerKey = array_search($product['provider'], $providers);
                $logo = $json['providers'][$providerKey]['logo'];
                $productDescription = '<img src="modules/addons/ispapissl_addon/logos/' . $logo . '" />';
                if (!isset($product['features']['domains'])) {
                    $product['features']['domains'] = null;
                }
                foreach ($product['features'] as $featKey => $featVal) {
                    switch ($featKey) {
                        case 'type':
                            switch ($featVal) {
                                case 'DV':
                                    $productType = 'domain';
                                    break;
                                case 'EV':
                                    $productType = 'extended';
                                    break;
                                case 'OV':
                                    $productType = 'organization';
                                    break;
                                default:
                                    $productType = 'unknown';
                            }
                            $productDescription .= PHP_EOL . 'validation: ' . $productType;
                            break;
                        case 'wildcard':
                            $productDescription .= PHP_EOL . 'wildcard: ' . ($featVal ? 'included' : 'no');
                            break;
                        case 'domains':
                            $productDescription .= PHP_EOL . 'additional domains: ' . ($featVal ?? 'no');
                            break;
                        case 'subdomains':
                            $productDescription .= PHP_EOL . 'additional subdomains: ' . $featVal;
                            break;
                        default:
                            $productDescription .= PHP_EOL . $featKey . ': ' . $featVal;
                    }
                }
            }

            if (@$_POST['ProductGroups']) {
                $productGroupId = DBHelper::getProductGroupId($product['provider']);
            } else {
                $productGroupId = $existingProduct ? $existingProduct->gid : DBHelper::getDefaultProductGroup();
            }
            if (!$productGroupId) {
                throw new Exception("Product group ID could not be determined for {$product['provider']}");
            }

            if (!$existingProduct) {
                $productId = DBHelper::createProduct($productName, $productDescription, $productGroupId, $certificateClass, $_POST['AutoSetup']);
            } else {
                $productId = $existingProduct->id;
                DBHelper::updateProduct($productId, $productName, $productDescription, $productGroupId, $_POST['AutoSetup']);
            }

            $productPrices = [];
            foreach ($currencies as $currency) {
                $newPrice = round($_POST['NewPrice'][$certificateClass] * $currency['rate'], 2);
                if ($_POST['RoundAllCurrencies'] && !empty($_POST['Rounding'])) {
                    $whole = floor($newPrice);
                    $fraction = $newPrice - $whole;
                    $roundTo = $_POST['Rounding'];
                    $newPrice = $whole + $roundTo;
                    if ($fraction > $roundTo) {
                        $newPrice += 1;
                    }
                }
                $productPrices[$currency['id']] = $newPrice;
            }

            $productCurrencies = DBHelper::getProductCurrencies($productId);
            foreach ($productCurrencies as $currencyId) {
                if (array_search($currencyId, array_column($currencies, 'id')) === false) {
                    continue; // this should not happen, but just to be sure...
                }
                DBHelper::updatePricing($productId, $currencyId, $productPrices[$currencyId]);
            }
            foreach ($currencies as $currency) {
                if (in_array($currency['id'], $productCurrencies->toArray())) {
                    continue;
                }
                DBHelper::createPricing($productId, $currency['id'], $productPrices[$currency['id']]);
            }
        }
    }

    /**
     * Import the SSL Product Groups
     * @throws Exception
     */
    public static function importProductGroups(): void
    {
        $json = self::loadDefinitionFile();
        foreach ($json['providers'] as $provider) {
            $productGroupId = DBHelper::getProductGroupId($provider['name']);
            if ($productGroupId) {
                DBHelper::updateProductGroup($productGroupId, $provider);
            } else {
                DBHelper::createProductGroup($provider);
            }
        }
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function loadDefinitionFile(): array
    {
        $file = file_get_contents(__DIR__ . '/../../../addons/ispapissl_addon/products.json');
        if ($file === false) {
            throw new Exception("Unable to parse products definition file");
        }
        return json_decode($file, true);
    }

    /**
     * Get list of available currencies
     * @return array<int, array<string, mixed>>
     */
    public static function getCurrencies(): array
    {
        $currencies = localAPI('GetCurrencies', []);
        if ($currencies['result'] == 'success') {
            return $currencies['currencies']['currency'];
        }
        return [];
    }

    /**
     * Get the base64 encoded HEXONET logo
     * @return string
     */
    public static function getLogo(): string
    {
        $data = file_get_contents(__DIR__ . '/../../../addons/ispapissl_addon/logo.png');
        return $data ? 'data:image/png;base64,' . base64_encode($data) : '';
    }

    /**
     * Load appropriate language strings
     */
    public static function loadLanguage(): void
    {
        $language = $GLOBALS["CONFIG"]["Language"] ?? 'english';
        if ($_SESSION["uid"]) {
            $language = DBHelper::getUserLanguage($_SESSION['uid']);
        } elseif (isset($_SESSION["adminid"])) {
            $language = DBHelper::getAdminLanguage($_SESSION['adminid']);
        }

        $dir = realpath(__DIR__ . "/../lang");
        $englishFile = $dir . "/english.php";
        $languageFile = $dir . "/" . strtolower($language) . ".php";

        $_LANG = [];
        $translations = [];
        if (file_exists($languageFile)) {
            require $languageFile;
            $translations = $_LANG;
        }
        if (file_exists($englishFile)) {
            require $englishFile;
            $translations += $_LANG;
        }
        $GLOBALS['_LANG'] += $translations;
    }
}
