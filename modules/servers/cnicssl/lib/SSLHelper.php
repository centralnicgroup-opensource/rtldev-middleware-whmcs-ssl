<?php

namespace CNIC\WHMCS\SSL;

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
     * @return array<string, mixed>|null
     */
    public static function getProviders(): ?array
    {
        $file = realpath(__DIR__ . '/../../../addons/cnicssl_addon/resources/providers.json');
        if ($file === false) {
            return null;
        }
        $json = file_get_contents($file);
        if ($json === false) {
            return null;
        }
        return json_decode($json, true);
    }

    /**
     * @param string $provider
     * @return array<string, mixed>|null
     */
    public static function getCertificates(string $provider): ?array
    {
        $file = realpath(__DIR__ . '/../../../addons/cnicssl_addon/resources/' . strtolower($provider) . '.json');
        if ($file === false) {
            return null;
        }
        $json = file_get_contents($file);
        if ($json === false) {
            return null;
        }
        return json_decode($json, true);
    }

    /**
     * @param float $price
     * @param string $currency
     * @param string $defaultCurrency
     * @param array<string, array<string, mixed>> $exchangeRates
     * @return float|null
     */
    public static function getPrice(float $price, string $currency, string $defaultCurrency, array $exchangeRates): ?float
    {
        $currencies = self::getCurrencies();
        $arrayKey = array_search($currency, array_column($currencies, 'code'));
        if ($arrayKey === false) {
            if (in_array($currency, $exchangeRates['CURRENCYFROM'])) {
                // Product currency is same as ISPAPI base currency
                $exchangeKey = array_search($defaultCurrency, $exchangeRates['CURRENCYTO']);
                if ($exchangeKey === false) {
                    return null;
                }
                $price = round($price * $exchangeRates['RATE'][$exchangeKey], 2);
            } else {
                // Convert to ISPAPI base currency
                $exchangeKey = array_search($currency, $exchangeRates['CURRENCYTO']);
                if ($exchangeKey === false) {
                    return null;
                }
                $price = round($price / $exchangeRates['RATE'][$exchangeKey], 2);
                if ($defaultCurrency != $exchangeRates['CURRENCYFROM'][$exchangeKey]) {
                    // Convert to WHMCS default currency
                    $exchangeKey = array_search($defaultCurrency, $exchangeRates['CURRENCYTO']);
                    $price = round($price * $exchangeRates['RATE'][$exchangeKey], 2);
                }
            }
        } else {
            $price = round($price / $currencies[$arrayKey]['rate'], 2);
        }
        return $price;
    }

    /**
     * Import the SSL products
     * @throws Exception
     */
    public static function importProducts(string $registrar): void
    {
        $currencies = self::getCurrencies();
        $providers = self::getProviders();
        $certs = self::getCertificates($registrar);
        $assetHelper = \DI::make("asset"); // @phpstan-ignore-line
        $webRoot = $assetHelper->getWebRoot();

        if ($providers === null || $certs === null) {
            return;
        }

        foreach ($_POST['SelectedCertificate'] as $certificateClass => $val) {
            $product = $certs[$certificateClass];
            $provider = $providers[$product["provider"]];
            $productName = $product['name'];
            $existingProduct = DBHelper::getProduct($certificateClass);

            $productDescription = '';
            if ($_POST['ProductDescriptions']) {
                $logo = $provider['logo'];
                $productDescription = '<img src="' . $webRoot . '/modules/addons/cnicssl_addon/logos/' . $logo . '" />';
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
                $productId = DBHelper::createProduct($registrar, $productName, $productDescription, $productGroupId, $certificateClass, $_POST['AutoSetup']);
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
        DBHelper::migrateOrders();
    }

    /**
     * Import the SSL Product Groups
     * @throws Exception
     */
    public static function importProductGroups(): void
    {
        $providers = self::getProviders();
        if ($providers === null) {
            return;
        }
        foreach ($providers as $name => $provider) {
            $productGroupId = DBHelper::getProductGroupId($name);
            if ($productGroupId) {
                DBHelper::updateProductGroup($productGroupId, $name, $provider);
            } else {
                DBHelper::createProductGroup($name, $provider);
            }
        }
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
        $data = file_get_contents(__DIR__ . '/../../../addons/cnicssl_addon/logo.png');
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

    /**
     * @param int $serverType
     * @return string
     */
    public static function getServerType(int $serverType): string
    {
        switch ($serverType) {
            case 1001:
                return "APACHESSL";
            case 1002:
                return "APACHESSLEAY";
            case 1013:
            case 1014:
                return "IIS";
            default:
                return "OTHER";
        }
    }

    /**
     * @param string $csr
     * @return array<string, string>
     * @throws Exception
     */
    public static function parseCSR(string $csr): array
    {
        $subject = openssl_csr_get_subject($csr);
        if ($subject === false) {
            throw new Exception("Invalid CSR");
        }
        return array_change_key_case($subject, CASE_UPPER);
    }

    /**
     * @param string $domain
     * @return array<string>
     */
    public static function getValidationEmails(string $domain): array
    {
        $domain = preg_replace("/^\*\./", '', $domain);
        $domainObj = new \Utopia\Domains\Domain($domain);
        $mainDomain = $domainObj->getRegisterable() ?: $domain;
        return [
            "admin@" . $mainDomain,
            "administrator@" . $mainDomain,
            "hostmaster@" . $mainDomain,
            "postmaster@" . $mainDomain,
            "webmaster@" . $mainDomain,
        ];
    }
}
