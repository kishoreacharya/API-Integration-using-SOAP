<?php
include_once 'CommonLib.php';
include_once 'class/database.php';
include_once 'class/xmlparse.php';
include_once 'class/experian.php';

#default configuration setings
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
ini_set('max_input_time', 600);
date_default_timezone_set('Europe/London');

#########################################################UAT SERVER DETAILS###########################################################################
    //WSDL
    $wsdlUAT = "https://secure.wasp.uat.uk.experian.com/WASPAuthenticator/tokenService.asmx?wsdl";
    $wsdl = "https://secure.authenticator.uk.experian.com/WASPAuthenticator/TokenService.asmx?wsdl";

    //X.509 Certificate
    $certFileUAT = "/home/sites/routetwo.disclosures.co.uk/private/UAT/experianssl.pem";
    $certFile = "/home/sites/routetwo.disclosures.co.uk/private/LIVE/liveExperianCert.pem";
    $passphraseUAT = "Se!fjsn21";
    $passphrase = "Cdrwjikrr";
#########################################################PRODUCTION SERVER DETAILS###########################################################################

//SOAP client connection parameters
$connectOptions = array(
    'soap_version'=>SOAP_1_1,
    'exceptions'=>true,
    'trace'=>1,
    'cache_wsdl'=>WSDL_CACHE_NONE,
    'local_cert' => $certFile,
    'passphrase' => $passphrase
);

$connectOptionsUAT = array(
    'soap_version'=>SOAP_1_1,
    'exceptions'=>true,
    'trace'=>1,
    'cache_wsdl'=>WSDL_CACHE_NONE,
    'local_cert' => $certFileUAT,
    'passphrase' => $passphraseUAT
);

//Initiate SOAP Client
$soapClient = new SoapClient($wsdl, $connectOptions);

//Initiate SOAP Client
$soapClientUAT = new SoapClient($wsdlUAT, $connectOptionsUAT);
?>
