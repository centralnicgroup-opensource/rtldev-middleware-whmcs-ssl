<?php

/**
 * ISPAPI SSL Module for WHMCS
 *
 * SSL Certificates Registration using WHMCS & HEXONET
 *
 * For more information, please refer to the online documentation.
 * @see https://wiki.hexonet.net/wiki/WHMCS_Modules
 * @noinspection PhpUnused
 */

require_once(__DIR__ . '/vendor/autoload.php');

use HEXONET\WHMCS\ISPAPI\SSL\APIHelper;
use HEXONET\WHMCS\ISPAPI\SSL\DBHelper;
use HEXONET\WHMCS\ISPAPI\SSL\SSLHelper;
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
function ispapissl_MetaData(): array
{
    return [
        "DisplayName" => "ISPAPI SSL Certificates",
        "APIVersion" => "1.1",
        "RequiresServer" => false,
        "AutoGenerateUsernameAndPassword" => false,
        "MODULEVersion" => "9.3.3" // custom meta data
    ];
}

/**
 * Config options of the module.
 * @return array<string, array<string, string>>
 */
function ispapissl_ConfigOptions(): array
{
    DBHelper::createEmailTemplateIfNotExisting();

    return [
        'Certificate Class' => [
            'Type' => 'text',
            'Size' => '25',
        ]
    ];
}

/**
 * Account will be created under Client Profile page > Products/Services when ordered a ssl certificate
 * @param array<string, mixed> $params
 * @return string
 */
function ispapissl_CreateAccount(array $params): string
{
    try {
        if (DBHelper::orderExists($params['serviceid'])) {
            throw new Exception("An SSL Order already exists for this order");
        }
        $certClass = $params['configoptions']['Certificate Class'] ?? $params['configoption1'];
        $response = APIHelper::createCertificate($certClass);
        $orderId = $response['ORDERID'][0];
        $sslOrderId = DBHelper::createOrder($params['clientsdetails']['userid'], $params['serviceid'], $orderId, $certClass);
        SSLHelper::sendConfigurationEmail($params['serviceid'], $sslOrderId);
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * @param array<string, mixed> $params
 * @return string
 */
function ispapissl_TerminateAccount(array $params): string
{
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
function ispapissl_AdminServicesTabFields(array $params): array
{
    $order = DBHelper::getOrder($params["serviceid"], $params["addonId"]);
    $remoteId = '-';
    if ($order && $order->remoteid) {
        $remoteId = $order->remoteid;
    }
    $status = $order ? $order->status : 'Not Yet Provisioned';
    return ["ISPAPI Order ID" => $remoteId, "SSL Configuration Status" => $status];
}

/**
 * @return array<string, string>
 */
function ispapissl_AdminCustomButtonArray(): array
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
function ispapissl_Resend(array $params): string
{
    try {
        $sslOrderId = DBHelper::getOrderId($params['serviceid']);
        if (!$sslOrderId) {
            throw new Exception('No SSL Order exists for this product');
        }
        SSLHelper::sendConfigurationEmail($params['serviceid'], $sslOrderId);
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * Revoke certificate
 * @param array<string, mixed> $params
 * @return string
 */
function ispapissl_Revoke(array $params): string
{
    try {
        $order = DBHelper::getOrder($params["serviceid"], $params["addonId"]);
        if (!$order) {
            return 'Could not find certificate';
        }
        APIHelper::revokeCertificate($order->remoteid);
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * Reissue certificate
 * @param array<string, mixed> $params
 * @return string
 */
function ispapissl_Reissue(array $params): string
{
    try {
        $order = DBHelper::getOrder($params["serviceid"], $params["addonId"]);
        if (!$order) {
            return 'Could not find certificate';
        }
        APIHelper::reissueCertificate($order->remoteid, $params['csr']);
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/**
 * Renew certificate
 * @param array<string, mixed> $params
 * @return string
 */
function ispapissl_Renew(array $params): string
{
    try {
        $order = DBHelper::getOrder($params["serviceid"], $params["addonId"]);
        if (!$order) {
            return 'Could not find certificate';
        }
        APIHelper::renewCertificate($order->remoteid);
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
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
function ispapissl_SSLStepOne(array $params): void
{
    try {
        $order = APIHelper::getOrder($params['remoteid']);
        if ($order['SSLCERTID'] > 0) {
            DBHelper::updateOrder($params['serviceid'], $params['addonId'], ['completiondate' => Carbon::now(), 'status' => 'Completed']);
        } else {
            DBHelper::updateOrder($params['serviceid'], $params['addonId'], ['completiondate' => '', 'status' => 'Awaiting Configuration']);
        }
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
    }
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function ispapissl_SSLStepTwo(array $params): array
{
    try {
        if (!strlen($params['jobtitle'])) {
            $params['jobtitle'] = 'N/A';
        }

        $csr = APIHelper::parseCSR($params['csr']);
        $values['displaydata'] = [
            'Domain' => $csr['CN'][0],
            'Organization' => $csr['O'][0],
            'Organization Unit' => $csr['OU'][0],
            'Email' => $csr['EMAILADDRESS'][0],
            'Locality' => $csr['L'][0],
            'State' => $csr['ST'][0],
            'Country' => $csr['C'][0]
        ];
        array_walk($values['displaydata'], 'htmlspecialchars');
        $values['approveremails'] = [];

        $certClass = $params['configoptions']['Certificate Class'] ?? $params['configoption1'];

        $response = APIHelper::getEmailAddress($certClass, $params['csr']);
        if (isset($response['EMAIL'])) {
            $values['approveremails'] = $response['EMAIL'];
        } else {
            $domain = preg_replace('/^\*\./', '', $csr['CN'][0]);
            if (count(explode('.', $domain)) < 2) {
                throw new Exception("Invalid CN in CSR");
            }
            $response = APIHelper::getValidationAddresses($certClass, $domain);
            $values['approveremails'] = $response['EMAIL'];
        }
        $contact = [];
        foreach (['', 'ADMINCONTACT', 'TECHCONTACT', 'BILLINGCONTACT'] as $contactType) {
            $contact[$contactType . 'ORGANIZATION'] = $params['orgname'];
            $contact[$contactType . 'FIRSTNAME'] = $params['firstname'];
            $contact[$contactType . 'LASTNAME'] = $params['lastname'];
            $contact[$contactType . 'NAME'] = $params['firstname'] . ' ' . $params['lastname'];
            $contact[$contactType . 'JOBTITLE'] = $params['jobtitle'];
            $contact[$contactType . 'EMAIL'] = $params['email'];
            $contact[$contactType . 'STREET'] = $params['address1'];
            $contact[$contactType . 'CITY'] = $params['city'];
            $contact[$contactType . 'PROVINCE'] = $params['state'];
            $contact[$contactType . 'ZIP'] = $params['postcode'];
            $contact[$contactType . 'COUNTRY'] = $params['country'];
            $contact[$contactType . 'PHONE'] = $params['phonenumber'];
            $contact[$contactType . 'FAX'] = $params['faxnumber'];
        }
        switch ($params['servertype']) {
            case 1001:
                $serverType = 'APACHESSL';
                break;
            case 1002:
                $serverType = 'APACHESSLEAY';
                break;
            case 1013:
            case 1014:
                $serverType = 'IIS';
                break;
            default:
                $serverType = 'OTHER';
        }
        APIHelper::replaceCertificate($params['remoteid'], $certClass, $params['csr'], $serverType, $csr['CN'][0], $contact);
        DBHelper::updateHosting($params['serviceid'], ['domain' => $csr['CN'][0]]);
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return ["error" => $e->getMessage()];
    }

    return $values;
}

/**
 * @param array<string, mixed> $params
 * @return array<string, string>
 */
function ispapissl_SSLStepThree(array $params): array
{
    try {
        $orderId = $params['remoteid'];
        $certClass = $params['configoptions']['Certificate Class'] ?? $params['configoption1'];

        APIHelper::updateCertificate($orderId, $certClass, $params['approveremail']);
        APIHelper::executeOrder($orderId);
        DBHelper::updateOrder($params['serviceid'], $params['addonId'], ['completiondate' => Carbon::now()]);
        return [];
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return ["error" => $e->getMessage()];
    }
}

/**
 * Display certificate details in the client area
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function ispapissl_ClientArea(array $params): array
{
    try {
        SSLHelper::loadLanguage();
        $order = DBHelper::getOrder($params['serviceid'], $params['addonId']);

        $tpl = [
            'id' => $params['serviceid'],
            'LANG' => $GLOBALS['_LANG'],
            'config' => ['servertype' => 1000], // Default to OTHER
            'cert' => []
        ];

        if ($order) {
            $tpl['md5certId'] = md5($order->id);
            $tpl['orderStatus'] = $order->status;

            $response = APIHelper::getOrder($order->remoteid);

            $contactMappings = [
                'firstname' => 'firstname',
                'lastname' => 'lastname',
                'orgname' => 'companyname',
                'email' => 'email',
                'address1' => 'address1',
                'address2' => 'address2',
                'city' => 'city',
                'state' => 'state',
                'postcode' => 'postcode',
                'country' => 'country',
                'phonenumber' => 'phonenumber'
            ];
            foreach ($contactMappings as $key => $item) {
                $tpl['config'][$key] = htmlspecialchars($params['clientsdetails'][$item]);
            }

            $certId = $response['SSLCERTID'];
            if ($certId > 0) {
                $status = APIHelper::getCertStatus($certId);
                foreach ($status as $key => $val) {
                    $tpl['cert'][strtolower($key)] = htmlspecialchars(implode(PHP_EOL, $val));
                }

                if (isset($status['STATUS'])) {
                    if (in_array($status['STATUS'][0], ['ACTIVE', 'REPLACED'])) {
                        DBHelper::updateHosting($params['serviceid'], [
                            'nextduedate' => $status['REGISTRATIONEXPIRATIONDATE'][0],
                            'domain' => $status['SSLCERTCN'][0]
                        ]);
                    } else {
                        DBHelper::updateHosting($params['serviceid'], [
                            'domain' => $status['SSLCERTCN'][0]
                        ]);
                    }
                }

                if (isset($_REQUEST['sslresendcertapproveremail'])) {
                    if (isset($_REQUEST['approverEmail'])) {
                        $approverEmail = !empty($_REQUEST['customApproverEmail']) ? $_REQUEST['customApproverEmail'] : $_REQUEST['approverEmail'];
                        $tpl['approverEmail'] = $approverEmail;
                        APIHelper::resendEmail($certId, $approverEmail);
                        $tpl['successMessage'] = $GLOBALS['_LANG']['sslresendsuccess'];
                    } else {
                        $tpl['approverEmails'] = [];
                        $response = APIHelper::getCertEmail($certId);
                        if (isset($response['EMAIL'])) {
                            $tpl['approverEmails'] = $response['EMAIL'];
                        }
                        $domain = preg_replace('/^\*\./', '', $status['SSLCERTCN'][0]);
                        if (count(explode('.', $domain)) < 2) {
                            $tpl['errorMessage'] = $GLOBALS['_LANG']['orderForm']['domainInvalid'];
                        } else {
                            $certClass = $params['configoptions']['Certificate Class'] ?? $params['configoption1'];
                            $response = APIHelper::getValidationAddresses($certClass, $domain);
                            $tpl['approverEmails'] = array_unique(array_merge($tpl['approverEmails'], $response['EMAIL']));
                        }
                        return [
                            'templatefile' => "templates/approval.tpl",
                            'vars' => $tpl
                        ];
                    }
                }
            } elseif (extension_loaded('openssl') && $params['domain']) {
                $dn = [
                    "countryName" => $params['clientsdetails']['countrycode'],
                    "stateOrProvinceName" => $params['clientsdetails']['statecode'] ?? 'n/a',
                    "localityName" => $params['clientsdetails']['city'],
                    "organizationName" => $params['clientsdetails']['companyname'] ?? $params['clientsdetails']['fullname'],
                    "organizationalUnitName" => "NET",
                    "commonName" => $params['domain'],
                    "emailAddress" => $params['clientsdetails']['email']
                ];
                $privateKey = openssl_pkey_new([
                    "private_key_bits" => 2048,
                    "private_key_type" => OPENSSL_KEYTYPE_RSA,
                ]);
                $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha256']);
                if ($csr) {
                    openssl_csr_export($csr, $csrString);
                    $tpl['config']['csr'] = $csrString;
                }
            }
        }
        return [
            'templatefile' => "templates/clientarea.tpl",
            'vars' => $tpl
        ];
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return [
            'templatefile' => "templates/error.tpl",
            'vars' => ['errorMessage' => $e->getMessage()]
        ];
    }
}
