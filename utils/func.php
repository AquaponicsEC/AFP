<?php

if (!function_exists('_translate')) {

    /**
     * Return translation for given English string
     * ($lang Array of translation (English to specific locale))
     * @param string $str -> string to be translated
     * @return string translated
     */
    function _translate($str) {
        include $_SERVER['DOCUMENT_ROOT'] . "/se/locale/" . $_SESSION['lang'] . ".php";
        return (isset($lang[$str]) ? $lang[$str] : $str);
    }

}

/**
 * Session Control - Global
 * @param type $user -> Login username
 * @return int
 */
function sessionCtrl($user) {
    include($_SERVER['DOCUMENT_ROOT'] . '/se/rf_locals.php');

    $fname = $loc_root . "/Users/sessionCtrl.json";

    $ssid = session_id();
    $utime = time();

    $arr = array();

    if (file_exists($fname)) {
        $arr = json_decode(file_get_contents($fname), true);
    }

    if (!isset($arr[$user])) { // new user
        $arr[$user]['ssid'] = $ssid;
        $arr[$user]['utime'] = $utime;
    } else {
        if ($arr[$user]['ssid'] == $ssid) { // same session
            $arr[$user]['utime'] = $utime;
        } else {
            if (isset($loc_LicenseNo)) {
                if (count($arr) == $loc_LicenseNo) {
                    return -6; //limit number of licensees
                }
            }

            if ($utime - $arr[$user]['utime'] > 20 * 60) { // suppose ok
                $arr[$user]['ssid'] = $ssid;
                $arr[$user]['utime'] = $utime;
            } else { // 
                return -5; //activeUser
            }
        }
    }

    file_put_contents($fname, json_encode($arr, JSON_PRETTY_PRINT));
    return 1;
}

/**
 * Create user directory into user folder in SpectrumE
 * @param type $doc, array $doc document
 * @param type $newPassword, password 
 * @return boolean
 */
function create_user_dir($doc, $password, $portal) {
    include($_SERVER['DOCUMENT_ROOT'] . '/se/rf_locals.php');

    $UserAccessXML = $loc_udir . '/UserAccess.xml';
    $xml = simplexml_load_file($UserAccessXML);

    $usr = $doc['email'];

    if (empty($xml->xpath('user[@email="' . $usr . '"]'))) {

        $dom = new DOMDocument('1.0', 'iso-8859-1');
        $dom->preserveWhiteSpace = false;
        $dom->load($UserAccessXML);

        $xpath = new DOMXPath($dom);

        $node = $dom->createElement('user');
        $newuser = $dom->appendChild($node);

        $newuser->setAttribute('email', $usr);
        $newuser->setAttribute('fname', $doc['fname']);
        $newuser->setAttribute('lname', $doc['lname']);
        $newuser->setAttribute('password', $password);
        $newuser->setAttribute('role', $doc['role']);

        $lang = ($doc['lang']) ? $doc['lang'] : 'en';
        $newuser->setAttribute('lang', $lang);

        $exp = ($doc['exp']) ? $doc['exp'] : 22220101;
        $newuser->setAttribute('exp', $exp);

        $next = $xpath->query('user')->item(0);
        $next->parentNode->insertBefore($newuser, $next);

        $dom->formatOutput = true;
        $dom->save($UserAccessXML);

        //Create New Folder
        $source = $loc_udir . '/_' . $portal . '_' . $doc['role'];
        $destination = $loc_udir . "/$usr";

        exec('xcopy "' . $source . '" "' . $destination . '" /e/i');

        return true;
    }
}

/**
 * Create user directory into user folder in SpectrumE
 * @param type $user -> user in SE
 * @param type $role -> user role in system (if it's not included it takes by default 'internal'
 */
function createUserDirectory($user, $role = '') {

    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    include($docRoot . '/se/rf_locals.php');

    $role = !empty($role) ? $role : 'internal';

    $source = $loc_udir . '/_' . $_SESSION['portal'] . '_' . $role;
    $destination = $loc_udir . "/$user";

    exec('xcopy "' . $source . '" "' . $destination . '" /e/i');
}

/**
 * Sync the users from userAccess xml into Mongo
 * @param type $loc_udir -> userAccess location
 * @param type $db -> mongo db connection
 */
function syncUserAccess() {

    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    include($docRoot . '/se/rf_locals.php');
    include($docRoot . '/se/dbconnect.php');

    $xml = simplexml_load_file($loc_udir . '/UserAccess.xml');

    $xmlArray = json_decode(json_encode((array) $xml), TRUE);

    if (array_key_exists('user', $xmlArray)) {
        if (array_key_exists('@attributes', $xmlArray)) {
            $doc = $xml['user']['@attributes'];
            $m->$dbname->insert($doc);
        } else {
            for ($i = 0; $i < count($xmlArray['user']); $i++) {
                $doc = $xmlArray['user'][$i]['@attributes'];

                $existingUser = $m->$dbname->userAcess->findOne(array('email' => $doc['email']));

                if (!$existingUser) {
                    $date = date("Y-m-d H:i:s");

                    $doc['_id'] = uniqid();

                    if (isset($doc['role'])) {
                        $doc['role'] = explode('|', $doc['role']);
                    }

                    $doc['_TYPE'] = 'user';
                    $doc['password'] = sha1($doc['password']);
                    $doc['Status']['user'] = $_SESSION['usr'];
                    $doc['Status']['process'] = 'active'; //needed for recovering password
                    $doc['Status']['createdType'] = 'internal'; //needed for differentiation between internal or external user
                    $doc['Status']['createdBy'] = $_SESSION['usr'];
                    $doc['Status']['dateTime'] = $date;
                    $doc['Status']['createdDateTime'] = $date;
                    $doc['Status']['state'] = 'USR-00';
                }
                $m->$dbname->userAccess->insert($doc);
            }
        }
    }
}

/**
 * Updates UserAccess.xml for a given user
 * @param type $loc_udir -> userAccess location
 * @param type $doc -> array (document with user information)
 */
function updateUserAccess($loc_udir, $doc) {

    $xml = $loc_udir . '/UserAccess.xml';
    //$user = simplexml_load_file($UserAccessXML)->xpath('user[@email="'.$doc['email'].'"]');     

    $dom = new DOMDocument('1.0', 'iso-8859-1');
    $dom->load($xml);

    $xpath = new DOMXPath($dom);
    $user = $xpath->query('user[@email="' . $doc['email'] . '"]');

    if (isset($doc['email'])) {
        $user->item(0)->setAttribute('email', $doc['email']);
    }
    if (isset($doc['fname'])) {
        $user->item(0)->setAttribute('fname', $doc['fname']);
    }
    if (isset($doc['lname'])) {
        $user->item(0)->setAttribute('lname', $doc['lname']);
    }
    if (isset($doc['exp'])) {
        $user->item(0)->setAttribute('exp', $doc['exp']);
    }
    if (isset($doc['role'])) {
        $user->item(0)->setAttribute('role', $doc['role']);
    }
    if (isset($doc['lang'])) {
        $user->item(0)->setAttribute('lang', $doc['lang']);
    }

    $dom->save($xml);
}

/**
 * Gets target state from scxml
 * @param type $wfid -> id workflow
 * @param type $state -> current state
 * @param type $proc -> event name
 * @return target state
 */
function getTarget($wfid, $state, $proc) {

    $root = getConfigFile('workflow', 'xml', $wfid);
    $scxml = $root->scxml;

    /*     * If the scxml is not defined in user,
     * it gets the scxml from the extra configFile definition set in user xml* */
    if (!$scxml) {
        getConfigFileDefaultElements($root, $scxml, 'scxml');
    }

    $xst = $scxml->xpath('state[@id="' . $state . '"]/transition[@event="' . $proc . '"]'); //get status
    if (isset($xst[0])) {
        $target = (string) $xst[0]->attributes()->target;
        return $target;
    } else {
        return null;
    }
}

/**
 * Sends Mail with the related information 
 * @param type $to
 * @param type $from
 * @param type $subject
 * @param type $textContent
 * @param type $htmlContent
 * @return type
 */
function sendEmail($to, $from, $subject, $textContent, $htmlContent = '', $attachment = false) {

    include $_SERVER['DOCUMENT_ROOT'] . "/se/rf_locals.php";
    require_once $_SERVER['DOCUMENT_ROOT'] . "/se/utils/PHPMailer/PHPMailerAutoload.php";

    //global $smtpPassword, $smtpServer, $smtpUsername, $senderEmail, $senderName;
    // Send mail
    $mail = new PHPMailer();
    $mail->IsSMTP(); // telling the class to use SMTP
    //Doesn't work if SMTPDebug is not set
    $mail->SMTPDebug = false;
    $mail->CharSet = 'UTF-8';


    if (!isset($smtpPassword)) {
        // SMTP Configuration
        $mail->SMTPAuth = false; // enable SMTP authentication
        $mail->Host = ($smtpServer) ? $smtpServer : 'atdi.us.com'; // SMTP server
        $mail->Port = ($smtpPort) ? $smtpPort : 587; // optional if you don't want to use the default 
    } else {
        // SMTP Configuration
        $mail->SMTPAuth = true; // enable SMTP authentication
        $mail->Host = ($smtpServer) ? $smtpServer : 'atdi.us.com'; // SMTP server
        $mail->Username = ($smtpUsername) ? $smtpUsername : 'info@atdi.us.com';
        $mail->Password = ($smtpPassword) ? $smtpPassword : 'SGER2016';
        $mail->Port = 587; // optional if you don't want to use the default 
    }


    $mail->From = ($senderEmail) ? $senderEmail : 'SpectrumE@atdi.us.com';
    $mail->FromName = ($senderName) ? $senderName : 'Spectrum-E';
    $mail->Subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $mail->MsgHTML("<br /><br />" . $htmlContent);
    //$mail->Body("<br /><br />" . $htmlContent);
    // Add as many as you want
    $toArray = explode(',', $to);
    foreach ($toArray as $toEmail) {
        $mail->AddAddress($toEmail);
    }
    $mail->AddBCC('aftic.sger@gmail.com');

    if ($attachment) {
        foreach ($attachment as $attch) {
            $mail->AddAttachment($attch['tmp_name'], $attch['name']);
        }
    }

    $image = $_SERVER['DOCUMENT_ROOT'] . '/se/portal/' . $_SESSION['portal'] . '/images/email-logo.png';
    $mail->AddEmbeddedImage($image, "portal-logo", $image);

    // If you want to attach a file, relative path to it
    //$mail->AddAttachment("images/phpmailer.gif");

    $response = NULL;
    if (!$mail->Send()) {
        $response = "Mailer Error: " . $mail->ErrorInfo;
    } else {
        $response = "Message sent!";
    }

    //$output = json_encode(array("response" => $response));  
    //header('content-type: application/json; charset=utf-8');
}

/**
 * Sends Mail with the related information 
 * @param type $to
 * @param type $from
 * @param type $subject
 * @param type $textContent
 * @param type $htmlContent
 * @attachments type array with subdocuments ['filename'] and ['content'] in base64 encode
 * @return type
 */
function sendEmailWithAttch($to, $from, $subject, $textContent, $htmlContent = '', $attachments = null) {
    $boundaryHash = '_m-' . md5(date('r', time()));
    $boundaryHash2 = '_m-' . md5(date('r', time()));
    $atchHeader = "";
    $headers = <<<AKAM
From: SGER <$from>
MIME-Version: 1.0
Content-Type: multipart/mixed;
    boundary="$boundaryHash"
AKAM;

    foreach ($attachments as $attachment) {
        $atchName = $attachment['filename'];
        $atchContent = $attachment['content'];
        $atchType = "txt";

        $atchHeader.=<<<ATTA
--$boundaryHash
Content-Type: $atchType;
    name="$atchName"
Content-Transfer-Encoding: base64
Content-Disposition: attachment;
filename="$atchName"

$atchContent
ATTA;
    }

    $body = <<<AKAM
This is a multi-part message in MIME format.

--$boundaryHash
Content-Type: multipart/alternative;
    boundary="$boundaryHash2"

--$boundaryHash2
Content-Type: text/plain;
    charset="utf-8"
Content-Transfer-Encoding: quoted-printable

$textContent
--$boundaryHash2
Content-Type: text/html;
    charset="utf-8"
Content-Transfer-Encoding: quoted-printable

$htmlContent

--$boundaryHash2--

$atchHeader
--$boundaryHash--
AKAM;

    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

/**
 * Convert stdClass Object to Array in PHP
 * @param type $d = stdClass Object 
 * @return array
 */
function stdClassObjectToArray($d) {
    if (is_object($d)) {
        // Gets the properties of the given object
        // with get_object_vars function
        $d = get_object_vars($d);
    }

    if (is_array($d)) {
        /*
         * Return array converted to object
         * Using __FUNCTION__ (Magic constant)
         * for recursive call
         */
        return array_map(__FUNCTION__, $d);
    } else {
        // Return array
        return $d;
    }
}

/**
 * Obtain value for $key in $data array, regardless of $key depth (separate levels using a period)
 * @param string $key
 * @param array $data
 * @return mixed
 */
function getArrayValue($key, $data) {
    $keys = explode('.', $key);
    $value = null;
    $node = $data;
    while (list($var, $val) = each($keys)) {
        if (isset($node[$val])) {
            $value = $node[$val];
            $node = $node[$val];
        } else {
            $value = null;
        }
    }
    return $value;
}

function mongoDMS2DEC($str) {

    $str = str_replace(",", ".", $str);
    $deg = (isset($str['field']['deg']) ? floatval($str['field']['deg']) : 0);
    $min = (isset($str['field']['minutes']) ? floatval($str['field']['minutes']) : 0);
    $sec = (isset($str['field']['seconds']) ? floatval($str['field']['seconds']) : 0);
    $val = $deg + $min / 60 + $sec / 3600;
    $val = -$val;

    return round($val, 5);
}

function getDECfromDMS($str) {

    $nstr = explode('.', $str);
    $deg = $nstr[0];
    $sgn = explode('-', $deg);
    $deg = (count($sgn) > 1) ? $sgn[1] : $sgn[0];
    $min = substr($nstr[1], 0, 2);
    $sec = substr($nstr[1], 2);
    if (strlen($sec) > 2) {
        $sec = $sec / 10 ** (strlen($sec) - 2);
    }
    $val = $deg + $min / 60 + $sec / 3600;
    if (count($sgn) > 1) {
        $val *= -1;
    }
    return round($val, 5);
}

if (!function_exists('DEC2DMS')) {
    /**
     * convert coordinates from DEC to DMS
     * 
     * Project: SpectrumE
     * Module: Anatel, SGER
     * Subject: Geographic Coordinates
     * 
     * @param type $value
     * @param type $isLat : 1- latitude , 0- Longitude
     * @return type
     */
    function DEC2DMS($value, $isLat) {

        $deg = floor(abs($value));
        $min = floor((abs($value) - $deg) * 60);
        $sec = (abs($value) - $deg - $min / 60) * 3600.;

        if ($isLat)
            $nsew = ($value < 0) ? 'S' : 'N';
        if (!$isLat)
            $nsew = ($value < 0) ? 'W' : 'E';

        $dms['dms'] = sprintf("%.0f&deg; %02.0f&#39; %04.1f&#34; %s", $deg, $min, $sec, $nsew);
        $dms['d'] = $deg;
        $dms['m'] = $min;
        $dms['s'] = round($sec, 2);
        $dms['o'] = $nsew;

        return $dms;
    }
}

if (!function_exists('convertDMS2DEC')) {
    
    /**
     * Convert DMS coordinates format to DEC format
     * @param string or array $dms -> coordinates in DMS format as string or separated values in array
     * @param int $round -> round any characters, but 5 by default
     * @return coordinates in decimal format
     */
    function convertDMS2DEC($dms, $round = 5) {

        //Only when DMS value are coming separated in array
        if (is_array($dms)) {
            
            $D = floatval($dms['D']);
            $M = floatval($dms['M']);
            $S = floatval($dms['S']);
            $H = preg_match('/[SsWw]/', $dms['H']);
            
        } else {
            $string = removeSpecialCaracter($dms);
            $str = str_split(preg_replace('/[SsWwNnEe]+/', '', $string), 2);
            $D = (isset($str[0]) ? floatval($str[0]) : 0);
            $M = (isset($str[1]) ? floatval($str[1]) : 0);
            $S = (isset($str[2]) ? floatval($str[2]) : 0);
            $H = preg_match('/[SsWw]/', $dms);
            
        }

        $result = (abs($D) + abs($M) / 60 + abs($S) / 3600);

        /*
         * If $dms has South (Ss) or West(Ww) -> Hemisphere $H = 1 (TRUE)
         * and the latitude or longitude converted must have be negative
         */
        if ($H == 1) {
            $result *= -1;
        }

        return round($result, $round);
    }

}

/*
 * Removes special caracter from a string
 * @param string $str
 */
function removeSpecialCaracter($str){
    $a = ['.',',','"',"'",'`','~','-',' '];
    $b = ['','','',"",'','',''];
    return str_replace($a, $b, $str);
}

/**
 * Convert Emission Code into Bandwidth in MHz
 * 
 * Project: SpectrumE
 * Module: SGER
 * Subject: Emission Code to Bandwidth
 * 
 * @param type $code
 * @return type
 */
function EMS2BW($code) {

    $ems = strtoupper($code);
    $str = str_split($code);
    $count = count($str);
    $m = '';
    $n = '';

    for ($m = 0; $m < $count && $str[$m] >= '0' && $str[$m] <= '9'; $m++) {
        
    }
    $str[$m] = '.';

    for ($n = $m + 1; $n < strlen($ems) && $str[$n] >= '0' && $str[$n] <= '9'; $n++) {
        
    }
    $str[$n] = 0;

    $bw = array_slice($str, 0, $n);
    $bw = implode("", $bw);

    if (isset($ems[$m])) {
        if ($ems[$m] == 'M')
            $bw = $bw * 1000;
        if ($ems[$m] == 'G')
            $bw = $bw * 1000000;
        if ($ems[$m] == 'H')
            $bw = ($bw / 1000);
        $bw = min(100000.0, max($bw, 1.0));
    }

    return $bw;  // kHz
}

/**
 * Convert channel # into frequency in MHz
 * @param int $ch = channel number
 * @return int - frequency value
 */
function channel2frequency($ch) {
    //TV
    $ch = preg_replace('/[^0-9.]+/', '', $ch);
    if ($ch >= 2 && $ch <= 4)
        return intval(($ch - 2) * 6 + 57);
    if ($ch >= 5 && $ch <= 6)
        return intval(($ch - 5) * 6 + 79);
    if ($ch >= 7 && $ch <= 13)
        return intval(($ch - 7) * 6 + 177);
    if ($ch >= 14 && $ch <= 68)
        return intval(($ch - 14) * 6 + 473);

    //FM
    if ($ch >= 198 && $ch <= 300)
        return round(($ch - 198) * .2 + 87.5, 1);
    if ($ch = 6)
        return 85;

    return 1;
}

/**
 * Get user permissions from Workflow json and state chart xml 
 * @param string $wfid workflow id (json file's name)
 * @param number $view applist view (tab id)
 * @param string $state state name from mongo document
 * @return array
 */
function getUserPermissions($wfid, $view, $state) {

    $userspath = 'C:/atdi/SpectrumE/Users/';
    $role = $_SESSION['usrrole'];
    $portal = $_SESSION['portal'];
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $wfFile = $_SESSION['usrpath'] . 'workflow/' . $wfid . '.json';
    if (!file_exists($wfFile))
        $wfFile = $_SESSION['usrpath'] . 'workflow/' . $portal . '_' . $wfid . '.json';
    if (!file_exists($wfFile))
        $wfFile = $userspath . '_' . $portal . '_' . $role . '/workflow/' . $portal . '_' . $wfid . '.json';
    if (!file_exists($wfFile))
        $wfFile = $docRoot . '/se/_conf/users/' . $portal . '/_' . $portal . '_' . $role . '/workflow/' . $portal . '_' . $wfid . '.json';
    $wf = json_decode(file_get_contents($wfFile), true);

    $buttons = array();

    if (isset($wf['view'][$view]['buttons'])) {
        foreach ($wf['view'][$view]['buttons'] as $button) {
            $buttons[] = $button[0];
        }
    }

    if (!empty($state)) {
        $fname = $_SESSION['usrpath'] . 'workflow/' . $wf['scxml'] . '.xml';
        if (!file_exists($fname))
            $fname = $_SESSION['usrpath'] . 'workflow/' . $portal . '_' . $wf['scxml'] . '.xml';
        if (!file_exists($fname))
            $fname = $userspath . '_' . $portal . '_' . $role . '/workflow/' . $portal . '_' . $wf['scxml'] . '.xml';
        if (!file_exists($fname))
            $fname = $docRoot . '/se/_conf/users/' . $portal . '/_' . $portal . '_' . $role . '/workflow/' . $portal . '_' . $wf['scxml'] . '.xml';
        $scxml = simplexml_load_file($fname);  //State Chart XML

        $xst = $scxml->scxml->xpath('state[@id="' . $state . '"]'); //this fix issue with root on xml file
        if ($xst) {
            for ($i = 0; $xst[0]->transition[$i]; $i++) {
                $event[] = (string) $xst[0]->transition[$i]->attributes()->event;
            }
        }
        return array_merge($buttons, $event);
    } else {
        return $buttons;
    }
}

/**
 * Translates string to specific language base on the lang session variable 
 * @global array $lang - array that includes all the translation located in locale folder 
 * @param string $str - string to be translated
 * @return string - string translated to specific language
 */
function _t($str) {
    global $lang;
    return (isset($lang[$str]) ? $lang[$str] : $str);
}

/**
 * Gets Execution Time
 * @param time $time_start = microtime(true); (set at the begining of the page)
 */
function execTime($time_start) {
    echo "<br/>";
    $time_end = microtime(true);
    $execution_time0 = ($time_end - $time_start);
    $execution_time1 = $execution_time0 / 60;
    echo round($execution_time1, 2) . " minutes<br/>";
    echo round($execution_time0, 2) . " seconds<br/><br/>";
}

/**
 * 
 * @param type $x
 * @param type $y
 * @return type
 */
function getLoc($long, $lat) {
    $locx = array("type" => "Point");
    $coordinates = array();
    $coordinates['0'] = (double) $long;
    $coordinates['1'] = (double) $lat;
    $locx["coordinates"] = $coordinates;
    return $locx;
}

/**
 * Check CSV headers array against $fieldDef: remove units, add .lat/.long for coordinates
 * @param type $csvHeader
 * @param type $fieldDef
 * @return mixed
 *              true: $csvHeader is valid
 *              string: name of the field that has an error
 */
function csvCheckHeaders(&$csvHeader, $fieldDef) {
    $coordPattern = ['/\.lat$/', '/\.long$/'];
    $cordRep = '${1}';

    foreach ($csvHeader as $key => $field) {
        // remove units in case we have them (anything after a space)
        $shortField = explode(' ', $field)[0];
        $csvHeader[$key] = $shortField;

        // remove ".lat" and ".long" from field name for coordinates
        $shortField = preg_replace($coordPattern, $cordRep, $shortField);

        // check if field exists in _TYPE definition
        if (!array_key_exists($shortField, $fieldDef)) {
            return $field;
        }
    }
    return true;
}

/**
 * Return MongoDB-compatible array out of plain PHP array, using $fieldDef for the
 * MongoDB document structure and data types.
 * @param array $sourceRow
 * @param array $fieldDef
 * @return array
 */
function csvImportFormatValues($sourceRow, $fieldDef) {
    $row = array();
    foreach ($fieldDef as $fieldName => $fieldAttr) {
        // set default attributes
        if (!isset($fieldAttr['isArray'])) :
            $fieldAttr['isArray'] = false;
        endif;

        $value = null;

        // update fields based on their data type
        switch ($fieldAttr['dataType']):
            case 'integer':
                if ($sourceRow[$fieldName] != '') {
                    $value = intval($sourceRow[$fieldName]);
                } else {
                    $value = null;
                }
                break;

            case 'range': // treat range filter as numeric value
            case 'number':
            case 'float':
            case 'real':
                if ($sourceRow[$fieldName] != '') {
                    if ($fieldAttr['isArray']) {
                        $value = array_map('floatval', explode('|', $sourceRow[$fieldName]));
                    } else {
                        $value = floatval($sourceRow[$fieldName]);
                    }
                } else {
                    $value = null;
                }
                break;

            case 'coord':
                // $row[$fieldName] = 'coord';
                if (!empty($sourceRow[$fieldName . '.long']) &&
                        !empty($sourceRow[$fieldName . '.lat'])) {
                    $value = [ 'type' => 'Point', 'coordinates' => [floatval($sourceRow[$fieldName . '.long']), floatval($sourceRow[$fieldName . '.lat'])]];
                }
                break;

            case 'string':
                if ($sourceRow[$fieldName] != '') {
                    $value = utf8_encode($sourceRow[$fieldName]);
                } else {
                    $value = null;
                }
        endswitch;

        // check if $value is not null - skip field if null
        if ($value === null)
            continue;

        // send value to $row array based on key's length
        $keyPart = explode('.', $fieldName);
        $partCount = count($keyPart);
        switch ($partCount):
            case 1: $row[$keyPart[0]] = $value;
                break;
            case 2: $row[$keyPart[0]][$keyPart[1]] = $value;
                break;
            case 3: $row[$keyPart[0]][$keyPart[1]][$keyPart[2]] = $value;
                break;
            case 4: $row[$keyPart[0]][$keyPart[1]][$keyPart[2]][$keyPart[3]] = $value;
                break;
        endswitch;
    }
    return $row;
}

/**
 * Return $field formatted for usage in CSV file
 * @param mixed $value
 * @return string
 */
function csvField($value) {
    $tmp = trim($value);
    if (strpos($value, '"') !== false) {
        $tmp = '"' . str_replace(['"'], ['""'], $value) . '"';
    } elseif (strpos($value, ',') !== false) {
        $tmp = '"' . $tmp . '"';
    }

    return utf8_decode($tmp);
}

/**
 * Get Options for Select Element (drop down menu) from DB
 * @param type $m -> Mongo Connection
 * @param type $collection -> Collection that holds the different menu options
 * @param type $name -> Name of the menu option needed to be displayed
 */
function getSelectOptionsFromDB($name, $collection) {

    global $m;

    $doc = $m->$_SESSION['dbname']->combos->findOne(array('NAME' => $name));

    $return = array();

    if ($doc['COLLECTIONS']) {
        foreach ($doc['COLLECTIONS'] as $col) {
            if ($col['COLLECTION'] == $collection) {
                asort($doc['OPTIONS']);
                $return = $doc['OPTIONS'];
            }
        }
    } else
        $return = $doc['OPTIONS'];

    return $return;
}

/**
 * List all the collections from Mongo DB
 * @global type $m -> MongoDB Connection
 * @return array -> Collection's names
 */
function getCollections() {
    global $m;
    $collections = $m->$_SESSION['dbname']->listCollections();

    foreach ($collections as $collection) {
        $col = explode('.', $collection);
        $return[$col[1]] = $col[1];
    }
    asort($return);
    return $return;
}

/**
 * Get the collection view, and name from configFile.xml (ctx)
 * 
 * Project Spectrum-E
 * Module Anatel
 * Subject View and collection
 * 
 * @param xml content $root
 * @param string $state
 * @param string $fid Form id (defaults to null)
 * @return array [collection,view]
 */
function getCollectionConfigFile($root, $state, $fid = '') {

    /** $xml = $root->form; */
    $xml = $fid === '' ? $root->form : $root->xpath('form[@id="' . $fid . '"]')[0];

    foreach ($xml->ctx as $ctx) {

        $states = (string) $ctx->attributes()->states;
        $collState = in_array($state, explode('|', (string) $states));

        if ($collState) {
            $return['collection'] = (string) $ctx->attributes()->collection;
            $return['view'] = (string) $ctx->attributes()->view;
            $return['name'] = (string) $ctx->attributes()->name;
            break;
        }
    }

    if (empty($return)) {
        /** or return a exception informing that the collection was not found in the configFile */
        $return = ['collection' => 'application', 'view' => '0'];
        return $return;
    } else {

        return $return;
    }
}

/**
 * Get form information from right xml 
 * @param type $root -> root file xml
 * @param type $xmlForm -> xml information for form
 * @param type $wfid -> workflow id
 * @param type $fid -> form id
 * @return type $wfi -> nuevo wfid correspondiente al form encontrado
 */
function getConfigFileForm(&$root, &$xmlForm, $wfid, $fid, $usrrole = null) {

    $returnWfid = $wfid;

    $form = isset($root->xpath('form[@id="' . $fid . '"]')[0]) ? $root->xpath('form[@id="' . $fid . '"]')[0] : null;
    $xmlForm = $fid === '' ? $root->form : $form;

    /** Get form from master form if it is defined in the xml file * */
    $masterForm = (string) $xmlForm['masterForm'];
    if (!empty($masterForm)) {
        $masterForm = (string) $xmlForm['masterForm'];
        if (!empty($masterForm)) {
            $label = (string) $xmlForm['label'];
            $breadcrumb = (string) $xmlForm['breadcrumb'];

            $xmlForm = isset($root->xpath('form[@id="' . $masterForm . '"]')[0]) ? $root->xpath('form[@id="' . $masterForm . '"]')[0] : null;

            /** Redefine label and breadcrumb * */
            $xmlForm['breadcrumb'] = $breadcrumb;
            $xmlForm['label'] = $label;
        }
    }

    if (empty($xmlForm) && isset($root->confFile)) {
        $confFiles = $root->confFile;
        $countConfFiles = count($confFiles);
        for ($i = 0; $i < $countConfFiles; $i++) {
            $nameConfFile = (string) $confFiles[$i]['name'];
            $wfidConfFile = (string) $confFiles[$i]['wfid'];
            $rootForm = getConfigFile('workflow', 'xml', $nameConfFile, $usrrole);
            $xmlForm = isset($rootForm->xpath('form[@id="' . $fid . '"]')[0]) ? $rootForm->xpath('form[@id="' . $fid . '"]')[0] : null;

            /** Get form from master form if it is defined in the xml file * */
            $masterForm = (string) $xmlForm['masterForm'];
            if (!empty($masterForm)) {
                $label = (string) $xmlForm['label'];
                $breadcrumb = (string) $xmlForm['breadcrumb'];

                $xmlForm = isset($rootForm->xpath('form[@id="' . $masterForm . '"]')[0]) ? $rootForm->xpath('form[@id="' . $masterForm . '"]')[0] : null;

                /** Redefine label and breadcrumb * */
                $xmlForm['breadcrumb'] = $breadcrumb;
                $xmlForm['label'] = $label;
            }

            if ($xmlForm && !empty($wfidConfFile)) {
                $root = getConfigFile('workflow', 'xml', $wfidConfFile, $usrrole);
                $returnWfid = $wfidConfFile;
                return $returnWfid;
            }
            if ($xmlForm && empty($wfidConfFile)) {
                $root = getConfigFile('workflow', 'xml', $wfid, $usrrole);
                $returnWfid = $wfid;
                return $returnWfid;
            }
        }
    }

    return $returnWfid;
}

/**
 * Get information from default xml -> defined by tag confFile in user xml
 * @param type $root -> root of user xml config file
 * @param type $tag -> tag needed from xml
 * @param type $xmlTagName -> tag name in xml 
 */
function getConfigFileDefaultElements(&$root, &$tag, $xmlTagName) {

    if (!$tag && isset($root->confFile)) {
        $confFiles = $root->confFile;
        $countConfFiles = count($confFiles);
        for ($i = 0; $i < $countConfFiles; $i++) {
            $nameConfFile = (string) $confFiles[$i]['name'];
            $roleConfFile = (string) $confFiles[$i]['role'];
            $rootDefault = getConfigFile('workflow', 'xml', $nameConfFile, $roleConfFile);

            //Definition of state chart xml
            if ($xmlTagName == 'scxml' && !$tag) {
                $tag = $rootDefault->scxml;
            }

            //Definition to delete in batch
            if ($xmlTagName == 'delete' && !$tag) {
                $tag = $rootDefault->delete;
            }

            //Definition of attachments
            if ($xmlTagName == 'attachments' && !$tag) {
                $tag = $rootDefault->attachments;
            }

            //Definition of checklist
            if ($xmlTagName == 'checklist' && !$tag) {
                $tag = $rootDefault->checklist;
            }

            //Definition of terms and conditions
            if ($xmlTagName == 'terms' && !$tag) {
                $tag = $rootDefault->terms;
            }
        }
    }
}

/**
 * Get search information from right xml 
 * @param type $root -> root file xml
 * @param type $xmlSearch -> xml information for search
 * @param type $wfid -> workflow id
 * @param type $searchId -> form id
 */
function getConfigFileSearch(&$root, &$xmlSearch, $wfid, $searchId, $usrrole = null) {
    $form = isset($root->xpath('search[@id="' . $searchId . '"]')[0]) ? $root->xpath('search[@id="' . $searchId . '"]')[0] : null;
    $xmlSearch = $searchId === '' ? $root->form : $form;

    if (!isset($xmlSearch) && isset($root->confFile)) {
        $confFiles = $root->confFile;
        $countConfFiles = count($confFiles);
        for ($i = 0; !isset($xmlSearch) && $i < $countConfFiles; $i++) {
            $nameConfFile = (string) $confFiles[$i]['name'];
            $wfidConfFile = (string) $confFiles[$i]['wfid'];
            $rootForm = getConfigFile('workflow', 'xml', $nameConfFile, $usrrole);
            $xmlSearch = isset($rootForm->xpath('search[@id="' . $searchId . '"]')[0]) ? $rootForm->xpath('search[@id="' . $searchId . '"]')[0] : null;
            if ($xmlSearch && !empty($wfidConfFile)) {
                $root = getConfigFile('workflow', 'xml', $wfidConfFile, $usrrole);
            }
            if ($xmlSearch && empty($wfidConfFile)) {
                $root = getConfigFile('workflow', 'xml', $wfid, $usrrole);
            }
        }
    }

    /*     * If the form is not defined in user xml conf file, 
     * it gets the form from the default user (admin)* */
//    if($fid && !$xmlSearch){
//       getConfigFileForm($root, $xmlSearch, $wfid, $searchId, 'admin');
//    }
}

/**
 * Gets all possible values for a given Vble in MongoDB collection
 * @global type $m -> MongoDB connection
 * @param type $collection -> Collection name in Mongo
 * @param type $key -> Key to be distincted 
 * @return array -> sorted array('key' => 'key');
 */
function getDistinctVble($collection, $key) {
    global $m;
    $values = $m->$_SESSION['dbname']->$collection->distinct($key);
    foreach ($values as $val) {
        $return[$val] = $val;
    }
    if (!$return)
        $return = array('' => '-');
    asort($return);
    return $return;
}

/**
 * Gets all the field for a given common fields in a collection
 * Main fields are set in the Data Structure Array by _TYPE
 * @param type $collection -> Collection name in Mongo
 * @param type $type -> name of common variable in collection : _TYPE (suggested)
 * @return array -> sorted array(''key'=>'label')
 */
function getFieldsFromDBs($collection, $type) {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/se/_conf/datastructure/' . $_SESSION['portal'] . '/ds-' . $collection . '.php');
    $fields = $mongoStructure[$collection][$type];

    foreach ($fields as $k => $v) {
        if (!isset($v['skipImport']))
            $return[$k] = $v['label'];
    }

    asort($return);
    return $return;
}

/**
 * Get Services and Footnotes for Bands (Spectrum Allocation Chart) in a list
 * @global type $m -> Mongo Connection
 * @param type $type -> Document Type in Collection
 * @param type $collection -> Collection name
 * @return array list of services or notes depending on the Type
 */
function getPlanningOptionsFromDB($type, $collection) {

    global $m;

    $cursor = $m->$_SESSION['dbname']->$collection->find(array('_TYPE' => $type));

    $return = array();

    foreach ($cursor as $doc) {
        if ($type == 'SERVICE') {
            $return[$doc['code']] = $doc['title'];
        }
        if ($type == 'NOTE') {
            $return[''] = '- Nota -';
            $return[$doc['code']] = $doc['code'];
        }
    }
    asort($return);

    return $return;
}

/**
 * 
 * 
 * Project: SpectrumE
 * Module: Anatel
 * Subject: check variable
 * 
 * @param String $var Variavel a ser testada
 * @param String $return Valor a ser devolvido caso a Variavel seja nula
 * @param integer $repeat Quatidade vezes que será repetida o $return caso não seja null o $return
 * @return String
 */
function checkVariable($var, $return = null, $repeat = 5) {
    if (!empty($var)) {
        return $var;
    } else {
        if ($return != null) {
            return str_pad('', $repeat, $return);
        } else {
            return null;
        }
    }
}

/**
 * Get ModalBox for eventHandler / formHandler responses 
 * 
 * Project: SpectrumE
 * Module: Anatel, SGER
 * Subject: ModalBox builder
 * 
 * @param array $response Array with custom modalBox content
 * @return array with modalBox Id
 */
function getModalBox($response) {

    if (!isset($response['title'])) {
        $response['title'] = _translate('System Message');
    }
    if (!isset($response['buttons'])) {
        $response['buttons'] = '<button class="btn-apps" type="button" onclick="hideModal(\'generic_Box\');"><i class="fa fa-times"></i> ' . _translate('Close') . '</button>';
    }

    if (!isset($response['exit'])) {
        $response['exit'] = 'onclick="hideModal(\'generic_Box\');"';
    }

    $response['boxWidth'] = (isset($response['boxWidth'])) ? 'style="width: ' . $response['boxWidth'] . '"' : '';
    $response['mContStyle'] = (isset($response['mContStyle'])) ? $response['mContStyle'] : '';
    $response['message'] = (isset($response['message'])) ? $response['message'] : '';

    $modalBox = '
            <div id="generic_Box" class="modalPage">
                <div class="modalBackground"></div>
                <div class="modalContainer"  ' . $response['boxWidth'] . '>
                    <div class="mCont_top">' . $response['title'] . '</div>
                    <div id="exit_box" ' . $response['exit'] . '></div>
                    <div class="mCont" ' . $response['mContStyle'] . '>' . $response['message'] . '</div>
                    <div class="mCont_bottom wfButtons">
                        ' . $response['buttons'] . '
                    </div>
                </div>
            </div>';

    $modalId = (isset($response['divId'])) ? $response['divId'] : 'generic_Box';

    $response = [
        'modal' => $modalBox,
        'modalId' => $modalId,
    ];

    return $response;
}

/**
 * Returns Error System Message
 * @param type $error: Error code (int)
 */
function errorMessage($error) {

    $title = _t('System Message');

    if ($error == 0) {
        $message = _t("Cannot Find Information");
    }
    if ($error == 1) {
        $message = _t("Cannot Load Information");
    }
    if ($error == 2) {
        $message = _t("Cannot retrieve document Information");
    }
    if ($error == 3) {
        $message = _t("Tag terms not found");
    }
    if ($error == 4) {
        $message = _t("Tag section not found");
    }

    if ($error == 'noBre') {
        $message = _t("No BRE tag in xml: " . $_SESSION['wfid']);
    }

    if ($error == 'errorATP') {
        $message = _t("atp tag not found in xml");
    }

    $return = "
        <div class='errorPageMsg'>
            <h1>" . $title . "</h1>
            <p>" . $message . ".</p>
        </div>";

    die($return);
}

/**
 * Imports microwaves that intersect the box (wind-farm) (mainly used for wind turbine analysis)
 * @param type $p0_x -> Longitude Site A
 * @param type $p0_y -> Latidude Site A
 * @param type $p1_x -> Longitude Site B
 * @param type $p1_y -> Latitude Site B
 * @param type $lonmin -> Box: bottom left longitude
 * @param type $lonmax -> Box: upper right longitude
 * @param type $latmin -> Box: bottom left latitude
 * @param type $latmax -> Box: upper right latitude
 * @return integer -> 1: MW inside Box , 0: MW outside box
 */
function MW2BoxIntersect($p0_x, $p0_y, $p1_x, $p1_y, $lonmin, $lonmax, $latmin, $latmax) {
    // MW in box
    if ($p0_y > $latmin && $p0_y < $latmax && $p0_x > $lonmin && $p0_x < $lonmax && $p1_y > $latmin && $p1_y < $latmax && $p1_x > $lonmin && $p1_x < $lonmax) {
        return 1;
    }

    for ($i = 0; $i < 4; $i++) {
        if ($i == 0) {
            $p2_x = $lonmin;
            $p2_y = $latmax;
            $p3_x = $lonmax;
            $p3_y = $latmax;
        }
        if ($i == 1) {
            $p2_x = $lonmax;
            $p2_y = $latmax;
            $p3_x = $lonmax;
            $p3_y = $latmin;
        }
        if ($i == 2) {
            $p2_x = $lonmax;
            $p2_y = $latmin;
            $p3_x = $lonmin;
            $p3_y = $latmin;
        }
        if ($i == 3) {
            $p2_x = $lonmin;
            $p2_y = $latmin;
            $p3_x = $lonmin;
            $p3_y = $latmax;
        }

        $s1_x = $p1_x - $p0_x;
        $s1_y = $p1_y - $p0_y;
        $s2_x = $p3_x - $p2_x;
        $s2_y = $p3_y - $p2_y;

        $s = (-$s1_y * ($p0_x - $p2_x) + $s1_x * ($p0_y - $p2_y)) / (-$s2_x * $s1_y + $s1_x * $s2_y);
        $t = ( $s2_x * ($p0_y - $p2_y) - $s2_y * ($p0_x - $p2_x)) / (-$s2_x * $s1_y + $s1_x * $s2_y);

        if ($s >= 0 && $s <= 1 && $t >= 0 && $t <= 1) {
            return 1;
        }
    }

    return 0;
}

/**
 * Read the xmlfile in the $tagName to return the structures of the keys with name, label, and transform tag. $filename and $delimiter are to get the file name and delimiter info from the xml
 * @param type $root
 * @param type $tagName
 * @return array
 */
function getXMLKeysPlainTextFile($root, $tagName) {
    $returnedKeys = array();
    $keys = $root->$tagName->key;
    foreach ($keys as $key) {
        $tmpKey['name'] = (string) $key->attributes()->name;
        $tmpKey['transform'] = (string) $key->attributes()->transform;
        $tmpKey['label'] = (string) $key->attributes()->label;
        array_push($returnedKeys, $tmpKey);
    }
    return $returnedKeys;
}

/**
 * Generate a plain document separated by $delimiter named $fileName in the $fileLocation folder with the structure declared in the array $keys[0]['label'],$keys[0]['name'] and $keys[0]['type'] using the information of the array $docs
 * @param type $docs Array with the information of stations or things to export
 * @param type $keys Array declaring the structure of the $docs to export $keys[0]['label'],$keys[0]['name'] and $keys[0]['type']
 * @param type $fileLocation
 * @param type $fileName
 * @param type $delimiter Char Pipe delimiter by default
 * @param type $labels set false to not show titles. true by default
 */
function generatePlainTextFile($docs, $keys, $fileLocation, $fileName, $delimiter = '|', $labels = true) {
    $delimiterSize = strlen($delimiter);
    $fileExists = file_exists($fileLocation . $fileName);
    if (!$fileExists) {
        $file = fopen($fileLocation . $fileName, "w");
    } else {
        $file = fopen($fileLocation . $fileName, "a+");
    }
    if ($labels && !$fileExists) {
        $content = '';
        foreach ($keys as $key) {
            $content .= $key['label'] . $delimiter; // (string) $key->attributes()->label;
        }
        $content = trim($content, $delimiter);
        fwrite($file, $content . PHP_EOL);
    }
    $content2save = '';
    foreach ($docs as $doc) {
        unset($content);
        if (isset($doc['Status']['state'])) {
            $state = $doc['Status']['state'];
        } else {
            $state = 'SinEstado';
        }
        $content = '';
        foreach ($keys as $key) {
            $name = $key['name'];
            if (isset($key['transform'])) {
                $transform = $key['transform'];
            } else {
                $transform = false;
            }
            if (isset($doc[explode(".", $name)[0]])) {
                $value = $doc;
                foreach (explode(".", $name) as $subKey) {
                    $subKey = str_replace("%[Status][state]%", ('' . $state . ''), $subKey);
                    if (isset($value[$subKey])) {
                        $value = $value[$subKey];
                    } else {
                        $value = '';
                    }
                }
                if (!empty($value) && is_array($value) && !isAssoc($value)) {
                    $isStringArray = true;
                    foreach ($value as $arrVal) {
                        if (is_array($arrVal)) {
                            $isStringArray = false;
                            continue;
                        }
                    }
                    if ($isStringArray) {
                        $value = implode(', ', $value);
                    } else {
                        $value = '';
                    }
                }
                if ($transform !== false) {
                    $content .= /* transformValue($name, $value) */str_replace($delimiter, ' ', $value) . $delimiter;
                } else {
                    $content .= str_replace($delimiter, ' ', $value) . $delimiter;
                }
            } else {
                $content .= $delimiter;
            }
        }
        if (strlen($content) > 0) {
            $content = substr($content, 0, -$delimiterSize);
        }
        $content2save .= $content . PHP_EOL;
    }
    fwrite($file, $content2save);
    fclose($file);
    return $fileLocation . '/' . $fileName;
}

/**
 * Find String in between Characters
 * @param type $start -> inital character
 * @param type $end -> final character
 * @param type $string -> string lookup
 * @return array -> with all keys
 */
function getInbetweenStrings($start, $end, $string) {
    $matches = array();
    $regex = "/$start([a-zA-Z0-9_.]*)$end/";
    preg_match_all($regex, $string, $matches);
    return $matches[1];
}

/**
 * Replace string with value from data base variable
 * @param type $string -> string to look for variable 
 * @param type $doc -> mongo document
 * @return string with value replaced
 */
function replaceStringWithDBValue($string, $doc) {
    $string = str_replace('\\%', '***PerCent***', $string);
    while (strpos($string, '%') !== false) {
        $position1 = strpos($string, '%');
        $position2 = strpos($string, '%', $position1 + 1);
        if ($position2 === false) {
            break;
        }
        $key = substr($string, $position1 + 1, $position2 - $position1 - 1);
        $value = "";
        if (isset($doc[explode("|", $key)[0]])) {
            $value = $doc;
            foreach (explode("|", $key) as $subKey) {
                $subKey = str_replace("%[Status][state]%", ('' . $doc['Status']['state'] . ''), $subKey);
                if (isset($value[$subKey])) {
                    $value = $value[$subKey];
                } else {
                    $value = '***Error key ' . $key . ' BD***';
                    continue;
                }
            }
        }
        $string = str_replace('%' . $key . '%', $value, $string);
    }
    $string = str_replace('***PerCent***', '%', $string);
    return $string;
}

/**
 * Replace string with value from session variable
 * @param type $string -> string to look for variable 
 * @return string with value replaced
 */
function replaceStringWithSessionValue($string) {
    $string = str_replace('\\%', '***PerCent***', $string);
    while (strpos($string, '%') !== false) {
        $position1 = strpos($string, '%');
        $position2 = strpos($string, '%', $position1 + 1);
        if ($position2 === false) {
            break;
        }
        $key = substr($string, $position1 + 1, $position2 - $position1 - 1);
        $value = "";
        if (isset($_SESSION[$key])) {
            $value = $_SESSION[$key];
        }
        $string = str_replace('%' . $key . '%', $value, $string);
    }
    $string = str_replace('***PerCent***', '%', $string);
    $string = str_replace(' []','<br/>', $string);
    
    return $string;
}

/**
 * Replace string with value from session variable
 * @param type $string -> string to look for variable 
 * @return string with value replaced
 */
function replaceStringSessionValue($string) {

    $stringArray = getInbetweenStrings('@@', '@@', $string);
    foreach ($stringArray as $key) {
        if (isset($_SESSION[$key])) {
            $string = str_replace('@@' . $key . '@@', $_SESSION[$key], $string);
        } else {
            $string = str_replace('@@' . $key . '@@', '', $string);
        }
    }
    return $string;
}

/**
 * Replace string with value from data base variable
 * @param type $string -> string to look for variable 
 * @param type $doc -> mongo document
 * @return string with value replaced
 */
function replaceStringDocValue($string, $doc) {
    $stringArray = getInbetweenStrings('%@', '@%', $string);
    foreach ($stringArray as $key) {
        $value = "";
        $subKey = explode(".", $key);
        if (isset($doc[$subKey[0]])) {

            $subKeyCount = count($subKey);
            switch ($subKeyCount):
                case 1: $value = $doc[$subKey[0]];
                    break;
                case 2: $value = $doc[$subKey[0]][$subKey[1]];
                    break;
                case 3: $value = $doc[$subKey[0]][$subKey[1]][$subKey[2]];
                    break;
                case 4: $value = $doc[$subKey[0]][$subKey[1]][$subKey[2]][$subKey[3]];
                    break;
            endswitch;

            $string = str_replace('%@' . $key . '@%', $value, $string);
        } else {
            $string = str_replace('%@' . $key . '@%', '', $string);
        }
    }
    return $string;
}

/**
 * Return true if the xml key match the role, wfid, state and service
 * @param type $xmlKey xml object key from jform xml
 * @param type $wfid wfid
 * @param type $doc document with state and service
 * @return boolean (true if is allowed)
 */
function xml_allowed_key($xmlKey, $doc, $wfid = null) {
    $state = isset($doc['Status']['state']) ? $doc['Status']['state'] : '';
    $service = isset($doc['service']) ? $doc['service'] : '';
    $appType = isset($doc['application']['type']) ? $doc['application']['type'] : '';

    $allowed = true;

    $states = (string) $xmlKey->attributes()->states;
    $ninStates = (string) $xmlKey->attributes()->ninStates;
    if ($allowed && !empty($states)) {
        $allowed = in_array($state, explode('|', $states));
    }
    if ($allowed && !empty($ninStates)) {
        $allowed = !in_array($state, explode('|', $ninStates));
    }

    $wfids = (string) $xmlKey->attributes()->wfids;
    $ninWfids = (string) $xmlKey->attributes()->ninWfids;
    if (!isset($wfid)) {
        $wfid = isset($_SESSION['wfid']) ? $_SESSION['wfid'] : '';
    }
    if ($allowed && !empty($wfids)) {
        $allowed = in_array($wfid, explode('|', (string) $wfids));
    }
    if ($allowed && !empty($ninWfids)) {
        $allowed = !in_array($wfid, explode('|', (string) $ninWfids));
    }

    $roles = (string) $xmlKey->attributes()->roles;
    $ninRoles = (string) $xmlKey->attributes()->ninRoles;
    if ($allowed && !empty($roles)) {
        $allowed = in_array($_SESSION['usrrole'], explode('|', (string) $roles));
    }
    if ($allowed && !empty($ninRoles)) {
        $allowed = !in_array($_SESSION['usrrole'], explode('|', (string) $ninRoles));
    }

    $services = (string) $xmlKey->attributes()->services;
    $ninServices = (string) $xmlKey->attributes()->ninServices;
    if ($allowed && !empty($services)) {
        $allowed = in_array($service, explode('|', $services));
    }
    if ($allowed && !empty($ninServices)) {
        $allowed = !in_array($service, explode('|', $ninServices));
    }

    $appTypes = (string) $xmlKey->attributes()->appType;
    $ninAppTypes = (string) $xmlKey->attributes()->ninAppType;
    if ($allowed && !empty($appTypes)) {
        $allowed = in_array($appType, explode('|', $appTypes));
    }
    if ($allowed && !empty($ninAppTypes)) {
        $allowed = !in_array($appType, explode('|', $ninAppTypes));
    }

    return $allowed;
}

/**
 * Check if current state is in the allowed states for the given object (btn, btnGroup, event, key in jform, separator)
 * @param type $states -> string with states separated by |
 * @param type $doc -> mongo document
 * @param type $ninStates -> string with states separated by | for not allowed states
 * @return array with allowed states and showState true;
 */
function getAllowedStates($states, $doc, $ninStates = '') {

    $return['states'] = null;
    $return['showState'] = false;

    if ($states) {
        $showState = in_array($doc['Status']['state'], explode('|', (string) $states));
    }

    if (!empty($ninStates)) {
        $return['states'] = $doc['Status']['state'];
        $return['showState'] = !in_array($doc['Status']['state'], explode('|', (string) $ninStates));
    }

    if ($states && !$showState) {
        $return['states'] = $states;
        $return['showState'] = $showState;
    }

    return $return;
}

/**
 * Check if current role is in the allowed roles for the given object (btn, btnGroup, event, key in jform, separator)
 * @param type $roles -> string with states separated by |
 * @param type $ninRoles -> string with states separated by | for not allowed roles
 * @return array with allowed roles and showRoles true;
 */
function getAllowedRoles($roles, $ninRoles = '') {

    $return['roles'] = null;
    $return['showRole'] = false;

    if ($roles) {
        $showRoles = in_array($_SESSION['usrrole'], explode('|', (string) $roles));
    }

    if (!empty($ninRoles)) {
        $return['roles'] = $_SESSION['usrrole'];
        $return['showRole'] = !in_array($_SESSION['usrrole'], explode('|', (string) $ninRoles));
    }

    if ($roles && !$showRoles) {
        $return['roles'] = $roles;
        $return['showRole'] = $showRoles;
    }

    return $return;
}

/**
 * Check if current wfid is in the allowed roles for the given object (btn, btnGroup, event, key in jform, separator)
 * @param type $wfids -> string with wfids separated by |
 * @param type $wfid -> current workflow id
 * @return array with allowed wfid and showWfid true;
 */
function getAllowedWfid($wfids, $wfid, $ninWfid = '') {

    $wfid = isset($wfid) ? $wfid : null;
    $wfid = isset($_SESSION['wfid']) ? $_SESSION['wfid'] : $wfid;

    $return['wfids'] = null;
    $return['showWfid'] = false;

    if ($wfids) {
        $showWfid = in_array($wfid, explode('|', (string) $wfids));
    }

    if (!empty($ninWfid)) {
        $return['wfids'] = $wfid;
        $return['showWfid'] = !in_array($wfid, explode('|', (string) $ninWfid));
    }

    if ($wfids && !$showWfid) {
        $return['wfids'] = $wfids;
        $return['showWfid'] = $showWfid;
    }

    return $return;
}

/**
 * Get keys for ITU notification Section 
 * @param type $fp - fileOpen
 * @param type $sectionDataElms -> Array with section elements
 * @param type $sectionDataElmsValues -> Array with section elements values from DB (defined in each portal/itu)
 */
function getITUkeys($fp, $sectionDataElmsValues) {

    foreach ($sectionDataElmsValues as $dataElmKey => $dataElmValue) {
        if (!empty($sectionDataElmsValues[$dataElmKey])) {
            fwrite($fp, $dataElmKey . '=' . $dataElmValue . "\n");
        }
    }
}

/**
 * Get Brific Local Document to save it into BR-IFIC local data
 * @param type $brificType -> Brific Type (fmtv_terra, fxm_terra, lfmf_terra)
 * @param type $app -> Values for notice section
 * @param type $noticeHeadValues -> Values for notice Head Section
 * @param type $noticeValues -> Values for notice section
 * @param type $noticeAntennaValues -> Values for Antenna Sub-Section
 * @param type $noticeAntennaRotationalValues -> Values for Antenna Rotational Sub-SubSection 
 * @param type $noticeAntennaRxStationValues -> Values for Antenna RX Station Sub-SubSection
 * @param type $noticeAntennaRxStationPointValues -> Values for Point Sub-SubSubSection
 */
function getBrificLocalDocument($brificType, $doc, $noticeHeadValues, $noticeValues, $noticeAntennaValues, $noticeAntennaRotationalValues, $noticeAntennaRxStationValues, $noticeAntennaRxStationPointValues) {

    $brificDoc['_id'] = $doc['_id'];
    $brificDoc['_TYPE'] = $brificType;
    $brificDoc['applicationId'] = $doc['applicationId'];
    $brificDoc['emsId'] = $doc['emsId'];
    $brificDoc['wfid'] = $_SESSION['wfid'];

    $brificDoc['terrakey'] = '';
    $brificDoc['adm'] = $noticeHeadValues['t_adm'];
    $brificDoc['freq_assgn'] = $noticeValues['t_freq_assgn'];
    $brificDoc['stn_cls'] = $noticeValues['t_stn_cls'];
    $brificDoc['emi_cls'] = $noticeValues['t_trg_emi_cls'];
    $brificDoc['bdwdth'] = '';
    $brificDoc['bdwdth_cde'] = $noticeValues['t_trg_bdwdth_cde'];
    $brificDoc['stn_type'] = '';
    $brificDoc['freq_min'] = '';
    $brificDoc['freq_max'] = '';

    $brificDoc['Status']['state'] = $doc['state'];
    $brificDoc['Status']['user'] = $doc['user'];
    $brificDoc['Status']['dateTime'] = $doc['date'];
    $brificDoc['Status']['createdDateTime'] = $doc['date'];
    $brificDoc['Status']['createdBy'] = $doc['user'];

    if ($_SESSION['portal'] == 'arg') {
        $brificDoc['carpetaTecnica'] = $doc['carpetaTecnica'];
    }

    return $brificDoc;
}

/**
 * Print BRE validation messages applied for jform and bre in batch
 * @return table
 */
function printBreMessages() {

    $msg = getValidationMessage(0);

    $return = '<table border="1">';
    $return .= '<tr><th>Name Control</th><th>Field</th><th>Description</th><th>Message</th></tr>';
    foreach ($msg as $msgKey => $msgValue) {
        $return .= '<tr><td>' . $msgKey . '</td>';

        foreach ($msgValue as $k => $v) {
            $return .= '<td>' . $k . '</td><td>' . $v[1] . '</td><td>' . $v[2] . '</td>';
        }

        $return .= '</tr>';
    }
    $return .= '</table>';

    return $return;
}

/**
 * 
 * Project: SpectrumE
 * Module: Anatel, SGER
 * Subject: date format
 * 
 * @param string $format
 * @return string
 */
function getDateFormat($format) {
    if (empty($format)) {
        return 'Y-m-d';
    }
    return $format;
}

/**
 * Verifica se a função existe e 
 * @param string $function
 * @param type $value
 * @return type
 */
function formatter($function, $value) {
    if (!empty($function) && function_exists($function)) {
        $value = call_user_func($function, $value);
    }
    return $value;
}

/**
 * Change date format
 * 
 * Project: SpectrumE
 * Module: Anatel, jlist
 * Subejct: Formatter datetime
 * 
 * @param string $date
 * @param string $format
 * @param string $outFormat
 * @return string
 */
function formatterDateTime($date, $format = 'Y-m-d H:i:s', $outFormat = 'Y-m-d') {
    $datetime = DateTime::createFromFormat($format, $date);
    if ($datetime) {
        return $datetime->format($outFormat);
    }
    return $date;
}

/**
 * Use to change Variables value (@variables@)at terms and conditions page
 * 
 * Project: SpectrumE
 * Module: Anatel, terms and conditions page
 * Subejct: Variable value Replace
 * 
 * @param string $content
 * @param SimplexmlElement $roo:variables is located at root->variables->key
 * @param json(Mongo) $doc
 */
function getVariablesValue($content, $root, $doc = []) {

    if ($root instanceof SimpleXMLElement && isset($root->variable)) {
        foreach ($root->variable->key as $variables) {
            $varName = (string) $variables->attributes()->name;
            $varValue = (string) $variables->attributes()->value;
            $dbValue = (string) $variables->attributes()->dbValue;
            $varCondition = (string) $variables->attributes()->condition;
            $formatter = (string) $variables->attributes()->formatter;
            $varContent = trim((string) $variables);

            if (getCondition($varCondition, $doc)) {
                if ($varValue != '' || $varValue != null) {
                    $content = str_replace($varName, formatter($formatter, $varValue), $content);
                } elseif ($dbValue != '' || $dbValue != null) {
                    $name = getFieldValue($dbValue, $doc);
                    if (empty($name)) {
                        $content = str_replace($varName, '', $content);
                    } elseif (is_array($name)) {
                        $listValue = (string) $variables->attributes()->listValue;
                        $listCondition = (string) $variables->attributes()->listCondition;
                        $varValue = '';
                        if (!empty($listValue)) {
                            foreach ($name as $key => $value) {
                                $listName = getFieldValue($listValue, $value);
                                if (!empty($listName) && getCondition($listCondition, $value)) {
                                    $varValue .= str_replace($varName, formatter($formatter, $listName), $varContent);
                                }
                            }
                        } else {
                            $varValue .= str_replace($varName, formatter($formatter, $name), $varContent);
                        }
                        $content = str_replace($varName, $varValue, $content);
                    } else {
                        $content = str_replace($varName, formatter($formatter, $name), $content);
                    }
                } elseif (!empty($varContent)) {
                    $content = str_replace($varName, $varContent, $content);
                }
            } else {
                $content = str_replace($varName, '', $content);
            }
        }
    }
    return $content;
}

/**
 * Give support to getVariablesValue
 * 
 * Project: SpectrumE
 * Module: Anatel, Terms and Conditions page
 * Subejct: Variable value Replace
 * 
 * @param type $stringCondition
 * @param type $array
 * @return boolean
 */
function getCondition($stringCondition, $array) {

    if (!empty($stringCondition)) {

        $return = false;
        $conditions = preg_split("/[&|]/", $stringCondition);

        foreach ($conditions as $condition) {

            $result[$condition] = 'false';
            if (strpos($condition, '=') > 0) {

                list($condField, $condValue) = explode('=', $condition);

                if ($condValue == getFieldValue($condField, $array)) {

                    $result[$condition] = 'true';
                }
            }
        }

        foreach ($result as $condition => $bool) {

            $stringCondition = str_replace($condition, $bool, $stringCondition);
        }

        eval("\$return = " . str_replace(['&', '|'], ['&&', '||'], $stringCondition) . ";");

        return $return;
    } else {

        return true;
    }
}

/**
 * Give support to Terms and Conditions Variable change (getVariablesValue)
 * 
 * Project: SpectrumE
 * Module: Anatel, Terms and Conditions page
 * Subejct: Variable value Replace
 * 
 * Check if the variable is a value inside of configFile or Database and return the value
 * @param type $fieldName
 * @param type $array
 */
function getFieldValue($fieldName, $array) {
    if ($fieldName == "/") {
        return $array;
    }
    $keys = preg_split("/[.|]/", $fieldName);
    $return = null;
    $node = $array;
    while (list($var, $val) = each($keys)) {
        if (isset($node[$val])) {
            $return = $node[$val];
            $node = $node[$val];
        } else {
            return null;
        }
    }
    return $return;
}

function convertUnits($value, $fromUnit, $toUnit) {
    // build units conversion table - we use PHP expressions to allow for more complicated conversions
    $conversionTable = [
        // Frequency
        'hz..hz' => '$res = $value * 1;', 'hz..khz' => '$res = $value * 0.001;', 'hz..mhz' => '$res = $value * 0.000001;', 'hz..ghz' => '$res = $value * 0.000000001;',
        'khz..hz' => '$res = $value * 1000;', 'khz..khz' => '$res = $value * 1;', 'khz..mhz' => '$res = $value * 0.001;', 'khz..ghz' => '$res = $value * 0.000001;',
        'mhz..hz' => '$res = $value * 1000000;', 'mhz..khz' => '$res = $value * 1000;', 'mhz..mhz' => '$res = $value * 1;', 'mhz..ghz' => '$res = $value * 0.001;',
        'ghz..hz' => '$res = $value * 1000000000;', 'ghz..khz' => '$res = $value * 1000000;', 'ghz..mhz' => '$res = $value * 1000;', 'ghz..ghz' => '$res = $value * 1;'
    ];

    $value = (float) $value;
    $fromUnit = strtolower($fromUnit);
    $toUnit = strtolower($toUnit);

    if ($fromUnit == $toUnit)
        return $value;

    if (!isset($conversionTable[$fromUnit . '..' . $toUnit])) :
        // desired conversion was not found                    
        return 'error';
    else:
        eval($conversionTable[$fromUnit . '..' . $toUnit]);
        return $res;
    endif;
}

/**
 * Removes null values from a array
 * 
 * Project: SpectrumE
 * Module: Anatel
 * Subject: null values
 * 
 * @param array $array
 * @return array
 */
function removeNull($array) {
    if (is_array($array)) {
        foreach ($array as $k => $v) {
            if (empty($v) && ($v != '0')) {
                unset($array[$k]);
            } else {
                $array[$k] = removeNull($array[$k]);
            }
        }
    } elseif (empty($array) && ($array != '0')) {
        return null;
    }
    return $array;
}

/**
 * Convert the given Power from dBm to Watt
 * @param float $pwr Power in dBm
 * @return float Power in Watt
 */
function w2dbm($pwr) {
    $dbm = 10 * log10(1000 * $pwr);
    return floatval($dbm);
}

/**
 * Convert the given Power from Watt to dBm
 * @param float $dbm Power in Watt
 * @return float Power in dBm
 */
function dbm2w($dbm) {
    $pwr = pow(10, ($dbm / 10)) / 1000;
    return floatval($pwr);
}

/**
 * Convert the given Power from unit to dBm
 * @param float $pwr Power in the given unit
 * @param string $unit Unit of given Power
 * @return float Power in dBm
 */
function convertPower2dbm($pwr, $unit) {
    $return = 0;
    if ($unit == 'dBm') {
        $return = $pwr;
    } elseif ($unit == 'W') {
        $return = w2dbm($pwr);
    } elseif ($unit == 'kW') {
        $return = w2dbm(1000 * $pwr);
    } elseif ($unit == 'mW') {
        $return = w2dbm($pwr / 1000);
    } elseif ($unit == 'dBW') {
        $return = $pwr + 30;
    } elseif ($unit == 'dBK') {
        $return = $pwr + 60;
    }
    return floatval($return);
}

/**
 * Convert the given Power from dBm to Unit
 * @param float $pwr Power in dBm
 * @param string $unit Unit to convert
 * @return float Power in the given unit
 */
function convertPowerDbm2unit($pwr, $unit) {
    $return = 0;
    if ($unit == 'dBm') {
        $return = $pwr;
    } elseif ($unit == 'W') {
        $return = dbm2w($pwr);
    } elseif ($unit == 'kW') {
        $return = dbm2w($pwr) / 1000;
    } elseif ($unit == 'mW') {
        $return = dbm2w($pwr) * 1000;
    } elseif ($unit == 'dBW') {
        $return = $pwr - 30;
    } elseif ($unit == 'dBK') {
        $return = $pwr - 60;
    }
    return floatval($return);
}

/**
 * Convert the given Power from Unit1 to Unit2
 * @param float $pwr Power in unit1
 * @param string $unitOrigin Unit of given power
 * @param string $unitDest Unit to convert
 * @return float Power in the given unit
 */
function convertPowerUnits($pwr, $unitOrigin, $unitDest) {
    $pwrDbm = convertPower2dbm($pwr, $unitOrigin);
    $return = convertPowerDbm2unit($pwrDbm, $unitDest);
    return floatval($return);
}

/**
 * Convert the given gain from Unit1 to Unit2
 * @param float $gain gain in unit1
 * @param string $unitOrigin Unit of given gain
 * @param string $unitDest Unit to convert
 * @return float gain in the given unit
 */
function convertGainUnits($gain, $unitOrigin, $unitDest) {
    $return = 0;
    if ($unitOrigin == 'dBi' && $unitDest == 'dBd') {
        $return = $gain - 2.14;
    } elseif ($unitOrigin == 'dBd' && $unitDest == 'dBi') {
        $return = $gain + 2.14;
    } elseif ($unitOrigin == 'dBi' && $unitDest == 'dBi') {
        $return = $gain;
    } elseif ($unitOrigin == 'dBd' && $unitDest == 'dBd') {
        $return = $gain;
    }

    return floatval($return);
}

/**
 * Remove all special characteres
 * 
 * Project: SpectrumE
 * Module: Anatel
 * Subject: Encode
 * 
 * @param mixe $var
 * @return mixe
 */
function removeSpecialCharacter($var){
    if(is_array($var)){
        foreach ($var as $k => $v){
            if(is_array($v)){
                $var[$k] = removeSpecialCharacter($v);
            } else {
                $var[$k] = iconv('UTF-8', 'UTF-8//IGNORE', $v);
            }
        }
    } else {
        $var = iconv('UTF-8', 'UTF-8//IGNORE', $var);
    }
    return $var;
}


if (!function_exists('formatBytes')) {
    
    /**
     * Gets format Bytes
     * @param type $size
     * @param type $precision
     * @return type
     */
    function formatBytes($size, $precision = 2){
        $base = log($size) / log(1024);
        $suffixes = array('', 'kB', 'MB', 'GB', 'TB');
        return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
    }
}

if (!function_exists('convertFreqUnit2Unit')) {
    /**
     * @param type $freq -> Frequency value
     * @param type $oldunit -> old unit
     * @param type $newunit -> new unit
     * @return Frequency value in new unit
     */
    function convertFreqUnit2Unit($freq, $oldunit, $newunit){
        switch($oldunit){
            case 'Hz':
                switch ($newunit){
                    case 'kHz': $F = $freq/pow(10,3); break;
                    case 'MHz': $F = $freq/pow(10,6); break;
                    case 'GHz': $F = $freq/pow(10,9); break;
                }
                break;
            case 'kHz':
                switch ($newunit){
                    case 'Hz': $F = $freq*pow(10,3); break;
                    case 'MHz': $F = $freq/pow(10,3); break;
                    case 'GHz': $F = $freq/pow(10,6); break;
                }
                break;
            case 'MHz':
                switch ($newunit){
                    case 'Hz': $F = $freq*pow(10,6); break;
                    case 'kHz': $F = $freq*pow(10,3); break;
                    case 'GHz': $F = $freq/pow(10,3); break;
                }
                break;
            case 'GHz':
                switch ($newunit){
                    case 'Hz': $F = $freq*pow(10,9); break;
                    case 'kHz': $F = $freq*pow(10,6); break;
                    case 'MHz': $F = $freq*pow(10,3); break;
                }
                break;

        }
        return $F;
    }
}

if (!function_exists('convertPowerUnit2Unit')) {

    /**
     * 
     * @param type $pwr -> Power value
     * @param type $oldunit -> old unit
     * @param type $newunit -> new unit
     * @return Frequency value in new unit
     */
    function convertPowerUnit2Unit($pwr, $oldunit, $newunit){

        switch($newunit){
            case 'W':
                switch ($oldunit){
                    case 'dBW': $P = pow(10, ($pwr)/10); break;
                    case 'dBm': $P = pow(10, ($pwr-30)/10); break;
                }
                break;

            default:
                switch ($newunit){
                    case 'dBW': $P = 10*log10($pwr); break;
                    case 'dBm': $P = 10*log10($pwr) + 30; break;
                    case 'dBk': $P = 10*log10($pwr) - 30; break;
                }
                break;
        }
        return $P;
    }
}

if (!function_exists('pr')) {
    function pr($var) {
        $template = PHP_SAPI !== 'cli' ? '<pre>%s</pre>' : "\n%s\n";
        printf($template, print_r($var, true));
    }

}
