<?php

use WHMCS\Database\Capsule;
use ISPAPISSL\Helper;

require_once(dirname(__FILE__)."/../../../includes/registrarfunctions.php");

require_once(dirname(__FILE__)."/lib/Helper.class.php");
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


function ispapissl_MetaData()
{
    return array(
        'DisplayName' => 'ISPAPI SSL Certificates',
    );
}

function ispapissl_ConfigOptions()
{
    $data = Helper::SQLCall("SELECT * FROM tblemailtemplates WHERE name='SSL Certificate Configuration Required'", array(), "fetchall");

    if(empty($data)){

        // TODO adding variables in the text of the column causes the problem 
         #$test = Helper::SQLCall("INSERT INTO tblemailtemplates (type, name, subject, message, fromname, fromemail, disabled, custom, language, copyto, plaintext) VALUES ('product', 'SSL Certificate Configuration Required', 'SSL Certificate Configuration Required', '<p>Dear '.'$client_name'.',</p><p>Thank you for your order for an SSL Certificate. Before you can use your certificate, it requires configuration which can be done at the URL below.</p><p>{$ssl_configuration_link}</p><p>Instructions are provided throughout the process but if you experience any problems or have any questions, please open a ticket for assistance.</p><p>{$signature}</p>', '', '', '', '', '', '', '0')", array(), "execute");

        full_query('INSERT INTO `tblemailtemplates` (`type` ,`name` ,`subject` ,`message` ,`fromname` ,`fromemail` ,`disabled` ,`custom` ,`language` ,`copyto` ,`plaintext` )VALUES (\'product\', \'SSL Certificate Configuration Required\', \'SSL Certificate Configuration Required\', \'<p>Dear {$client_name},</p><p>Thank you for your order for an SSL Certificate. Before you can use your certificate, it requires configuration which can be done at the URL below.</p><p>{$ssl_configuration_link}</p><p>Instructions are provided throughout the process but if you experience any problems or have any questions, please open a ticket for assistance.</p><p>{$signature}</p>\', \'\', \'\', \'\', \'\', \'\', \'\', \'0\')');
    }


    return array(
        'Certificate Class' => array(
            'Type' => 'text',
            'Size' => '25',
        ),
        'Registrar' => array(
            'Type' => 'text',
            'Size' => '25',
        ),
        'Years' => array(
            'Type' => 'dropdown',
            'Options' => '1,2,3,4,5,6,7,8,9,10'
        )
    );
}

function ispapissl_CreateAccount(array $params)
{

    try {
        include(dirname( __FILE__ ).DIRECTORY_SEPARATOR.'ispapissl-config.php');

        $data = Helper::SQLCall("SELECT * FROM tblsslorders WHERE serviceid=?", array($params['serviceid']), "fetchall");

        if(!empty($data)){
            throw new Exception("An SSL Order already exists for this order");
        }

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

        $command = array( 'ORDER' => 'CREATE',
                          'COMMAND' => 'CreateSSLCert',
                          'SSLCERTCLASS' => $certclass,
                          'PERIOD' => $certyears );


        $response = Helper::APICall($registrar, $command);

		if($response['CODE'] != 200 ) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
		}

		$orderid = $response['PROPERTY']['ORDERID'][0];

        //insert certificate order in the DB along with the status
        Helper::SQLCall("INSERT INTO tblsslorders (userid, serviceid, remoteid, module, certtype, status) VALUES (?, ?, ?, 'ispapissl', ?, 'Awaiting Configuration')", array($params['clientsdetails']['userid'], $params['serviceid'], $orderid, $certclass), "execute");
        //get the id (sslorderid) of the inserted item
        $sslorderid = Helper::SQLCall("SELECT id from tblsslorders WHERE remoteid=?", array($orderid), "fetch");

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

function ispapissl_AdminCustomButtonArray() {
    return array('Resend Configuration Email' => 'resend');
}

//resend configuration link if the product exists. click on 'Resend Configuration Email'
function ispapissl_resend($params) {

    try {
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

function ispapissl_sslstepone($params) {

    $registrar = $params['configoption2'];

    try {
        $orderid = $params['remoteid'];

        if (!$_SESSION['ispapisslcert'][$orderid]['id']) {
            $command = array('COMMAND' => 'QueryOrderList', 'ORDERID' => $orderid);
            $response = Helper::APICall($registrar, $command);

            if ($response['CODE'] != 200) {
                throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
            }

            $cert_allowconfig = true;

            if (isset($response['PROPERTY']['LASTRESPONSE']) && strlen($response['PROPERTY']['LASTRESPONSE'][0])) {

                $order_response = Helper::ParseResponse($registrar, urldecode($response['PROPERTY']['LASTRESPONSE'][0]));

                if (isset($order_response['PROPERTY']['SSLCERTID'])) {
                    $_SESSION['ispapisslcert'][$orderid]['id'] = $order_response['PROPERTY']['SSLCERTID'][0];
                    $cert_allowconfig = false;
                }
            }

            if (!$cert_allowconfig) {
                //TODO
                //update_query('tblsslorders', array('completiondate' => 'now()', 'status' => 'Completed'), array('serviceid' => $params['serviceid'], 'status' => array('sqltype' => 'NEQ', 'value' => 'Completed')));
                
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

function ispapissl_sslsteptwo($params) {

    try {
        include(dirname( __FILE__ ).DIRECTORY_SEPARATOR.'ispapissl-config.php');

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
        $csr_command = array( 'COMMAND' => 'ParseSSLCertCSR', 'CSR' => explode('
', $params['csr'] ));

        $registrar = $params['configoption2'];

        $csr_response = Helper::APICall($registrar, $csr_command);

        if ($csr_response['CODE'] != 200 ) {
            throw new Exception($csr_response['CODE'].' '.$csr_response['DESCRIPTION']);
        }

        $values['displaydata']['Domain'] = htmlspecialchars( $csr_response['PROPERTY']['CN'][0] );
        $values['displaydata']['Organization'] = htmlspecialchars( $csr_response['PROPERTY']['O'][0] );
        $values['displaydata']['Organization Unit'] = htmlspecialchars( $csr_response['PROPERTY']['OU'][0] );
        $values['displaydata']['Email'] = htmlspecialchars( $csr_response['PROPERTY']['EMAILADDRESS'][0] );
        $values['displaydata']['Locality'] = htmlspecialchars( $csr_response['PROPERTY']['L'][0] );
        $values['displaydata']['State'] = htmlspecialchars( $csr_response['PROPERTY']['ST'][0] );
        $values['displaydata']['Country'] = htmlspecialchars( $csr_response['PROPERTY']['C'][0] );
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


        $appemail_command = array('COMMAND' => 'QuerySSLCertDCVEmailAddressList', 'SSLCERTCLASS' => $certclass, 'CSR' => explode('
', $params['csr'] ) );

        $appemail_response = Helper::APICall($registrar, $appemail_command);

        if (isset($appemail_response['PROPERTY']['EMAIL'])) {

            $values['approveremails'] = $appemail_response['PROPERTY']['EMAIL'];
        } else {
            $approverdomain = explode('.', preg_replace( '/^\*\./', '', $csr_response['PROPERTY']['CN'][0]));

            if (count($approverdomain) < 2) {
                throw new Exception("Invalid CN in CSR");
            }

            if (count($approverdomain) == 2) {
                $approverdomain = implode('.', $approverdomain);
            } else {
                $tld = array_pop($approverdomain);
                $sld = array_pop($approverdomain);
                $dom = array_pop($approverdomain);

                if (preg_match( '/^([a-z][a-z]|com|net|org|biz|info)$/i', $sld)) {
                    $approverdomain = $dom.'.'.$sld.'.'.$tld;
                } else {
                    $approverdomain = $sld.'.'.$tld;
                }
            }

            $approvers = array('admin', 'administrator', 'hostmaster', 'root', 'webmaster', 'postmaster');
            foreach ($approvers as $approver) {
                $values['approveremails'][] = $approver.'@'.$approverdomain;
            }
        }

        $command = array('ORDER' => 'REPLACE', 'ORDERID' => $orderid, 'COMMAND' => 'CreateSSLCert', 'SSLCERTCLASS' => $certclass, 'PERIOD' => $certyears, 'CSR' => explode('
', $params['csr']), 'SERVERSOFTWARE' => $ispapissl_server_map[$params['servertype']], 'SSLCERTDOMAIN' => $csr_response['PROPERTY']['CN'][0]);
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

function ispapissl_sslstepthree($params) {

    try{
        include(dirname( __FILE__ ).DIRECTORY_SEPARATOR.'ispapissl-config.php');
        $orderid = $params['remoteid'];

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

        $command = array('ORDER' => 'UPDATE', 'ORDERID' => $orderid, 'COMMAND' => 'CreateSSLCert', 'SSLCERTCLASS' => $certclass, 'PERIOD' => $certyears, 'EMAIL' => $params['approveremail']);

        $response = Helper::APICall($registrar, $command);

        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
        }

        $command = array('COMMAND' => 'ExecuteOrder', 'ORDERID' => $orderid);

        $response = Helper::APICall($registrar, $command);

        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
        }
        // TODO
        //update_query('tblsslorders', array('completiondate' => 'now()', 'status' => 'Completed'), array('serviceid' => $params['serviceid'], 'status' => array('sqltype' => 'NEQ', 'value' => 'Completed')));

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

function ispapissl_ClientArea($params) {

    include(dirname( __FILE__ ).DIRECTORY_SEPARATOR.'ispapissl-config.php');

    $data = Helper::SQLCall("SELECT id, remoteid, status FROM tblsslorders WHERE serviceid=?", array($params['serviceid']), "fetch");

    $params['remoteid'] = $data['remoteid'];
    $params['status'] = $data['status'];
    $sslorderid = $data['id'];
    $orderid = $params['remoteid'];

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

        $order_command = Helper::ParseResponse($registrar, urldecode($response['PROPERTY']['ORDERCOMMAND'][0]));

        $csr = array();
        $i = 0;
        while (isset($order_command['CSR'.$i])) {
            if (strlen($order_command['CSR'.$i])) {
                $csr[] = $order_command['CSR'.$i];
            }
            ++$i;
        }

        $csr = implode( '
', $csr );

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

    if ((isset( $response['PROPERTY']['LASTRESPONSE']) && strlen($response['PROPERTY']['LASTRESPONSE'][0]))) {

        $order_response = Helper::ParseResponse($registrar, urldecode($response['PROPERTY']['LASTRESPONSE'][0]));

        if (isset($order_response['PROPERTY']['SSLCERTID'])) { 
            $cert_id = $order_response['PROPERTY']['SSLCERTID'][0];

            $status_command = array('COMMAND' => 'StatusSSLCert', 'SSLCERTID' => $cert_id);

            $status_response = Helper::APICall($registrar, $status_command);

            if (isset($status_response['PROPERTY']['CSR'])) {
                $csr = implode( '
', $status_response['PROPERTY']['CSR'] );
                $ispapissl['config']['csr'] = htmlspecialchars($csr);
            }

            if (isset($status_response['PROPERTY']['CRT'])) {
                $crt = implode( '
', $status_response['PROPERTY']['CRT'] );
                $ispapissl['crt'] = htmlspecialchars($crt);
            }

            if (isset($status_response['PROPERTY']['CACRT'])) {
                $cacrt = implode( '
', $status_response['PROPERTY']['CACRT'] );
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

    if (!isset( $ispapissl['config']['servertype'] )) {
        $ispapissl['config']['servertype'] = '1002';
    }

    $_LANG = array('sslcrt' => 'Certificate', 'sslcacrt' => 'CA / Intermediate Certificate', 'sslprocessingstatus' => 'Processing Status', 'sslresendcertapproveremail' => 'Resend Approver Email');
    foreach ($_LANG as $key => $value) {
        if (!isset($GLOBALS['_LANG'][$key])) {
            $GLOBALS['_LANG'][$key] = $value;
            continue;
        }
    }

// TODO Where is this option $_REQUEST['sslresendcertapproveremail'] - could not find it? //IMO never triggered 
    if (isset($_REQUEST['sslresendcertapproveremail']) && isset($_REQUEST['approveremail'])) {
        $resend_command = array('COMMAND' => 'ResendSSLCertEmail', 'SSLCERTID' => $cert_id, 'EMAIL' => $_REQUEST['approveremail']);

        $resend_response = Helper::APICall($registrar, $resend_command);

        if ($resend_response['CODE'] == 200) {
            unset($_REQUEST[sslresendcertapproveremail]); //TODO this is a mistake (in the previous version) - should be as follows. Meaning this never been triggered. Otherwise we would have received complaints from customers 
            // unset($_REQUEST['sslresendcertapproveremail']);
        } else {
            $ispapissl['errormessage'] = $resend_response['DESCRIPTION'];
        }
    }
    // TODO Where is this option $_REQUEST['sslresendcertapproveremail']  - could not find it? //IMO never triggered 
    if (isset($_REQUEST['sslresendcertapproveremail'])) {
        $ispapissl['sslresendcertapproveremail'] = 1;
        $ispapissl['approveremails'] = array();
        $appemail_command = array('COMMAND' => 'QuerySSLCertDCVEmailAddressList', 'SSLCERTID' => $cert_id);

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

    $templateFile = "ispapissl-clientarea.tpl";
    return array(
        'tabOverviewReplacementTemplate' => $templateFile,
        'templateVariables' => array(
            'ispapissl' => $ispapissl,
            'LANG' => $GLOBALS['_LANG']
        ),
    );
}

global $ispapissl_module_version;
$ispapissl_module_version = '7.0.0';



// sample CSR - https://www.digicert.com/order/sample-csr.php


