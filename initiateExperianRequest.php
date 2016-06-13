<?php
include_once 'includes.php';

global $DB_HOST, $DB_USER, $DB_PASSWORD, $DB_NAME, $link;
global $DB_HOST_CENTRAL, $DB_USER_CENTRAL, $DB_PASSWORD_CENTRAL, $DB_NAME_CENTRAL, $link_CENTRAL;

//Initiate DB Client
$DB_USER = "";
$DB_PASSWORD = "";
$DB_NAME = "";
$DB_BRAND = "mysql";
$DB_HOST = "localhost";

$DB_USER_CENTRAL = $DB_USER;
$DB_PASSWORD_CENTRAL = $DB_PASSWORD;
$DB_NAME_CENTRAL = $DB_NAME;
$DB_BRAND_CENTRAL = $DB_BRAND;
$DB_HOST_CENTRAL = $DB_HOST;

$link = getDBConnection();
$query="select * from client_db_list where db_list_id='$did'";
$commonRes=getDBRecords($query);

$DB_HOSTNAME = '';
$DB_USER = '';
$DB_PASSWORD = '';
$DB_DBNAME = '';

if(count($commonRes) > 0){
    $DB_HOSTNAME = trim($commonRes[0]['db_host']);
    $DB_USER = trim($commonRes[0]['db_user']);
    $DB_PASSWORD=trim($commonRes[0]['db_password']);
    $DB_DBNAME = trim($commonRes[0]['db_name']);
}


$dbClient = new Database($DB_USER,$DB_PASSWORD,$DB_DBNAME,$DB_HOSTNAME);


$dbClientCentral = new Database($DB_USER_CENTRAL,$DB_PASSWORD_CENTRAL,$DB_NAME_CENTRAL,$DB_HOST_CENTRAL);


//Initiate XML PARSER Client
$parserClient = new xml2Array();

if($UATFLAG == '')
{
	//Code to generate token
	$objExperian = new Experian($soapClient,$parserClient,$dbClient,$dbClientCentral);
	$objExperian->configExperianParams('PROD');
        $objExperian->dbId = $did;
}
else
{
	//Code to generate token
	$objExperian = new Experian($soapClientUAT,$parserClient,$dbClient,$dbClientCentral);
	$objExperian->configExperianParams('UAT');
        $objExperian->dbId = $did;
       $arrToken = $objExperian->generateToken();	
       print_r($arrToken); exit;
}

//Set the Type of Client - Whether Disclosure Platform Client
$objExperian->discPlatformClient = "N";
if(!empty($_token))
{
	$objExperian->discPlatformClient = "Y";
}
//Set the Type of Client - Whether Disclosure Platform Client

//Set the Type of Client - Whether Scouts Client
$objExperian->scoutsClient = "N";
if(!empty($scoutsClient))
{
	$objExperian->scoutsClient = "Y";
}
//Set the Type of Client - Whether Scouts Client

//$arrToken = $objExperian->generateToken();
$objExperian->getStoredToken('DB');

//Code to perform Id Check
$extId = $eid;
$arrInput = array('external_verifier_detail_id'=>$extId);
$arrIdCheckResult = $objExperian->checkIdentity($arrInput);
if($objExperian->discPlatformClient == "Y")
	$arrResult = $objExperian->setIdentityResultPlatformClient($arrIdCheckResult);
else
    $arrResult = $objExperian->setIdentityResult($arrIdCheckResult);
$objExperian->setCentralTransactionLog($arrIdCheckResult);
?>
