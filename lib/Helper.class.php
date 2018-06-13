<?php
namespace ISPAPISSL;


use WHMCS\Database\Capsule;
use WHMCS_ClientArea;
use PDO;

require_once(dirname(__FILE__)."/../../../../includes/registrarfunctions.php");

/**
 * PHP Helper Class
 *
 * @copyright  2018 HEXONET GmbH
 */
class Helper
{

    /*
     *  Constructor
     */
    public function __construct(){}

    /*
     * Helper to send API command to the given registrar. Returns the response
     *
     * @param string $registrar The registrar
     * @param string $command The API command to send
     *
     * @return array The response from the API
     */
    public static function APICall($registrar, $command){
        $registrarconfigoptions = getregistrarconfigoptions($registrar);
        $registrar_config = call_user_func($registrar."_config", $registrarconfigoptions);
        return call_user_func($registrar."_call", $command, $registrar_config);
    }

    /*
     * Helper to send API Response to the given registrar. Returns the parsed response
     *
     * @param string $registrar The registrar
     * @param string $response The API response to send
     *
     * @return array The parsed response from the API
     */
    public static function ParseResponse($registrar, $response){
        return call_user_func($registrar."_parse_response", $response);
    }

        /*
     * Helper to send SQL call to the Database with Capsule
     * Set $debug = true in the function to have DEBUG output in the JSON string
     *
     * @param string $sql The SQL query
     * @param array $params The parameters of the query
     * @param $fetchmode The fetching mode of the query (fetch or fetchall) - DEFAULT = fetch

     * @return json|array The SQL query response or JSON string with error message.
     */
    public static function SQLCall($sql, $params, $fetchmode = "fetch"){
        $debug = false;

        try {
            $pdo = Capsule::connection()->getPdo();
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);

            if($fetchmode == "fetch"){
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }elseif($fetchmode == "execute"){
                return $result;
            }else{ //ELSE returns fetchall
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (\Exception $e) {
            //TODO - do we need Internationalization in this module ?
            // and how is the better way to display a error msg
            // $i18n = new i18n();
            if($debug){
                echo json_encode( array("feedback" => array( "f_type" => "sqlerror") ) );
            }else{
                echo json_encode( array("feedback" => array( "f_type" => "sqlerror") ) );
            }
            die();
        }
    }


}

?>
