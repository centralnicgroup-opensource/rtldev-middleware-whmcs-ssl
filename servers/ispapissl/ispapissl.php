<?php

/**
 * ISPAPI SSL Module for WHMCS
 *
 * SSL Certificates Registration using WHMCS & HEXONET
 *
 * For more information, please refer to the online documentation.
 * @see https://wiki.hexonet.net/wiki/WHMCS_Modules
 */

use HEXONET\WHMCS\ISPAPI\SSL\APIHelper;
use HEXONET\WHMCS\ISPAPI\SSL\SSLHelper;
use WHMCS\Carbon;

require_once(__DIR__ . '/../../servers/ispapissl/lib/APIHelper.php');
require_once(__DIR__ . '/../../servers/ispapissl/lib/SSLHelper.php');

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related abilities and
 * settings.
 *
 * @see https://developers.whmcs.com/provisioning-modules/meta-data-params/
 *
 * @return array
 */
function ispapissl_MetaData()
{
    return [
        "DisplayName" => "ISPAPI SSL Certificates",
        "APIVersion" => "1.1",
        "RequiresServer" => false,
        "AutoGenerateUsernameAndPassword" => false,
        "MODULEVersion" => "8.0.1" // custom meta data
    ];
}

/*
 * Config options of the module.
 */
function ispapissl_ConfigOptions()
{
    SSLHelper::createEmailTemplateIfNotExisting();

    return [
        'Certificate Class' => [
            'Type' => 'text',
            'Size' => '25',
        ]
    ];
}

/*
 * Account will be created under Client Profile page > Products/Services when ordered a ssl certificate
 */
function ispapissl_CreateAccount(array $params)
{
    try {
        if (SSLHelper::orderExists($params['serviceid'])) {
            throw new Exception("An SSL Order already exists for this order");
        }
        $certClass = $params['configoptions']['Certificate Class'] ?? $params['configoption1'];
        $response = APIHelper::createCertificate($certClass);
        $orderId = $response['ORDERID'][0];
        $sslOrderId = SSLHelper::createOrder($params['clientsdetails']['userid'], $params['serviceid'], $orderId, $certClass);
        SSLHelper::sendConfigurationEmail($params['serviceid'], $sslOrderId);
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

function ispapissl_TerminateAccount(array $params)
{
    $order = SSLHelper::getOrder($params['serviceid'], $params['addonId']);
    if (!$order || $order->status == "Awaiting Configuration") {
        return "SSL Either not Provisioned or Not Awaiting Configuration so unable to cancel";
    }
    SSLHelper::updateOrder($params['serviceid'], $params['addonId'], ['status' => 'Cancelled']);
    return "success";
}

function ispapissl_AdminServicesTabFields(array $params)
{
    $order = SSLHelper::getOrder($params["serviceid"], $params["addonId"]);
    $remoteId = '-';
    if ($order && $order->remoteid) {
        $remoteId = $order->remoteid;
    }
    $status = $order ? $order->status : 'Not Yet Provisioned';
    return ["ISPAPI Order ID" => $remoteId, "SSL Configuration Status" => $status];
}

function ispapissl_AdminCustomButtonArray()
{
    return [
        'Revoke' => 'Revoke',
        'Resend Configuration Email' => 'Resend',
    ];
}

/*
 * Resend configuration link if the product exists. click on 'Resend Configuration Email' button on the admin area.
 */
function ispapissl_Resend(array $params)
{
    try {
        $sslOrderId = SSLHelper::getOrderId($params['serviceid']);
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

function ispapissl_Revoke(array $params)
{
    try {
        $order = SSLHelper::getOrder($params["serviceid"], $params["addonId"]);
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

function ispapissl_Reissue(array $params)
{
    try {
        $order = SSLHelper::getOrder($params["serviceid"], $params["addonId"]);
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

function ispapissl_Renew(array $params)
{
    try {
        $order = SSLHelper::getOrder($params["serviceid"], $params["addonId"]);
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
function ispapissl_SSLStepOne(array $params)
{
    try {
        $orderId = $params['remoteid'];
        if (!$_SESSION['ispapisslcert'][$orderId]['id']) {
            $order = APIHelper::getOrder($orderId);
            if ($order['SSLCERTID'] > 0) {
                $_SESSION['ispapisslcert'][$orderId]['id'] = $order['SSLCERTID'];
                SSLHelper::updateOrder($params['serviceid'], $params['addonId'], ['completiondate' => Carbon::now(), 'status' => 'Completed']);
            } else {
                SSLHelper::updateOrder($params['serviceid'], $params['addonId'], ['completiondate' => '', 'status' => 'waiting Configuration']);
            }
        }
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
    }
}

function ispapissl_SSLStepTwo(array $params)
{
    try {
        $orderId = $params['remoteid'];
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
        APIHelper::replaceCertificate($orderId, $certClass, $params['csr'], $serverType, $csr['CN'][0], $contact);
        SSLHelper::updateHosting($params['serviceid'], ['domain' => $csr['CN'][0]]);
    } catch (Exception $e) {
        logModuleCall(
            'ispapissl',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return ["error" => $e->getMessage()];
    }

    return $values;
}

function ispapissl_SSLStepThree(array $params)
{
    try {
        $orderId = $params['remoteid'];
        $certClass = $params['configoptions']['Certificate Class'] ?? $params['configoption1'];

        APIHelper::updateCertificate($orderId, $certClass, $params['approveremail']);
        APIHelper::executeOrder($orderId);
        SSLHelper::updateOrder($params['serviceid'], $params['addonId'], ['completiondate' => Carbon::now()]);
        return null;
    } catch (Exception $e) {
        logModuleCall(
            'ispapissl',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return ["error" => $e->getMessage()];
    }
}

/*
 * On ClientArea, product details page - the status of the purchased SSL certificate will be displayed.
 */
function ispapissl_ClientArea(array $params)
{
    try {
        SSLHelper::loadLanguage();
        $order = SSLHelper::getOrder($params['serviceid'], $params['addonId']);

        $tpl = [
            'id' => $params['serviceid'],
            'md5certId' => md5($order->id),
            'status' => $order->status,
            'LANG' => $GLOBALS['_LANG']
        ];

        $response = APIHelper::getOrder($order->remoteid);

        if (strlen($response['CSR'])) {
            $tpl['config']['csr'] = htmlspecialchars($response['CSR']);
        }

        $contactMappings = [
            'firstname' => 'ADMINCONTACTFIRSTNAME',
            'lastname' => 'ADMINCONTACTLASTNAME',
            'orgname' => 'ADMINCONTACTORGANIZATION',
            'jobtitle' => 'ADMINCONTACTJOBTITLE',
            'email' => 'ADMINCONTACTEMAIL',
            'address1' => 'ADMINCONTACTSTREET',
            'city' => 'ADMINCONTACTCITY',
            'state' => 'ADMINCONTACTPROVINCE',
            'postcode' => 'ADMINCONTACTZIP',
            'country' => 'ADMINCONTACTCOUNTRY',
            'phonenumber' => 'ADMINCONTACTPHONE'
        ];
        foreach ($contactMappings as $key => $item) {
            if (isset($response['COMMAND'][$item])) {
                $tpl['config'][$key] = htmlspecialchars($response['COMMAND'][$item]);
            }
        }

        $contactMappings = [
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'orgname' => 'companyname',
            'jobtitle' => '',
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
            if (!isset($tpl['config'][$key])) {
                $tpl['config'][$key] = htmlspecialchars($params['clientsdetails'][$item]);
            }
        }

        $certId = $response['SSLCERTID'];
        if ($certId > 0) {
            $status = APIHelper::getCertStatus($certId);
            if (isset($status['CSR'])) {
                $tpl['config']['csr'] = htmlspecialchars(implode(PHP_EOL, $status['CSR']));
            }
            if (isset($status['CRT'])) {
                $tpl['crt'] = htmlspecialchars(implode(PHP_EOL, $status['CRT']));
            }
            if (isset($status['CACRT'])) {
                $tpl['cacrt'] = htmlspecialchars(implode(PHP_EOL, $status['CACRT']));
            }
            if (isset($status['STATUS'])) {
                $tpl['processingStatus'] = htmlspecialchars($status['STATUS'][0]);

                if ($status['STATUS'][0] == 'ACTIVE') {
                    $exp_date = $status['REGISTRATIONEXPIRATIONDATE'][0];
                    $tpl['displayData']['Expires'] = $exp_date;

                    SSLHelper::updateHosting($params['serviceid'], [
                        'nextduedate' => $exp_date,
                        'domain' => $status['SSLCERTCN'][0]
                    ]);
                } else {
                    SSLHelper::updateHosting($params['serviceid'], [
                        'domain' => $status['SSLCERTCN'][0]
                    ]);
                }

                $tpl['displayData']['CN'] = htmlspecialchars($status['SSLCERTCN'][0]);
            }

            if (isset($status['STATUSDETAILS'])) {
                $tpl['processingdetails'] = htmlspecialchars(urldecode($status['STATUSDETAILS'][0]));
            }

            if (!isset($tpl['config']['servertype'])) {
                $tpl['config']['servertype'] = '1000'; // OTHER
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
                    if (isset($status)) {
                        $domain = preg_replace('/^\*\./', '', $status['SSLCERTCN'][0]);
                        if (count(explode('.', $domain)) < 2) {
                            $tpl['errorMessage'] = $GLOBALS['_LANG']['orderForm']['domainInvalid'];
                        } else {
                            $certClass = $params['configoptions']['Certificate Class'] ?? $params['configoption1'];
                            $response = APIHelper::getValidationAddresses($certClass, $domain);
                            $tpl['approverEmails'] = array_unique(array_merge($tpl['approverEmails'], $response['EMAIL']));
                        }
                    }
                    return [
                        'templatefile' => "templates/approval.tpl",
                        'vars' => $tpl
                    ];
                }
            }
        }
        return [
            'templatefile' => "templates/clientarea.tpl",
            'vars' => $tpl
        ];
    } catch (Exception $e) {
        return [
            'templatefile' => "templates/error.tpl",
            'vars' => ['errorMessage' => $e->getMessage()]
        ];
    }
}
