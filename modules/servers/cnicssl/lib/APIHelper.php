<?php

namespace CNIC\WHMCS\SSL;

use Exception;

class APIHelper
{
    /**
     * Get user status
     * @param string $registrar
     * @return array<string, array<string, mixed>>
     * @throws Exception
     */
    public static function getProducts(string $registrar): array
    {
        if ($registrar === "RRPproxy") {
            $products = RRPproxy::getProducts();
        } else {
            $products = ISPAPI::getProducts();
        }

        $defaultCurrency = DBHelper::getDefaultCurrency();
        foreach ($products as $productKey => $product) {
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
     * Create certificate
     * @param string $registrar
     * @param int $serviceId
     * @param string $certClass
     * @param array<string, mixed> $contact
     * @param string $approvalMethod
     * @param string $email
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function createCertificate(string $registrar, int $serviceId, string $certClass, array $contact, string $approvalMethod, string $email): array
    {
        $configData = DBHelper::getOrderConfigData($serviceId);
        $serverType = SSLHelper::getServerType($configData['servertype']);
        if ($registrar === "RRPproxy") {
            return RRPproxy::createCertificate($certClass, $contact, $approvalMethod, $email, $serverType, $configData["csr"]);
        }
        return ISPAPI::createCertificate($certClass, $contact, $approvalMethod, $email, $serverType, $configData["csr"]);
    }

    /**
     * Renew certificate
     * @param string $registrar
     * @param string $certId
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function renewCertificate(string $registrar, string $certId): array
    {
        if ($registrar === "RRPproxy") {
            return RRPproxy::renewCertificate($certId);
        }
        return ISPAPI::renewCertificate($certId);
    }

    /**
     * Reissue certificate
     * @param string $registrar
     * @param string $certId
     * @param string $csr
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function reissueCertificate(string $registrar, string $certId, string $csr): array
    {
        if ($registrar === "RRPproxy") {
            return RRPproxy::reissueCertificate($certId, $csr);
        }
        return ISPAPI::reissueCertificate($certId, $csr);
    }

    /**
     * Revoke certificate
     * @param string $registrar
     * @param string $certId
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function revokeCertificate(string $registrar, string $certId): array
    {
        if ($registrar === "RRPproxy") {
            return RRPproxy::revokeCertificate($certId);
        }
        return ISPAPI::revokeCertificate($certId);
    }

    /**
     * Get certificate status
     * @param string $registrar
     * @param string $certId
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function getCertStatus(string $registrar, string $certId): array
    {
        if ($registrar === "RRPproxy") {
            return RRPproxy::getCertStatus($certId);
        }
        return ISPAPI::getCertStatus($certId);
    }

    /**
     * Get current exchange rates
     * @param string $registrar
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function getExchangeRates(string $registrar): array
    {
        if ($registrar === "RRPproxy") {
            return RRPproxy::getExchangeRates();
        }
        return ISPAPI::getExchangeRates();
    }

    /**
     * Resend validation e-mail
     * @param string $registrar
     * @param string $certId
     * @param string $approverEmail
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function resendEmail(string $registrar, string $certId, string $approverEmail): array
    {
        if ($registrar === "RRPproxy") {
            return RRPproxy::resendEmail($certId, $approverEmail);
        }
        return ISPAPI::resendEmail($certId, $approverEmail);
    }
}
