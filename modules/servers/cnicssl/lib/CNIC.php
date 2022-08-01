<?php

namespace CNIC\WHMCS\SSL;

use Exception;

class CNIC implements IRegistrar
{
    /**
     * @return array<string, array<string, mixed>>
     * @throws Exception
     */
    public static function getProducts(): array
    {
        $defaultCurrency = DBHelper::getDefaultCurrency();
        $exchangeRates = self::getExchangeRates();

        $products = [];
        $services = self::getResponse("QueryServiceList", [
            "SERVICE" => "certificate",
            "INACTIVE" => 0
        ]);

        $certificates = SSLHelper::getCertificates("cnic");

        foreach ($services["TYPE"] as $n => $productKey) {
            if ($services["DEPENDENCY"][$n]) {
                continue;
            }
            $price = SSLHelper::getPrice((float)$services["ANNUAL"][$n], $services["CURRENCY"][$n], $defaultCurrency->code, $exchangeRates);
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

        uasort($products, function ($a, $b) {
            return [$a['Provider'], $a['Name']] <=> [$b['Provider'], $b['Name']];
        });

        return $products;
    }

    /**
     * Get current exchange rates
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function getExchangeRates(): array
    {
        return self::getResponse("QueryExchangeRates");
    }

    /**
     * @inheritDoc
     */
    public static function getCertStatus(string $certId): array
    {
        $command = [
            'CERTIFICATE' => $certId
        ];
        $status = self::getResponse("StatusCertificate", $command);

        $status["VALIDATIONEMAIL"][0] = $status["APPROVEREMAIL"][0];
        $status["REGISTRATIONEXPIRATIONDATE"][0] = $status["CERTIFICATEEXPIRATIONDATE"][0];
        $status["ORDERID"][0] = $status["CERTIFICATE"][0];
        $csr = SSLHelper::parseCSR(implode(PHP_EOL, $status['CSR']));
        $status["SSLCERTCN"][0] = $csr['CN'];
        $status["SERVERSOFTWARE"][0] = $status["WEBSERVERTYPE"][0];
        $status["VALIDATION"][0] = $status["AUTHMETHOD"][0];
        if (isset($status["DNSAUTHNAME"][0])) {
            $validation = explode(" ", $status["DNSAUTHNAME"][0]);
            $status["DNSTYPE"][0] = strtoupper($validation[1]);
            $status["DNSHOST"][0] = $validation[0];
            $status["DNSVALUE"][0] = $validation[2];
        }

        $contacts = [
            "owner" => self::getContact($status["OWNERCONTACT"][0])
        ];
        $contacts["admin"] = $contacts["owner"];
        $contacts["tech"] = $contacts["owner"];
        $contacts["billing"] = $contacts["owner"];
        if ($status["ADMINCONTACT"][0] != $status["OWNERCONTACT"][0]) {
            $contacts["admin"] = self::getContact($status["ADMINCONTACT"][0]);
        }
        if ($status["TECHCONTACT"][0] != $status["OWNERCONTACT"][0]) {
            $contacts["tech"] = self::getContact($status["TECHCONTACT"][0]);
        }
        if ($status["BILLINGCONTACT"][0] != $status["OWNERCONTACT"][0]) {
            $contacts["billing"] = self::getContact($status["BILLINGCONTACT"][0]);
        }

        foreach (["owner", "admin", "tech", "billing"] as $contactType) {
            $prefix = "";
            if ($contactType != "owner") {
                $prefix = $contactType . "contact";
            }
            $status[$prefix . "name"][0] = $contacts[$contactType]["FIRSTNAME"][0] . " " . $contacts[$contactType]["LASTNAME"][0];
            $status[$prefix . "jobtitle"][0] = $contacts[$contactType]["TITLE"][0];
            $status[$prefix . "organization"][0] = $contacts[$contactType]["ORGANIZATION"][0];
            $status[$prefix . "email"][0] = $contacts[$contactType]["EMAIL"][0];
            $status[$prefix . "phone"][0] = $contacts[$contactType]["PHONE"][0];
            $status[$prefix . "street"][0] = $contacts[$contactType]["STREET"][0];
            $status[$prefix . "zip"][0] = $contacts[$contactType]["ZIP"][0];
            $status[$prefix . "city"][0] = $contacts[$contactType]["CITY"][0];
            $status[$prefix . "province"][0] = $contacts[$contactType]["STATE"][0];
            $status[$prefix . "country"][0] = $contacts[$contactType]["COUNTRY"][0];
        }

        return $status;
    }

    /**
     * @param string $contactHandle
     * @return array<string, mixed>
     * @throws Exception
     */
    private static function getContact(string $contactHandle): array
    {
        $command = [
            'CONTACT' => $contactHandle
        ];
        return self::getResponse("StatusContact", $command);
    }

    /**
     * @inheritDoc
     */
    public static function createCertificate(string $certClass, array $contact, string $approvalMethod, string $email, string $serverType, string $csr): array
    {
        $command = [
            'firstname' => $contact["FIRSTNAME"],
            'lastname' => $contact["LASTNAME"],
            'email' => $contact["EMAIL"],
            'street0' => $contact["STREET"],
            'street1' => $contact["STREET2"],
            'city' => $contact["CITY"],
            'state' => $contact["PROVINCE"],
            'zip' => $contact["ZIP"],
            'country' => $contact["COUNTRY"],
            'phone' => $contact["PHONE"],
            'new' => 0,
            'preverify' => 1,
            'autodelete' => 1
        ];
        if ($contact["ORGANIZATION"]) {
            $command['organization'] = $contact["ORGANIZATION"];
        }
        if ($contact["JOBTITLE"]) {
            $command['title'] = $contact["JOBTITLE"];
        }
        if ($contact["FAX"]) {
            $command['fax'] = $contact["FAX"];
        }
        $response = self::getResponse("AddContact", $command);
        $contactId = $response["CONTACT"][0];

        $command = [
            'CLASS' => $certClass,
            'PERIOD' => 1,
            'CSR' => explode(PHP_EOL, $csr),
            'WEBSERVERTYPE' => $serverType,
            'APPROVEREMAIL0' => $email,
            'OWNERCONTACT0' => $contactId,
            'ADMINCONTACT0' => $contactId,
            'BILLINGCONTACT0' => $contactId,
            'TECHCONTACT0' => $contactId,
        ];
        switch ($approvalMethod) {
            case 'dns-txt-token':
                $command["AUTHMETHOD"] = "DNS";
                break;
            case 'file':
                $command["AUTHMETHOD"] = "FILE";
                break;
            default:
                $command["AUTHMETHOD"] = "EMAIL";
        }

        $response = self::getResponse("AddCertificate", $command);

        $contents = null;
        if (isset($response["FILEAUTHCONTENTS"])) {
            $contents = implode(PHP_EOL, $response["FILEAUTHCONTENTS"]);
        }

        return [
            "CERTID" => $response["CERTIFICATE"][0],
            "FILEAUTH_NAME" => @$response["FILEAUTHNAME"][0],
            "FILEAUTH_CONTENTS" => $contents,
            "DNSAUTH_NAME" => @$response["DNSAUTHNAME"][0],
        ];
    }

    /**
     * @inheritDoc
     */
    public static function renewCertificate(string $certId): array
    {
        $command = [
            'CERTIFICATE' => $certId
        ];
        return self::getResponse("RenewCertificate", $command);
    }

    /**
     * @inheritDoc
     */
    public static function reissueCertificate(string $certId, string $csr): array
    {
        $command = [
            'CERTIFICATE' => $certId,
            'CSR' => explode(PHP_EOL, $csr)
        ];
        return self::getResponse("ReissueCertificate", $command);
    }

    /**
     * @inheritDoc
     */
    public static function revokeCertificate(string $certId): array
    {
        $command = [
            'CERTIFICATE' => $certId
        ];
        return self::getResponse("DeleteCertificate", $command);
    }

    /**
     * @inheritDoc
     */
    public static function resendEmail(string $certId, string $email): array
    {
        $command = [
            'CERTIFICATE' => $certId,
            //            'SUB' => null,
            'APPROVEREMAIL' => $email,
            'AUTHMETHOD' => 'EMAIL'
        ];
        return self::getResponse("ModifyCertificate", $command);
    }

    /**
     * Make an API call and process the response
     * @param string $command
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     * @throws Exception
     */
    private static function getResponse(string $command, array $args = []): array
    {
        if (!class_exists('\WHMCS\Module\Registrar\Keysystems\APIClient')) {
            $vendor = realpath(__DIR__ . '/../../../registrars/keysystems/vendor/autoload.php');
            if ($vendor && file_exists($vendor)) {
                require_once $vendor;
            } else {
                throw new Exception("The CentralNic Reseller Registrar Module is required. Please install it and activate it.");
            }
        }

        $api = new \WHMCS\Module\Registrar\Keysystems\APIClient();
        $api->args = $args;
        $api->call($command);

        if ($api->response['CODE'] != 200) {
            throw new Exception($api->response['CODE'] . ' ' . $api->response['DESCRIPTION']);
        }
        return $api->properties;
    }
}
