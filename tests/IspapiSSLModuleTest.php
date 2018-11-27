<?php
namespace IspapiTest;

use PHPUnit\Framework\TestCase;

/**
 * ISPAPI SSL Module Test
 *
 * PHPUnit test that asserts the fundamental requirements of a WHMCS
 * provisioning module.
 *
 * Custom module tests are added in addtion.
 *
 * @copyright Copyright (c) HEXONET GmbH 2018
 * @license https://github.com/hexonet/whmcs-ispapi-ssl/LICENSE
 */

class IspapiSSLModuleTest extends TestCase
{
    /** @var string $moduleName */
    protected $moduleName = 'ispapissl';

    /**
     * Asserts the required config options function is defined.
     */
    public function testRequiredConfigOptionsFunctionExists()
    {
        $this->assertTrue(function_exists($this->moduleName . '_ConfigOptions'));
    }

    /**
     * Data provider of module function return data types.
     *
     * Used in verifying module functions return data of the correct type.
     *
     * @return array
     */
    public function providerFunctionReturnTypes()
    {
        return array(
            //'Config Options' => array('ConfigOptions', 'array'),
            'Meta Data' => array('MetaData', 'array'),
            'Create' => array('CreateAccount', 'string'),
            'Suspend' => array('SuspendAccount', 'string'),
            'Unsuspend' => array('UnsuspendAccount', 'string'),
            'Terminate' => array('TerminateAccount', 'string'),
            'Change Password' => array('ChangePassword', 'string'),
            'Change Package' => array('ChangePackage', 'string'),
            'Test Connection' => array('TestConnection', 'array'),
            'Admin Area Custom Button Array' => array('AdminCustomButtonArray', 'array'),
            'Client Area Custom Button Array' => array('ClientAreaCustomButtonArray', 'array'),
            'Admin Services Tab Fields' => array('AdminServicesTabFields', 'array'),
            'Admin Services Tab Fields Save' => array('AdminServicesTabFieldsSave', 'null'),
            'Service Single Sign-On' => array('ServiceSingleSignOn', 'array'),
            'Admin Single Sign-On' => array('AdminSingleSignOn', 'array'),
            //'Client Area Output' => array('ClientArea', 'array'),
        );
    }

    /**
     * Test module functions return appropriate data types.
     *
     * @param string $function
     * @param string $returnType
     *
     * @dataProvider providerFunctionReturnTypes
     */
    public function testFunctionsReturnAppropriateDataType($function, $returnType)
    {
        if (function_exists($this->moduleName . '_' . $function)) {
            $result = call_user_func($this->moduleName . '_' . $function, array());
            if ($returnType == 'array') {
                $this->assertTrue(is_array($result));
            } elseif ($returnType == 'null') {
                $this->assertTrue(is_null($result));
            } else {
                $this->assertTrue(is_string($result));
            }
        }
    }
}
