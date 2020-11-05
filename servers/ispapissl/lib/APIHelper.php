<?php

namespace HEXONET\WHMCS\ISPAPI\SSL;

use WHMCS\Module\Registrar\Ispapi\Ispapi;

class APIHelper
{
    public static function getUserStatus()
    {
        $command = [
            'COMMAND' => 'StatusUser'
        ];
        return self::getResponse($command);
    }

    public static function createCertificate($certClass)
    {
        $command = [
            'COMMAND' => 'CreateSSLCert',
            'ORDER' => 'CREATE',
            'SSLCERTCLASS' => $certClass,
            'PERIOD' => 1
        ];
        return self::getResponse($command);
    }

    public static function updateCertificate($orderId, $certClass, $email)
    {
        $command = [
            'COMMAND' => 'CreateSSLCert',
            'ORDER' => 'UPDATE',
            'ORDERID' => $orderId,
            'SSLCERTCLASS' => $certClass,
            'PERIOD' => 1,
            'EMAIL' => $email
        ];
        return self::getResponse($command);
    }

    public static function replaceCertificate($orderId, $certClass, $csr, $serverType, $domain, $contact)
    {
        $command = [
            'COMMAND' => 'CreateSSLCert',
            'ORDER' => 'REPLACE',
            'SSLCERTCLASS' => $certClass,
            'PERIOD' => 1,
            'ORDERID' => $orderId,
            'CSR' => explode(PHP_EOL, $csr),
            'SERVERSOFTWARE' => $serverType,
            'SSLCERTDOMAIN' => $domain
        ];
        array_push($command, $contact);
        return self::getResponse($command);
    }

    public static function getOrder($orderId)
    {
        $command = [
            'COMMAND' => 'QueryOrderList',
            'ORDERID' => $orderId
        ];
        return self::getResponse($command);
    }

    public static function executeOrder($orderId)
    {
        $command = [
            'COMMAND' => 'ExecuteOrder',
            'ORDERID' => $orderId
        ];
        return self::getResponse($command);
    }

    public static function parseCSR($csr)
    {
        $command = [
            'COMMAND' => 'ParseSSLCertCSR',
            'CSR' => explode(PHP_EOL, $csr)
        ];
        return self::getResponse($command);
    }

    public static function getCertStatus($certId)
    {
        $command = [
            'COMMAND' => 'StatusSSLCert',
            'SSLCERTID' => $certId
        ];
        return self::getResponse($command);
    }

    public static function getCertEmail($certId)
    {
        $command = [
            'COMMAND' => 'QuerySSLCertDCVEmailAddressList',
            'SSLCERTID' => $certId
        ];
        return self::getResponse($command);
    }

    public static function getEmailAddress($certClass, $csr)
    {
        $command = [
            'COMMAND' => 'QuerySSLCertDCVEmailAddressList',
            'SSLCERTCLASS' => $certClass,
            'CSR' => explode(PHP_EOL, $csr)
        ];
        return self::getResponse($command);
    }

    public static function resendEmail($certId, $email)
    {
        $command = [
            'COMMAND' => 'ResendSSLCertEmail',
            'SSLCERTID' => $certId,
            'EMAIL' => $email
        ];
        return self::getResponse($command);
    }

    public static function getExchangeRates()
    {
        $command = [
            'COMMAND' => 'QueryExchangeRates'
        ];
        return self::getResponse($command);
    }

    private static function getResponse($command)
    {
        $response = Ispapi::call($command);
        if ($response['CODE'] != 200) {
            throw new \Exception($response['CODE'] . ' ' . $response['DESCRIPTION']);
        }
        return $response['PROPERTY'];
    }
}
