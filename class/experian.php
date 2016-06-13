<?php
class Experian
{
    //*******************Class Variables*******************
    public $dbId;
    private $token;

    //SOAP Client
    private $soapClient;
    //XML Parser Client
    private $parserClient;
    //DB Client
    private $dbClient;
    private $dbClientCentral;

    //UAT Certificate File
    private $certFile;
    private $passphrase;

    //WASP SERVER - TOKEN SERVICE
    public $urlTokenService;
    //ID Hub SERVICE
    public $urlIdHubService;

    public $strWSSENS;
    public $soapAction;

    public $xmlDomObj;
    public $tokenFilePath;

    public $requestWritePath;
    public $responseWritePath;

    public $uatFlag;

    public $intTryCount = 0;

    public $arrTransactionData = array();
	
	public $discPlatformClient;
	public $scoutsClient;

    //*******************Constructor********************
    function __construct($soapClient,$parserClient,$dbClient,$dbClientCentral) {
        $this->soapClient = $soapClient;
        $this->parserClient = $parserClient;
        $this->dbClient = $dbClient;
        $this->dbClientCentral = $dbClientCentral;

        $this->xmlDomObj = new DOMDocument('1.0');
    }
    //*******************Class Methods*******************

    public function configExperianParams($key)
    {
        switch($key)
        {
            case 'UAT':
                $this->uatFlag = 'Y';
        
                $this->certFile = "/home/sites/routetwo.disclosures.co.uk/private/UAT/experiansslUAT.pem";
                $this->passphrase = "";
                $this->urlTokenService = "https://secure.wasp.uat.uk.experian.com/";
                $this->urlIdHubService = "https://ukid.uat.uk.experian.com/";
                $this->strWSSENS = "http://docs.oasis-open.org/";
                $this->soapAction = "http://www.uk.experian.com/";
                $this->tokenFilePath = 'Token/UAT/token.txt';
                $this->requestWritePath = 'requestUAT.xml';
                $this->responseWritePath = 'responseUAT.xml';
            break;

            case 'PROD':
                $this->uatFlag = 'N';
                //$this->certFile = "/home/sites/routetwo.disclosures.co.uk/";
                //$this->passphrase = "";
                $this->certFile = "/home/sites/routetwo.disclosures.co.uk/";
                $this->passphrase = "";
                $this->urlTokenService = "https://secure.authenticator.uk.experian.com/";
                $this->urlIdHubService = "https://ukid.uk.experian.com/";
                $this->strWSSENS = "http://docs.oasis-open.org/";
                $this->soapAction = "http://www.uk.experian.com/";
                $this->tokenFilePath = 'Token/token.txt';
                $this->requestWritePath = 'request.xml';
                $this->responseWritePath = 'response.xml';
            break;
        }
    }

    //***********************************************Token Methods***********************************************

    //Return XML for Token Service
    public function getTokenServiceXML() {
        $XMLTokenService = file_get_contents('tokenService.xml');
        return $XMLTokenService;
    }

    //Creates token and returns
    public function generateToken() {
        $arrResult = '';
        $XMLTokenService = $this->getTokenServiceXML();
        $action = (object) array("action"=>$this->soapAction);

        $soapAction = new SoapVar($action, SOAP_ENC_OBJECT, 'action', $this->strWSSENS);
        $soapHeader = new SoapHeader($this->strWSSENS, 'Action', $soapAction, true,$this->strWSSENS);
        $this->soapClient->__setSoapHeaders(array($soapHeader));
        $soapResponse = $this->soapClient->__doRequest($XMLTokenService, $this->urlTokenService,$this->soapAction, SOAP_1_1,0);
	 $arrResult = $this->processSOAPResponse('TOKENSERVICE',$soapResponse);
        $this->setToken($arrResult['response']);
        //$this->writeToken2File($arrResult['response']);
        $this->safeguardToken($arrResult['response'],'DB');

        return $arrResult;
    }

    //Fetch token from Object / DB
    public function getStoredToken($key) {
        if(empty($this->token))
        {
            $strToken = '';
            switch($key)
            {
                case 'DB':  $sqlReadToken = "SELECT token FROM token_generation_log WHERE test_data_yn = '".$this->uatFlag."' ORDER BY id DESC LIMIT 1";
                            $rdReadToken = $this->dbClientCentral->getDBRecords($sqlReadToken);

                            $strToken = $rdReadToken[0]['token'];
                break;

                case 'FILE': $strToken = file_get_contents($this->tokenFilePath);
                break;
            }

            if(!empty($strToken))
                $this->setToken($strToken);
        }
        return $this->token;
    }

    //Save token received from Experian
    public function setToken($params) {
        $this->token = $params;
    }

    //Save token received from Experian
    public function writeToken2File($params) {
        if(!empty($params))
        {
            $fp = @fopen($this->tokenFilePath,'w');
            @fwrite($fp,$params);
        }
    }

    //Save token received from Experian
    public function safeguardToken($params,$key) {
        if(!empty($params))
        {
            switch($key)
            {
                case 'DB':  $time = time();
                            $sqlSaveToken = "INSERT INTO token_generation_log (created_on,token,test_data_yn) VALUES ('".$time."','".$params."','".$this->uatFlag."')";
                            $this->dbClientCentral->Query($sqlSaveToken);
                break;

                case 'FILE': $fp = @fopen($this->tokenFilePath,'w');
                             @fwrite($fp,$params);
                break;
            }
        }
    }

    //Store token for logging purpose
    public function logToken($params) {
       $sqlSetToken = "INSERT INTO token_log (token,datetime) VALUES ('$params',NOW())";
       $this->dbClient->Query($sqlSetToken);
    }

    //Checks token expiry
    //true -- if expired
    //false -- if not expired
    public function isTokenExpired($params) {
	if($params['stsresponse']['stsresult'] == "Error issuing authentication token")
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    //***********************************************ID Check Methods***********************************************

    //Fetch user details like Fname, Sname, DOB, Address
    public function getUserDetails($params) {
        $sqlUserData = "SELECT * FROM external_verifier_detail WHERE external_verifier_detail_id = '".$params['external_verifier_detail_id']."'";
        $rsUserData = $this->dbClient->getDBRecords($sqlUserData);

        $rsUserAddress = $this->getAddress($params);
        $userDetails['UserData'] = $rsUserData[0];
        $userDetails['UserAddress'] = $rsUserAddress;

        return $userDetails;
    }
	
	//Fetch user details like Fname, Sname, DOB, Address - For Disclosure Platform Clients
    public function getUserDetailsPlatformClient($params) {
        $sqlUserData = "SELECT 
							   DATE_FORMAT(appR2.date_of_birth, '%d%m%Y') as date_of_birth, appfname.application_name as fname, appmname.application_name as mname, appsname.application_name as sname,
							   appCurAddress.flatnumber, appCurAddress.housename, appCurAddress.housenumber, appCurAddress.address_line_one as current_address1, appCurAddress.address_line_two as current_address2,
							   appCurAddress.town_city as current_town, appCurAddress.county as current_county, appCurAddress.postcode current_postcode, appCurAddress.lived_from_year as lived_from_year, appCurAddress.lived_from_month as lived_from_month 
		FROM 
				application_route_two appR2 
				INNER JOIN application_route_two_name appfname ON appR2.application_route_two_id = appfname.application_route_two_id AND appfname.m_name_type_id = 4 
				INNER JOIN application_route_two_name appsname ON appR2.application_route_two_id = appsname.application_route_two_id AND appsname.m_name_type_id = 3
				LEFT JOIN application_route_two_name appmname ON appR2.application_route_two_id = appmname.application_route_two_id AND appmname.m_name_type_id = 9
				INNER JOIN application_route_two_address appCurAddress ON appR2.application_route_two_id = appCurAddress.application_route_two_id AND appCurAddress.m_address_type_id = 1
		WHERE appR2.application_route_two_id = '".$params['external_verifier_detail_id']."'";
        $rsUserData = $this->dbClient->getDBRecords($sqlUserData);

        $rsUserAddress = $this->getAddressPlatformClient($params);
        $userDetails['UserData'] = $rsUserData[0];
        $userDetails['UserAddress'] = $rsUserAddress;

        return $userDetails;
    }
	
	//Fetch user details like Fname, Sname, DOB, Address - For Scouts Clients
    public function getUserDetailsScoutsClient($params) {
        $sqlUserData = "SELECT DATE_FORMAT(date_of_birth, '%d%m%Y') as date_of_birth, fname, mname, sname,
							   flatnumber, housename, housenumber, current_address1, current_address2,
							   current_town, current_county, current_postcode, lived_from_year, lived_from_month FROM external_verifier_detail WHERE external_verifier_detail_id = '".$params['external_verifier_detail_id']."'";

        $rsUserData = $this->dbClient->getDBRecords($sqlUserData);

        $rsUserAddress = $this->getAddress($params);
        $userDetails['UserData'] = $rsUserData[0];
        $userDetails['UserAddress'] = $rsUserAddress;

        return $userDetails;
    }

    //Fetch user address
    public function getAddress($params) {
        $sqlUserAddress = "SELECT * FROM external_address WHERE external_verifier_detail_id = '".$params['external_verifier_detail_id']."' ORDER BY address_id ASC";
        $rsUserAddress = $this->dbClient->getDBRecords($sqlUserAddress);

        $arrAddress = '';
        if(count($rsUserAddress) > 0)
        {
            for($i = 0; $i < count($rsUserAddress); $i++)
            {
                $arrAddress[$rsUserAddress[$i]['address_id']] = $rsUserAddress[$i];
            }
        }

	return $arrAddress;
    }
	
	//Fetch user address - For Disclosure Platform Clients
    public function getAddressPlatformClient($params) {
        $sqlUserAddress = "SELECT flatnumber as flatnumber, housename as housename, housenumber as housenumber, address_line_one as address1, address_line_two as address2, town_city as town_city, county as county, postcode as postcode, lived_from_year as lived_from_year, lived_until_month as lived_from_month, lived_until_year as lived_until_year, lived_until_month as lived_until_month 
		FROM application_route_two_address WHERE application_route_two_id = '".$params['external_verifier_detail_id']."' AND m_address_type_id <> 1 ORDER BY application_address_id ASC";
        $rsUserAddress = $this->dbClient->getDBRecords($sqlUserAddress);

        $arrAddress = '';
        if(count($rsUserAddress) > 0)
        {
            for($i = 0; $i < count($rsUserAddress); $i++)
            {
                $arrAddress[$rsUserAddress[$i]['application_address_id']] = $rsUserAddress[$i];
            }
        }

	return $arrAddress;
    }

    public function storeReqResXML($reqxml,$resxml,$extId)
    {
        $sqlSetXML = 'INSERT INTO experian_request_response (external_id, central_db_id, request_xml,response_xml, test_data_yn) VALUES ("'.$extId.'","'.$this->dbId.'","'.addslashes($reqxml).'",
			"'.addslashes($resxml).'","'.$this->uatFlag.'")';
	 $this->dbClientCentral->Query($sqlSetXML);
    }

    //Experian API call - executeRequest
    public function checkIdentity($params) {
        $arrResult = '';

        $XMLExecuteRequest = '';
       $XMLExecuteRequest = $this->getExecuteRequestXML($params);
	
	$fp = fopen($this->requestWritePath,"a");
	$strBreak = "*************************************************************";
	fwrite($fp,$XMLExecuteRequest.$strBreak);
	fclose($fp);
	
	//$XMLExecuteRequest = file_get_contents("nn.xml");     
        $soapResponse = '';
        $soapResponse = $this->soapClient->__doRequest($XMLExecuteRequest, $this->urlIdHubService,$this->soapAction, SOAP_1_1,0);

	$fp1 = fopen($this->responseWritePath,"a");
	fwrite($fp1,$soapResponse.$strBreak);
	fclose($fp1);

        $this->storeReqResXML($XMLExecuteRequest,$soapResponse,$params['external_verifier_detail_id']);

	$arrErrorData = $this->isError($soapResponse);

	if(empty($arrErrorData['FAULT']))
	{
           $arrResult = $this->processSOAPResponse('EXECUTEREQUEST',$soapResponse);
	}
       else
       {
	    $strErrorStatus = $this->handleError($arrErrorData,$params);            
            if($strErrorStatus <= 0)
            {
                $this->intTryCount++;		  
                if($this->intTryCount < 2)
                {
                    $this->getStoredToken('DB');
                    $this->xmlDomObj = new DOMDocument('1.0');
                    $this->parserClient = new xml2Array();
                    $arrResult = '';
                    $arrResult = $this->checkIdentity($params);
                }
                else
                {
                    //Add mail function
                }
            }
	}
	$arrResult['external_verifier_detail_id'] = $params['external_verifier_detail_id'];
       return $arrResult;
    }

    //update transaction result
    public function setIdentityResult($params) {
	 $time = time();
         if(!empty($params['URN']))
         {
            $setResult = "UPDATE external_verifier_detail SET reference_number = '".$params['URN']."', result = '".$params['result']."', result_date = '".$time."' WHERE external_verifier_detail_id = '".$params['external_verifier_detail_id']."' AND active = 'Y'";
            $this->dbClient->Query($setResult);
         }
    }
	
	//update transaction result - For Disclosure Platform Clients
    public function setIdentityResultPlatformClient($params) {
	 $time = Date('Y-m-d H:i:s');
         if(!empty($params['URN']))
         {
            $setResult = "UPDATE application_route_two SET reference_number = '".$params['URN']."', result = '".$params['result']."', result_date = '".$time."' WHERE application_route_two_id = '".$params['external_verifier_detail_id']."' AND cancelled_date IS NULL";
            $this->dbClient->Query($setResult);
         }
    }

    //Process SOAP Response
    public function processSOAPResponse($key,$params) {
        //Parse through XML
        $arrParam = $this->parserClient->parse($params);
	 
	$arrAPIResponse = array();
        $arrAPIResponse['apiKey'] = $key;
        switch($key)
        {
            case 'TOKENSERVICE':

                    if(!$this->isTokenExpired($arrParam['SOAP:ENVELOPE']['SOAP:BODY']))
                    {
                        $arrAPIResponse['tokenExpired'] = 'N';
                        $arrAPIResponse['response'] = $arrParam['SOAP:ENVELOPE']['SOAP:BODY']['STSRESPONSE']['STSRESULT']['DATA'];
                    }
                    else
                    {
                        $arrAPIResponse['tokenExpired'] = 'Y';
                    }
            break;

            case 'EXECUTEREQUEST':
		      $URN = $arrParam['ENV:ENVELOPE'][0]['ENV:BODY']['EIH:PROCESSCONFIGRESPONSE']['EIH:DECISIONHEADER']['EIH:UNIQUEREFERENCENO']['DATA'];
		      $Result = $arrParam['ENV:ENVELOPE'][0]['ENV:BODY']['EIH:PROCESSCONFIGRESPONSE']['EIH:DECISIONHEADER']['EIH:AUTHENTICATIONDECISION']['DATA'];	      

                    $arrAPIResponse['URN'] = $URN;
		      
		      if($Result == 'Authenticated')
		      	  $Result = "PASS";
		      else
		      	  $Result = "FAIL";
			
                    $arrAPIResponse['result'] = $Result;
            break;
        }

        return $arrAPIResponse;
    }

    //Return XML for ExecuteRequest Service - Identity Check
    public function getExecuteRequestXML($params) {
        //Node1 = soap:Envelope
        $Envelope = $this->xmlDomObj->createElement('soap:Envelope');
            $Envelope->setAttribute('xmlns:soap','http://schemas.xmlsoap.org/soap/envelope/');
            $Envelope->setAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
	    $Envelope->setAttribute('xmlns:xsd','http://www.w3.org/2001/XMLSchema');
	    $Envelope->setAttribute('xmlns:wsa','http://schemas.xmlsoap.org/ws/2004/08/addressing');
            $Envelope->setAttribute('xmlns:wsse','http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
            $Envelope->setAttribute('xmlns:wsu','http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');

        //############################################Header Section ######################################################################
        $Header = $this->getExecuteRequestHeaderXML();

        //############################################Body Section ######################################################################
        $Body = $this->xmlDomObj->createElement('soap:Body');

        $ExecuteRequest = $this->xmlDomObj->createElement('ExecuteRequest');
            $ExecuteRequest->setAttribute('xmlns','http://schema.uk.experian.com/eih/2011/03');

            $EIHHeader = $this->getEIHConfigurationXML($params);

            $ProcessConfigReference = $this->xmlDomObj->createElement('ProcessConfigReference');
                $ProcessConfigName = $this->xmlDomObj->createElement('ProcessConfigName','CRB Check');
            $ProcessConfigReference->appendChild($ProcessConfigName);

            $ResponseType = $this->xmlDomObj->createElement('ResponseType','Decision Only');
            $Consent = $this->xmlDomObj->createElement('Consent','Yes');

            //############################################Personal Data Section ######################################################################
            $arrUserDetails = $this->getUserDetailsXML($params);
	    $PersonalData = $arrUserDetails['PersonalData'];
            $Addresses = $arrUserDetails['AddressData'];

        $ExecuteRequest->appendChild($EIHHeader);
        $ExecuteRequest->appendChild($ProcessConfigReference);
        $ExecuteRequest->appendChild($ResponseType);
        $ExecuteRequest->appendChild($Consent);
        $ExecuteRequest->appendChild($PersonalData);

        //############################################Address Section ######################################################################
        $ExecuteRequest->appendChild($Addresses);

        $Body->appendChild($ExecuteRequest);

        $Envelope->appendChild($Header);
        $Envelope->appendChild($Body);

        $this->xmlDomObj->appendChild($Envelope);

        $XMLExecuteRequestService = '';
        $XMLExecuteRequestService = $this->xmlDomObj->saveXML();

	return $XMLExecuteRequestService;
    }

    public function getExecuteRequestHeaderXML() {
        $currentDate = gmdate('Y-m-d');
        $currentTime = gmdate('H:i:s');
        $strCurrentTimestamp = $currentDate."T".$currentTime."Z";

        $nextDay = time() + (1 * 24 * 60 * 60);
        $expiryDate = gmdate('Y-m-d',$nextDay);
        $expiryTime = gmdate('H:i:s');
        $strExpiryTimestamp = $expiryDate."T".$expiryTime."Z";

	$Header = $this->xmlDomObj->createElement('soap:Header');
        $Action = $this->xmlDomObj->createElement('wsa:Action');

        $MessageID = $this->xmlDomObj->createElement('wsa:MessageID','urn:uuid:13b4acb4-cbed-4a44-9653-b0ec61887690');

        $ReplyTo = $this->xmlDomObj->createElement('wsa:ReplyTo');
            $Address = $this->xmlDomObj->createElement('wsa:Address','http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous');
        $ReplyTo->appendChild($Address);

        $To = $this->xmlDomObj->createElement('wsa:To','https://ukid.uat.uk.experian.com/EIHEndpoint');

        $Security = $this->xmlDomObj->createElement('wsse:Security');
            $Timestamp = $this->xmlDomObj->createElement('wsu:Timestamp');
                $Timestamp->setAttribute('wsu:Id','Timestamp-eae374ac-2b08-4085-b056-d7a978ab5bfe');

                $Created = $this->xmlDomObj->createElement('wsu:Created',$strCurrentTimestamp);
                $Expires = $this->xmlDomObj->createElement('wsu:Expires',$strExpiryTimestamp);
            $Timestamp->appendChild($Created);
            $Timestamp->appendChild($Expires);

            $this->arrTransactionData['transaction_datetime'] = $strCurrentTimestamp;

            $BinarySecurityToken = $this->xmlDomObj->createElement('wsse:BinarySecurityToken',$this->token);
		  $BinarySecurityToken->setAttribute('xmlns:wsu','http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');
                $BinarySecurityToken->setAttribute('ValueType','ExperianWASP');
                $BinarySecurityToken->setAttribute('EncodingType','http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary');
                $BinarySecurityToken->setAttribute('wsu:Id','SecurityToken-360cdfd9-0664-4a65-a0f0-92a0229bf83a');

        $Security->appendChild($Timestamp);
        $Security->appendChild($BinarySecurityToken);

        $Header->appendChild($Action);
        $Header->appendChild($MessageID);
        $Header->appendChild($ReplyTo);
        $Header->appendChild($To);
        $Header->appendChild($Security);

        return $Header;
    }

    public function getEIHConfigurationXML($params) {
        $EIHHeader = $this->xmlDomObj->createElement('EIHHeader');
            $EIHHeader->setAttribute('xmlns','http://schema.uk.experian.com/eih/2011/03/EIHHeader');

            $ClientUser = $this->xmlDomObj->createElement('ClientUser','ADL');
            $ReferenceId = $this->xmlDomObj->createElement('ReferenceId','ADL'.$this->dbId.'EXT'.$params['external_verifier_detail_id']);

            $this->arrTransactionData['central_db_id'] = $this->dbId;
            $this->arrTransactionData['adl_ref_id'] = "ADL".$this->dbId."EXT".$params['external_verifier_detail_id'];

        $EIHHeader->appendChild($ClientUser);
        $EIHHeader->appendChild($ReferenceId);

        return $EIHHeader;
    }

    //Format the data as per SOAP request
    public function getUserDetailsXML($params) {
		if($this->discPlatformClient == "Y")
			$arrUserDetails = $this->getUserDetailsPlatformClient($params);
		else if($this->scoutsClient == "Y")
			$arrUserDetails = $this->getUserDetailsScoutsClient($params);
		else 
			$arrUserDetails = $this->getUserDetails($params);

        $PersonalData = $this->getPersonalDataXML($arrUserDetails);
        $AddressData = $this->getAddressDataXML($arrUserDetails);

        return array('PersonalData'=>$PersonalData,'AddressData'=>$AddressData);
    }

    public function getPersonalDataXML($params) {
        $dob = substr($params['UserData']['date_of_birth'],4).'-'.substr($params['UserData']['date_of_birth'],2,2).'-'.substr($params['UserData']['date_of_birth'],0,2);

        $PersonalData = $this->xmlDomObj->createElement('PersonalData');
                $Name = $this->xmlDomObj->createElement('Name');
                    //Title not there in system
                    //$Title = $this->xmlDomObj->createElement('Title','Mr');
                    $Forename = $this->xmlDomObj->createElement('Forename',strtoupper(stripslashes($params['UserData']['fname'])));
                    $MiddleName = $this->xmlDomObj->createElement('MiddleName',strtoupper(stripslashes($params['UserData']['mname'])));
                    $Surname = $this->xmlDomObj->createElement('Surname',strtoupper(stripslashes($params['UserData']['sname'])));

                    $this->arrTransactionData['forname'] = strtoupper(stripslashes($params['UserData']['fname']));
                    $this->arrTransactionData['surname'] = strtoupper(stripslashes($params['UserData']['sname']));
                    $this->arrTransactionData['test_data_yn'] = $this->uatFlag;

                //$Name->appendChild($Title);
                $Name->appendChild($Forename);
                $Name->appendChild($MiddleName);
                $Name->appendChild($Surname);

                //Gende not there in system
                //$Gender = $this->xmlDomObj->createElement('Gender','Male');
                $BirthDate = $this->xmlDomObj->createElement('BirthDate',$dob);
        $PersonalData->appendChild($Name);
        //$PersonalData->appendChild($Gender);
        $PersonalData->appendChild($BirthDate);
        return $PersonalData;
    }

    public function getAddressDataXML($params) {
        $currentDate = date('d');
        $currentDMY = date('Y-m-d');;
        $Addresses = $this->xmlDomObj->createElement('Addresses');

        //Create Current Address
        $Address = $this->xmlDomObj->createElement('Address');

        $AddressDetail = $this->xmlDomObj->createElement('AddressDetail');
            $FlatNumber = $this->xmlDomObj->createElement('FlatOrApartmentNumber',strtoupper(stripslashes($params['UserData']['flatnumber'])));
            $HouseName = $this->xmlDomObj->createElement('HouseName',strtoupper(stripslashes($params['UserData']['housename'])));
	    //$HouseName = $this->xmlDomObj->createElement('HouseName','1');
            $HouseNumber = $this->xmlDomObj->createElement('HouseNumber',strtoupper(stripslashes($params['UserData']['housenumber'])));

            //Street ****
            $Address1 = $this->xmlDomObj->createElement('Address1',strtoupper(stripslashes($params['UserData']['current_address1'])));

            //District - Not there in system
            $Address2 = $this->xmlDomObj->createElement('Address2',strtoupper(stripslashes($params['UserData']['current_address2'])));

            //Town ****
            $Address3 = $this->xmlDomObj->createElement('Address3',strtoupper(stripslashes($params['UserData']['current_town'])));

            //County
            $Address4 = $this->xmlDomObj->createElement('Address4',strtoupper(stripslashes($params['UserData']['current_county'])));

            $PostCode = $this->xmlDomObj->createElement('PostCode',strtoupper($params['UserData']['current_postcode']));
            $Country = $this->xmlDomObj->createElement('Country','GB');

        //$CurResisdentFrom = $params['UserData']['lived_from_year'].'-'.$params['UserData']['lived_from_month'].'-'.$currentDate;
        $CurResisdentFrom = $params['UserData']['lived_from_year'].'-'.$params['UserData']['lived_from_month'].'-20';

        $AddressDetail->appendChild($FlatNumber);
        $AddressDetail->appendChild($HouseName);
        $AddressDetail->appendChild($HouseNumber);
        $AddressDetail->appendChild($Address1);
        $AddressDetail->appendChild($Address2);
        $AddressDetail->appendChild($Address3);
        $AddressDetail->appendChild($Address4);
        $AddressDetail->appendChild($PostCode);
        $AddressDetail->appendChild($Country);

        $TypeOfAddress = $this->xmlDomObj->createElement('TypeOfAddress','UK');
        $AddressStatus = $this->xmlDomObj->createElement('AddressStatus','Current');
        $ResidentFrom = $this->xmlDomObj->createElement('ResidentFrom',$CurResisdentFrom);
        $ResidentTo = $this->xmlDomObj->createElement('ResidentTo',$currentDMY);

        $Address->appendChild($AddressDetail);
        $Address->appendChild($TypeOfAddress);
        $Address->appendChild($AddressStatus);
        $Address->appendChild($ResidentFrom);
        $Address->appendChild($ResidentTo);

        $Addresses->appendChild($Address);


        $count = 2;
        foreach($params['UserAddress'] as $arrAddress)
        {
            if($count <= 4)
            {
                $strAddressStatus = 'Current';
                switch($count)
                {
                    case '1': $strAddressStatus = 'Current';
                    break;

                    case '2': $strAddressStatus = 'First Previous';
                    break;

                    case '3': $strAddressStatus = 'Second Previous';
                    break;

                    case '4': $strAddressStatus = 'Third Previous';
                    break;

                    case '5': $strAddressStatus = 'Fourth Previous';
                    break;
                }

                $Address = $this->xmlDomObj->createElement('Address');

                $AddressDetail = $this->xmlDomObj->createElement('AddressDetail');

                    $FlatNumber = $this->xmlDomObj->createElement('FlatOrApartmentNumber',strtoupper(stripslashes($arrAddress['flatnumber'])));
		    $HouseName = $this->xmlDomObj->createElement('HouseName',strtoupper(stripslashes($arrAddress['housename'])));
		    //$HouseName = $this->xmlDomObj->createElement('HouseName','1');
                    $HouseNumber = $this->xmlDomObj->createElement('HouseNumber',strtoupper(stripslashes($arrAddress['housenumber'])));

                    $Address1 = $this->xmlDomObj->createElement('Address1',strtoupper(stripslashes($arrAddress['address1'])));
                    $Address2 = $this->xmlDomObj->createElement('Address2',strtoupper(stripslashes($arrAddress['address2'])));
                    $Address3 = $this->xmlDomObj->createElement('Address3',strtoupper(stripslashes($arrAddress['town_city'])));
                    $Address4 = $this->xmlDomObj->createElement('Address4',strtoupper(stripslashes($arrAddress['county'])));
                    $PostCode = $this->xmlDomObj->createElement('PostCode',strtoupper($arrAddress['postcode']));
                    $Country = $this->xmlDomObj->createElement('Country','GB');

               $AddressDetail->appendChild($FlatNumber);
               $AddressDetail->appendChild($HouseName);
               $AddressDetail->appendChild($HouseNumber);
               $AddressDetail->appendChild($Address1);
               $AddressDetail->appendChild($Address2);
               $AddressDetail->appendChild($Address3);
               $AddressDetail->appendChild($Address4);
               $AddressDetail->appendChild($PostCode);
               $AddressDetail->appendChild($Country);

               //$PrevResisdentFrom = $arrAddress['lived_from_year'].'-'.$arrAddress['lived_from_month'].'-'.$currentDate;
               //$PrevResisdentTo = $arrAddress['lived_until_year'].'-'.$arrAddress['lived_until_month'].'-'.$currentDate;

		 $PrevResisdentFrom = $arrAddress['lived_from_year'].'-'.$arrAddress['lived_from_month'].'-20';
               $PrevResisdentTo = $arrAddress['lived_until_year'].'-'.$arrAddress['lived_until_month'].'-20';

               $TypeOfAddress = $this->xmlDomObj->createElement('TypeOfAddress','UK');
               $AddressStatus = $this->xmlDomObj->createElement('AddressStatus',$strAddressStatus);
               $ResidentFrom = $this->xmlDomObj->createElement('ResidentFrom',$PrevResisdentFrom);
               $ResidentTo = $this->xmlDomObj->createElement('ResidentTo',$PrevResisdentTo);

               $Address->appendChild($AddressDetail);
               $Address->appendChild($TypeOfAddress);
               $Address->appendChild($AddressStatus);
               $Address->appendChild($ResidentFrom);
               $Address->appendChild($ResidentTo);

               $Addresses->appendChild($Address);
            }
            $count++;
        }
        return $Addresses;
     }

     public function isError($strResponse) {
          $arrResponse = '';
         $arrResponse = $this->parserClient->parse($strResponse);

	  $arrData = array();
          $strFault = '';
	  $strFault = $arrResponse['SOAP-ENV:ENVELOPE']['SOAP-ENV:BODY']['SOAP-ENV:FAULT']['FAULTSTRING'];
	  if($strFault != '')
	  {
              $arrData['FAULT'] = 'Y';
              $arrData['FAULT_CODE'] = $strFault;
	  }
         return $arrData;
     }

     public function handleError($arrData,$params) {
        $strErrorStatus = 1;
	switch($arrData['FAULT_CODE']['DATA'])
        {
            case 'EIH Fault':
                //echo "Token Expired!";
                $this->generateToken();
                $strErrorStatus = 0;
            break;

            default:
                //echo "Error not Handled!";
            break;
        }
        return $strErrorStatus;
    }

     public function setCentralTransactionLog($params)
     {
         $time = time();
         if(!empty($params['URN']))
         {
            if($params['result'] ==  'PASS')
                $result = 'Authenticated';
            else
                $result = 'Not Authenticated';

	     $strTimestamp = '';
            $strTimestamp = $this->arrTransactionData['transaction_datetime'];
            //$strTimestamp = str_replace('T', ' ', $strTimestamp);
            //$strTimestamp = str_replace('Z', '', $strTimestamp);
            
            $setResult = 'INSERT INTO experian_transaction_log (central_db_id, external_id, forname, surname, transaction_datetime, exp_urn_id, adl_ref_id
                , result, transaction_timestamp, test_data_yn) VALUES ("'.$this->arrTransactionData['central_db_id'].'",
                "'.$params['external_verifier_detail_id'].'", "'.$this->arrTransactionData['forname'].'", "'.$this->arrTransactionData['surname'].'",
                "'.$this->arrTransactionData['transaction_datetime'].'", "'.$params['URN'].'","'.$this->arrTransactionData['adl_ref_id'].'",
                "'.$result.'", "'.$strTimestamp.'","'.$this->arrTransactionData['test_data_yn'].'")';            
            $this->dbClientCentral->Query($setResult);
         }
     }

     //Experian API call - executeRequest
    public function checkIdentityAgain($params) {
        $arrResult = '';

        $XMLExecuteRequest = '';
       $XMLExecuteRequest = $this->getExecuteRequestXML($params);
	
	/*$fp = fopen($this->requestWritePath,"a");
	$strBreak = "*************************************************************";
	fwrite($fp,$XMLExecuteRequest.$strBreak);
	fclose($fp);*/
	
	//$XMLExecuteRequest = file_get_contents("nn.xml");     
        $soapResponse = '';
        $soapResponse = $this->soapClient->__doRequest($XMLExecuteRequest, $this->urlIdHubService,$this->soapAction, SOAP_1_1,0);

	/*$fp1 = fopen($this->responseWritePath,"a");
	fwrite($fp1,$soapResponse.$strBreak);
	fclose($fp1);*/

        $this->storeReqResXML($XMLExecuteRequest,$soapResponse,$params['external_verifier_detail_id']);

	$arrErrorData = $this->isError($soapResponse);
        
	if(empty($arrErrorData['FAULT']))
	{
           $arrResult = $this->processSOAPResponse('EXECUTEREQUEST',$soapResponse);
	}
       else
       {
	    //do nothing
	}
	$arrResult['external_verifier_detail_id'] = $params['external_verifier_detail_id'];	
       return $arrResult;
    }

    function generateLogs()
    {
	$filename = 'response_old.xml';
	$filename1 = 'request_old.xml';
	$strFileContents = file_get_contents($filename);
	$strFileContents1 = file_get_contents($filename1);
	$arrFileContents = explode("*************************************************************",$strFileContents);
	$arrFileContents1 = explode("*************************************************************",$strFileContents1);
	
	$sqlInsert = 'INSERT INTO  experian_transaction_log ("central_db_id", "external_id", "forname", "surname", "transaction_datetime", "exp_urn_id", "adl_ref_id", "result", "comments", 
		      "transaction_timestamp", "test_data_yn", "transaction_timestamp") VALUES ';

	$sqlvalues = '';

	$arrGlobalData = array();
	
	$i = 0;
	
	foreach($arrFileContents as $strValue)
	{
		$arrResponse = '';
		$this->parserClient = new xml2Array();
    		$arrResponse = $this->parserClient->parse($strValue);

		$urn = '';
		$adlRefId = '';
		$strResult = '';
		$arrcentralDBId = '';
		$centralDBId = '';
		$extDBId = '';

	  	$adlRefId = $arrResponse['ENV:ENVELOPE']['ENV:BODY']['EIH:PROCESSCONFIGRESPONSE']['HEADER:EIHHEADER']['HEADER:REFERENCEID']['DATA'];
		$urn = $arrResponse['ENV:ENVELOPE']['ENV:BODY']['EIH:PROCESSCONFIGRESPONSE']['EIH:DECISIONHEADER']['EIH:UNIQUEREFERENCENO']['DATA'];
		$strResult = $arrResponse['ENV:ENVELOPE']['ENV:BODY']['EIH:PROCESSCONFIGRESPONSE']['EIH:DECISIONHEADER']['EIH:AUTHENTICATIONDECISION']['DATA'];
		$arrcentralDBId = explode('EXT',$adlRefId);
		$centralDBId = $arrcentralDBId[0];
		$extDBId = $arrcentralDBId[1];

		$arrGlobalData[$i]['urn'] = $urn;
		$arrGlobalData[$i]['strResult'] = $strResult;
		$arrGlobalData[$i]['adlRefId'] = $adlRefId;
		$arrGlobalData[$i]['centralDBId'] = substr($centralDBId,3);
		$arrGlobalData[$i]['extDBId'] = $extDBId;
	
		$i++;	
	}

	unset($arrFileContents);

	$j = 0;

	foreach($arrFileContents1 as $strValue1)
	{	
		$arrResponse1 = '';
		$this->parserClient = new xml2Array();
    		$arrResponse1 = $this->parserClient->parse($strValue1);
		//print_r($arrResponse1);
		//if($j == 31)
		//print_r($arrResponse1); exit;

		$fname = '';
		$sname = '';
		$transDate = '';

	  	$fname = $arrResponse1['SOAP:ENVELOPE']['SOAP:BODY']['EXECUTEREQUEST']['PERSONALDATA']['NAME']['FORENAME']['DATA'];
		$sname = $arrResponse1['SOAP:ENVELOPE']['SOAP:BODY']['EXECUTEREQUEST']['PERSONALDATA']['NAME']['SURNAME']['DATA'];
		$transDate = $arrResponse1['SOAP:ENVELOPE']['SOAP:HEADER']['WSSE:SECURITY']['WSU:TIMESTAMP']['WSU:CREATED']['DATA'];

		$arrGlobalData[$j]['fname'] = $fname;
		$arrGlobalData[$j]['sname'] = $sname;
		$arrGlobalData[$j]['transDate'] = $transDate;
	
		$j++;	
	}
	
	//print_r($arrGlobalData); 
	//exit;

	foreach($arrGlobalData as $key => $arrData)
	{
		echo $sqlvalues = ' ("'.$arrData['centralDBId'].'","'.$arrData['extDBId'].'","'.$arrData['fname'].'","'.$arrData['sname'].'","'.$arrData['transDate'].'","'.$arrData['urn'].'",
              "'.$arrData['adlRefId'].'","'.$arrData['strResult'].'", "N") ,';
	}exit;
    }    
  
    public function checkIdentityTest($params) {
        $arrResult = '';
       //$XMLExecuteRequest = $this->getExecuteRequestXML($params);
	
	
	echo $XMLExecuteRequest = file_get_contents("test.xml"); 

       $fp = fopen("request.xml","a");
	$strBreak = "*************************************************************";
	fwrite($fp,$XMLExecuteRequest.$strBreak);
	fclose($fp);   

       $soapResponse = $this->soapClient->__doRequest($XMLExecuteRequest, $this->urlIdHubService,$this->soapAction, SOAP_1_1,0);
       print_r($soapResponse);
       $strBreak = "*************************************************************";
	$fp1 = fopen($this->responseWritePath,"a");
	fwrite($fp1,$soapResponse.$strBreak);
	fclose($fp1);
	
       
	$arrErrorData = $this->isError($soapResponse);
       
	if(empty($arrErrorData['FAULT']))
	{
           $arrResult = $this->processSOAPResponse('EXECUTEREQUEST',$soapResponse);
	}
       else
       {
	    $this->handleError($arrErrorData,$params);
            //$arrResult = $this->processSOAPResponse('EXECUTEREQUEST',$soapResponse);
	}
	$arrResult['external_verifier_detail_id'] = $params['external_verifier_detail_id'];
	 
       return $arrResult;
    }

    
}
?>
