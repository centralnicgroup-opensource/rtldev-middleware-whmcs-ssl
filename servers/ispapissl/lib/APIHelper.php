<?php

namespace HEXONET\WHMCS\ISPAPI\SSL;

class APIHelper
{
    public static function createCertificate($certClass, $certYears)
    {
        $command = [
            'COMMAND' => 'CreateSSLCert',
            'ORDER' => 'CREATE',
            'SSLCERTCLASS' => $certClass,
            'PERIOD' => $certYears
        ];
        return self::getResponse($command);
    }

    public static function updateCertificate($orderId, $certClass, $certYears, $email)
    {
        $command = [
            'COMMAND' => 'CreateSSLCert',
            'ORDER' => 'UPDATE',
            'ORDERID' => $orderId,
            'SSLCERTCLASS' => $certClass,
            'PERIOD' => $certYears,
            'EMAIL' => $email
        ];
        return self::getResponse($command);
    }

    public static function replaceCertificate($orderId, $certClass, $certYears, $csr, $serverType, $domain, $contact)
    {
        $command = [
            'COMMAND' => 'CreateSSLCert',
            'ORDER' => 'REPLACE',
            'SSLCERTCLASS' => $certClass,
            'PERIOD' => $certYears,
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
            'COMMAND' => 'QueryOrderList', 'ORDERID' => $orderId];
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

    private static function getResponse($command)
    {
        $response = Ispapi::call($command);
        if ($response['CODE'] != 200) {
            throw new \Exception($response['CODE'] . ' ' . $response['DESCRIPTION']);
        }
        return $response['PROPERTY'];
    }
}
