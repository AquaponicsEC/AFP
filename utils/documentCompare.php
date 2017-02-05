<?php
/**
 * Present a document's current and log versions for comparison, highlighting changes
 */
?><!DOCTYPE html>
<html>
    <head>
        <?php
        $docRoot = $_SERVER['DOCUMENT_ROOT'];
        include($docRoot . '/se/rf_header.php');
        include_once ($docRoot . '/se/dbconnect.php');
        include($docRoot . "/se/eapp/getConfigFile.php");
        session_write_close();

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

        if(isset($_SESSION['mongoAuth'])){
            $DbConOpt = $_SESSION['mongoAuth'];
        }
        if (!isset($DbConOpt)) {
            $DbConOpt = [];
        }

        // open connection to log database
        if (empty($logConnection)) {
            die('Log db connection parameters not set [' . __LINE__ . '].');
        }
        if (empty($logDbname)) {
            die('Log db name not set [' . __LINE__ . '].');
        }

        for ($retry = 2; $retry > 0;) {
            try {
                $mLog = new MongoClient($logConnection, $DbConOpt);
                $retry = 0;
            } catch (MongoConnectionException $e) {
                if (--$retry == 0)
                    die($e);
            }
        }

        if (isset($_REQUEST['db'])) {
            $srcDb = $_REQUEST['db'];
        } else if (isset($dbname)) {
            $srcDb = $dbname;
        } else {
            die('Source DB required [db]');
        }

        if (isset($_REQUEST['col'])) {
            $srcCol = $_REQUEST['col'];
        } else if (isset($_SESSION['collection'])) {
            $srcCol = $_SESSION['collection'];
        } else {
            die('Source collection required [col]');
        }

        if (isset($_REQUEST['id']) && preg_match("/^[a-zA-Z0-9 _-]*$/i",$_REQUEST['id'])) {
            $srcId = $_REQUEST['id'];
        } else {
            die('Source document id required [id]');
        }

        $headerLoc = isset($_REQUEST['header']) ? $_REQUEST['header'] : 'rev';

        $pageTitle = _translate('Document History');

        // define fields that should be presented at the top of the list
        $docFields = array();
        if ($headerLoc == 'fields') {
            $revField = _translate('Revision');
        } else {
            $revField = _translate('Field');
        }
        $docFields = [ $revField => true, _translate('Event user') => true, _translate('Modified on') => true, _translate('Event') => true, _translate('Event _id') => true] + $docFields;

        // load current document
        $tmpDoc = $m->$dbname->$srcCol->findOne(array('_id' => $srcId));

        $currDoc = null;
        $flatCurrent = array();

        if ($tmpDoc != null) {
            $tmpDoc['Status']['state'] = isset($tmpDoc['Status']['state']) ? $tmpDoc['Status']['state'] : '';

            $currDoc = array(
                '_id' => $tmpDoc['_id'],
                'srcDb' => $srcDb,
                'srcCol' => $srcCol,
                'srcId' => $tmpDoc['_id'],
                'user' => $tmpDoc['Status']['user'],
                'event' => _translate('(Current)') . ' ' . $tmpDoc['Status']['state']
            );
            $currDoc['prevDoc'] = $tmpDoc;

            $tmpCurrLabel = _translate('(Current)');

            $tmpDate = isset($tmpDoc['Status']['dateTime']) ? $tmpDoc['Status']['dateTime'] : '';
            $tmpUser = isset($tmpDoc['Status']['user']) ? $tmpDoc['Status']['user'] : '';
            $tmpState = isset($tmpDoc['Status']['state']) ? $tmpDoc['Status']['state'] : '';

            $flatCurrent = [
                $revField => $tmpCurrLabel,
                _translate('Event user') => $tmpCurrLabel,
                _translate('Modified on') => $tmpCurrLabel,
                _translate('Event') => $tmpCurrLabel,
                _translate('Event _id') => $tmpCurrLabel
            ];
        }
        array_flatten($flatCurrent, $currDoc['prevDoc']);
        extractFields($docFields, $flatCurrent);

        // obtain log records from DB
        $query = ['srcDb' => $srcDb, 'srcCol' => $srcCol, 'srcId' => $srcId];
        $histCursor = $mLog->$logDbname->log->find($query)
                ->timeout(-1)
                ->sort(['dateTime' => -1, '_id' => -1]);

        // flatten the historic documents and build list of unique fields
        if (count($flatCurrent)) {
            $histDocs = array($flatCurrent);
            $docIndex = 1;
        } else {
            $histDocs = array();
            $docIndex = 0;
        }

        $revIndex = $histCursor->count();
        foreach ($histCursor as $k => $histDoc) {
            $histDocs[$docIndex] = [
                $revField => _translate('Revision') . ' ' . $revIndex,
                _translate('Event user') => $histDoc['user'],
                _translate('Modified on') => $histDoc['dateTime'],
                _translate('Event') => $histDoc['event'],
                _translate('Event _id') => $histDoc['_id'],
            ];

            // set default values for empty fields (we consider that they come from a migration
            $histDoc['prevDoc']['Status']['dateTime'] = isset($histDoc['prevDoc']['Status']['dateTime']) ? $histDoc['prevDoc']['Status']['dateTime'] : _translate('(Migration)');
            $histDoc['prevDoc']['Status']['user'] = isset($histDoc['prevDoc']['Status']['user']) ? $histDoc['prevDoc']['Status']['user'] : _translate('(Migration)');
            $histDoc['prevDoc']['Status']['state'] = isset($histDoc['prevDoc']['Status']['state']) ? $histDoc['prevDoc']['Status']['state'] : _translate('(Migration)');

            array_flatten($histDocs[$docIndex], $histDoc['prevDoc']);
            extractFields($docFields, $histDocs[$docIndex]);
            $docIndex++;
            $revIndex--;
        }

        if ($headerLoc == "rev") {
            $style = '   td>span { max-width: 300px; display: table-cell; white-space: normal; word-wrap: break-word; word-break: keep-all; line-height: 1.5em; padding-top: 5px; padding-bottom: 5px; }';
            $headerUrl = 'fields';
        } else {
            $style = '   td>span { }';
            $headerUrl = 'rev';
        }

        $swapURL = $_SERVER["PHP_SELF"] . '?col=' . $srcCol . '&amp;id=' . $srcId . '&amp;header=' . $headerUrl;
        if (isset($_SESSION['collection'])) {
            $swapURL = $_SERVER["PHP_SELF"] . '?id=' . $srcId . '&amp;header=' . $headerUrl;
        }
        ?>
        <link rel="stylesheet" type="text/css" href="/se/utils/css/fonts.css">
        <link rel="stylesheet" type="text/css" href="/se/utils/css/main.css">
        <link rel="stylesheet" type="text/css" href="/se/utils/css/portal.css">
        <?php
        if (file_exists($docRoot . '/se/portal/' . $_SESSION['portal'] . '/css/default.css')) {
            echo '<link rel="stylesheet" type="text/css" href="/se/portal/' . $_SESSION['portal'] . '/css/default.css">';
        }
        ?>
        <title><?= $pageTitle; ?></title>
        <style>
            table.history { margin-left: 0; clear: both; }
            table.history td { vertical-align: top; }
<?= $style ?>
        </style>
    </head>
    <body>
<?php include('../topbar.php'); ?>
        <nav class="navigation">
            <ul>
                <li><a href="/se/rf_home.php"><?php _e('Home'); ?></a></li>
                <li><span><?= $pageTitle; ?></span></li>
            </ul>
        </nav>

        <div class="container">
            <h1><?= $pageTitle; ?></h1>
            <div class="separator"></div>

            <table>
                <tbody>
                    <?php
                    if (!isset($_SESSION['collection'])) {
                        echo '<tr><td>' . _translate("Collection") . '</td><td>' . $srcCol . '</td></tr>';
                    }
                    if (isset($tmpDoc['_TYPE'])) {
                        echo '<tr><td>' . _translate('Type') . '</td><td>' . $tmpDoc['_TYPE'] . '</td></tr>';
                    }
                    ?>
                    <tr><td><?php _e("Original Document Id") ?></td><td><?= $srcId ?></td></tr>
                </tbody>
            </table>

            <br/>
            <button class="btn-apps" onclick="window.location.href = '<?= $swapURL ?>'" style="margin-left: 0px; margin-bottom: 10px;"><i class="fa fa-retweet"></i> <?php _e("Change orientation"); ?></button>

            <table class="history">
                <?php
                // load base _TYPE fields definition (if available)
                $docStruct = array();
                if (file_exists('../portal/' . $_SESSION['portal'] . '/locale/ds-emission.php')) {
                    include_once('../portal/' . $_SESSION['portal'] . '/locale/ds-emission.php');
                    if (isset($mongoStructure[$srcCol][$tmpDoc['_TYPE']])) {
                        $docStruct = $mongoStructure[$srcCol][$tmpDoc['_TYPE']];
                    }
                }

                //Take into account User's Configuration File (XML)
                if (isset($_SESSION['confFile'])) {

                    $fid = isset($_SESSION['fid']) ? $_SESSION['fid'] : null;
                    $wfid = isset($_SESSION['wfid']) ? $_SESSION['wfid'] : null;

                    $confFile = ($wfid) ? $wfid : $fid;
                    $root = getConfigFile('workflow', 'xml', $confFile);
                    
                    $jlist = $root->jlist;

                    /** If jlist definition does not exists in current user or role xml
                    * config file, it takes the default from main role (admin)
                    */
                    if(!$jlist){
                       $jlistDefault = getConfigFile('workflow', 'xml', $wfid, 'admin');
                       $jlist = $jlistDefault->jlist;
                    }

                    $xml = $root->xpath('form[@id="' . $tmpDoc['_TYPE'] . '"]');

                    if (!$xml) {
                        $xml = $root->xpath('form[@id="' . $fid . '"]');
                    }
                    if (!$xml) {
                        $xml = $root->xpath('form[@id="' . $wfid . '"]');
                    }
                    if (!$xml) {
                        $fid = (string) $jlist->view[(int) $_SESSION['view']]['id'];
                        if ($fid) {
                            $xml = $root->xpath('form[@id="' . $fid . '"]');
                        }
                    }

                    $xml = $xml[0];

                    for ($i = 0; $xml->key[$i]; $i++) {
                        $key = (string) $xml->key[$i]->attributes()->name;
                        $lbl = (string) $xml->key[$i]->attributes()->label;
                        $type = (string) $xml->key[$i]->attributes()->type;
                        $unit = (string) $xml->key[$i]->attributes()->unit;
                        if (strlen($lbl) == 0) {
                            $lbl = $key;
                        }

                        $key = str_replace("|", ".", $key);

                        $unitField = ($unit) ? ['unit' => $unit] : '';

                        if ($type !== "separator") {
                            $docStruct[$key] = ['title' => $lbl, $unitField];
                        }
                    }
                }

                // output fields
                if ($headerLoc == 'fields') {
                    outputRevisionAsRow($docStruct, $docFields, $histDocs); // documents as rows
                } else {
                    outputRevisionAsCol($docStruct, $docFields, $histDocs); // documents as columns
                }
                ?>
            </table>
        </div>
        <script>
            // highlight row / column when user clicks on cell
            allRows = document.getElementsByTagName("tr");
            var selectedRows = new Array(allRows.length);
            for (var i = 0, l = allRows.length; i < l; i++) {
                allRows[i].onclick = new Function("selectRow(" + i + ")");
                selectedRows[i] = -1;
            }

            function selectRow(rowID) {
                selectedRows[rowID] *= -1;
                allRows[rowID].style.backgroundColor = (selectedRows[rowID] < 0 ? "" : "#F7F7F7");
            }

            function sendToMap(id, key) {
                var FD = new FormData();

                var url = "sendHistToMap.php?id=" + id + "&key=" + key;

                xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function () {
                    if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
                        alert('El registro fue enviado exitosamente.');
                    }
                    if (xmlhttp.readyState === 4 && xmlhttp.status === 400) {
                        var response = JSON.parse(xmlhttp.responseText);
                        alert(response.message);
                    }
                };
                xmlhttp.open("POST", url, true);
                xmlhttp.send(FD);
            }
        </script>
    </body>
</html>
<?php

/**
 * Convert a multi-dimensional array to a one dimensional one 
 * Subarrays would be kept within a "key.subkey" structure
 * @param array $result Result array
 * @param array $array Source array
 * @param string $keyName
 * @return boolean
 */
function array_flatten(&$result, $array, $keyName = '') {
    if (count($array) == 0)
        return true;

    foreach ($array as $key => $value) {
        $tmpKey = ($keyName != '') ? $keyName . '.' . $key : $key;

        if (is_array($value)) {
            array_flatten($result, $value, $tmpKey);
        } else {
            $result[$tmpKey] = $value;
        }
    }

    return true;
}

/**
 * Add field names found in $refDoc to $fieldsArray
 * @param array $fieldsArray
 * @param array $refDoc
 */
function extractFields(&$fieldsArray, $refDoc) {
    foreach ($refDoc as $k => $v) {
        $fieldsArray[$k] = true;
    }
}

/**
 * Output the comparison table, showing revisions as columns
 * @param array $docFields
 * @param array $histDocs
 */
function outputRevisionAsCol($docStruct, $docFields, $histDocs) {

    if ($_SESSION['portal'] == 'mex' && ($histDocs[0]['_TYPE'] == 'AM_MEX' || $histDocs[0]['_TYPE'] == 'AM_INTL'|| $histDocs[0]['_TYPE'] == 'FM'|| $histDocs[0]['_TYPE'] == 'TV')) {
        $htmlCode[$fieldName] = '<tr><td></td>';
        foreach ($histDocs as $key => $histDoc) {
            $htmlCode[$fieldName] .= '<td><button class="btn-apps" onclick="sendToMap(\'' . $histDoc['_id'] . '\',\'' . $histDoc['Evento _id'] . '\')" style="margin-left: 0px; margin-bottom: 10px;">' . _translate("Enviar a Spectrum-E") . '</button></td>';
        }
        $htmlCode[$fieldName] .= '</tr>';
    } else {
        $htmlCode[$fieldName] = '';
    }
    foreach ($docFields as $fieldName => $tmp) {
        // get the field's label using an existing document data structure (from portal/xxx/locale/ds-[collection].php)
        $fieldLabel = $fieldName;
        if (isset($docStruct[$fieldName])) {
            $fieldLabel = $docStruct[$fieldName]['title'];
            $fieldLabel .= isset($docStruct[$fieldName]['unit']) ? ' (' . $docStruct[$fieldName]['unit'] . ')' : '';
        }
        $htmlCode[$fieldName] .= '<tr><th>' . $fieldLabel . "</th>";

        // output fields
        foreach ($histDocs as $k => $doc) {
            // check if value has changed from previous document
            $class = '';
            if ($k < count($histDocs) - 1) {
                $nextDoc = $histDocs[$k + 1];

                // set values to empty if they don't exist in document
                // by using this code going from null (non-set) to empty ("") will not be highlighted as a change
                if (!array_key_exists($fieldName, $nextDoc)) {
                    $nextDoc[$fieldName] = null;
                }
                if (!array_key_exists($fieldName, $doc)) {
                    $doc[$fieldName] = null;
                }

                // quickly compare values but 'double' data types
                if ($doc[$fieldName] != $nextDoc[$fieldName] && gettype($doc[$fieldName]) != 'double') {
                    $class = ' class="highlight"';
                }

                // values of type 'double' require additional conditions
                if ($doc[$fieldName] != $nextDoc[$fieldName] && gettype($doc[$fieldName]) == 'double') {
                    if ((string) $doc[$fieldName] != (string) $nextDoc[$fieldName]) {
                        $class = ' class="highlight"';
                    }
                }
            }
            $htmlCode[$fieldName] .= '<td' . $class . '><span>';
            $htmlCode[$fieldName] .= isset($doc[$fieldName]) ? htmlentities($doc[$fieldName]) : '&nbsp;';
            $htmlCode[$fieldName] .= '</span></td>';
        }
        $htmlCode[$fieldName] .= "</tr>";

        //remove fields that are not in User's Configuration File (XML)
        if (isset($_SESSION['confFile'])) {
            $htmlCode = excludeFields($htmlCode, $fieldName, $docStruct);
        }
    }
    echo implode('', $htmlCode);
}

/**
 * Output the comparison table, showing revisions as rows
 * @param array $docFields
 * @param array $histDocs
 */
function outputRevisionAsRow($docStruct, $docFields, $histDocs) {
    echo "<tr>";
    foreach ($docFields as $fieldName => $tmp) {
        //  echo '<th>' . $fieldName . "</th>";
        // get the field's label using an existing document data structure (from portal/xxx/locale/ds-[collection].php)
        $fieldLabel = $fieldName;
        if (isset($docStruct[$fieldName])) {
            $fieldLabel = $docStruct[$fieldName]['title'];
            $fieldLabel .= isset($docStruct[$fieldName]['unit']) ? ' (' . $docStruct[$fieldName]['unit'] . ')' : '';
        }
        $htmlCode[$fieldName] = '<th>' . $fieldLabel . "</th>";

        //remove fields that are not in User's Configuration File (XML)
        if (isset($_SESSION['confFile'])) {
            $htmlCode = excludeFields($htmlCode, $fieldName, $docStruct);
        }
    }

    echo implode('', $htmlCode);

    echo "</tr>\n";

    foreach ($histDocs as $k => $doc) {
        echo "<tr>";
        foreach ($docFields as $fieldName => $tmp) {

            // check if value has changed from previous document
            $class = '';
            if ($k < count($histDocs) - 1) {
                $nextDoc = $histDocs[$k + 1];

                // set values to empty if they don't exist in document
                // by using this code going from null (non-set) to empty ("") will not be highlighted as a change
                if (!array_key_exists($fieldName, $nextDoc)) {
                    $nextDoc[$fieldName] = null;
                }
                if (!array_key_exists($fieldName, $doc)) {
                    $doc[$fieldName] = null;
                }

                if ($doc[$fieldName] != $nextDoc[$fieldName]) {
                    $class = ' class="highlight"';
                }
            }

            unset($htmlCode);

            $htmlCode[$fieldName] = '<td' . $class . '><span>';
            $htmlCode[$fieldName] .= isset($doc[$fieldName]) ? htmlentities($doc[$fieldName]) : '&nbsp;';
            $htmlCode[$fieldName] .= '</span></td>';

            $prevDoc = $doc;

            //remove fields that are not in User's Configuration File (XML)
            if (isset($_SESSION['confFile'])) {
                $htmlCode = excludeFields($htmlCode, $fieldName, $docStruct);
            }

            echo implode('', $htmlCode);
        }
        echo "</tr>\n";
    }
}

/**
 * Exclude Fields that do not exist in user's conf file XML (or any $docStruct if applies)
 * @param type $htmlCode -> html code to show in interface
 * @param type $fieldName -> field name for document
 * @param type $docStruct -> document structure array
 * @return HTML code without the unwanted fields
 */
function excludeFields($htmlCode, $fieldName, $docStruct) {
    $excludeFields = ['Campo', 'Revisión', 'Usuario evento', 'Fecha modificación', 'Evento', 'Event _id', '_id'];
    $exclude = in_array($fieldName, $excludeFields);
    if ($exclude === false) {
        if (!array_key_exists($fieldName, $docStruct)) {
            unset($htmlCode[$fieldName]);
        }
    }
    return $htmlCode;
}
