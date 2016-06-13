<?php
include_once 'includes.php';

//Initiate DB Client
$DB_USER = "";
$DB_PASSWORD = "";
$DB_DBNAME = ";
$DB_BRAND = "mysql";
$DB_HOSTNAME = "localhost";

$dbClient = new Database($DB_USER,$DB_PASSWORD,$DB_DBNAME,$DB_HOSTNAME);

//Initiate XML PARSER Client
$parserClient = new xml2Array();

//Code to generate token
$objExperian = new Experian($soapClient,$parserClient,$dbClient);
$objExperian->configExperianParams('PROD');
$objExperian->dbId = 100;

$arrToken = $objExperian->generateToken();
//print_r($arrToken);
$objExperian->getStoredToken('FILE');

//Code to perform Id Check
$arrIdCheckResult = $objExperian->checkIdentityTest(array('external_verifier_detail_id'=>290));
echo '<pre>';
print_r($arrIdCheckResult);
?>
