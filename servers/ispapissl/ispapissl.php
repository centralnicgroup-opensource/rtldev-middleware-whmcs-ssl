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
use WHMCS\Module\Registrar\Ispapi\LoadRegistrars;
use HEXONET\ResponseParser as RP;

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
        "MODULEVersion" => "8.0.1" // custom meta data
    ];
}

/*
 * Config options of the module.
 */
function ispapissl_ConfigOptions()
{
    SSLHelper::createEmailTemplateIfNotExisting();
    $registrars = new LoadRegistrars();

    return [
        'Certificate Class' => [
            'Type' => 'text',
            'Size' => '25',
        ],
        'Registrar' => [
            'Type' => 'dropdown',
            'Options' => implode(",", $registrars->getLoadedRegistars())
        ],
        'Years' => [
            'Type' => 'dropdown',
            'Options' => '1,2,3,4,5,6,7,8,9,10'
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
        $certYears = $params['configoptions']['Years'] ?? $params['configoption3'];
        $response = APIHelper::createCertificate($certClass, $certYears);
        $orderId = $response['ORDERID'][0];
        $sslOrderId = SSLHelper::createOrder($params['clientsdetails']['userid'], $params['serviceid'], $orderId, $certClass);
        SSLHelper::sendConfigurationEmail($params['serviceid'], $sslOrderId);
    } catch (Exception $e) {
        logModuleCall('ispapissl', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/*
 * Custom button for resending configuration email.
 */
function ispapissl_AdminCustomButtonArray()
{
    return ['Resend Configuration Email' => 'resend'];
}

/*
 * Resend configuration link if the product exists. click on 'Resend Configuration Email' button on the admin area.
 */
function ispapissl_resend($params)
{
    try {
        $sslOrderId = SSLHelper::getOrderId($params['serviceid']);
        if (!$sslOrderId) {
            throw new Exception('No SSL Order exists for this product');
        }
        SSLHelper::sendConfigurationEmail($params['serviceid'], $sslOrderId);
    } catch (Exception $e) {
        logModuleCall('provisioningmodule', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return 'success';
}

/*
 * The following three steps are essential to setup the ssl certificate. When the customer clicks on the configuration email, he will be guided to complete these steps.
 */
function ispapissl_sslstepone($params)
{
    try {
        $orderId = $params['remoteid'];
        if (!$_SESSION['ispapisslcert'][$orderId]['id']) {
            $allowConfig = true;
            $response = APIHelper::getOrder($orderId);
            if (isset($response['LASTRESPONSE']) && strlen($response['LASTRESPONSE'][0])) {
                $order_response = RP::parse(urldecode($response['LASTRESPONSE'][0]));
                if (isset($order_response['SSLCERTID'])) {
                    $_SESSION['ispapisslcert'][$orderId]['id'] = $order_response['SSLCERTID'][0];
                    $allowConfig = false;
                }
            }
            SSLHelper::updateOrder($params['serviceid'], [
                'completiondate' => $allowConfig ? '' : Carbon::now(),
                'status' => $allowConfig ? 'Awaiting Configuration' : 'Completed'
            ]);
        }
    } catch (Exception $e) {
        logModuleCall('provisioningmodule', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
    }
}

function ispapissl_sslsteptwo($params)
{
    try {
        $ispapissl_server_map = [];
        include(implode(DIRECTORY_SEPARATOR, [__DIR__, "ispapissl-config.php"]));
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
            'Country' => $csr['C'][0],
            'approveremails' => []
        ];
        array_walk($values, 'htmlspecialchars');

        $certClass = $params['configoptions']['Certificate Type'] ?? $params['configoption1'];
        $certYears = $params['configoptions']['Years'] ?? $params['configoption3'];

        $response = APIHelper::getEmailAddress($certClass, $params['csr']);
        if (isset($response['EMAIL'])) {
            $values['approveremails'] = $response['EMAIL'];
        } else {
            $domain = explode('.', preg_replace('/^\*\./', '', $csr['CN'][0]));
            if (count($domain) < 2) {
                throw new Exception("Invalid CN in CSR");
            }
            $domain = SSLHelper::parseDomain($domain);
            foreach (['admin', 'administrator', 'hostmaster', 'root', 'webmaster', 'postmaster'] as $mailbox) {
                $values['approveremails'][] = $mailbox . '@' . $domain;
            }
        }
        $contact = [];
        foreach (['', 'ADMINCONTACT', 'TECHCONTACT', 'BILLINGCONTACT'] as $contactType) {
            $contact[$contactType . 'ORGANIZATION'] = $params['organisationname'];
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
        APIHelper::replaceCertificate($orderId, $certClass, $certYears, $params['csr'], $ispapissl_server_map[$params['servertype']], $csr['CN'][0], $contact);
        SSLHelper::updateHosting($params['serviceid'], ['domain' => $csr['CN'][0]]);
    } catch (Exception $e) {
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return ["error" => $e->getMessage()];
    }

    return $values;
}

function ispapissl_sslstepthree($params)
{
    try {
        $orderId = $params['remoteid'];
        $certClass = $params['configoptions']['Certificate Type'] ?? $params['configoption1'];
        $certYears = $params['configoptions']['Years'] ?? $params['configoption3'];

        APIHelper::updateCertificate($orderId, $certClass, $certYears, $params['approveremail']);
        APIHelper::executeOrder($orderId);
        SSLHelper::updateOrder($params['serviceid'], ['completiondate' => Carbon::now()]);
        return null;
    } catch (Exception $e) {
        logModuleCall(
            'provisioningmodule',
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
function ispapissl_ClientArea($params)
{
    $data = SSLHelper::getOrder($params['serviceid']);

    $params['remoteid'] = $data->remoteid;
    $params['status'] = $data->status;
    $sslOrderId = $data->id;
    $orderId = $params['remoteid'];

    $tpl = [
        'id' => $params['serviceid'],
        'md5certid' => md5($sslOrderId),
        'status' => $params['status']
    ];

    $response = APIHelper::getOrder($orderId);

    if (isset($response['ORDERCOMMAND']) && strlen($response['ORDERCOMMAND'][0])) {
        $order_command = RP::parse(urldecode($response['ORDERCOMMAND'][0]));

        $csr = [];
        $i = 0;
        while (isset($order_command['CSR' . $i])) {
            if (strlen($order_command['CSR' . $i])) {
                $csr[] = $order_command['CSR' . $i];
            }
            $i++;
        }
        $csr = implode(PHP_EOL, $csr);
        if (strlen($csr)) {
            $tpl['config']['csr'] = htmlspecialchars($csr);
        }

        $contactMappings = [
            'firstname' => 'ADMINCONTACTFIRSTNAME',
            'lastname' => 'ADMINCONTACTLASTNAME',
            'organisationname' => 'ADMINCONTACTORGANIZATION',
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
            if (isset($order_command[$item])) {
                $tpl['config'][$key] = htmlspecialchars($order_command[$item]);
            }
        }
    }

    if ((isset($response['LASTRESPONSE']) && strlen($response['LASTRESPONSE'][0]))) {
        $order_response = RP::parse(urldecode($response['LASTRESPONSE'][0]));

        if (isset($order_response['SSLCERTID'])) {
            $certId = $order_response['SSLCERTID'][0];
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
                $tpl['processingstatus'] = htmlspecialchars($status['STATUS'][0]);

                if ($status['STATUS'][0] == 'ACTIVE') {
                    $exp_date = $status['REGISTRATIONEXPIRATIONDATE'][0];
                    $tpl['displaydata']['Expires'] = $exp_date;

                    SSLHelper::updateHosting($params['serviceid'], [
                        'nextduedate' => $exp_date,
                        'domain' => $status['SSLCERTCN'][0]
                    ]);
                } else {
                    SSLHelper::updateHosting($params['serviceid'], [
                        'domain' => $status['SSLCERTCN'][0]
                    ]);
                }

                $tpl['displaydata']['CN'] = htmlspecialchars($status['SSLCERTCN'][0]);
            }

            if (isset($status['STATUSDETAILS'])) {
                $tpl['processingdetails'] = htmlspecialchars(urldecode($status['STATUSDETAILS'][0]));
            }
        }
    }

    $contactMappings = [
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'organisationname' => 'companyname',
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

    if (!isset($tpl['config']['servertype'])) {
        $tpl['config']['servertype'] = '1002';
    }

    $_LANG = [
        'sslcrt' => 'Certificate',
        'sslcacrt' => 'CA / Intermediate Certificate',
        'sslprocessingstatus' => 'Processing Status',
        'sslresendcertapproveremail' => 'Resend Approver Email'
    ];
    foreach ($_LANG as $key => $value) {
        if (!isset($GLOBALS['_LANG'][$key])) {
            $GLOBALS['_LANG'][$key] = $value;
        }
    }

    if (isset($certId)) {
        //provide user a possibility to resend approver email to selected email id
        //when user enters a approver email in the text box:
        if (isset($_REQUEST['sslresendcertapproveremail']) && isset($_REQUEST['approveremail'])) {
            $tpl['approveremail'] = $_REQUEST['approveremail'];
            try {
                APIHelper::resendEmail($certId, $_REQUEST['approveremail']);
                unset($_REQUEST['sslresendcertapproveremail']);
                $tpl['successmessage'] = 'Successfully resent the approver email';
            } catch (Exception $e) {
                $tpl['errormessage'] = $e->getMessage();
            }
        }
        //when user chooses from the listed approver emails
        if (isset($_REQUEST['sslresendcertapproveremail'])) {
            $tpl['sslresendcertapproveremail'] = 1;
            $tpl['approveremails'] = [];

            $appemail_response = APIHelper::getCertEmail($certId);
            if (isset($appemail_response['EMAIL'])) {
                $tpl['approveremails'] = $appemail_response['EMAIL'];
            } elseif (isset($status)) {
                $domain = explode('.', preg_replace('/^\*\./', '', $status['SSLCERTCN'][0]));
                if (count($domain) < 2) {
                    $tpl['errormessage'] = 'Invalid Domain';
                } else {
                    $domain = SSLHelper::parseDomain($domain);
                    foreach (['admin', 'administrator', 'hostmaster', 'root', 'webmaster', 'postmaster'] as $mailbox) {
                        $tpl['approveremails'][] = $mailbox . '@' . $domain;
                    }
                }
            }
        }
    }

    return [
        'tabOverviewReplacementTemplate' => "ispapissl-clientarea.tpl",
        'templateVariables' => [
            'ispapissl' => $tpl,
            'LANG' => $GLOBALS['_LANG']
        ],
    ];
}
