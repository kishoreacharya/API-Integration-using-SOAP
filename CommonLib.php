<?php

# Session Related Functions
# This function removes all the session variables.

function clearSession() {
    global $HTTP_SESSION_VARS;
    if (!empty($HTTP_SESSION_VARS)) {
        while (list($key, $value) = each($HTTP_SESSION_VARS)) {
            session_unregister($key);
        }
    }
}

# Function to add a variable to the session.
# not advised to use with session_register().

function addToSession($key, $value) {
    //global $_SESSION;
    $_SESSION["$key"] = $value;
}

# This function gets the value of a session variable.

function getFromSession($key) {
    //global $_SESSION;
    return $_SESSION["$key"];
}

#- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
#- - - - - - - - - - - - - Date Functions  - - - - - - - - - - - - - - - - - - -
# function to get date after few days

function getDateAfterDays($days=0) {
    $d = date("d");
    $m = date("m");
    $y = date("Y");
    $d+=$days;
    $dateLater = date("Y-m-d", mktime(0, 0, 0, $m, $d, $y));
    return $dateLater;
}

#- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
#- - - - - - - - - - - - - Database related functions - - - - - - - - - - - - - -
# general function to return  the result set

function getDBRecords($query) {
    global $link, $php_errormsg;
    $values = null;

    debugPrint($query, true);
    $res = @mysql_query($query, $link);
    if (mysql_error($link)) {
        reportError(mysql_error($link) . " in Query : " . $query);
        #exit;
        return $values;
    }

    $i = 0;
    $cols = mysql_num_fields($res);
    ini_set("memory_limit", "500M");
    while ($row = mysql_fetch_array($res)) {
        #debugPrint("");
        for ($j = 0; $j < $cols; $j++) {
            $values[$i][mysql_field_name($res, $j)] = $row[$j];
            # $values[$i]["name"] = $row[$j];
            # debugPrint(mysql_field_name($res, $j)." : ".$values[$i][mysql_field_name($res, $j)]." ");
        }
        $i+=1;
    }
    return $values;
}

function updateDBRecord($query) {
    global $link;
    if (empty($link) || !(is_resource($link))) {
        $link = getDBConnection();
    }
    $res = @mysql_query($query, $link);
    if (mysql_error($link)) {

        reportError(mysql_error($link) . " in Query : " . $query);
        return false;
        #exit;
    } else {
        return true;
    }
}

function updateDBRecord_old($query) {
    global $link;
    if (empty($link) || !(is_resource($link))) {
        $link = getDBConnection();
    }

    $res = @mysql_query($query, $link);
    if (mysql_error($link)) {
        reportError(mysql_error($link) . " in Query : " . $query);
        #exit;
    }
    return $res;
}

function isRecordFound($query) {
    global $link;
    $result = mysql_query($query, $link);
    if (mysql_error($link)) {
        reportError(mysql_error($link) . " in Query : " . $query);
        exit;
    }
    $row_count = mysql_fetch_array($result);
    $numRows = mysql_num_rows($result);
    #debugPrint("Number of rows : ".$numRows." Query : ".$query);

    if (!empty($numRows) && $numRows > 0)
        return true;

    return false;
}

function getDBConnection() {
    global $DB_HOST, $DB_USER, $DB_PASSWORD, $DB_NAME, $link;
    if (empty($link) || !(is_resource($link))) {
        $link = mysql_connect($DB_HOST, $DB_USER, $DB_PASSWORD) or
                die("Failed to connect to the database ..!");
        mysql_select_db($DB_NAME);
    }
    return $link;
}

#- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
#- - - - - - - - - - - - - File Upload related functions - - - - - - - - - - - - - -
$crbAdmin = "Location:LoginError.htm";
$my_max_file_size = "102400"; # in bytes
$image_max_width = "800";
$image_max_height = "800";
$the_path = FILE_UPLOAD_PATH;
$registered_types = array(
    "application/x-gzip-compressed" => ".tar.gz, .tgz",
    "application/x-zip-compressed" => ".zip",
    "application/x-tar" => ".tar",
    "text/plain" => ".html, .php, .txt, .inc (etc)",
    "image/bmp" => ".bmp, .ico",
    "image/gif" => ".gif",
    "image/pjpeg" => ".jpg, .jpeg",
    "image/jpeg" => ".jpg, .jpeg",
    "application/x-shockwave-flash" => ".swf",
    "application/msword" => ".doc",
    "application/vnd.ms-excel" => ".xls",
    "application/octet-stream" => ".exe, .fla (etc)"
); # these are only a few examples, you can find many more!

$allowed_types = array("image/txt", "image/doc", "image/gif", "image/jpg", "image/GIF", "image/jpeg", "image/pjpeg", "application/octet-stream");

if (!ereg("^4", phpversion())) {

    function in_arraynew($needle, $haystack) {  # we have this function in PHP4, so for you PHP3 people
        for ($i = 0; $i < count($haystack); $i++) {
            if ($haystack[$i] == $needle) {
                return true;
            }
        }
    }

}

# This function is for validating the type of file and its size.

function validate_upload($fileName) {
    global $_FILES, $my_max_file_size, $image_max_width, $image_max_height, $allowed_types, $registered_types;
    $start_error = "\n<b>Error:</b>\n<ul>";
    $the_file = $_FILES[$fileName];
    $the_file_type = $_FILES[$fileName]['type'];
    $the_file_size = $_FILES[$fileName]['size'];
    $the_file_name = $_FILES[$fileName]['tmp_name'];

    if ($the_file == "none") {
        $error = "NOFILE";  //.= "\n<li>You did not upload anything!</li>";
    } else { # check if we are allowed to upload this file_type
        if (!in_array($the_file_type, $allowed_types)) {
            $error = "NOFLTYPE"; //.= "\n<li>The file that you uploaded was of a type that is not allowed, you are only allowed to upload files of the type:\n<ul>";

            while ($type = current($allowed_types)) {
                $error = "NOFLTYPE"; //.= "\n<li>" . $registered_types[$type] . " (" . $type . ")</li>";
                next($allowed_types);
            }
            #$error .= "\n</ul>";
        }

        if (ereg("image", $the_file_type) && (in_array($the_file_type, $allowed_types))) {

            $size = GetImageSize($the_file_name);
            list($foo, $width, $bar, $height) = explode("\"", $size[3]);

            if ($width > $image_max_width) {
                $error = "BIGFILE";  //.= "\n<li>Your image should not be wider than " . $image_max_width . " Pixels</li>";
            }

            if ($height > $image_max_height) {
                $error = "BIGFILE";  //.= "\n<li>Your image should not be higher than " . $image_max_height . " Pixels</li>";
            }
        }

        if ($error) {
            #$error = $start_error . $error . "\n</ul>";
            return $error;
        } else {
            return false;
        }
    }
}

# END validate_upload
# --

function list_files() {
    global $the_path;
    $handle = dir($the_path);
    print "\n<b>Uploaded files:</b><br>";
    while ($file = $handle->read()) {
        if (($file != ".") && ($file != "..")) {
            print "\n <a href='" . $the_path . $file . "'>" . $file . "</a><br>";
        }
    }
    print "<hr>";
}

# --

function upload($fileName) {
    global $the_path, $_FILES;
    $the_file_name = $_FILES[$fileName]['name'];
    $error = validate_upload($fileName);
    if ($error) {
        return $error;
    } else {
        //print $the_path.$the_file_name;
        if (!@copy($_FILES[$fileName]['tmp_name'], $the_path . $the_file_name))
            return "NOFILE";
        else
            return "1";
    }
}

# END upload
#- - - - - - - - - - - - - End of File Upload related functions - - - - - - - - - - - -
# Debug Print function

function debugPrint($val, $force=false) {
    global $LOG_DIRECTORY, $DEBUG_FILE, $ENABLE_DEBUG;
    /**
      if($ENABLE_DEBUG==true || $force==true){
      #print "<br><b><font color=\"#FF0000\">$val</font></b>";
      $filename = $LOG_DIRECTORY.$DEBUG_FILE;
      $fp = fopen ($filename, "aw");
      $content = "\n".time()." --> ".$val;
      fputs($fp, $content, strlen($content));
      fclose ($fp);

      }
     * */
}

function reportError($errorMsg) {
    debugPrint($errorMsg, true);
}

# date functions

function getTimeOfDate($dt) {
    $d = substr($dt, 0, 2);
    $m = substr($dt, 2, 2);
    $y = substr($dt, 4, 4);

    $timeOfDate = @mktime(0, 0, 0, $m, $d, $y);
    #print "<br>".$d.":".$m.":".$y." : ".$timeOfDate;
    return $timeOfDate;
}

function getFormDate($dt) {
    $d = substr($dt, 0, 2);
    $m = substr($dt, 3, 2);
    $y = substr($dt, 6, 4);
    # print "<br>".$d.":".$m.":".$y;
    if (!empty($dt) && $dt != "")
        $formDate = @date("dmY", mktime(0, 0, 0, $m, $d, $y));
    #else
    #$formDate = $dt;
    if (empty($fromDate) || $formDate == "")
        $formDate = $d . $m . $y;
    return $formDate;
}

function getDateAfterMonths($dt, $months=0) {
    $d = substr($dt, 0, 2);
    $m = substr($dt, 2, 2);
    $y = substr($dt, 4, 4);
    $dateAfterMonths = $dt;

    if ($months > 0 && $y > 1970) {
        $dateAfterMonths = date("dmY", mktime(0, 0, 0, $m + $months, $d, $y));
    } else {
        if (($m + $months) > 12)
            $y = $y + 1;
        else
            $m=$m + $months;
        $dateAfterMonths = $d . $m . $y; #print $dateAfterMonths;
    }
    return $dateAfterMonths;
}

function getFormatedDate($dt, $format=0) {
    $d = substr($dt, 0, 2);
    $m = substr($dt, 2, 2);
    $y = substr($dt, 4, 4);

    $formatedDt = $dt;
    if (!empty($dt) && $dt != "") {
        switch ($format) {
            case 1 :
                $formatedDt = date("d-m-Y", mktime(0, 0, 0, $m, $d, $y));
                if (empty($formatedDt) || $y < 1970

                    )$formatedDt = $d . "-" . $m . "-" . $y;
                break;
            case 2 :
                $formatedDt = date("m-d-Y", mktime(0, 0, 0, $m, $d, $y));
                if (empty($formatedDt) || $y < 1970

                    )$formatedDt = $m . "-" . $d . "-" . $y;
                break;
            case 3 :
                $formatedDt = @date("d/m/Y", mktime(0, 0, 0, $m, $d, $y));
                if (empty($formatedDt) || $y < 1970

                    )$formatedDt = $d . "/" . $m . "/" . $y;
                break;
            case 4 :
                if ($m == '01' || $m == 1)
                    $month = "JAN";
                if ($m == '02' || $m == 2)
                    $month = "FEB";
                if ($m == '03' || $m == 3)
                    $month = "MAR";
                if ($m == '04' || $m == 4)
                    $month = "APR";
                if ($m == '05' || $m == 5)
                    $month = "MAY";
                if ($m == '06' || $m == 6)
                    $month = "JUN";
                if ($m == '07' || $m == 7)
                    $month = "JUL";
                if ($m == '08' || $m == 8)
                    $month = "AUG";
                if ($m == '09' || $m == 9)
                    $month = "SEP";
                if ($m == '10' || $m == 10)
                    $month = "OCT";
                if ($m == '11' || $m == 11)
                    $month = "NOV";
                if ($m == '12' || $m == 12)
                    $month = "DEC";
                $formatedDt = $d . "-" . $month . "-" . $y;
                if (empty($formatedDt)

                    )$formatedDt = $d . "-" . $m . "-" . $y;
                break;
            default :
                $formatedDt = date("Y-m-d", mktime(0, 0, 0, $m, $d, $y));
                break;
        }
    }

    return $formatedDt;
}

function compareDates($dt1, $dt2) {
    $d1 = substr($dt1, 0, 2);
    $m1 = substr($dt1, 2, 2);
    $y1 = substr($dt1, 4, 4);

    $d2 = substr($dt2, 0, 2);
    $m2 = substr($dt2, 2, 2);
    $y2 = substr($dt2, 4, 4);
    if ($y1 < 1970 || $y2 < 1970) {
        if ($y1 < 1970

            )$x = 1970 - $y1;
        if ($y2 < 1970

            )$x = 1970 - $y2;
        $y1+=$x + 1;
        $y2+=$x + 1;
        $dt1 = $d1 . $m1 . $y1;
        $dt2 = $d2 . $m2 . $y2;
    }

    $time1 = getTimeOfDate($dt1);
    $time2 = getTimeOfDate($dt2);
    #print " Diff : ".($time1 - $time2);
    if ($time1 == $time2)
        return 0;
    if ($time1 < $time2)
        return 1;
    if ($time1 > $time2)
        return 2;

    return -1;
}

# dropdown list component.

function getDropDownList($values, $name, $initialValue, $js="") {
    $dpl = "<select name=\"" . $name . "\" $js>";
    for ($i = 0; $i < count($values); $i++) {
        #	print $values[$i]["name"]."  ".count($values);
        if ($initialValue == $values[$i]["id"])
            $selected = "selected";
        else
            $selected = "";

        $dpl.="<option $selected value=\"" . $values[$i]["id"] . "\">" . $values[$i]["name"] . "</option>";
    }
    $dpl.="</select>";
    return $dpl;
}

function getIP() {
    $ip;
    if (getenv("HTTP_CLIENT_IP"))
        $ip = getenv("HTTP_CLIENT_IP");
    else if (getenv("HTTP_X_FORWARDED_FOR"))
        $ip = getenv("HTTP_X_FORWARDED_FOR");
    else if (getenv("REMOTE_ADDR"))
        $ip = getenv("REMOTE_ADDR");
    else
        $ip = "UNKNOWN";
    return $ip;
}

function getVisibilityText($key) {
    $strVisibleText = $_COOKIE[$key];

    $strVisibilityStyle = '';
    switch ($strVisibleText) {
        case 'smallText':
            $strVisibilityStyle = 'font-size:8px;';
            break;

        case 'mediumText':
            $strVisibilityStyle = 'font-size:12px;';
            break;

        case 'largeText':
            $strVisibilityStyle = 'font-size:15px;';
            break;

        default:
            $strVisibilityStyle = 'font-size:12px;';
            break;
    }
    return $strVisibilityStyle;
}

function curPageName() {
    return substr($_SERVER["SCRIPT_NAME"], strrpos($_SERVER["SCRIPT_NAME"], "/") + 1);
}

function getTextOnly($key) {
    $strTextOnly = $_COOKIE[$key];
    return $strTextOnly;
}

function GetAll($parent_id, $myPageAccess, $status, $divId='', $divStyle='') {
    global $accessLevel;
    $strOutput = '';

    $query = 'select * from system_files where file_sub_id=' . $parent_id . ' and active_yn = "Y"';
    if ($status == 'LO') {
        if ($accessLevel == 'officer') {
            $query.= ' and access in ("C","O") and file_id in ('.$myPageAccess.')';
        } else {
            $query.= ' and access in ("C","O")';
        }
    } else {
        $query.=' and file_id in (' . $myPageAccess . ')';
    }
    $result = getDBRecords($query);

    if (count($result) > 0) {
        $strOutput .= '<div id="div' . $divId . '" style="' . $divStyle . '"><ul>' . "\n";

        for ($i = 0; $i < count($result); $i++) {
//            $open = '';
//            $nopen = '';
//GetAll
//            if (count($this->node) > 0 && in_array($result[$i]['file_id'], $this->node))
//            {
//                $open = 'class="open"';
//            }
//
//            if (count($this->node) > 0 && in_array($result[$i]['file_id'], $this->node))
//            {
//                $nopen = 'class="checked"';
//            }
            $divId = $result[$i]['file_id'];

            $strOutput .= '<li id="' . $result[$i]['file_id'] . '"><input type="checkbox" onClick=\'checkChildBox("link' . $result[$i]['file_id'] . '","' . $result[$i]['file_id'] . '","' . $parent_id . '");\' name="' . $divId . '" id="link' . $result[$i]['file_id'] . '" value=""/>' . $result[$i]['file_desc'] . "\n";
            $strOutput .= GetAll($result[$i]['file_id'], $myPageAccess, $status, $divId, 'display:none;');
            $strOutput .= '</li>' . "\n";
        }
        $strOutput .= '</ul></div>' . "\n";
    }
    return $strOutput;
}

function GetAll_AccessRights($parent_id, $officer_type, $divId='', $divStyle='') 
{
    $strOutput = '';

	if($officer_type == "A") $access="'A','C'";
    elseif($officer_type == "O") $access="'O','C'";

    $query = 'select * from system_files where file_sub_id=' . $parent_id . ' and active_yn = "Y"';
    $query.=" and access in ($access)";   
    $result = getDBRecords($query);

    if (count($result) > 0) {
        $strOutput .= '<div id="div' . $divId . '" style="' . $divStyle . '"><ul>' . "\n";

        for ($i = 0; $i < count($result); $i++) 
		{

            $divId = $result[$i]['file_id'];

            $strOutput .= '<li id="' . $result[$i]['file_id'] . '"><input type="checkbox" onClick=\'checkChildBox("link' . $result[$i]['file_id'] . '","' . $result[$i]['file_id'] . '","' . $parent_id . '");\' name="' . $divId . '" id="link' . $result[$i]['file_id'] . '" value=""/>' . $result[$i]['file_desc'] . "\n";
            $strOutput .= GetAll_AccessRights($result[$i]['file_id'], $officer_type, $divId, 'display:none;');
            $strOutput .= '</li>' . "\n";
        }
        $strOutput .= '</ul></div>' . "\n";
    }
    return $strOutput;
   
}
function GetAll_ChildType($parent_id, $officer_type,$access_id_list, $divId='', $divStyle='')
{
    $arrSelectedItems = @explode(',',$access_id_list);

    $strOutput = '';

	if($officer_type == "A") $access="'A','C'";
    elseif($officer_type == "O") $access="'O','C'";

    $query = 'select * from system_files where file_sub_id=' . $parent_id . ' and active_yn = "Y"';
    $query.=" and access in ($access)";
    $result = getDBRecords($query);

    if (count($result) > 0) {
        $strOutput .= '<div id="div' . $divId . '" style="' . $divStyle . '"><ul>' . "\n";

        for ($i = 0; $i < count($result); $i++)
		{

                if(in_array($result[$i]['file_id'],$arrSelectedItems ))
                {
            $divId = $result[$i]['file_id'];

            $strOutput .= '<li id="' . $result[$i]['file_id'] . '"><input type="checkbox" onClick=\'checkChildBox("link' . $result[$i]['file_id'] . '","' . $result[$i]['file_id'] . '","' . $parent_id . '");\' name="' . $divId . '" id="link' . $result[$i]['file_id'] . '" value=""/>' . $result[$i]['file_desc'] . "\n";
            $strOutput .= GetAll_ChildType($result[$i]['file_id'], $officer_type,$access_id_list, $divId, 'display:none;');
            $strOutput .= '</li>' . "\n";
                }
        }
        $strOutput .= '</ul></div>' . "\n";
    }
    return $strOutput;
}

function GetAllDept($parent_id, $myPageAccess, $divId='', $divStyle='') {
    $queryDept = 'select * from company where parent_id='.$parent_id.' order by name ASC';
    //  echo $query;
    $resultDept = getDBRecords($queryDept);
    $strOutputDept = '';
    if (count($resultDept) > 0) {
        $strOutputDept .= '<div id="div' . $divId . '" style="' . $divStyle . '"><ul>' . "\n";

        for ($i = 0; $i < count($resultDept); $i++) {
            /*$open = "";
            $nopen = "";

           if (count($this->node) > 0 && in_array($result[$i]['company_id'], $this->node)) {
                $open = "class='open'";
            }
            if (count($this->node) > 0 && in_array($result[$i]['company_id'], $this->node)) {
                $nopen = "class='checked'";
            }*/
            $divId = $resultDept[$i]['company_id'];

            $strOutputDept .= '<li id="' . $resultDept[$i]['company_id'] . '"><input type="checkbox" onClick=\'checkChildBoxDept("link' . $resultDept[$i]['company_id'] . '","' . $resultDept[$i]['company_id'] . '","' . $parent_id . '");\' name="' . $divId . '" id="link' . $resultDept[$i]['company_id'] . '" value=""/>' . $resultDept[$i]['name']. "\n";
            $strOutputDept.=GetAllDept($resultDept[$i]['company_id'], $myPageAccess,$divId,'display:none;');
            $strOutputDept.= '</li>' . "\n";
        }
       $strOutputDept.= '</ul></div>' . "\n";
    }
    return $strOutputDept;

}

function getBrowser()
{
    $u_agent = $_SERVER['HTTP_USER_AGENT'];
    $bname = 'Unknown';
    $platform = 'Unknown';
    $version= "";

    //First get the platform?
    if (preg_match('/linux/i', $u_agent)) {
        $platform = 'linux';
    }
    elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
        $platform = 'mac';
    }
    elseif (preg_match('/windows|win32/i', $u_agent)) {
        $platform = 'windows';
    }

    // Next get the name of the useragent yes seperately and for good reason
    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent))
    {
        $bname = 'Internet Explorer';
        $ub = "MSIE";
    }
    elseif(preg_match('/Firefox/i',$u_agent))
    {
        $bname = 'Mozilla Firefox';
        $ub = "Firefox";
    }
    elseif(preg_match('/Chrome/i',$u_agent))
    {
        $bname = 'Google Chrome';
        $ub = "Chrome";
    }
    elseif(preg_match('/Safari/i',$u_agent))
    {
        $bname = 'Apple Safari';
        $ub = "Safari";
    }
    elseif(preg_match('/Opera/i',$u_agent))
    {
        $bname = 'Opera';
        $ub = "Opera";
    }
    elseif(preg_match('/Netscape/i',$u_agent))
    {
        $bname = 'Netscape';
        $ub = "Netscape";
    }

    // finally get the correct version number
    $known = array('Version', $ub, 'other');
    $pattern = '#(?<browser>' . join('|', $known) .
    ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
    if (!preg_match_all($pattern, $u_agent, $matches)) {
        // we have no matching number just continue
    }

    // see how many we have
    $i = count($matches['browser']);
    if ($i != 1) {
        //we will have two since we are not using 'other' argument yet
        //see if version is before or after the name
        if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
            $version= $matches['version'][0];
        }
        else {
            $version= $matches['version'][1];
        }
    }
    else {
        $version= $matches['version'][0];
    }

    // check if we have a number
    if ($version==null || $version=="") {$version="?";}

    return array(
        'userAgent' => $u_agent,
        'name'      => $bname,
        'version'   => $version,
        'platform'  => $platform,
        'pattern'    => $pattern
    );
}

function GetAllBranch($branchDetail,$userFlag,$divId='testing', $divStyle='')
{
    $branchDetailList = explode(",", $branchDetail);
    $branchDetailListCount=count($branchDetailList);

    $checked='';
    
    if($userFlag=='New')
        $checked='checked';

    if($userFlag=='NewOl')
        $checked='';

    $arrTopLevels = array(1,2,3,4);
    
    $strOutputBranch= '';
    $strOutputBranch .= '<div id="div'.$divId.'" style="'.$divStyle.'"><ul>'."\n";

    $arrFinalDeptList = array();
    $arrParentDept =  array();
    if(in_array(1,$branchDetailList)){
        $arrFinalDeptList[] = 1;
        $arrParentDept[1] = array();
    }
    if(in_array(2,$branchDetailList)){
        $arrFinalDeptList[] = 2;
        $arrParentDept[2] = array();

    }
    if(in_array(3,$branchDetailList)){
        $arrFinalDeptList[] = 3;
        $arrParentDept[3] = array();
    }
    if(in_array(4,$branchDetailList)){
        $arrFinalDeptList[] = 4;
        $arrParentDept[4] = array();
    }

    $arrParentRelation = array();
    

    $sqlOrderByTeam = "SELECT company_id,name,parent_id FROM company WHERE company_id In ($branchDetail) AND company_id NOT IN (1,2,3,4) ORDER BY parent_id,name";
    $rsOrderByTeam = getDBRecords($sqlOrderByTeam);

    
    if(count($rsOrderByTeam) > 0)
    {
        for ($k = 0; $k < count($rsOrderByTeam); $k++)
        {
            $arrFinalDeptList[] = $rsOrderByTeam[$k]['company_id'];
            $arrParentRelation[$rsOrderByTeam[$k]['company_id']] = $rsOrderByTeam[$k]['parent_id'];
            if(array_key_exists($rsOrderByTeam[$k]['parent_id'], $arrParentDept))
            {
                if(!array_key_exists($rsOrderByTeam[$k]['company_id'],$arrParentDept[$rsOrderByTeam[$k]['parent_id']]))
                    $arrParentDept[$rsOrderByTeam[$k]['parent_id']][$rsOrderByTeam[$k]['company_id']] = array();
                
            }
            else
            {
                if(array_key_exists($rsOrderByTeam[$k]['parent_id'],$arrParentDept[1]))
                {
                    if(!array_key_exists($rsOrderByTeam[$k]['company_id'],$arrParentDept[1][$rsOrderByTeam[$k]['parent_id']]))
                        $arrParentDept[1][$rsOrderByTeam[$k]['parent_id']][$rsOrderByTeam[$k]['company_id']] = array();
                }
                else if(array_key_exists($rsOrderByTeam[$k]['parent_id'],$arrParentDept[2]))
                {
                    if(!array_key_exists($rsOrderByTeam[$k]['company_id'],$arrParentDept[2][$rsOrderByTeam[$k]['parent_id']]))
                        $arrParentDept[2][$rsOrderByTeam[$k]['parent_id']][$rsOrderByTeam[$k]['company_id']] = array();                    
                }
                else if(array_key_exists($rsOrderByTeam[$k]['parent_id'],$arrParentDept[3]))
                {
                     if(!array_key_exists($rsOrderByTeam[$k]['company_id'],$arrParentDept[3][$rsOrderByTeam[$k]['parent_id']]))
                        $arrParentDept[3][$rsOrderByTeam[$k]['parent_id']][$rsOrderByTeam[$k]['company_id']] = array();                     
                }
                else if(array_key_exists($rsOrderByTeam[$k]['parent_id'],$arrParentDept[4]))
                {
                     if(!array_key_exists($rsOrderByTeam[$k]['company_id'],$arrParentDept[4][$rsOrderByTeam[$k]['parent_id']]))
                        $arrParentDept[4][$rsOrderByTeam[$k]['parent_id']][$rsOrderByTeam[$k]['company_id']] = array();                     
                }
                else
                {
                    $getParentId5 = $arrParentRelation[$rsOrderByTeam[$k]['company_id']];
                    if(!empty($getParentId5))
                        $getParentId4 = $arrParentRelation[$getParentId5];
                    if(!empty($getParentId4))
                        $getParentId3 = $arrParentRelation[$getParentId4];
                    if(!empty($getParentId3))
                        $getParentId2 = $arrParentRelation[$getParentId3];
                    if(!empty($getParentId2))
                        $getParentId1 = $arrParentRelation[$getParentId2];

                    if(!empty($getParentId5) && !empty($getParentId4) && !empty($getParentId3) && !empty($getParentId2) && !empty($getParentId1))
                    {
                        $arrParentDept[$getParentId1][$getParentId2][$getParentId3][$getParentId4][$getParentId5][$rsOrderByTeam[$k]['company_id']] = array();
                    }
                    else if(!empty($getParentId5) && !empty($getParentId4) && !empty($getParentId3) && !empty($getParentId2) && empty($getParentId1))
                    {
                        $arrParentDept[$getParentId2][$getParentId3][$getParentId4][$getParentId5][$rsOrderByTeam[$k]['company_id']] = array();
                    }
                    else if(!empty($getParentId5) && !empty($getParentId4) && !empty($getParentId3) && empty($getParentId2) && empty($getParentId1))
                    {
                        $arrParentDept[$getParentId3][$getParentId4][$getParentId5][$rsOrderByTeam[$k]['company_id']] = array();
                    }
                    else if(!empty($getParentId5) && !empty($getParentId4) && empty($getParentId3) && empty($getParentId2) && empty($getParentId1))
                    {
                        $arrParentDept[$getParentId4][$getParentId5][$rsOrderByTeam[$k]['company_id']] = array();
                    }                    
                }
            }
        }
    }    
    
    foreach($arrParentDept as $parentId=>$arrDepts)
    {
        $strName = '';
        if($parentId == 1)
           $strName = "Essex County Council";
        else if($parentId == 2)
           $strName = "ECC External Services";
        else if($parentId == 3)
           $strName = "ECC School/Academy";
        else if($parentId == 4)
           $strName = "ECC Internal Services";


        $strOutputBranch .='<input type="checkbox" '.$checked.' name="selectList_'.$parentId.'" onClick=\'checkChildBoxBranch("selectList_' . $parentId.'");\' id="selectList_' . $parentId.'" value="" /><strong>'.$strName.'</strong>'."<br />";
        
        $queryTopBranch = "select o.org_id,o.name,c.company_id,c.name cName from organisation o,company c where o.company_id ='$parentId' and o.company_id=c.company_id order by c.name,o.name ASC";
        $resultTopBranch = getDBRecords($queryTopBranch);

        $strOutputBranch .= '<div id=div_selectList_'.$resultTopBranch[0]['company_id'].'>';
        if(count($resultTopBranch) > 0)
        {
            /*$strOutputBranch .= '<ul>'."\n";
            for ($n = 0; $n < count($resultTopBranch); $n++)
            {
                $strOutputBranch .= '<li id="li'.$resultTopBranch[$n]['org_id'].'"><input '.$checked.' type="checkbox" name="'.$resultTopBranch[$n]['org_id'].'" id="link' .$resultTopBranch[$n]['org_id'].'" value="'.$resultTopBranch[$n]['org_id'].'"/>'.$resultTopBranch[$n]['name']."\n";
                $strOutputBranch.= '</li>'."\n";
            }
           $strOutputBranch.= '</ul>'."\n";*/
           $strOutputBranch.= getBranchListing($resultTopBranch[0]['company_id'],$resultTopBranch,$checked);
        }

        foreach($arrDepts as $deptId=>$arrSubDepts)
        {
            $queryBranch = "select o.org_id,o.name,c.company_id,c.name cName from organisation o,company c where o.company_id ='$deptId' and o.company_id=c.company_id order by c.name,o.name ASC";
            $resultBranch = getDBRecords($queryBranch);

            if(count($resultBranch) > 0)
            {
                if($resultBranch[0]['company_id'] == 1 || $resultBranch[0]['company_id'] == 2 || $resultBranch[0]['company_id'] == 3 || $resultBranch[0]['company_id'] == 4)
                    $strOutputBranch .='<input type="checkbox" '.$checked.' name="selectList_'.$resultBranch[0]['company_id'].'" onClick=\'checkChildBoxBranch("selectList_' . $resultBranch[0]['company_id'].'");\' id="selectList_' . $resultBranch[0]['company_id'].'" value="" /><strong>'.$resultBranch[0]['cName'].'</strong>'."<br />";
                else
                {
                    $strOutputBranch .='<ul><li><input type="checkbox" '.$checked.' name="selectList_'.$resultBranch[0]['company_id'].'" onClick=\'checkChildBoxBranch("selectList_' . $resultBranch[0]['company_id'].'");\' id="selectList_' . $resultBranch[0]['company_id'].'" value="" /><strong>'.$resultBranch[0]['cName'].'</strong>'."<br />";
                }
                $strOutputBranch .= '<div id=div_selectList_'.$resultBranch[0]['company_id'].'>';
                /*$strOutputBranch .= '<ul>'."\n";
                for ($i = 0; $i < count($resultBranch); $i++)
                {
                    $strOutputBranch .= '<li id="li'.$resultBranch[$i]['org_id'].'"><input '.$checked.' type="checkbox" name="'.$resultBranch[$i]['org_id'].'" id="link' .$resultBranch[$i]['org_id'].'" value="'.$resultBranch[$i]['org_id'].'"/>'.$resultBranch[$i]['name']."\n";
                    $strOutputBranch.= '</li>'."\n";
                }
               $strOutputBranch.= '</ul>'."\n";*/
               $strOutputBranch.= getBranchListing($resultBranch[0]['company_id'],$resultBranch,$checked);
               //$strOutputBranch.= '</div>'."\n";
               if(is_array($arrSubDepts) && count($arrSubDepts) > 0)
                    $strOutputBranch.= getDeptListing($arrSubDepts,$checked);
               
               $strOutputBranch.= '</div>';
               if($resultBranch[0]['company_id'] != 1 && $resultBranch[0]['company_id'] != 2 && $resultBranch[0]['company_id'] != 3 && $resultBranch[0]['company_id'] != 4)
                   $strOutputBranch.= '</li></ul>'."\n";
            }
            else
            {
                $queryDeptName = "select company_id,name from company where company_id ='".$deptId."'";
                $resultDeptName = getDBRecords($queryDeptName);

                if($resultDeptName[0]['company_id'] == 1 || $resultDeptName[0]['company_id'] == 2 || $resultDeptName[0]['company_id'] == 3 || $resultDeptName[0]['company_id'] == 4)
                    $strOutputBranch .='<input type="checkbox" '.$checked.' name="selectList_'.$resultDeptName[0]['company_id'].'" onClick=\'checkChildBoxBranch("selectList_' . $resultDeptName[0]['company_id'].'");\' id="selectList_' . $resultDeptName[0]['company_id'].'" value="" /><strong>'.$resultDeptName[0]['name'].'</strong>'."<br />";
                else
                {
                    $strOutputBranch .='<ul><li><input type="checkbox" '.$checked.' name="selectList_'.$resultDeptName[0]['company_id'].'" onClick=\'checkChildBoxBranch("selectList_' . $resultDeptName[0]['company_id'].'");\' id="selectList_' . $resultDeptName[0]['company_id'].'" value="" /><strong>'.$resultDeptName[0]['name'].'</strong>'."<br />";
                }
                $strOutputBranch .= '<div id=div_selectList_'.$resultDeptName[0]['company_id'].'>';

                if(is_array($arrSubDepts) && count($arrSubDepts) > 0)
                    $strOutputBranch.= getDeptListing($arrSubDepts,$checked);

                $strOutputBranch .= '</div>';

                if($resultDeptName[0]['company_id'] != 1 && $resultDeptName[0]['company_id'] != 2 && $resultDeptName[0]['company_id'] != 3 && $resultDeptName[0]['company_id'] != 4)
                   $strOutputBranch.= '</li></ul>'."\n";
            }
        }
        $strOutputBranch.= '</div>'."\n";
    }
    $strOutputBranch.= '</ul></div>'."\n";


    return $strOutputBranch;
}

function getBranchListing($compId,$result,$checked)
{
    $strOutputBranch .= '<ul>'."\n";
    for ($n = 0; $n < count($result); $n++)
    {
        $strOutputBranch .= '<li id="li'.$result[$n]['org_id'].'"><input '.$checked.' type="checkbox" name="'.$result[$n]['org_id'].'" id="link' .$result[$n]['org_id'].'" value="'.$result[$n]['org_id'].'"/>'.$result[$n]['name']."\n";
        $strOutputBranch.= '</li>'."\n";
    }
   $strOutputBranch.= '</ul>'."\n";
   return $strOutputBranch;
}

function getDeptListing($arrSubDepts,$checked)
{
    foreach($arrSubDepts as $deptId=>$arrSubLevelDepts)
    {
        $queryBranch = "select o.org_id,o.name,c.company_id,c.name cName from organisation o,company c where o.company_id ='$deptId' and o.company_id=c.company_id order by c.name,o.name ASC";
        $resultBranch = getDBRecords($queryBranch);

        if(count($resultBranch) > 0)
        {
            $strOutputBranch .='<ul><li><input type="checkbox" '.$checked.' name="selectList_'.$resultBranch[0]['company_id'].'" onClick=\'checkChildBoxBranch("selectList_' . $resultBranch[0]['company_id'].'");\' id="selectList_' . $resultBranch[0]['company_id'].'" value="" /><strong>'.$resultBranch[0]['cName'].'</strong>'."<br />";
            $strOutputBranch .= '<div id=div_selectList_'.$resultBranch[0]['company_id'].'>';
            /*$strOutputBranch .= '<ul>'."\n";
            for ($i = 0; $i < count($resultBranch); $i++)
            {
                $strOutputBranch .= '<li id="li'.$resultBranch[$i]['org_id'].'"><input '.$checked.' type="checkbox" name="'.$resultBranch[$i]['org_id'].'" id="link' .$resultBranch[$i]['org_id'].'" value="'.$resultBranch[$i]['org_id'].'"/>'.$resultBranch[$i]['name']."\n";
                $strOutputBranch.= '</li>'."\n";
            }
           $strOutputBranch.= '</ul>'."\n";*/
           $strOutputBranch.= getBranchListing($resultBranch[0]['company_id'],$resultBranch,$checked);
           
           
           if(is_array($arrSubLevelDepts) && count($arrSubLevelDepts) > 0)
                $strOutputBranch .= getDeptListing($arrSubLevelDepts,$checked);
           $strOutputBranch.= '</div>'."\n";
           $strOutputBranch.= '</li></ul>'."\n";
        }
        else
        {
            $queryDeptName = "select company_id,name from company where company_id ='".$deptId."'";
            $resultDeptName = getDBRecords($queryDeptName);

            if($resultDeptName[0]['company_id'] == 1 || $resultDeptName[0]['company_id'] == 2 || $resultDeptName[0]['company_id'] == 3 || $resultDeptName[0]['company_id'] == 4)
                $strOutputBranch .='<input type="checkbox" '.$checked.' name="selectList_'.$resultDeptName[0]['company_id'].'" onClick=\'checkChildBoxBranch("selectList_' . $resultDeptName[0]['company_id'].'");\' id="selectList_' . $resultDeptName[0]['company_id'].'" value="" /><strong>'.$resultDeptName[0]['name'].'</strong>'."<br />";
            else
            {
                $strOutputBranch .='<ul><li><input type="checkbox" '.$checked.' name="selectList_'.$resultDeptName[0]['company_id'].'" onClick=\'checkChildBoxBranch("selectList_' . $resultDeptName[0]['company_id'].'");\' id="selectList_' . $resultDeptName[0]['company_id'].'" value="" /><strong>'.$resultDeptName[0]['name'].'</strong>'."<br />";
            }
            $strOutputBranch .= '<div id=div_selectList_'.$resultDeptName[0]['company_id'].'>';

            if(is_array($arrSubLevelDepts) && count($arrSubLevelDepts) > 0)
                $strOutputBranch .= getDeptListing($arrSubLevelDepts,$checked);

             $strOutputBranch .= '</div>';

              if($resultDeptName[0]['company_id'] != 1 && $resultDeptName[0]['company_id'] != 2 && $resultDeptName[0]['company_id'] != 3 && $resultDeptName[0]['company_id'] != 4)
                   $strOutputBranch.= '</li></ul>'."\n";
        }
    }
    return $strOutputBranch;
}
function GetAllBranchChildLevel($branchDetail,$userFlag,$divId='testing', $divStyle='')
{
    $branchDetailList = explode(",", $branchDetail);
    $branchDetailListCount=count($branchDetailList);

    $checked='';

    if($userFlag=='New')
        $checked='checked';

    if($userFlag=='NewOl')
        $checked='';

    
   $selectParent = "select company_id from company WHERE parent_id In ($branchDetail)";
    $countDisplay = getDBRecords($selectParent);
    if(count($countDisplay == 0)){
            $queryBranch = "select o.org_id,o.name,c.company_id,c.name cName from organisation o,company c where o.company_id In ($branchDetail) and o.company_id=c.company_id order by c.name,o.name ASC";
            $resultBranch = getDBRecords($queryBranch);
            if(count($resultBranch) > 0){
                $strOutputBranch .='<ul><li><input type="checkbox" '.$checked.' name="selectList_'.$resultBranch[0]['company_id'].'" onClick=\'checkChildBoxBranch("selectList_' . $resultBranch[0]['company_id'].'");\' id="selectList_' . $resultBranch[0]['company_id'].'" value="" /><strong>'.$resultBranch[0]['cName'].'</strong>'."<br />";

                $strOutputBranch .= '<div id=div_selectList_'.$resultBranch[0]['company_id'].'>';
                /*$strOutputBranch .= '<ul>'."\n";
                for ($i = 0; $i < count($resultBranch); $i++)
                {
                    $strOutputBranch .= '<li id="li'.$resultBranch[$i]['org_id'].'"><input '.$checked.' type="checkbox" name="'.$resultBranch[$i]['org_id'].'" id="link' .$resultBranch[$i]['org_id'].'" value="'.$resultBranch[$i]['org_id'].'"/>'.$resultBranch[$i]['name']."\n";
                    $strOutputBranch.= '</li>'."\n";
                }
               $strOutputBranch.= '</ul>'."\n";*/
               $strOutputBranch.= getBranchListing($resultBranch[0]['company_id'],$resultBranch,$checked);
            }
    }
     $strOutputBranch.= '</div></ul>'."\n";
    return $strOutputBranch;

}

?>
