<?php

namespace HEXONET\WHMCS\ISPAPI\SSL;

class SSLHelper
{
    public static function sendConfigurationEmail(int $serviceId, int $sslOrderId)
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

    public static function getProductName(string $certificateClass)
    {
        $certificateNames = [
            'COMODO_ESSENTIALSSL' => 'Sectigo Essential SSL',
            'COMODO_ESSENTIALSSL_WILDCARD' => 'Sectigo Essential SSL Wildcard',
            'COMODO_SSL_EV' => 'Sectigo EV SSL',
            'COMODO_INSTANTSSL' => 'Sectigo Instant SSL',
            'COMODO_INSTANTSSL_PREMIUM' => 'Sectigo Instant SSL Premium',
            'COMODO_POSITIVESSL' => 'Sectigo Positive SSL',
            'COMODO_PREMIUMSSL_WILDCARD' => 'Sectigo Premium SSL Wildcard',
            'COMODO_SSL' => 'Sectigo DV SSL',
            'COMODO_SSL_WILDCARD' => 'Sectigo DV SSL Wildcard',
            'GEOTRUST_QUICKSSL' => 'GeoTrust Quick SSL',
            'GEOTRUST_QUICKSSLPREMIUM' => 'GeoTrust Quick SSL Premium',
            'GEOTRUST_QUICKSSLPREMIUM_SAN' => 'GeoTrust Quick SSL Premium SAN Package',
            'GEOTRUST_RAPIDSSL' => 'GeoTrust Rapid SSL',
            'GEOTRUST_RAPIDSSL_WILDCARD' => 'GeoTrust Rapid SSL Wildcard',
            'GEOTRUST_TRUEBIZID' => 'GeoTrust True Business ID',
            'GEOTRUST_TRUEBIZID_SAN' => 'GeoTrust True Business ID SAN Package',
            'GEOTRUST_TRUEBIZID_EV' => 'GeoTrust True Business ID EV',
            'GEOTRUST_TRUEBIZID_EV_SAN' => 'GeoTrust True Business ID EV SAN Package',
            'GEOTRUST_TRUEBIZID_WILDCARD' => 'GeoTrust True Business ID Wildcard',
            'SYMANTEC_SECURESITE' => 'Symantec Secure Site',
            'SYMANTEC_SECURESITE_EV' => 'Symantec Secure Site EV',
            'SYMANTEC_SECURESITE_PRO' => 'Symantec Secure Site Pro',
            'SYMANTEC_SECURESITE_PRO_EV' => 'Symantec Secure Site Pro EV',
            'THAWTE_SSL123' => 'thawte SSL 123',
            'THAWTE_SSLWEBSERVER' => 'thawte SSL Webserver',
            'THAWTE_SSLWEBSERVER_EV' => 'thawte SSL Webserver EV',
            'THAWTE_SSLWEBSERVER_WILDCARD' => 'thawte SSL Webserver Wildcard',
            'TRUSTWAVE_DOMAINVETTEDSSL' => 'Trustwave Domain vetted SSL',
            'TRUSTWAVE_PREMIUMSSL' => 'Trustwave Premium SSL',
            'TRUSTWAVE_PREMIUMSSL_SAN' => 'Trustwave Premium SSL SAN Package',
            'TRUSTWAVE_PREMIUMSSL_EV' => 'Trustwave Premium SSL EV',
            'TRUSTWAVE_PREMIUMSSL_EV_SAN' => 'Trustwave Premium SSL EV SAN Package',
            'TRUSTWAVE_PREMIUMSSL_WILDCARD' => 'Trustwave Premium SSL Wildcard'
        ];
        if (isset($certificateNames[$certificateClass])) {
            return $certificateNames[$certificateClass];
        }

        $certificateName = str_replace('_', ' ', strtolower($certificateClass));
        $certificateName = str_replace('ssl', 'SSL', $certificateName);
        $certificateName = str_replace(' ev', ' EV', $certificateName);
        $certificateName = str_replace(' san', ' SAN', $certificateName);
        $certificateName = str_replace('domainvetted', 'Domain-Vetted ', $certificateName);
        return ucwords($certificateName);
    }

    public static function getProducts()
    {
        $user = APIHelper::getUserStatus();
        $currencies = self::getCurrencies();
        $defaultCurrency = DBHelper::getDefaultCurrency();
        $exchangeRates = APIHelper::getExchangeRates();

        $pattern = '/PRICE_CLASS_SSLCERT_(.*_.*)_ANNUAL$/';
        $products = [];
        $certs = preg_grep($pattern, $user['RELATIONTYPE']);
        if (!is_array($certs)) {
            return $products;
        }
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

            $products[$productKey] = [
                'id' => 0,
                'Name' => self::getProductName($productKey),
                'Cost' => number_format($price, 2),
                'Margin' => 0,
                'AutoSetup' => false
            ];

            $existingProduct = DBHelper::getProduct($productKey);
            if ($existingProduct) {
                $products[$productKey]['id'] = $existingProduct->id;
                $products[$productKey]['AutoSetup'] = $existingProduct->autosetup ? true : false;
                $products[$productKey]['Price'] = DBHelper::getProductPricing($existingProduct->id, $defaultCurrency->id);
                $products[$productKey]['Margin'] = round(((($products[$productKey]['Price'] / $products[$productKey]['Cost']) * 100) - 100), 2);
            }
        }
        return $products;
    }

    public static function importProducts()
    {
        $currencies = self::getCurrencies();
        $productGroupId = DBHelper::getProductGroupId($_POST['ProductGroup']);

        foreach ($_POST['SelectedCertificate'] as $certificateClass => $val) {
            $productName = self::getProductName($certificateClass);
            $productId = DBHelper::getProductId($certificateClass);
            if (!$productId) {
                $productId = DBHelper::createProduct($productName, $productGroupId, $certificateClass, $_POST['AutoSetup']);
            } else {
                DBHelper::updateProduct($productId, $_POST['AutoSetup']);
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

    public static function getCurrencies()
    {
        $currencies = localAPI('GetCurrencies', []);
        if ($currencies['result'] == 'success') {
            return $currencies['currencies']['currency'];
        }
        return [];
    }

    public static function getLogo()
    {
        $data = file_get_contents(__DIR__ . '/../../../addons/ispapissl_addon/logo.png');
        return 'data:image/png;base64,' . base64_encode($data);
    }

    public static function loadLanguage()
    {
        $language = isset($GLOBALS["CONFIG"]["Language"]) ? $GLOBALS["CONFIG"]["Language"] : 'english';
        if (isset($_SESSION["adminid"])) {
            $language = DBHelper::getAdminLanguage($_SESSION['adminid']);
        } elseif ($_SESSION["uid"]) {
            $language = DBHelper::getUserLanguage($_SESSION['uid']);
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
