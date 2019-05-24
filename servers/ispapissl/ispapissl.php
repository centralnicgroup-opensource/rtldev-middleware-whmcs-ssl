<?php
/**
 * ISPAPI SSL Module for WHMCS
 *
 * SSL Certificates Registration using WHMCS & HEXONET
 *
 * For more information, please refer to the online documentation.
 * @see https://wiki.hexonet.net/wiki/WHMCS_Modules
 */

 // if (!defined("WHMCS")) {
 //     die("This file cannot be accessed directly");
 // }

use WHMCS\Database\Capsule;
use ISPAPISSL\Helper;
use ISPAPISSL\LoadRegistrars;

if (defined("ROOTDIR")) {
    require_once(implode(DIRECTORY_SEPARATOR, array(ROOTDIR,"includes","registrarfunctions.php")));
}
require_once(implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__),"lib","Helper.class.php")));
require_once(implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__),"lib","LoadRegistrars.class.php")));


global $module_version;
$module_version = "7.3.0";

function ispapissl_MetaData()
{
    return array(
        'DisplayName' => 'ISPAPI SSL Certificates',
    );
}

/*
 * Config options of the module.
 */
function ispapissl_ConfigOptions()
{
    $data = Helper::SQLCall("SELECT * FROM tblemailtemplates WHERE name='SSL Certificate Configuration Required'", array(), "fetchall");

    if (empty($data)) {
        Helper::SQLCall("INSERT INTO tblemailtemplates (type, name, subject, message, fromname, fromemail, disabled, custom, language, copyto, plaintext) VALUES ('product', 'SSL Certificate Configuration Required', 'SSL Certificate Configuration Required', '<p>Dear {\$client_name},</p><p>Thank you for your order for an SSL Certificate. Before you can use your certificate, it requires configuration which can be done at the URL below.</p><p>{\$ssl_configuration_link}</p><p>Instructions are provided throughout the process but if you experience any problems or have any questions, please open a ticket for assistance.</p><p>{\$signature}</p>', '', '', '', '', '', '', '0')", array(), "execute");
    }

    //load all the ISPAPI registrars (Ispapi, Hexonet)
    $ispapi_registrars = new LoadRegistrars();
    $registrars = $ispapi_registrars->getLoadedRegistars();

    return array(
        'Certificate Class' => array(
            'Type' => 'text',
            'Size' => '25',
        ),
        'Registrar' => array(
            'Type' => 'dropdown',
            'Options' => implode(",", $registrars)
        ),
        'Years' => array(
            'Type' => 'dropdown',
            'Options' => '1,2,3,4,5,6,7,8,9,10'
        )
    );
}

/*
 * Account will be created under Client Profile page > Products/Services when ordered a ssl certificate
 */
function ispapissl_CreateAccount(array $params)
{
    try {
        include(implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__),"ispapissl-config.php")));
        //to check if the customer order already exists. if not create the order at hexonet by clicking 'create' button
        $data = Helper::SQLCall("SELECT * FROM tblsslorders WHERE serviceid=?", array($params['serviceid']), "fetchall");

        if (!empty($data)) {
            throw new Exception("An SSL Order already exists for this order");
        }

        //configuration options set from ispapissl_ConfigOptions()
        if ($params['configoptions']['Certificate Class']) {
            $certclass = $params['configoptions']['Certificate Class'];
        } else {
            //configoption1 - certificate class name
            $certclass = $params['configoption1'];
        }

        if ($params['configoptions']['Years']) {
            $certyears = $params['configoptions']['Years'];
        } else {
            $certyears = $params['configoption3'];
        }

        $registrar = $params['configoption2'];
        //command to create the order of ssl certificate at hexonet
        $command = array( 'ORDER' => 'CREATE',
                          'COMMAND' => 'CreateSSLCert',
                          'SSLCERTCLASS' => $certclass,
                          'PERIOD' => $certyears );

        $response = Helper::APICall($registrar, $command);

        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
        }

        $orderid = $response['PROPERTY']['ORDERID'][0];

        //insert certificate - a customer order in the database with its status
        Helper::SQLCall("INSERT INTO tblsslorders (userid, serviceid, remoteid, module, certtype, status) VALUES (?, ?, ?, 'ispapissl', ?, 'Awaiting Configuration')", array($params['clientsdetails']['userid'], $params['serviceid'], $orderid, $certclass), "execute");
        //the id (sslorderid) of the inserted item
        $sslorderid = Helper::SQLCall("SELECT id from tblsslorders WHERE remoteid=?", array($orderid), "fetch");
        //send configuration link to the customer via email based on the order id. Customer then follow the next steps by clicking the link to configure certificate
        global $CONFIG;
        $sslconfigurationlink = $CONFIG['SystemURL'].'/configuressl.php?cert='.md5($sslorderid['id']);

        $sslconfigurationlink = '<a href="'.$sslconfigurationlink.'">'.$sslconfigurationlink.'</a>';
        sendmessage('SSL Certificate Configuration Required', $params['serviceid'], array('ssl_configuration_link' => $sslconfigurationlink));
    } catch (Exception $e) {
        logModuleCall(
            'ispapissl',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}

/*
 * Custom button for resending configuration email.
 */
function ispapissl_AdminCustomButtonArray()
{
    return array('Resend Configuration Email' => 'resend');
}

/*
 * Resend configuration link if the product exists. click on 'Resend Configuration Email' button on the admin area.
 */
function ispapissl_resend($params)
{

    try {
        //if the order id exists, allow to resend configuration email
        $data = Helper::SQLCall("SELECT id FROM tblsslorders WHERE serviceid=?", array($params['serviceid']), "fetch");

        $id = $data['id'];
        if (!$id) {
            throw new Exception('No SSL Order exists for this product');
        }
        global $CONFIG;
        $sslconfigurationlink = $CONFIG['SystemURL'].'/configuressl.php?cert='.md5($id);

        $sslconfigurationlink = '<a href="'.$sslconfigurationlink.'">'.$sslconfigurationlink.'</a>';

        sendmessage('SSL Certificate Configuration Required', $params['serviceid'], array('ssl_configuration_link' => $sslconfigurationlink));
    } catch (Exception $e) {
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
     return 'success';
}

/*
 * The following three steps are essential to setup the ssl certificate. When the customer clicks on the configuration email, he will be guided to complete these steps.
 */
function ispapissl_sslstepone($params)
{

    $registrar = $params['configoption2'];

    try {
        $orderid = $params['remoteid'];
        //check order id of the certificate set at hexonet and update its status on WHMCS
        if (!$_SESSION['ispapisslcert'][$orderid]['id']) {
            $command = array('COMMAND' => 'QueryOrderList', 'ORDERID' => $orderid);
            $response = Helper::APICall($registrar, $command);

            if ($response['CODE'] != 200) {
                throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
            }

            $cert_allowconfig = true;

            if (isset($response['PROPERTY']['LASTRESPONSE']) && strlen($response['PROPERTY']['LASTRESPONSE'][0])) {
                $order_response = Helper::parseResponse($registrar, urldecode($response['PROPERTY']['LASTRESPONSE'][0]));

                if (isset($order_response['PROPERTY']['SSLCERTID'])) {
                    $_SESSION['ispapisslcert'][$orderid]['id'] = $order_response['PROPERTY']['SSLCERTID'][0];
                    $cert_allowconfig = false;
                }
            }

            if (!$cert_allowconfig) {
                Helper::SQLCall("UPDATE tblsslorders SET completiondate=now(), status='Completed' WHERE serviceid=?", array($params['serviceid']), "execute");
            } else {
                Helper::SQLCall("UPDATE tblsslorders SET completiondate='', status='Awaiting Configuration' WHERE serviceid=?", array($params['serviceid']), "execute");
            }
        }
    } catch (Exception $e) {
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }
}

function ispapissl_sslsteptwo($params)
{

    try {
        include(implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__),"ispapissl-config.php")));
        //orderid and customer's contact data
        $orderid = $params['remoteid'];
        $cert_id = $_SESSION['enomsslcert'][$orderid]['id'];
        $webservertype = $params['servertype'];
        $csr = $params['csr'];
        $firstname = $params['firstname'];
        $lastname = $params['lastname'];
        $organisationname = $params['organisationname'];
        $jobtitle = $params['jobtitle'];
        $emailaddress = $params['email'];
        $address1 = $params['address1'];
        $address2 = $params['address2'];
        $city = $params['city'];
        $state = $params['state'];
        $postcode = $params['postcode'];
        $country = $params['country'];
        $phonenumber = $params['phonenumber'];
        $faxnumber = $params['faxnumber'];

        $values = array();
        //parse CSR submitted by the customer
        $csr_command = array( 'COMMAND' => 'ParseSSLCertCSR', 'CSR' => explode(PHP_EOL, $params['csr']));

        $registrar = $params['configoption2'];

        $csr_response = Helper::APICall($registrar, $csr_command);

        if ($csr_response['CODE'] != 200) {
            throw new Exception($csr_response['CODE'].' '.$csr_response['DESCRIPTION']);
        }
        //contact information from the parsed CSR
        $values['displaydata']['Domain'] = htmlspecialchars($csr_response['PROPERTY']['CN'][0]);
        $values['displaydata']['Organization'] = htmlspecialchars($csr_response['PROPERTY']['O'][0]);
        $values['displaydata']['Organization Unit'] = htmlspecialchars($csr_response['PROPERTY']['OU'][0]);
        $values['displaydata']['Email'] = htmlspecialchars($csr_response['PROPERTY']['EMAILADDRESS'][0]);
        $values['displaydata']['Locality'] = htmlspecialchars($csr_response['PROPERTY']['L'][0]);
        $values['displaydata']['State'] = htmlspecialchars($csr_response['PROPERTY']['ST'][0]);
        $values['displaydata']['Country'] = htmlspecialchars($csr_response['PROPERTY']['C'][0]);
        $values['approveremails'] = array();

        if ($params['configoptions']['Certificate Type']) {
            $certclass = $params['configoptions']['Certificate Type'];
        } else {
            $certclass = $params['configoption1'];
        }

        if ($params['configoptions']['Years']) {
            $certyears = $params['configoptions']['Years'];
        } else {
            $certyears = $params['configoption3'];
        }

        //approver email to the customer
        $appemail_command = array('COMMAND' => 'QuerySSLCertDCVEmailAddressList', 'SSLCERTCLASS' => $certclass, 'CSR' => explode(PHP_EOL, $params['csr']) );

        $appemail_response = Helper::APICall($registrar, $appemail_command);

        if (isset($appemail_response['PROPERTY']['EMAIL'])) {
            $values['approveremails'] = $appemail_response['PROPERTY']['EMAIL'];
        } else {
            $approverdomain = explode('.', preg_replace('/^\*\./', '', $csr_response['PROPERTY']['CN'][0]));

            if (count($approverdomain) < 2) {
                throw new Exception("Invalid CN in CSR");
            }

            if (count($approverdomain) == 2) {
                $approverdomain = implode('.', $approverdomain);
            } else {
                $tld = array_pop($approverdomain);
                $sld = array_pop($approverdomain);
                $dom = array_pop($approverdomain);

                if (preg_match('/^([a-z][a-z]|com|net|org|biz|info)$/i', $sld)) {
                    $approverdomain = $dom.'.'.$sld.'.'.$tld;
                } else {
                    $approverdomain = $sld.'.'.$tld;
                }
            }
            //to choose email by the customer to which approver email will be sent
            $approvers = array('admin', 'administrator', 'hostmaster', 'root', 'webmaster', 'postmaster');
            foreach ($approvers as $approver) {
                $values['approveremails'][] = $approver.'@'.$approverdomain;
            }
        }
        //perform a REPLACE with CreateSSLCert command to add CSR and contact data
        $command = array('ORDER' => 'REPLACE', 'ORDERID' => $orderid, 'COMMAND' => 'CreateSSLCert', 'SSLCERTCLASS' => $certclass, 'PERIOD' => $certyears, 'CSR' => explode(PHP_EOL, $params['csr']), 'SERVERSOFTWARE' => $ispapissl_server_map[$params['servertype']], 'SSLCERTDOMAIN' => $csr_response['PROPERTY']['CN'][0]);
        $contacttypes = array('', 'ADMINCONTACT', 'TECHCONTACT', 'BILLINGCONTACT');

        if (!strlen($params['jobtitle'])) {
            $params['jobtitle'] = 'N/A';
        }

        foreach ($contacttypes as $contacttype) {
            $command[$contacttype.'ORGANIZATION'] = $params['organisationname'];
            $command[$contacttype.'FIRSTNAME'] = $params['firstname'];
            $command[$contacttype.'LASTNAME'] = $params['lastname'];
            $command[$contacttype.'NAME'] = $params['firstname'].' '.$params['lastname'];
            $command[$contacttype.'JOBTITLE'] = $params['jobtitle'];
            $command[$contacttype.'EMAIL'] = $params['email'];
            $command[$contacttype.'STREET'] = $params['address1'];
            $command[$contacttype.'CITY'] = $params['city'];
            $command[$contacttype.'PROVINCE'] = $params['state'];
            $command[$contacttype.'ZIP'] = $params['postcode'];
            $command[$contacttype.'COUNTRY'] = $params['country'];
            $command[$contacttype.'PHONE'] = $params['phonenumber'];
            $command[$contacttype.'FAX'] = $params['faxnumber'];
        }

        $response = Helper::APICall($registrar, $command);

        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
        }
        //update tblhosting with domain(from CSR) and id
        Helper::SQLCall("UPDATE tblhosting SET domain=? WHERE id=?", array($csr_response['PROPERTY']['CN'][0], $params['serviceid']), "execute");
    } catch (Exception $e) {
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return array("error" => $e->getMessage());
    }

    return $values;
}

function ispapissl_sslstepthree($params)
{

    try {
        include(implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__),"ispapissl-config.php")));
        $orderid = $params['remoteid'];
        //configured configOptions
        if ($params['configoptions']['Certificate Type']) {
            $certclass = $params['configoptions']['Certificate Type'];
        } else {
            $certclass = $params['configoption1'];
        }

        if ($params['configoptions']['Years']) {
            $certyears = $params['configoptions']['Years'];
        } else {
            $certyears = $params['configoption3'];
        }

        $registrar = $params['configoption2'];
        //perform an UPDATE the  createSSLCert to add approver email
        $command = array('ORDER' => 'UPDATE', 'ORDERID' => $orderid, 'COMMAND' => 'CreateSSLCert', 'SSLCERTCLASS' => $certclass, 'PERIOD' => $certyears, 'EMAIL' => $params['approveremail']);

        $response = Helper::APICall($registrar, $command);

        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
        }
        //execute the order
        $command = array('COMMAND' => 'ExecuteOrder', 'ORDERID' => $orderid);

        $response = Helper::APICall($registrar, $command);

        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
        }
        //update the status of the order at WHMCS
        Helper::SQLCall("UPDATE tblsslorders SET completiondate=now(), status='Completed' WHERE serviceid=?", array($params['serviceid']), "execute");
    } catch (Exception $e) {
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return array("error" => $e->getMessage());
    }
}

/*
 * On ClientArea, product details page - the status of the purchased SSL certificate will be displayed.
 */
function ispapissl_ClientArea($params)
{

    include(implode(DIRECTORY_SEPARATOR, array(dirname(__FILE__),"ispapissl-config.php")));

    $data = Helper::SQLCall("SELECT id, remoteid, status FROM tblsslorders WHERE serviceid=?", array($params['serviceid']), "fetch");

    $params['remoteid'] = $data['remoteid'];
    $params['status'] = $data['status'];
    $sslorderid = $data['id'];
    $orderid = $params['remoteid'];

    //config options
    if ($params['configoptions']['Certificate Type']) {
        $certclass = $params['configoptions']['Certificate Type'];
    } else {
        $certclass = $params['configoption1'];
    }

    if ($params['configoptions']['Years']) {
        $certyears = $params['configoptions']['Years'];
    } else {
        $certyears = $params['configoption4'];
    }

    $registrar = $params['configoption2'];

    $ispapissl = array();
    $ispapissl['id'] = $params['serviceid'];
    $ispapissl['md5certid'] = md5($sslorderid);
    $ispapissl['status'] = $params['status'];

    $command = array('COMMAND' => 'QueryOrderList', 'ORDERID' => $orderid);

    $response = Helper::APICall($registrar, $command);

    if (isset($response['PROPERTY']['ORDERCOMMAND']) && strlen($response['PROPERTY']['ORDERCOMMAND'][0])) {
        $order_command = Helper::parseResponse($registrar, urldecode($response['PROPERTY']['ORDERCOMMAND'][0]));

        $csr = array();
        $i = 0;
        while (isset($order_command['CSR'.$i])) {
            if (strlen($order_command['CSR'.$i])) {
                $csr[] = $order_command['CSR'.$i];
            }
            ++$i;
        }

        $csr = implode(PHP_EOL, $csr);


        if (strlen($csr)) {
            $ispapissl['config']['csr'] = htmlspecialchars($csr);
        }

        $clientsdatamap = array('firstname' => 'ADMINCONTACTFIRSTNAME', 'lastname' => 'ADMINCONTACTLASTNAME', 'organisationname' => 'ADMINCONTACTORGANIZATION', 'jobtitle' => 'ADMINCONTACTJOBTITLE', 'email' => 'ADMINCONTACTEMAIL', 'address1' => 'ADMINCONTACTSTREET', 'city' => 'ADMINCONTACTCITY', 'state' => 'ADMINCONTACTPROVINCE', 'postcode' => 'ADMINCONTACTZIP', 'country' => 'ADMINCONTACTCOUNTRY', 'phonenumber' => 'ADMINCONTACTPHONE');
        foreach ($clientsdatamap as $key => $item) {
            if (isset($order_command[$item])) {
                $ispapissl['config'][$key] = htmlspecialchars($order_command[$item]);
                continue;
            }
        }
    }

    if ((isset($response['PROPERTY']['LASTRESPONSE']) && strlen($response['PROPERTY']['LASTRESPONSE'][0]))) {
        $order_response = Helper::parseResponse($registrar, urldecode($response['PROPERTY']['LASTRESPONSE'][0]));

        if (isset($order_response['PROPERTY']['SSLCERTID'])) {
            $cert_id = $order_response['PROPERTY']['SSLCERTID'][0];

            $status_command = array('COMMAND' => 'StatusSSLCert', 'SSLCERTID' => $cert_id);

            $status_response = Helper::APICall($registrar, $status_command);

            if (isset($status_response['PROPERTY']['CSR'])) {
                $csr = implode(PHP_EOL, $status_response['PROPERTY']['CSR']);
                $ispapissl['config']['csr'] = htmlspecialchars($csr);
            }

            if (isset($status_response['PROPERTY']['CRT'])) {
                $crt = implode(PHP_EOL, $status_response['PROPERTY']['CRT']);
                $ispapissl['crt'] = htmlspecialchars($crt);
            }

            if (isset($status_response['PROPERTY']['CACRT'])) {
                $cacrt = implode(PHP_EOL, $status_response['PROPERTY']['CACRT']);
                $ispapissl['cacrt'] = htmlspecialchars($cacrt);
            }
            if (isset($status_response['PROPERTY']['STATUS'])) {
                $ispapissl['processingstatus'] = htmlspecialchars($status_response['PROPERTY']['STATUS'][0]);

                if ($status_response['PROPERTY']['STATUS'][0] == 'ACTIVE') {
                    $exp_date = $status_response['PROPERTY']['REGISTRATIONEXPIRATIONDATE'][0];
                    $ispapissl['displaydata']['Expires'] = $exp_date;

                    Helper::SQLCall("UPDATE tblhosting SET nextduedate=?, domain=? WHERE id=?", array($exp_date, $status_response['PROPERTY']['SSLCERTCN'][0], $params['serviceid']), "execute");
                } else {
                    Helper::SQLCall("UPDATE tblhosting SET domain=? WHERE id=?", array($status_response['PROPERTY']['SSLCERTCN'][0], $params['serviceid']), "execute");
                }

                $ispapissl['displaydata']['CN'] = htmlspecialchars($status_response['PROPERTY']['SSLCERTCN'][0]);
            }

            if (isset($status_response['PROPERTY']['STATUSDETAILS'])) {
                $ispapissl['processingdetails'] = htmlspecialchars(urldecode($status_response['PROPERTY']['STATUSDETAILS'][0]));
            }
        }
    }


    $clientsdatamap = array('firstname' => 'firstname', 'lastname' => 'lastname', 'organisationname' => 'companyname', 'jobtitle' => '', 'email' => 'email', 'address1' => 'address1', 'address2' => 'address2', 'city' => 'city', 'state' => 'state', 'postcode' => 'postcode', 'country' => 'country', 'phonenumber' => 'phonenumber');
    foreach ($clientsdatamap as $key => $item) {
        if (!isset($ispapissl['config'][$key])) {
            $ispapissl['config'][$key] = htmlspecialchars($params['clientsdetails'][$item]);
            continue;
        }
    }

    if (!isset($ispapissl['config']['servertype'])) {
        $ispapissl['config']['servertype'] = '1002';
    }

    $_LANG = array('sslcrt' => 'Certificate', 'sslcacrt' => 'CA / Intermediate Certificate', 'sslprocessingstatus' => 'Processing Status', 'sslresendcertapproveremail' => 'Resend Approver Email');
    foreach ($_LANG as $key => $value) {
        if (!isset($GLOBALS['_LANG'][$key])) {
            $GLOBALS['_LANG'][$key] = $value;
            continue;
        }
    }
    //provide user a possibility to resend approver email to selected email id
    //when user enters a approver email in the text box:
    if (isset($_REQUEST['sslresendcertapproveremail']) && isset($_REQUEST['approveremail'])) {
        $ispapissl['approveremail'] = $_REQUEST['approveremail'];
        $resend_command = array('COMMAND' => 'ResendSSLCertEmail', 'SSLCERTID' => $cert_id, 'EMAIL' => $_REQUEST['approveremail']);

        $resend_response = Helper::APICall($registrar, $resend_command);

        if ($resend_response['CODE'] == 200) {
            unset($_REQUEST[sslresendcertapproveremail]);
            $ispapissl['successmessage'] = 'Successfully resent the approver email';
        } else {
            $ispapissl['errormessage'] = $resend_response['DESCRIPTION'];
        }
    }

    //when user chooses from the listed approver emails
    if (isset($_REQUEST['sslresendcertapproveremail'])) {
        $ispapissl['sslresendcertapproveremail'] = 1;
        $ispapissl['approveremails'] = array();
        $appemail_command = array('COMMAND' => 'QuerySSLCertDCVEmailAddressList', 'SSLCERTID' => $cert_id);

        if ($resend_response['CODE'] == 200) {
            $ispapissl['successmessage'] = $resend_response['DESCRIPTION'];
        } else {
            $ispapissl['errormessage'] = $resend_response['DESCRIPTION'];
        }

        $appemail_response = Helper::APICall($registrar, $appemail_command);
        if (isset($appemail_response['PROPERTY']['EMAIL'])) {
            $ispapissl['approveremails'] = $appemail_response['PROPERTY']['EMAIL'];
        } else {
            $approverdomain = explode('.', preg_replace('/^\*\./', '', $status_response['PROPERTY']['SSLCERTCN'][0]));

            if (count($approverdomain) < 2) {
                $ispapissl['errormessage'] = 'Invalid Domain';
            } else {
                if (count($approverdomain) == 2) {
                    $approverdomain = implode('.', $approverdomain);
                } else {
                    $tld = array_pop($approverdomain);
                    $sld = array_pop($approverdomain);
                    $dom = array_pop($approverdomain);

                    if (preg_match('/^([a-z][a-z]|com|net|org|biz|info)$/i', $sld)) {
                        $approverdomain = $dom.'.'.$sld.'.'.$tld;
                    } else {
                        $approverdomain = $sld.'.'.$tld;
                    }
                }

                $approvers = array('admin', 'administrator', 'hostmaster', 'root', 'webmaster', 'postmaster');

                foreach ($approvers as $approver) {
                    $ispapissl['approveremails'][] = $approver.'@'.$approverdomain;
                }
            }
        }
    }
    //display status and other details of the configured ssl certificates on clientarea
    $templateFile = "ispapissl-clientarea.tpl";
    return array(
        'tabOverviewReplacementTemplate' => $templateFile,
        'templateVariables' => array(
            'ispapissl' => $ispapissl,
            'LANG' => $GLOBALS['_LANG']
        ),
    );
}
