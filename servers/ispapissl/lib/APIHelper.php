<?php

namespace HEXONET\WHMCS\ISPAPI\SSL;

use WHMCS\Module\Registrar\Ispapi\Ispapi;
use HEXONET\ResponseParser as RP;

class APIHelper
{
    public static function getUserStatus()
    {
        $command = [
            'COMMAND' => 'StatusUser'
        ];
        return self::getResponse($command);
    }

    public static function createCertificate(string $certClass)
    {
        $command = [
            'COMMAND' => 'CreateSSLCert',
            'ORDER' => 'CREATE',
            'SSLCERTCLASS' => $certClass,
            'PERIOD' => 1
        ];
        return self::getResponse($command);
    }

    public static function replaceCertificate(int $orderId, string $certClass, string $csr, string $serverType, string $domain, array $contact)
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
        $command = array_merge($command, $contact);
        return self::getResponse($command);
    }

    public static function updateCertificate(int $orderId, string $certClass, string $email)
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

    public static function renewCertificate(int $orderId)
    {
        $order = self::getOrder($orderId);
        $command = [
            'COMMAND' => 'RenewSSLCert',
            'SSLCERTID' => $order['SSLCERTID']
        ];
        return self::getResponse($command);
    }

    public static function reissueCertificate(int $orderId, string $csr)
    {
        $order = self::getOrder($orderId);
        $command = [
            'COMMAND' => 'ReissueSSLCert',
            'SSLCERTID' => $order['SSLCERTID'],
            'CSR' => explode(PHP_EOL, $csr)
        ];
        return self::getResponse($command);
    }

    public static function revokeCertificate(int $orderId)
    {
        $order = self::getOrder($orderId);
        $command = [
            'COMMAND' => 'RevokeSSLCert',
            'SSLCERTID' => $order['SSLCERTID'],
            'REASON' => 'WHMCS'
        ];
        return self::getResponse($command);
    }

    public static function getOrder(int $orderId)
    {
        $command = [
            'COMMAND' => 'QueryOrderList',
            'ORDERID' => $orderId
        ];
        $response = self::getResponse($command);

        $sslCertId = 0;
        if (isset($response['LASTRESPONSE'][0])) {
            $lastResponse = RP::parse(urldecode($response['LASTRESPONSE'][0]));
            $sslCertId = $lastResponse['PROPERTY']['SSLCERTID'][0];
        }
        $response['SSLCERTID'] = $sslCertId;

        $orderCommand = [];
        if (isset($response['ORDERCOMMAND'][0])) {
            $orderCommand = RP::parse(urldecode($response['ORDERCOMMAND'][0]));
        }
        $response['COMMAND'] = $orderCommand;

        return $response;
    }

    public static function executeOrder(int $orderId)
    {
        $command = [
            'COMMAND' => 'ExecuteOrder',
            'ORDERID' => $orderId
        ];
        return self::getResponse($command);
    }

    public static function parseCSR(string $csr)
    {
        $command = [
            'COMMAND' => 'ParseSSLCertCSR',
            'CSR' => explode(PHP_EOL, $csr)
        ];
        return self::getResponse($command);
    }

    public static function getCertStatus(int $certId)
    {
        $command = [
            'COMMAND' => 'StatusSSLCert',
            'SSLCERTID' => $certId
        ];
        return self::getResponse($command);
    }

    public static function getCertEmail(int $certId)
    {
        $command = [
            'COMMAND' => 'QuerySSLCertDCVEmailAddressList',
            'SSLCERTID' => $certId
        ];
        return self::getResponse($command);
    }

    public static function getEmailAddress(string $certClass, string $csr)
    {
        $command = [
            'COMMAND' => 'QuerySSLCertDCVEmailAddressList',
            'SSLCERTCLASS' => $certClass,
            'CSR' => explode(PHP_EOL, $csr)
        ];
        return self::getResponse($command);
    }

    public static function getValidationAddresses(string $certClass, string $domain)
    {
        $command = [
            'COMMAND' => 'QuerySSLCertDCVEMailAddressList',
            'SSLCERTCLASS' => $certClass,
            'DOMAIN' => $domain
        ];
        return self::getResponse($command);
    }

    public static function resendEmail(int $certId, string $email)
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

    private static function getResponse(array $command)
    {
        $response = Ispapi::call($command);
        if ($response['CODE'] != 200) {
            throw new \Exception($response['CODE'] . ' ' . $response['DESCRIPTION']);
        }
        return $response['PROPERTY'];
    }
}
