<?php
if(!empty($_REQUEST['eid']) && !empty($_REQUEST['did']) && !empty($_REQUEST['redURL']))
{
    $eid = base64_decode($_REQUEST['eid']);
    $did = base64_decode($_REQUEST['did']);

    $eidEnc = $_REQUEST['eid'];
    $didEnc = $_REQUEST['did'];

    $auth = $_REQUEST['auth'];  
    $redURL = $_REQUEST['redURL'];

    $orgId = $_REQUEST['orgId'];
    $apptype = $_REQUEST['apptype'];
	$_token = $_REQUEST['_token'];
	$scoutsClient = '';

    //if(is_numeric($eid) && is_numeric($did)) {
	 $scoutsClientType = strpos($redURL,"/secure/APIExternalSerivice.php");
        if($scoutsClientType > 0)
			$scoutsClient = "Y";
	
        include_once "initiateExperianRequest.php";
        $oldClient1 = strpos($redURL,"/secure/applications/");
        $oldClient2 = strpos($redURL,"/secure/newapplications/");
		$guiClient = strpos($redURL,"/england/");
		
		$discPlatformClient = strpos($redURL,"/application/api-external-service");
        
        if($oldClient1 > 0 || $oldClient2 > 0 || $guiClient > 0 )
        {
        	$pageRedirectURL = $redURL.'APIExternalSerivice.php';
        }
		else if($discPlatformClient > 0 || $scoutsClientType > 0)
		{
			$pageRedirectURL = $redURL;
		}
        else
        {
			$pageRedirectURL = $redURL.'php/APIExternalSerivice.php';	       
        }
		
        
        $postParams = array("eid" => $eidEnc, "auth" => $auth, "expFlag" => 1);
        
        if(!empty($orgId))
        	$postParams['orgId'] = $orgId;
        if(!empty($apptype))
        	$postParams['apptype'] = $apptype;
		if(!empty($_token))
        	$postParams['_token'] = $_token;

        require_once("formSubmission.php");
        die();
    //}
}
?>
