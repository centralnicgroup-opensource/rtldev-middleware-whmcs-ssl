<?php

namespace CNIC\WHMCS\SSL;

use CNIC\HEXONET\ResponseParser as RP;
use Exception;

class ISPAPI implements IRegistrar
{
    /**
     * @inheritDoc
     */
    public static function getProducts(): array
    {
        $defaultCurrency = DBHelper::getDefaultCurrency();
        $exchangeRates = self::getExchangeRates();

        $command = [
            'COMMAND' => 'StatusUser'
        ];
        $user = self::getResponse($command);

        $pattern = '/PRICE_CLASS_SSLCERT_(.*_.*)_ANNUAL1?$/';
        $products = [];
        $services = preg_grep($pattern, $user['RELATIONTYPE']);
        if (!is_array($services)) {
            return $products;
        }

        $certificates = SSLHelper::getCertificates("ispapi");

        foreach ($services as $key => $val) {
            preg_match($pattern, $val, $matches);
            $productKey = $matches[1];

            $currencyKey = array_search("PRICE_CLASS_SSLCERT_{$productKey}_CURRENCY", $user['RELATIONTYPE']);
            if ($currencyKey === false) {
                continue;
            }

            $price = SSLHelper::getPrice((float)$user['RELATIONVALUE'][$key], $user['RELATIONVALUE'][$currencyKey], $defaultCurrency->code, $exchangeRates);
            if ($price === null) {
                continue;
            }
            if (!isset($certificates[$productKey]) || $certificates[$productKey]["provider"] === null) {
                continue;
            }

            $products[(string)$productKey] = [
                'id' => 0,
                'Provider' => $certificates[$productKey]["provider"],
                'Name' => $certificates[$productKey]["name"],
                'Cost' => number_format($price, 2, '.', ''),
                'Price' => 0,
                'Margin' => 0,
                'AutoSetup' => false
            ];
        }
        return $products;
    }

    /**
     * @inheritDoc
     */
    public static function getExchangeRates(): array
    {
        $command = [
            'COMMAND' => 'QueryExchangeRates'
        ];
        return self::getResponse($command);
    }

    /**
     * Get order
     * @param string $orderId
     * @return array<string, mixed>
     * @throws Exception
     */
    private static function getOrder(string $orderId): array
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

        $csr = [];
        $i = 0;
        while (isset($response['COMMAND']['CSR' . $i])) {
            if (strlen($response['COMMAND']['CSR' . $i])) {
                $csr[] = $response['COMMAND']['CSR' . $i];
            }
            $i++;
        }
        $response['CSR'] = implode(PHP_EOL, $csr);

        return $response;
    }

    /**
     * @inheritDoc
     */
    public static function getCertStatus(string $certId): array
    {
        $command = [
            'COMMAND' => 'StatusSSLCert',
            'SSLCERTID' => $certId
        ];
        $status = self::getResponse($command);
        if (isset($status["VALIDATIONDNSRR"][0])) {
            $validation = explode(" ", $status["VALIDATIONDNSRR"][0]);
            $status["DNSTYPE"][0] = strtoupper($validation[1]);
            $status["DNSHOST"][0] = $validation[0];
            $status["DNSVALUE"][0] = $validation[2];
        }
        return $status;
    }

    /**
     * @inheritDoc
     */
    public static function createCertificate(string $certClass, array $contact, string $approvalMethod, string $email, string $serverType, string $csr): array
    {
        $command = [
            'COMMAND' => 'CreateSSLCert',
            'SSLCERTCLASS' => $certClass,
            'PERIOD' => 1,
            'CSR' => explode(PHP_EOL, $csr),
            'SERVERSOFTWARE' => $serverType
        ];
        foreach (['', 'ADMINCONTACT', 'TECHCONTACT', 'BILLINGCONTACT'] as $contactType) {
            $command[$contactType . 'ORGANIZATION'] = $contact['ORGANIZATION'];
            $command[$contactType . 'FIRSTNAME'] = $contact['FIRSTNAME'];
            $command[$contactType . 'LASTNAME'] = $contact['LASTNAME'];
            $command[$contactType . 'NAME'] = $contact['FIRSTNAME'] . ' ' . $contact['LASTNAME'];
            $command[$contactType . 'JOBTITLE'] = $contact['JOBTITLE'];
            $command[$contactType . 'EMAIL'] = $contact['EMAIL'];
            $command[$contactType . 'STREET'] = $contact['STREET'];
            $command[$contactType . 'CITY'] = $contact['CITY'];
            $command[$contactType . 'PROVINCE'] = $contact['PROVINCE'];
            $command[$contactType . 'ZIP'] = $contact['ZIP'];
            $command[$contactType . 'COUNTRY'] = $contact['COUNTRY'];
            $command[$contactType . 'PHONE'] = $contact['PHONE'];
            $command[$contactType . 'FAX'] = $contact['FAX'];
        }
        switch ($approvalMethod) {
            case 'dns-txt-token':
                $command["VALIDATION0"] = "DNSZONE";
//                $command["INTERNALDNS"] = 1;
                break;
            case 'file':
                $command["VALIDATION0"] = "URL";
                break;
            default:
                $command["EMAIL"] = $email;
        }

        $response = self::getResponse($command);

        return [
            "CERTID" => $response["SSLCERTID"][0],
            "FILEAUTH_NAME" => @$response["VALIDATIONURL"][0],
            "FILEAUTH_CONTENTS" => @$response["VALIDATIONURLCONTENT"][0],
            "DNSAUTH_NAME" => @$response["VALIDATIONDNSRR"][0],
        ];
    }

    /**
     * @inheritDoc
     */
    public static function renewCertificate(string $certId): array
    {
        $order = self::getOrder($certId);
        $command = [
            'COMMAND' => 'RenewSSLCert',
            'SSLCERTID' => $order['SSLCERTID']
        ];
        return self::getResponse($command);
    }

    /**
     * @inheritDoc
     */
    public static function reissueCertificate(string $certId, string $csr): array
    {
        $order = self::getOrder($certId);
        $command = [
            'COMMAND' => 'ReissueSSLCert',
            'SSLCERTID' => $order['SSLCERTID'],
            'CSR' => explode(PHP_EOL, $csr)
        ];
        return self::getResponse($command);
    }

    /**
     * @inheritDoc
     */
    public static function revokeCertificate(string $certId): array
    {
        $order = self::getOrder($certId);
        $command = [
            'COMMAND' => 'RevokeSSLCert',
            'SSLCERTID' => $order['SSLCERTID'],
            'REASON' => 'WHMCS'
        ];
        return self::getResponse($command);
    }

    /**
     * @inheritDoc
     */
    public static function resendEmail(string $certId, string $email): array
    {
        $command = [
            'COMMAND' => 'ResendSSLCertEmail',
            'SSLCERTID' => $certId,
            'EMAIL' => $email
        ];
        return self::getResponse($command);
    }

    /**
     * @inheritDoc
     */
    public static function changeValidationMethod(string $certId, string $method): array
    {
        return [];
    }

    /**
     * Make an API call and process the response
     * @param array<string, mixed> $command
     * @return array<string, mixed>
     * @throws Exception
     */
    private static function getResponse(array $command): array
    {
        if (!class_exists('\WHMCS\Module\Registrar\Ispapi\Ispapi')) {
            throw new Exception("The ISPAPI Registrar Module is required. Please install it and activate it.");
        }
        $response = \WHMCS\Module\Registrar\Ispapi\Ispapi::call($command);
        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'] . ' ' . $response['DESCRIPTION']);
        }
        return $response['PROPERTY'] ?? [];
    }
}
