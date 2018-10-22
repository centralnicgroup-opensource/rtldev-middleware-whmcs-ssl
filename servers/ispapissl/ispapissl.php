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

global $module_version;
$module_version = "7.1.0";

function ispapissl_MetaData()
{
    return array(
        'DisplayName' => 'ISPAPI SSL Certificates',
    );
}

function ispapissl_ConfigOptions()
{
    include(dirname(__FILE__).DIRECTORY_SEPARATOR.'ispapissl-config.php');
    $result = select_query('tblemailtemplates', 'COUNT(*)', array( 'name' => 'SSL Certificate Configuration Required'));
    $data = mysql_fetch_array($result);
    if (!$data[0]) {
        full_query('INSERT INTO `tblemailtemplates` (`type` ,`name` ,`subject` ,`message` ,`fromname` ,`fromemail` ,`disabled` ,`custom` ,`language` ,`copyto` ,`plaintext` )VALUES (\'product\', \'SSL Certificate Configuration Required\', \'SSL Certificate Configuration Required\', \'<p>Dear {$client_name},</p><p>Thank you for your order for an SSL Certificate. Before you can use your certificate, it requires configuration which can be done at the URL below.</p><p>{$ssl_configuration_link}</p><p>Instructions are provided throughout the process but if you experience any problems or have any questions, please open a ticket for assistance.</p><p>{$signature}</p>\', \'\', \'\', \'\', \'\', \'\', \'\', \'0\')');
    }

    return array(
        'Username' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => '',
        ),
        'Password' => array(
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => '',
        ),
        'Certificate Type' => array(
            'Type' => 'dropdown',
            'Options' => implode(',', array_keys($ispapissl_cert_map))
        ),
        'Years' => array(
            'Type' => 'dropdown',
            'Options' => '1,2,3,4,5,6,7,8,9,10'
        ),
        'Test Mode' => array(
            'Type' => 'yesno'
        )
    );
}

function ispapissl_CreateAccount(array $params)
{
    try {
        include(dirname(__FILE__).DIRECTORY_SEPARATOR.'ispapissl-config.php');
        $result = select_query('tblsslorders', 'COUNT(*)', array('serviceid' => $params['serviceid']));
        $data = mysql_fetch_array($result);
        if ($data[0]) {
            throw new Exception("An SSL Order already exists for this order");
        }

        if ($params['configoptions']['Certificate Type']) {
            $certtype = $params['configoptions']['Certificate Type'];
        } else {
            $certtype = $params['configoption3'];
        }

        if ($params['configoptions']['Years']) {
            $certyears = $params['configoptions']['Years'];
        } else {
            $certyears = $params['configoption4'];
        }

        $command = array( 'ORDER' => 'CREATE',
                          'COMMAND' => 'CreateSSLCert',
                          'SSLCERTCLASS' => $ispapissl_cert_map[$certtype],
                          'PERIOD' => $certyears );

        $response = ispapissl_call($command, ispapissl_config($params));

        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
        }

        $orderid = $response['PROPERTY']['ORDERID'][0];
        $sslorderid = insert_query(
            'tblsslorders',
            array( 'userid' => $params['clientsdetails']['userid'],
                                           'serviceid' => $params['serviceid'],
                                           'remoteid' => $orderid,
                                           'module' => 'ispapissl',
                                           'certtype' => $certtype,
            'status' => 'Awaiting Configuration')
        );
        global $CONFIG;
        $sslconfigurationlink = $CONFIG['SystemURL'].'/configuressl.php?cert='.md5($sslorderid);
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

function ispapissl_AdminCustomButtonArray()
{
    return array( 'Cancel' => 'cancel', 'Resend Configuration Email' => 'resend' );
}

function ispapissl_cancel($params)
{
    try {
        $result = select_query('tblsslorders', 'COUNT(*)', array('serviceid' => $params['serviceid'], 'status' => 'Awaiting Configuration'));
        $data = mysql_fetch_array($result);
        if (!$data[0]) {
            throw new Exception('No incomplete SSL Order exists for this order');
        }
        update_query('tblsslorders', array('status' => 'Cancelled'), array('serviceid' => $params['serviceid']));
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

function ispapissl_resend($params)
{
    try {
        $result = select_query('tblsslorders', 'id', array('serviceid' => $params['serviceid']));
        $data = mysql_fetch_array($result);
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


function ispapissl_sslstepone($params)
{
    try {
        include(dirname(__FILE__).DIRECTORY_SEPARATOR.'ispapissl-config.php');
        $orderid = $params['remoteid'];

        if (!$_SESSION['ispapisslcert'][$orderid]['id']) {
            $command = array('COMMAND' => 'QueryOrderList', 'ORDERID' => $orderid);
            $response = ispapissl_call($command, ispapissl_config($params));

            if ($response['CODE'] != 200) {
                throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
            }

            $cert_allowconfig = true;
            if (isset($response['PROPERTY']['LASTRESPONSE']) && strlen($response['PROPERTY']['LASTRESPONSE'][0])) {
                $order_response = ispapissl_parse_response(urldecode($response['PROPERTY']['LASTRESPONSE'][0]));

                if (isset($order_response['PROPERTY']['SSLCERTID'])) {
                    $_SESSION['ispapisslcert'][$orderid]['id'] = $order_response['PROPERTY']['SSLCERTID'][0];
                    $cert_allowconfig = false;
                }
            }

            if (!$cert_allowconfig) {
                update_query('tblsslorders', array('completiondate' => 'now()', 'status' => 'Completed'), array('serviceid' => $params['serviceid'], 'status' => array('sqltype' => 'NEQ', 'value' => 'Completed')));
            } else {
                update_query('tblsslorders', array('completiondate' => '', 'status' => 'Awaiting Configuration'), array('serviceid' => $params['serviceid']));
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
        include(dirname(__FILE__).DIRECTORY_SEPARATOR.'ispapissl-config.php');

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
', $params['csr']));
        $csr_response = ispapissl_call($csr_command, ispapissl_config($params));

        if ($csr_response['CODE'] != 200) {
            throw new Exception($csr_response['CODE'].' '.$csr_response['DESCRIPTION']);
        }

        $values['displaydata']['Domain'] = htmlspecialchars($csr_response['PROPERTY']['CN'][0]);
        $values['displaydata']['Organization'] = htmlspecialchars($csr_response['PROPERTY']['O'][0]);
        $values['displaydata']['Organization Unit'] = htmlspecialchars($csr_response['PROPERTY']['OU'][0]);
        $values['displaydata']['Email'] = htmlspecialchars($csr_response['PROPERTY']['EMAILADDRESS'][0]);
        $values['displaydata']['Locality'] = htmlspecialchars($csr_response['PROPERTY']['L'][0]);
        $values['displaydata']['State'] = htmlspecialchars($csr_response['PROPERTY']['ST'][0]);
        $values['displaydata']['Country'] = htmlspecialchars($csr_response['PROPERTY']['C'][0]);
        $values['approveremails'] = array();

        if ($params['configoptions']['Certificate Type']) {
            $certtype = $params['configoptions']['Certificate Type'];
        } else {
            $certtype = $params['configoption3'];
        }

        if ($params['configoptions']['Years']) {
            $certyears = $params['configoptions']['Years'];
        } else {
            $certyears = $params['configoption4'];
        }

        $appemail_command = array('COMMAND' => 'QuerySSLCertDCVEmailAddressList', 'SSLCERTCLASS' => $ispapissl_cert_map[$certtype], 'CSR' => explode('
', $params['csr']) );
        $appemail_response = ispapissl_call($appemail_command, ispapissl_config($params));

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

            $approvers = array('admin', 'administrator', 'hostmaster', 'root', 'webmaster', 'postmaster');
            foreach ($approvers as $approver) {
                $values['approveremails'][] = $approver.'@'.$approverdomain;
            }
        }

        $command = array('ORDER' => 'REPLACE', 'ORDERID' => $orderid, 'COMMAND' => 'CreateSSLCert', 'SSLCERTCLASS' => $ispapissl_cert_map[$certtype], 'PERIOD' => $certyears, 'CSR' => explode('
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

        $response = ispapissl_call($command, ispapissl_config($params));

        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
        }
        update_query('tblhosting', array('domain' => $csr_response['PROPERTY']['CN'][0] ), array('id' => $params['serviceid']));
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
        include(dirname(__FILE__).DIRECTORY_SEPARATOR.'ispapissl-config.php');
        $orderid = $params['remoteid'];

        if ($params['configoptions']['Certificate Type']) {
            $certtype = $params['configoptions']['Certificate Type'];
        } else {
            $certtype = $params['configoption3'];
        }

        if ($params['configoptions']['Years']) {
            $certyears = $params['configoptions']['Years'];
        } else {
            $certyears = $params['configoption4'];
        }

        $command = array('ORDER' => 'UPDATE', 'ORDERID' => $orderid, 'COMMAND' => 'CreateSSLCert', 'SSLCERTCLASS' => $ispapissl_cert_map[$certtype], 'PERIOD' => $certyears, 'EMAIL' => $params['approveremail']);
        $response = ispapissl_call($command, ispapissl_config($params));
        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
        }

        $command = array('COMMAND' => 'ExecuteOrder', 'ORDERID' => $orderid);
        $response = ispapissl_call($command, ispapissl_config($params));
        if ($response['CODE'] != 200) {
            throw new Exception($response['CODE'].' '.$response['DESCRIPTION']);
        }

        update_query('tblsslorders', array('completiondate' => 'now()', 'status' => 'Completed'), array('serviceid' => $params['serviceid'], 'status' => array('sqltype' => 'NEQ', 'value' => 'Completed')));
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

function ispapissl_ClientArea($params)
{
    $result = select_query('tblsslorders', 'id, remoteid, status', array('serviceid' => $params['serviceid']));
    $data = mysql_fetch_array($result);
    $params['remoteid'] = $data['remoteid'];
    $params['status'] = $data['status'];
    $sslorderid = $data['id'];
    $orderid = $params['remoteid'];

    if ($params['configoptions']['Certificate Type']) {
        $certtype = $params['configoptions']['Certificate Type'];
    } else {
        $certtype = $params['configoption3'];
    }

    if ($params['configoptions']['Years']) {
        $certyears = $params['configoptions']['Years'];
    } else {
        $certyears = $params['configoption4'];
    }

    $ispapissl = array();
    $ispapissl['id'] = $params['serviceid'];
    $ispapissl['md5certid'] = md5($sslorderid);
    $ispapissl['status'] = $params['status'];
    $command = array('COMMAND' => 'QueryOrderList', 'ORDERID' => $orderid);
    $response = ispapissl_call($command, ispapissl_config($params));

    // if ($response['CODE'] != 200) {
    //     $values['error'] = $response['CODE'].' '.$response['DESCRIPTION'];
    //     return $values;
    // }

    $cert_allowconfig = true;
    if (isset($response['PROPERTY']['ORDERCOMMAND']) && strlen($response['PROPERTY']['ORDERCOMMAND'][0])) {
        $order_command = ispapissl_parse_response(urldecode($response['PROPERTY']['ORDERCOMMAND'][0]));
        $csr = array();
        $i = 0;
        while (isset($order_command['CSR'.$i])) {
            if (strlen($order_command['CSR'.$i])) {
                $csr[] = $order_command['CSR'.$i];
            }
            ++$i;
        }

        $csr = implode('
', $csr);

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

    if (isset($response['PROPERTY']['LASTRESPONSE']) && strlen($response['PROPERTY']['LASTRESPONSE'][0])) {
        $order_response = ispapissl_parse_response(urldecode($response['PROPERTY']['LASTRESPONSE'][0]));

        if (isset($order_response['PROPERTY']['SSLCERTID'])) {
            $cert_id = $order_response['PROPERTY']['SSLCERTID'][0];
            $status_command = array('COMMAND' => 'StatusSSLCert', 'SSLCERTID' => $cert_id);
            $status_response = ispapissl_call($status_command, ispapissl_config($params));

            if (isset($status_response['PROPERTY']['CSR'])) {
                $csr = implode('
', $status_response['PROPERTY']['CSR']);
                $ispapissl['config']['csr'] = htmlspecialchars($csr);
            }

            if (isset($status_response['PROPERTY']['CRT'])) {
                $crt = implode('
', $status_response['PROPERTY']['CRT']);
                $ispapissl['crt'] = htmlspecialchars($crt);
            }

            if (isset($status_response['PROPERTY']['CACRT'])) {
                $cacrt = implode('
', $status_response['PROPERTY']['CACRT']);
                $ispapissl['cacrt'] = htmlspecialchars($cacrt);
            }

            if (isset($status_response['PROPERTY']['STATUS'])) {
                $ispapissl['processingstatus'] = htmlspecialchars($status_response['PROPERTY']['STATUS'][0]);

                if ($status_response['PROPERTY']['STATUS'][0] == 'ACTIVE') {
                    $exp_date = $status_response['PROPERTY']['REGISTRATIONEXPIRATIONDATE'][0];
                    $ispapissl['displaydata']['Expires'] = $exp_date;
                    update_query('tblhosting', array('nextduedate' => $exp_date, 'domain' => $status_response['PROPERTY']['SSLCERTCN'][0]), array('id' => $params['serviceid']));
                } else {
                    update_query('tblhosting', array('domain' => $status_response['PROPERTY']['SSLCERTCN'][0] ), array( 'id' => $params['serviceid']));
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

    if (isset($_REQUEST['sslresendcertapproveremail']) && isset($_REQUEST['approveremail'])) {
        $resend_command = array('COMMAND' => 'ResendSSLCertEmail', 'SSLCERTID' => $cert_id, 'EMAIL' => $_REQUEST['approveremail']);
        $resend_response = ispapissl_call($resend_command, ispapissl_config($params));

        if ($resend_response['CODE'] == 200) {
            unset($_REQUEST[sslresendcertapproveremail]);
        } else {
            $ispapissl['errormessage'] = $resend_response['DESCRIPTION'];
        }
    }

    if (isset($_REQUEST['sslresendcertapproveremail'])) {
        $ispapissl['sslresendcertapproveremail'] = 1;
        $ispapissl['approveremails'] = array();
        $appemail_command = array('COMMAND' => 'QuerySSLCertDCVEmailAddressList', 'SSLCERTID' => $cert_id);
        $appemail_response = ispapissl_call($appemail_command, ispapissl_config($params));

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


function ispapissl_config($params)
{
    $config = array();
    $config['entity'] = '54cd';
    $config['url'] = 'https://coreapi.1api.net/api/call.cgi';
    if ($params['configoption5']) {
        $config['entity'] = '1234';
    }
    $config['login'] = $params['configoption1'];
    $config['password'] = $params['configoption2'];
    return $config;
}

function ispapissl_call(&$command, $config)
{
    return ispapissl_parse_response(ispapissl_call_raw($command, $config));
}

function ispapissl_call_raw(&$command, $config)
{
    global $module_version;
    $args = array(  );
    $url = $config['url'];
    if (isset($config['login'])) {
        $args['s_login'] = $config['login'];
    }
    if (isset($config['password'])) {
        $args['s_pw'] = $config['password'];
    }
    if (isset($config['user'])) {
        $args['s_user'] = $config['user'];
    }
    if (isset($config['entity'])) {
        $args['s_entity'] = $config['entity'];
    }
    $args['s_command'] = ispapissl_encode_command($command);
    $config['curl'] = curl_init($url);

    if ($config['curl'] === false) {
        return '[RESPONSE]
CODE=423
API access error: curl_init failed
EOF
';
    }

    $postfields = array();
    foreach ($args as $key => $value) {
        $postfields[] = urlencode($key).'='.urlencode($value);
    }

    $postfields = implode('&', $postfields);
    curl_setopt($config['curl'], CURLOPT_POST, 1);
    curl_setopt($config['curl'], CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($config['curl'], CURLOPT_HEADER, 0);
    curl_setopt($config['curl'], CURLOPT_RETURNTRANSFER, 1);

    if (strlen($config['proxy'])) {
        curl_setopt($config['curl'], CURLOPT_PROXY, $config['proxy']);
    }

    curl_setopt($ch, CURLOPT_USERAGENT, 'ISPAPISSL/'.$module_version.' WHMCS/'. $GLOBALS['CONFIG']['Version'].' PHP/'.phpversion().' ('.php_uname('s').')');
    curl_setopt($ch, CURLOPT_REFERER, $GLOBALS['CONFIG']['SystemURL']);
    $response = curl_exec($config['curl']);
    return $response;
}

function ispapissl_encode_command($commandarray)
{
    if (!is_array($commandarray)) {
        return $commandarray;
    }

    $command = '';
    foreach ($commandarray as $k => $v) {
        if (is_array($v)) {
            $v = ispapissl_encode_command($v);
            $l = explode('
', trim($v));
            foreach ($l as $line) {
                $command .= ($k.$line.'
' );
            }
            continue;
        }

        $v = preg_replace('/
|
/', '', $v);
        $command .= ($k.'='.$v.'
' );
    }
    return $command;
}

function ispapissl_parse_response($response)
{
    if (is_array($response)) {
        return $response;
    }

    if (!$response) {
        return array( 'CODE' => '423', 'DESCRIPTION' => 'Empty response from API' );
    }

    $hash = array('PROPERTY' => array());
    $rlist = explode('
', $response);
    foreach ($rlist as $item) {
        if (preg_match('/^([^\=]*[^	\= ])[	 ]*=[	 ]*(.*)$/', $item, $m)) {
            $attr = $m[1];
            $value = $m[2];
            $value = preg_replace('/[	 ]*$/', '', $value);

            if (preg_match('/^property\[([^\]]*)\]/i', $attr, $m)) {
                $prop = strtoupper($m[1]);
                $prop = preg_replace('/\s/', '', $prop);

                if (in_array($prop, array_keys($hash['PROPERTY']))) {
                    array_push($hash['PROPERTY'][$prop], $value);
                    continue;
                }

                $hash['PROPERTY'][$prop] = array($value);
                continue;
            }

            $hash[strtoupper($attr)] = $value;
            continue;
        }
    }
    return $hash;
}
