<?php

//TODO
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
    //this file for ispapissl_server_map 'SERVERSOFTWARE' => $ispapissl_server_map[$params['servertype']]
    include(dirname( __FILE__ ).DIRECTORY_SEPARATOR.'ispapissl-config.php');

    $data = Helper::SQLCall("SELECT * FROM tblemailtemplates WHERE name='SSL Certificate Configuration Required'", array(), "fetchall");

    if(empty($data)){
        // TODO test this query - this query is not working - at the moment I am not able to see what is the error - I will update it later with SQLCall
        // $test7 = Helper::SQLCall("INSERT INTO tblemailtemplates (type, name, subject, message, fromname, fromemail, disabled, custom, language, copyto, plaintext) VALUES (product, 'SSL Certificate Configuration Required', 'SSL Certificate Configuration Required', '<p>Dear {$client_name},</p><p>Thank you for your order for an SSL Certificate. Before you can use your certificate, it requires configuration which can be done at the URL below.</p><p>{$ssl_configuration_link}</p><p>Instructions are provided throughout the process but if you experience any problems or have any questions, please open a ticket for assistance.</p><p>{$signature}</p>', '', '', '', '', '', '', '0')", array(), "execute");

        $test7 = full_query('INSERT INTO `tblemailtemplates` (`type` ,`name` ,`subject` ,`message` ,`fromname` ,`fromemail` ,`disabled` ,`custom` ,`language` ,`copyto` ,`plaintext` )VALUES (\'product\', \'SSL Certificate Configuration Required\', \'SSL Certificate Configuration Required\', \'<p>Dear {$client_name},</p><p>Thank you for your order for an SSL Certificate. Before you can use your certificate, it requires configuration which can be done at the URL below.</p><p>{$ssl_configuration_link}</p><p>Instructions are provided throughout the process but if you experience any problems or have any questions, please open a ticket for assistance.</p><p>{$signature}</p>\', \'\', \'\', \'\', \'\', \'\', \'\', \'0\')');
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

//when user chooses any following option => 'cancel' the order or 'Resend Configuration Email' on WHMCS admin area
function ispapissl_AdminCustomButtonArray() {
    return array( 'Cancel' => 'cancel', 'Resend Configuration Email' => 'resend');
}

//'Cancel'
function ispapissl_cancel($params) {

    try {
        //TODO - test once again the queries
        $data = Helper::SQLCall("SELECT * FROM tblsslorders WHERE serviceid=? AND status='Awaiting Configuration'", array($params['serviceid']), "fetchall");

        if(empty($data)){
            throw new Exception('No incomplete SSL Order exists for this order');
        }

        Helper::SQLCall("UPDATE tblsslorders SET status='Cancelled' WHERE serviceid=?", array($params['serviceid']), "execute");

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

//resend configuration link if the product exists. click on 'Rsend Configuration Email'
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

//Renew certificate TODO - not tested on OTE or PRODUCTION so far
function ispapissl_renew($params) {

    try {
        $data = Helper::SQLCall("SELECT * FROM tblsslorders WHERE serviceid=?", array($params['serviceid']), "fetch");

        $orderid = $data['remoteid'];

        $registrar = $params['configoption2'];

        if (!$data) {
            throw new Exception('No active SSL Order exists for this order');
        }
        //need SSLCERTID from this command and also last response which includes CSR and other details
        $command = array('COMMAND' => 'QueryOrderList', 'ORDERID' => $orderid);

        $response = Helper::APICall($registrar, $command);

        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
        }

        if (isset( $response['PROPERTY']['LASTRESPONSE']) && strlen($response['PROPERTY']['LASTRESPONSE'][0])) {
            $order_response = Helper::ParseResponse($registrar, urldecode($response['PROPERTY']['LASTRESPONSE'][0]));

            //get SSLCERTID to get the status information. Since I want to send RenewSSLCert command only of the STATUS of the certi is active
            if (isset($order_response['PROPERTY']['SSLCERTID'])) {
                $cert_id = $order_response['PROPERTY']['SSLCERTID'][0];
                $status_command = array('COMMAND' => 'StatusSSLCert', 'SSLCERTID' => $cert_id);

                $status_response = Helper::APICall($registrar, $status_command);

                if(isset($status_response['PROPERTY']['STATUS']) && strlen($status_response['PROPERTY']['STATUS'][0] == 'PENDINGCREATE')){ //TODO=== 'ACTIVE'

                    //send a renew command
                    $command = array('COMMAND' => 'RenewSSLCert', 'SSLCERTID' => $cert_id);

                    $response = Helper::APICall($registrar, $command);

                    if ($response['CODE'] != 200) {
                        throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
                    }
                    //TODO - what is next ?
                    //update something here if got a success response?
                            //be a database update? (on whmcs based on the result from response?) (which table?)

                }
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
         return $e->getMessage();
     }
     return 'success';
}

//steps one, two and three are involved in configuration of ssl certificate order
function ispapissl_sslstepone($params) {

    $registrar = $params['configoption2'];

    try {
        include(dirname( __FILE__ ).DIRECTORY_SEPARATOR.'ispapissl-config.php');
        $orderid = $params['remoteid'];

        if (!$_SESSION['ispapisslcert'][$orderid]['id']) {
            $command = array('COMMAND' => 'QueryOrderList', 'ORDERID' => $orderid);

            $response = Helper::APICall($registrar, $command);

            if ($response['CODE'] != 200) {
                throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
            }

            $cert_allowconfig = true;

            //clicked the configuration email for the second time//TODO-delete this comment
            if (isset($response['PROPERTY']['LASTRESPONSE']) && strlen($response['PROPERTY']['LASTRESPONSE'][0])) {

                $order_response = Helper::ParseResponse($registrar, urldecode($response['PROPERTY']['LASTRESPONSE'][0]));

                if (isset($order_response['PROPERTY']['SSLCERTID'])) {
                    $_SESSION['ispapisslcert'][$orderid]['id'] = $order_response['PROPERTY']['SSLCERTID'][0];
                    $cert_allowconfig = false;
                }
            }

            if (!$cert_allowconfig) {

                // TODO - test query - this one is complicated
                // this query is not understandable for me - i will update it later using SQLCall
                $test1 = update_query('tblsslorders', array('completiondate' => 'now()', 'status' => 'Completed'), array('serviceid' => $params['serviceid'], 'status' => array('sqltype' => 'NEQ', 'value' => 'Completed')));

                // $test1 = Helper::SQLCall("UPDATE tblsslorders SET completiondate=now(), status=Completed WHERE serviceid=? AND status=?", array($params['serviceid'], array('sqltype' => 'NEQ', 'value' => 'Completed')), "execute");
                // mail("tseelamkurthi@hexonet.net", "ispapissl_sslstepone-test1-update-SQLCall", print_r($test1, true));

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
        // TODO - test query - this query is not understandable for me - i will update it later to SQLCall
        $test4 = update_query('tblsslorders', array('completiondate' => 'now()', 'status' => 'Completed'), array('serviceid' => $params['serviceid'], 'status' => array('sqltype' => 'NEQ', 'value' => 'Completed')));
        // mail("tseelamkurthi@hexonet.net", "ispapissl_sslstepthree-test4-update", print_r($test4, true));
        // 1 - when finished configuration set up

        // $test4 = Helper::SQLCall("UPDATE tblsslorders SET completiondate='now()', status=Completed WHERE serviceid=? AND status=?", array($params['serviceid'], array('sqltype' => 'NEQ', 'value' => 'Completed')), "execute");
        // mail("tseelamkurthi@hexonet.net", "ispapissl_sslstepthree-test4-update-SQLCall", print_r($test4, true));

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
    // all the following data is need to display in the client area //TODO -remove the comment
    // Manage Product
    // SSL Certificate Information

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

    $cert_allowconfig = true;
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

    if (isset( $response['PROPERTY']['LASTRESPONSE']) && strlen($response['PROPERTY']['LASTRESPONSE'][0])) {

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
                    // TODO test this query
                    // Helper::SQLCall("UPDATE tblhosting SET nextduedate=?, domain=? WHERE serviceid=?", array($exp_date, $status_response['PROPERTY']['SSLCERTCN'][0], $params['serviceid']), "execute");

                    $test5 = update_query('tblhosting', array('nextduedate' => $exp_date, 'domain' => $status_response['PROPERTY']['SSLCERTCN'][0]), array('id' => $params['serviceid']));

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

// TODO Where is this option $_REQUEST['sslresendcertapproveremail'] be triggered?
    if (isset($_REQUEST['sslresendcertapproveremail']) && isset($_REQUEST['approveremail'])) {
        $resend_command = array('COMMAND' => 'ResendSSLCertEmail', 'SSLCERTID' => $cert_id, 'EMAIL' => $_REQUEST['approveremail']);

        $resend_response = Helper::APICall($registrar, $resend_command);

        if ($resend_response['CODE'] == 200) {
            unset($_REQUEST[sslresendcertapproveremail]); //TODO this is a mistake - should be as follows
            // unset($_REQUEST['sslresendcertapproveremail']);
        } else {
            $ispapissl['errormessage'] = $resend_response['DESCRIPTION'];
        }
    }

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

// TODO: currently am working on:
// 1. Test all updated sql queries - almost tested - except the two ununderstandle queries
// 2. remove $test variable when testing finished

//TODO
// 2. After 14 days of no activation of SSL => Incomplete SSL will be removed. Customer will be refunded *  ==> this needs to be done in this module?
// 3. what is it about 'reissuing ssl certificate'


// when I tried to configure "Comodo Essentialssl Wildcard" it gives the following error:
        // The following errors occurred:
        //
        //    541 Invalid attribute value; wildcard Common Name (CN) required for this class
//because - wildcard is for subdomains

// TODO - need to test my code-  complete process of  buying and renewing SSL certificate
//currently I am able to create a certificate and configure it. but status cannot be changed in the OTE system from pendingcreate to ACTIVE?
// am not sure how thats work
//if STATUS set to ACTIVE then I want to test my renew command code

// about renewing certificate => am not sure about many things - how it works entirely
//need an investigation

//what else need to be done?




// https://gitlab.hexonet.net/back-end-team/api-docs/blob/master/API/SSLCERT/RENEWSSLCERT.md

//Information about renew SSL certificates:

//from WHMCS => "This function runs each time a renewal invoice for a product becomes paid."

//from confluence -
//  A new Order is created/charged and thus a new certificate using the same configure as the existing the SSL,
// this new certificate is submitted for validation by OpenSRS/CA
// If the new SSL certificate is accepted it is set to ACTIVE and the old one is set to REPLACED
// If the validation fails then the entire new order can be cancelled and a refund is processed automatically

// Certificate renewal can only be done 60 days before the expiration date.
// Control panel will display the option to renew certificate regardless on whether the certificate is renewable at that point in time.
// Customer will still be able to create a renewal order and add to the shopping cart.
// However, if renewal order is executed before the certificate is allowed to be renewed, the renewal order command will fail.

// https://confluence.hexonet.net/display/HXP/Support+-+Renew+SSL+certificate

//from git
// https://gitlab.hexonet.net/back-end-team/xcore-service-sslcerts/blob/master/modules/command.RenewSSLCert.pl
