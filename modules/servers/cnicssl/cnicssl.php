<?php

/**
 * CentralNic SSL Module for WHMCS
 *
 * SSL Certificates Registration using WHMCS & HEXONET or RRPproxy
 *
 * For more information, please refer to the online documentation.
 * @see https://centralnic-reseller.github.io/centralnic-reseller/docs/cnic/whmcs/whmcs-ssl/
 * @noinspection PhpUnused
 */

require_once(__DIR__ . '/vendor/autoload.php');

use CNIC\WHMCS\SSL\APIHelper;
use CNIC\WHMCS\SSL\DBHelper;
use CNIC\WHMCS\SSL\SSLHelper;
use WHMCS\Carbon;

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related abilities and
 * settings.
 *
 * @see https://developers.whmcs.com/provisioning-modules/meta-data-params/
 *
 * @return array<string, mixed>
 */
function cnicssl_MetaData(): array
{
    return [
        "DisplayName" => "CNIC SSL Certificates",
        "APIVersion" => "1.1",
        "RequiresServer" => false,
        "AutoGenerateUsernameAndPassword" => false,
        "MODULEVersion" => "11.0.0" // custom meta data
    ];
}

/**
 * Config options of the module.
 * @return array<string, array<string, string>>
 */
function cnicssl_ConfigOptions(): array
{
    DBHelper::createEmailTemplateIfNotExisting();

    return [
        'Certificate Class' => [
            'Type' => 'text',
            'Size' => '25',
        ],
        'Registrar' => [
            'Type' => 'dropdown',
            'Options' => 'ISPAPI,RRPproxy',
            'Description' => 'Ensure the corresponding registrar module is installed and configured'
        ]
    ];
}

/**
 * Account will be created under Client Profile page > Products/Services when ordered a ssl certificate
 * @param array<string, mixed> $params
 * @return string
 */
function cnicssl_CreateAccount(array $params): string
{
    try {
        if (DBHelper::orderExists($params['serviceid'])) {
            throw new Exception("An SSL Order already exists for this order");
        }
        $certClass = $params['configoptions']['Certificate Class'] ?? $params['configoption1'];
        $sslOrderId = DBHelper::createOrder($params['clientsdetails']['userid'], $params['serviceid'], $certClass);
        SSLHelper::sendConfigurationEmail($params['serviceid'], $sslOrderId);
    } catch (Exception $e) {
        logModuleCall('cnicssl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * @param array<string, mixed> $params
 * @return string
 */
function cnicssl_TerminateAccount(array $params): string
{
    $revoke = cnicssl_Revoke($params);
    if ($revoke !== "success") {
        return $revoke;
    }
    $order = DBHelper::getOrder($params['serviceid'], $params['addonId']);
    if (!$order || $order->status == "Awaiting Configuration") {
        return "SSL Either not Provisioned or Not Awaiting Configuration so unable to cancel";
    }
    DBHelper::updateOrder($params['serviceid'], $params['addonId'], ['status' => 'Cancelled']);
    return "success";
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function cnicssl_AdminServicesTabFields(array $params): array
{
    $order = DBHelper::getOrder($params["serviceid"], $params["addonId"]);
    $remoteId = '-';
    if ($order && $order->remoteid) {
        $remoteId = $order->remoteid;
    }
    $status = $order ? $order->status : 'Not Yet Provisioned';
    return ["Remote Order ID" => $remoteId, "SSL Configuration Status" => $status];
}

/**
 * @return array<string, string>
 */
function cnicssl_AdminCustomButtonArray(): array
{
    return [
        'Revoke' => 'Revoke',
        'Resend Configuration Email' => 'Resend',
    ];
}

/**
 * Resend configuration link if the product exists
 * @param array<string, mixed> $params
 * @return string
 */
function cnicssl_Resend(array $params): string
{
    try {
        $sslOrderId = DBHelper::getOrderId($params['serviceid']);
        if (!$sslOrderId) {
            throw new Exception('No SSL Order exists for this product');
        }
        SSLHelper::sendConfigurationEmail($params['serviceid'], $sslOrderId);
    } catch (Exception $e) {
        logModuleCall('cnicssl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * Revoke certificate
 * @param array<string, mixed> $params
 * @return string
 */
function cnicssl_Revoke(array $params): string
{
    try {
        $order = DBHelper::getOrder($params["serviceid"], $params["addonId"]);
        if (!$order) {
            return 'Could not find certificate';
        }
        APIHelper::revokeCertificate($order->registrar, $order->remoteid);
    } catch (Exception $e) {
        logModuleCall('cnicssl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * Reissue certificate
 * @param array<string, mixed> $params
 * @return string
 */
function cnicssl_Reissue(array $params): string
{
    try {
        $order = DBHelper::getOrder($params["serviceid"], $params["addonId"]);
        if (!$order) {
            return 'Could not find certificate';
        }
        APIHelper::reissueCertificate($order->registrar, $order->remoteid, $params['csr']);
    } catch (Exception $e) {
        logModuleCall('cnicssl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * Renew certificate
 * @param array<string, mixed> $params
 * @return string
 */
function cnicssl_Renew(array $params): string
{
    try {
        $order = DBHelper::getOrder($params["serviceid"], $params["addonId"]);
        if (!$order) {
            return 'Could not find certificate';
        }
        APIHelper::renewCertificate($order->registrar, $order->remoteid);
    } catch (Exception $e) {
        logModuleCall('cnicssl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/*
 * The following three steps are essential to setup the ssl certificate. When the customer clicks on the configuration email, he will be guided to complete these steps.
 */
/**
 * @param array<string, mixed> $params
 */
function cnicssl_SSLStepOne(array $params): void
{
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function cnicssl_SSLStepTwo(array $params): array
{
    try {
        if (!strlen($params['jobtitle'])) {
            $params['jobtitle'] = 'N/A';
        }

        $certClass = $params['configoptions']['Certificate Class'] ?? $params['configoption1'];
        $registrar = $params['configoptions']['Registrar'] ?? $params['configoption2'];

        // Parse CSR
        $csr = SSLHelper::parseCSR($params['csr']);
        $values['displaydata'] = [
            'Domain' => $csr['CN'],
            'Organization' => $csr['O'],
            'Organization Unit' => $csr['OU'],
            'Email' => $csr['EMAILADDRESS'],
            'Locality' => $csr['L'],
            'State' => $csr['ST'],
            'Country' => $csr['C']
        ];
        array_walk($values['displaydata'], 'htmlspecialchars');

        // Determine approval email addresses
        $domain = preg_replace('/^\*\./', '', $csr['CN']);
        if ($domain == null || count(explode('.', $domain)) < 2) {
            throw new Exception("Invalid CN in CSR");
        }
        $values['approveremails'] = SSLHelper::getValidationEmails($domain);

        // Determine approval methods
        $values['approvalmethods'] = ['email'];
        $certificates = SSLHelper::getCertificates($registrar);
        if ($certificates !== null) {
            if ($certificates[$certClass]["dnsAuth"]) {
                $values['approvalmethods'][] = 'dns-txt-token';
            }
            if ($certificates[$certClass]["fileAuth"]) {
                $values['approvalmethods'][] = 'file';
            }
        }

        DBHelper::updateHosting($params['serviceid'], ['domain' => $csr['CN']]);
    } catch (Exception $e) {
        logModuleCall('cnicssl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return ["error" => $e->getMessage()];
    }

    return $values;
}

/**
 * @param array<string, mixed> $params
 * @return array<string, string>
 */
function cnicssl_SSLStepThree(array $params): array
{
    try {
        if (DBHelper::orderProcessed($params['serviceid'])) {
            throw new Exception("Order already processed");
        }

        $certClass = $params['configoptions']['Certificate Class'] ?? $params['configoption1'];
        $data = ['completiondate' => Carbon::now()];
        $contact = [
            'ORGANIZATION' => $params['orgname'],
            'FIRSTNAME' => $params['firstname'],
            'LASTNAME' => $params['lastname'],
            'JOBTITLE' => $params['jobtitle'],
            'EMAIL' => $params['email'],
            'STREET' => $params['address1'],
            'STREET2' => $params['address2'],
            'CITY' => $params['city'],
            'PROVINCE' => $params['state'],
            'ZIP' => $params['postcode'],
            'COUNTRY' => $params['country'],
            'PHONE' => $params['phonenumber'],
            'FAX' => $params['faxnumber']
        ];
        $response = APIHelper::createCertificate($params['configoption2'], $params['serviceid'], $certClass, $contact, $params['approvalmethod'], $params['approveremail']);
        $data["remoteid"] = $response["CERTID"];

        $authData = null;
        switch ($params['approvalmethod']) {
            case 'dns-txt-token':
                $validation = explode(" ", $response["DNSAUTH_NAME"]);
                $authData = [
                    "method" => "dnsauth",
                    "type" => strtoupper($validation[1]),
                    "host" => $validation[0],
                    "value" => $validation[2]
                ];
                break;
            case 'file':
                $path = parse_url($response["FILEAUTH_NAME"], PHP_URL_PATH);
                if ($path) {
                    $authData = [
                        "method" => "fileauth",
                        "path" => ltrim($path, "/"),
                        "name" => ltrim($path, "/"),
                        "contents" => $response["FILEAUTH_CONTENTS"]
                    ];
                }
                break;
        }
        if ($authData) {
            $data['authdata'] = json_encode($authData);
        }
        DBHelper::updateOrder($params['serviceid'], $params['addonId'], $data);
        return [];
    } catch (Exception $e) {
        logModuleCall('cnicssl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return ["error" => $e->getMessage()];
    }
}

/**
 * Display certificate details in the client area
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function cnicssl_ClientArea(array $params): array
{
    try {
        SSLHelper::loadLanguage();
        $certClass = $params["configoption1"];
        $registrar = $params["configoption2"];
        $providers = SSLHelper::getProviders();
        $certs = SSLHelper::getCertificates($registrar);
        $provider = isset($certs[$certClass]) ? $certs[$certClass]["provider"] : "";
        $logo = isset($providers[$provider]) ? $providers[$provider]["logo"] : "";

        $order = DBHelper::getOrder($params["serviceid"], $params["addonId"]);

        $tpl = [
            "id" => $params["serviceid"],
            "LANG" => $GLOBALS["_LANG"],
            "config" => ["servertype" => 1000], // Default to OTHER
            "cert" => [],
            "logo" => $logo
        ];

        if ($order) {
            $tpl["md5certId"] = md5($order->id);
            $tpl["orderStatus"] = $order->status;

            $contactMappings = [
                "firstname" => "firstname",
                "lastname" => "lastname",
                "orgname" => "companyname",
                "email" => "email",
                "address1" => "address1",
                "address2" => "address2",
                "city" => "city",
                "state" => "state",
                "postcode" => "postcode",
                "country" => "country",
                "phonenumber" => "phonenumber"
            ];
            foreach ($contactMappings as $key => $item) {
                $tpl["config"][$key] = htmlspecialchars($params["clientsdetails"][$item]);
            }

            $certId = $order->remoteid;
            if ($certId) {
                $status = APIHelper::getCertStatus($registrar, $certId);
                foreach ($status as $key => $val) {
                    $tpl["cert"][strtolower($key)] = htmlspecialchars(implode(PHP_EOL, $val));
                }

                if (isset($status["STATUS"])) {
                    if (in_array($status["STATUS"][0], ["ACTIVE", "REPLACED"])) {
                        DBHelper::updateHosting($params["serviceid"], [
                            "nextduedate" => $status["REGISTRATIONEXPIRATIONDATE"][0],
                            "domain" => $status["SSLCERTCN"][0]
                        ]);
                    } else {
                        DBHelper::updateHosting($params["serviceid"], [
                            "domain" => $status["SSLCERTCN"][0]
                        ]);
                    }
                }

                if (isset($_REQUEST["sslresendcertapproveremail"])) {
                    if (isset($_REQUEST["approverEmail"])) {
                        $approverEmail = !empty($_REQUEST["customApproverEmail"]) ? $_REQUEST["customApproverEmail"] : $_REQUEST["approverEmail"];
                        $tpl["approverEmail"] = $approverEmail;
                        APIHelper::resendEmail($registrar, $certId, $approverEmail);
                        $tpl["successMessage"] = $GLOBALS["_LANG"]["sslresendsuccess"];
                    } else {
                        $tpl["approverEmails"] = SSLHelper::getValidationEmails($status["SSLCERTCN"][0]);
                        return [
                            "templatefile" => "templates/approval.tpl",
                            "vars" => $tpl
                        ];
                    }
                }
            } elseif (extension_loaded("openssl") && $params["domain"]) {
                $dn = [
                    "countryName" => $params["clientsdetails"]["countrycode"],
                    "stateOrProvinceName" => $params["clientsdetails"]["statecode"] ?? "n/a",
                    "localityName" => $params["clientsdetails"]["city"],
                    "organizationName" => $params["clientsdetails"]["companyname"] ?? $params["clientsdetails"]["fullname"],
                    "organizationalUnitName" => "NET",
                    "commonName" => $params["domain"],
                    "emailAddress" => $params["clientsdetails"]["email"]
                ];
                $privateKey = openssl_pkey_new([
                    "private_key_bits" => 2048,
                    "private_key_type" => OPENSSL_KEYTYPE_RSA,
                ]);
                $csr = openssl_csr_new($dn, $privateKey, ["digest_alg" => "sha256"]);
                if ($csr) {
                    openssl_csr_export($csr, $csrString);
                    $tpl["config"]["csr"] = $csrString;
                }
            }
        }
        return [
            "templatefile" => "templates/clientarea.tpl",
            "vars" => $tpl
        ];
    } catch (Exception $e) {
        logModuleCall("cnicssl", __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return [
            "templatefile" => "templates/error.tpl",
            "vars" => ["errorMessage" => $e->getMessage()]
        ];
    }
}
