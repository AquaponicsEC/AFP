<?php

include_once "../rf_InitSession.php";
include_once "../portal/mex/locale/" . $_SESSION['lang'] . ".php";
include_once "../portal/mex/includes/dbconnect.php";
include_once "../portal/mex/includes/func.php";
include_once "../portal/mex/includes/portal_array.php";

set_time_limit(0);
ini_set('max_execution_time', 0);
session_write_close();

if (!function_exists('_e')) {

    function _e($str) {
        global $lang;
        echo (isset($lang[$str]) ? $lang[$str] : $str);
    }

}

if (!function_exists('_translate')) {

    /**
     * Return translation for given English string
     * @global array $lang Array of translation (English to specific locale)
     * @param string $str
     * @return string
     */
    function _translate($str) {
        global $lang;
        return (isset($lang[$str]) ? $lang[$str] : $str);
    }

}

/* * *****************************************************************************
 * 'sendtomap' handler
 */

// get DB documents
if ($_REQUEST['key'] == '(Actual)') {
    $query = ['_id' => $_REQUEST['id']];
    $cursor = $m->mex->emission->find($query);
    $actual = true;
} else {
    $query = ['_id' => $_REQUEST['key']];
    $cursor = $m->mex_log->log->find($query);
    $actual = false;
}
require_once('../portal/mex/BC/am_send2map.php');

// save objects in user's network template
$tplxml = simplexml_load_file($_SESSION['usrpath'] . 'template.xml');
$nObj = count($tplxml->object);

for ($k = 0; $k < $nObj; $k++) {
    $obj[] = (string) $tplxml->object[$k]->attributes()->type;
}
// get template numbers within user's XML file
$tpnbAMDay = nObj('AM-Daytime');
$tpnbAMNight = nObj('AM-Nighttime');
$tpnbFM = nObj('FM');
$tpnbTV = nObj('TV');
$tpnbMW = nObj('Microwave Link'); // get template number within user's XML file
$tpnbBaseRep = nObj('Tx/Rx Radio'); // get template number within user's XML file
$tpnbES = nObj('Earth Station'); // get template number within user's XML file
$tpnbGSO = nObj('GSO Satellite'); // get template number within user's XML file
$tpnbNGSO = nObj('NGSO Satellite'); // get template number within user's XML file

if (!$tplxml || !is_numeric($tpnbMW) || !is_numeric($tpnbBaseRep)) :// return error in case we coudln't find the template for the object
    $errObj = [];
    if (!$tplxml)
        $errObj[] = 'Template';
    if (!$tpnbMW)
        $errObj[] = 'Microwave Link';
    if (!$tpnbBaseRep)
        $errObj[] = 'Tx/Rx Radio';

    header('Content-type: application/json');
    $response = [
        'status' => 'error',
        'message' => 'No se pudo abrir la definición de objeto [' . join(', ', $errObj) . '] en el perfil del usuario.',
    ];
    header('Content-type: application/json');
    http_response_code(400);
    echo json_encode($response);
    exit();
endif;

$network = $_SESSION['usrpath'] . 'Networks/' . $_SESSION['network'] . '.net.xml';
$fpnet = fopen($network, "r+t");
if (!$fpnet) :// return error in case we coudln't open user's network XML
    header('Content-type: application/json');
    $response = [
        'status' => 'error',
        'message' => 'No se pudo abrir la definición de red del usuario.',
    ];
    header('Content-type: application/json');
    http_response_code(400);
    echo json_encode($response);
    exit();
endif;

// Move to end of the file
for ($i = $j = 0; $i < 40 && $j < 2; $i++) {
    fseek($fpnet, -$i, SEEK_END);
    if (fgetc($fpnet) == '>')
        $j++;
}

header('Content-type: application/json');
http_response_code(200);
flush();
ob_flush();

$docCount = [];
$totalCount = 0;
foreach ($cursor as $key => $station) :
    if ($actual == false) {
        $station = $station['prevDoc'];
    }
    $station['_id'] = $key;
    $stationType = isset($station['_TYPE']) ? $station['_TYPE'] : '';
    if ($stationType == 'AM_MEX' || $stationType == 'AM_INTL') {
        writeAMObject($station, $tplxml->object[$tpnbAMDay], $tplxml->object[$tpnbAMNight], $fpnet);
    } elseif ($stationType == 'FM') {
        writeFMObject($station, $tplxml->object[$tpnbFM], $fpnet);
    } elseif ($stationType == 'TV') {
        writeTVObject($station, $tplxml->object[$tpnbTV], $fpnet);
    }

    // update internal counters
    if (!isset($docCount[$stationType])) :
        $docCount[$stationType] = 0;
    endif;

    $docCount[$station['_TYPE']] ++;
    $totalCount++;

endforeach;

fprintf($fpnet, "\n</network>\n");
fclose($fpnet);

$docCount['TOTAL'] = $totalCount;

// send response headers
$response = [
    'status' => 'ok',
    'message' => 'Solicitud completada satisfactoriamente.',
    'recordCount' => $docCount,
];
echo json_encode($response);
exit();

function nObj($object) {
    global $obj;

    foreach ($obj as $p => $o) {
        if ($o == $object)
            return $p;
    }

    return false;
}

function getXmlTag($tag, $doc, $valKey, $valDefault = '', $unit = '') {
    $attb = null;

    if ($unit != '') {
        $attb .= ' unit="' . $unit . '"';
    }

    $value = array_key_exists($valKey, $doc) ? $doc[$valKey] : $valDefault;

    $tmp = "\t\t<" . $tag . $attb . '>' . $value . '</' . $tag . ">\n";
    return $tmp;
}

function writeFMObject($station, $xmlObj, $fpnet) {
    global $portalArray;

    fprintf($fpnet, "\n\t<object type=\"" . $xmlObj->attributes()->type . "\" uid=\"" . $station['_id'] . "\">");
    for ($i = 0; $xmlObj->param[$i]; $i++) {
        $tag = (string) $xmlObj->param[$i]->attributes()->name;
        $value = (string) $xmlObj->param[$i]->attributes()->default;
        $attb = null;

        switch ($tag):
            case 'callsign':
                $value = isset($station['callsign']) ? $station['callsign'] : '';
                break;

            case 'estado': $value = isset($station['estado']) ? $station['estado'] : '';
                break;

            case 'status': $value = isset($station['estadoOperacional']) ? $station['estadoOperacional'] : '';
                break;

            case 'country': $value = isset($station['pais']) ? $station['pais'] : '';
                break;

            case 'azimuth': $value = isset($station['antenna']['azimuth']) ? $station['antenna']['azimuth'] : '';
                $value = is_array($value) ? implode('|', $value) : $value;
                break;

            case 'tipoTransmisor': $value = isset($station['tipoTransmisor']) ? $station['tipoTransmisor'] : '';
                break;

            case 'poblacionServicio':
                if (isset($station['poblacionServicio'])) {
                    $value = $station['poblacionServicio'];
                } elseif (isset($station['localidad'])) {
                    $value = $station['localidad'];
                } else {
                    $value = '';
                }
                break;

            case 'antenna_directionality': $value = isset($station['antenna']['directionality']) ? $station['antenna']['directionality'] : '';
                break;

            case 'poblacion_servicio': $value = isset($station['poblacionServicio']) ? $station['poblacionServicio'] : '';
                break;

            case 'dfs':
                $value = isset($portalArray['fm']['contours'][$station['stnClass']]['FS']) ? $portalArray['fm']['contours'][$station['stnClass']]['FS'] : "";
                $attb = ' unit="dBu"';
                break;

            case 'licensee':
                $value = isset($station['razonSocial']) ? $station['razonSocial'] : '';
                $value = str_replace('%', '%%', $value);
                break;

            case 'municipio':
                $value = isset($station['municipio']) ? $station['municipio'] : '';
                break;

            case "latitude":
                $value = isset($station["loctx"]["coordinates"][1]) ? $station["loctx"]["coordinates"][1] : '';
                $attb = ' unit="deg"';
                break;

            case "longitude":
                $value = isset($station["loctx"]["coordinates"][0]) ? $station["loctx"]["coordinates"][0] : '';
                $attb = ' unit="deg"';
                break;

            case 'frequency': $value = isset($station['frequency']) ? $station['frequency'] : '100';
                $attb = ' unit="MHz"';
                break;

            case 'elevation': $value = isset($station['elevation']) ? $station['elevation'] : '';
                $attb = ' unit="m"';
                break;

            case 'channel': $value = isset($station['channel']) ? $station['channel'] : '';
                break;

            case 'stnClass': $value = isset($station['stnClass']) ? $station['stnClass'] : '';
                break;

            case 'erp': $value = isset($station['erp']) ? $station['erp'] : '1';
                $attb = ' unit="kW"';
                break;

            case 'antenna_height': $value = isset($station['antenna_height']) ? $station['antenna_height'] : '30';
                $attb = ' unit="m"';
                break;

            case 'antenna_name': $value = isset($station['antenna']['file_name']) ? $station['antenna']['file_name'] : '';
                break;

            case 'tilt': $value = isset($station['antenna']['tilt']) ? $station['antenna']['tilt'] : '';
                $attb = ' unit="deg"';
                break;

            case 'haat': $value = isset($station['haat']) ? $station['haat'] : '';
                $attb = ' unit="m"';
                break;

            case 'polarization':
                if (isset($station['polar'])) {
                    if ($station['polar'] == 'CIRCULAR') {
                        $value = 'C';
                    } elseif ($station['polar'] == 'VERTICAL') {
                        $value = 'V';
                    } elseif ($station['polar'] == 'Elíptica') {
                        $value = 'M';
                    } else {
                        $value = 'H';
                    }
                } elseif (isset($station['polarizacion'])) {
                    if ($station['polarizacion'] == 'CIRCULAR') {
                        $value = 'C';
                    } elseif ($station['polarizacion'] == 'VERTICAL') {
                        $value = 'V';
                    } elseif ($station['polarizacion'] == 'Elíptica') {
                        $value = 'M';
                    } else {
                        $value = 'H';
                    }
                }
                break;

            case 'emission': $value = isset($station['emission']) ? $station['emission'] : '200KF8E';
                break;
        endswitch;
//        error_log($tag . "-" . print_r($value, true));
        fprintf($fpnet, "\n\t\t<" . $tag);
        fprintf($fpnet, "" . $attb . ">" . $value . "</" . $tag . ">");
    }
    fprintf($fpnet, "\n\t</object>");
}

function writeTVObject($station, $xmlObj, $fpnet) {
    /*
     * Ignored fields from Spectrum-E:
      numservico
      stnClass
      source
      status
      indfase
      indcarater
      d_range
      p_range
      pl_matrix
      distance
      overlap_pc
      dfs
      code
      dbid
     */

// calculate frequency based on modulation
    if (!isset($station['modulation']) || ($station['modulation'] != 'A' && $station['modulation'] != 'D')) {
        $station['modulation'] = 'A';
    }

    if (!isset($station['frequency'])) {
        $station['frequency'] = '';
    } else {
        list($freqLow, $freqHigh) = explode('-', $station['frequency']);
        if ($station['modulation'] == 'A') { // analog TV
            $station['frequency'] = $freqLow + 1.25; // video carrier frequency
        }

        if ($station['modulation'] == 'D') { // digital TV
            $station['frequency'] = ($freqLow + $freqHigh) / 2; // center frequency
        }
    }

// get inddecalagem based on fdev
    $station['inddecalagem'] = '';
    if (isset($station['fdev']) && (substr($station['fdev'], 1, 1) == '+' || substr($station['fdev'], 1, 1) == '-')) {
        $station['inddecalagem'] = substr($station['fdev'], 1, 1);
    }

    fprintf($fpnet, "\n\t<object type=\"" . $xmlObj->attributes()->type . "\" uid=\"" . $station['_id'] . "\">");
    for ($i = 0; $xmlObj->param[$i]; $i++) {
        $tag = (string) $xmlObj->param[$i]->attributes()->name;
        $value = (string) $xmlObj->param[$i]->attributes()->default;
        $attb = null;

        switch ($tag):
            case 'callsign': $value = isset($station['callsign']) ? $station['callsign'] : '';
                break;

            case 'country': $value = isset($station['pais']) ? $station['pais'] : '';
                break;

            case 'poblacionServicio':
                if (isset($station['poblacionServicio'])) {
                    $value = $station['poblacionServicio'];
                } elseif (isset($station['localidad'])) {
                    $value = $station['localidad'];
                } else {
                    $value = '';
                }
                break;

            case 'licensee':
                $value = isset($station['licensee']) ? $station['licensee'] : '';
                $value = str_replace('%', '%%', $value);
                break;

            case 'municipio': $value = isset($station['municipio']) ? $station['municipio'] : '';
                break;

            case 'frequency':
                $value = $station['frequency'];
                $attb = ' unit="MHz"';
                break;

            case 'channel': $value = isset($station['channel']) ? $station['channel'] : '';
                break;

            case "latitude":
                $value = isset($station["loctx"]["coordinates"][1]) ? $station["loctx"]["coordinates"][1] : '';
                $value = is_array($value) && isset($value['dms']) ? $value['dms'] : $value;
                $attb = ' unit="deg"';
                break;

            case "longitude":
                $value = isset($station["loctx"]["coordinates"][0]) ? $station["loctx"]["coordinates"][0] : '';
                $value = is_array($value) && isset($value['dms']) ? $value['dms'] : $value;
                $attb = ' unit="deg"';
                break;

            case 'modulation': $value = isset($station['modulation']) ? $station['modulation'] : '';
                break;

            case 'antenna_height': $value = isset($station['antenna_height']) ? $station['antenna_height'] : '30';
                $attb = ' unit="m"';
                break;

            case 'erp': $value = isset($station['erp']) ? $station['erp'] : '10';
                $attb = ' unit="kW"';
                break;

            case 'elevation': $value = isset($station['elevation']) ? $station['elevation'] : '';
                $attb = ' unit="m"';
                break;

            case 'antenna_name': $value = isset($station['antenna']['file_name']) ? $station['antenna']['file_name'] : '';
                break;

            case 'polarization':
                if (isset($station['polar'])) {
                    if ($station['polar'] == 'CIRCULAR') {
                        $value = 'C';
                    } elseif ($station['polar'] == 'VERTICAL') {
                        $value = 'V';
                    } elseif ($station['polar'] == 'ELÍPTICA' || $station['polar'] == 'Elíptica') {
                        $value = 'M';
                    } else {
                        $value = 'H';
                    }
                } elseif (isset($station['polarizacion'])) {
                    if ($station['polarizacion'] == 'CIRCULAR') {
                        $value = 'C';
                    } elseif ($station['polarizacion'] == 'VERTICAL') {
                        $value = 'V';
                    } elseif ($station['polarizacion'] == 'ELÍPTICA' || $station['polarizacion'] == 'Elíptica') {
                        $value = 'M';
                    } else {
                        $value = 'H';
                    }
                }
                break;

            case 'tilt': $value = isset($station['antenna']['tilt']) ? $station['antenna']['tilt'] : '';
                $attb = ' unit="deg"';
                break;

            case 'azimuth': $value = '';
                $attb = ' unit="deg"';
                break;

            case 'directionality': $value = isset($station['antenna']['azimuth']) ? join(' | ', $station['antenna']['azimuth']) : '';
                $attb = ' unit="deg"';
                break;

            case 'haat': $value = isset($station['haat']) ? $station['haat'] : '';
                $attb = ' unit="m"';
                break;

            case 'dfs':
                if ($station['modulation'] == 'D') {
                    if ($station['channel'] >= 2 && $station['channel'] <= 6) {
                        $value = 35;
                    } elseif ($station['channel'] >= 7 && $station['channel'] <= 13) {
                        $value = 43;
                    } elseif ($station['channel'] >= 14 && $station['channel'] <= 69) {
                        $value = 48;
                    }
                } elseif ($station['modulation'] == 'A') {
                    if ($station['channel'] >= 2 && $station['channel'] <= 6) {
                        $value = 47;
                    } elseif ($station['channel'] >= 7 && $station['channel'] <= 13) {
                        $value = 56;
                    } elseif ($station['channel'] >= 14 && $station['channel'] <= 69) {
                        $value = 64;
                    }
                }
                $attb = ' unit="dBu"';
                break;

            case 'inddecalagem': $value = $station['inddecalagem'];
                break;
        endswitch;

        fprintf($fpnet, "\n\t\t<" . $tag);
        fprintf($fpnet, "" . $attb . ">" . $value . "</" . $tag . ">");
    }
    fprintf($fpnet, "\n\t</object>");
}
