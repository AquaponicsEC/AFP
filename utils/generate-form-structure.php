<?php
/**
 * Generate the Form class structure using an existing document as reference
 * Last update: 2015/06/08
 * Version 2.0
 */
if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1' && $_SERVER['REMOTE_ADDR'] != '::1')
    die('Access not allowed.');

ini_set('max_execution_time', 0);
?><!doctype html>
<html>
    <head>
        <style>
            body, input { font-family:monospace; }
            .no-select { -webkit-user-select: none;  /* Chrome all / Safari all */
                         -moz-user-select: none;     /* Firefox all */
                         -ms-user-select: none;      /* IE 10+ */
                         -o-user-select: none;
                         user-select: none;
            }
        </style>
        <script src="https://google-code-prettify.googlecode.com/svn/loader/run_prettify.js"></script>
    </head>
    <body><?php
        $collectionName = isset($_REQUEST['collection']) ? $_REQUEST['collection'] : '';
        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
        $id = isset($_REQUEST['id']) ? $_REQUEST['id'] : null;
        $dbName = isset($_REQUEST['db']) ? $_REQUEST['db'] : '';

        if ($dbName != '') {
            for ($retry = 2; $retry > 0;) :
                try {
                    $m = new MongoClient("mongodb://localhost:27017");
                    $retry = 0;
                } catch (MongoConnectionException $e) {
                    if (--$retry == 0)
                        die($e);
                }
            endfor;
        }
        ?><div class="no-select">
            <h2>Form class structure generator</h2>
            <form autocomplete="off">
                <table>
                    <tr><td colspan="2"><h3>MongoDB parameters</h3></td></tr>
                    <tr><td>Database:</td><td><input name="db" value="<?= $dbName ?>"/></td></tr>
                    <tr><td>Collection:</td><td><input name="collection" value="<?= $collectionName ?>"/></td></tr>
                    <tr><td>Type:</td><td><input name="type" value="<?= $type ?>"/> Values: (empty) Distinct using _TYPE / TABLE_NAME; *: Any documents; TYPE1[, TYPE2]: Specified type(s)</td></tr>
                    <tr><td>_id:</td><td><input name="id" value="<?= $id ?>"/> Optional: will use only this _id for reference.</td></tr>
                    <tr><td></td><td><input type="submit"/></td></tr>
                </table>
            </form><hr></div>
        <?php
        if (empty($collectionName)):
            echo "</body></html>";
            exit();
        endif;

        echo '<div class="no-select" style="font-size:1.2em"><b>IMPORTANT:</b><br><ol><li>The structure must be updated to match your specific needs, a real document and desired form structure.<li><b>tableField</b> and arrays of subdocuments are not supported.<li><b>dataType</b> is defined by the last document found.</ol></div>';

        $db = $m->selectDB($dbName);
        $collection = new MongoCollection($db, $collectionName);

        if ($id != null) {
            $cursor = $collection->find(['_id' => $id]);
            $docStruct = runCursor($cursor);
            $formCode[] = buildFormCode($docStruct, $collectionName);

            $resIds[] = 'Specific document:';
            $resIds = array_merge($resIds, $docStruct['ids']);
        } else {
            if ($type == '*'):
                // get any documents regardless of _TYPE
                $cursor = $collection->find([])->limit(100);
                $docStruct = runCursor($cursor);
                $formCode[] = buildFormCode($docStruct, $collectionName);

                $resIds[] = 'Any document:';
                $resIds = array_merge($resIds, $docStruct['ids']);
            elseif ($type == ''):
                $typeListing = $collection->distinct("_TYPE");
                $resIds[] = '_TYPE based:';
                foreach ($typeListing as $docType) {
                    $cursor = $collection->find(['_TYPE' => $docType])->limit(100);
                    $docStruct = runCursor($cursor);
                    $formCode[] = buildFormCode($docStruct, $docType);

                    $resIds[] = '_TYPE: ' . $docType;
                    $resIds = array_merge($resIds, $docStruct['ids']);
                    $resIds[] = '';
                }

                $typeListing = $collection->distinct("TABLE_NAME");
                $resIds[] = "\n";
                $resIds[] = 'TABLE_NAME based:';
                foreach ($typeListing as $docType) {
                    $cursor = $collection->find(['TABLE_NAME' => $docType])->limit(100);
                    $docStruct = runCursor($cursor);
                    $formCode[] = buildFormCode($docStruct, $docType);

                    $resIds[] = 'TABLE_NAME: ' . $docType;
                    $resIds = array_merge($resIds, $docStruct['ids']);
                    $resIds[] = '';
                }

            else:
                $resIds[] = 'Specific _TYPE(s):';
                $typeListing = explode(",", $type);
                foreach ($typeListing as $docType) {
                    $docType = trim($docType);
                    $cursor = $collection->find(['_TYPE' => $docType])->limit(100);
                    $docStruct = runCursor($cursor);
                    $formCode[] = buildFormCode($docStruct, $docType);

                    $resIds[] = '_TYPE: ' . $docType;
                    $resIds = array_merge($resIds, $docStruct['ids']);
                    $resIds[] = '';
                }
            endif;
        }
        ?>
        <div style="display:flex;">
            <div style="float:left;width:50%;margin-left:0px;"><h2>Form Structure</h2>
                <textarea class="prettyprint" style="width:100%; height: 600px"><?php echo join("\n", $formCode); ?></textarea>
            </div>
            <div style="width:15px; float:left"></div>
            <div style="float:left;width:50%"><h2>Reference Document Ids</h2>
                <textarea class="prettyprint" style="width:100%; height: 600px"><?php echo join("\n", $resIds); ?></textarea>
            </div>
        </div>
    </body>
</html>
<?php

function buildFormCode($docStruct, $type) {
    foreach ($docStruct['fields'] as $name) {
        $inputType = '';
        $dataType = isset($docStruct['types'][$name]) ? $docStruct['types'][$name] : '';

        if ($dataType == 'coordinates'):
            $dataType = '';
            $inputType = 'coordinates';
        endif;

        switch ($name):
            case '_id':
            case '_TYPE':
            case 'TABLE_NAME':
                $inputType = 'hidden';
                break;
        endswitch;

        $tmp = "        '" . $name . "' => ['label' => '" . prettyString($name) . "'";
        $tmp .= (($inputType != '') ? ", 'inputType' => '" . $inputType . "'" : '');
        $tmp .= (($dataType != '') ? ", 'dataType' => '" . $dataType . "'" : '');
        $tmp .= ']';

        $fields[] = $tmp;
    }

    $out = "&lt;?php\n\n/*\n * *****************************************************************************\n";
    $out .= " * Type: $type\n * Document field count: " . count($fields) . "\n */\n";

    $out .= "\$objDefinition = array(\n    'type' => '" . $type . "',\n";
    $out .= "    'fields' => [\n";
    $out .= "        'sepGeneral' => ['label' => 'Información General', 'inputType' => 'separator'],\n";

    $out .= join(",\n", $fields);
    $out .="\n    ]\n);\n?>\n";

    return $out;
}

function runCursor($cursor) {
    foreach ($cursor as $docIdx => $value) {
        foreach ($value as $field => $fVal) {
            getField($docStruct, $field, $fVal);
        }
        $docStruct['ids'][] = $value['_id'];
    }

    $structure = array_keys(array_flip($docStruct['fields']));

    $docStruct['fields'] = $structure;

    return $docStruct;
}

function getField(&$docStruct, $fieldName, $value) {
    if (is_array($value)) {
        if (isset($value['type']) && $value['type'] == 'Point') {
            // coordinates array
            $docStruct['types'][$fieldName] = 'coordinates';
            $docStruct['fields'][] = $fieldName;
        } else {
            foreach ($value as $fSubName => $fSubValue) {
                if (!is_int($fSubName)) {
                    getField($docStruct, $fieldName . '.' . $fSubName, $fSubValue);
                } else {
                    $docStruct['fields'][] = $fieldName;

                    switch (gettype($fSubValue)) {
                        case 'integer':
                            $docStruct['types'][$fieldName] = 'integer';
                            break;

                        case 'double':
                            $docStruct['types'][$fieldName] = 'double';
                            break;
                    }
                }
            }
        }
    } else {
        switch (gettype($value)) {
            case 'integer':
                $docStruct['types'][$fieldName] = 'integer';
                break;

            case 'double':
                $docStruct['types'][$fieldName] = 'double';
                break;
        }

        $docStruct['fields'][] = $fieldName;
    }
}

function prettyString($str) {
    $str = str_replace([ '_', '.'], [' ', ': '], $str);

    $hold = '';
    for ($i = 0; $i < strlen($str) - 1; $i++) {
        $c = substr($str, $i, 1);
        $n = substr($str, $i + 1, 1);

        if (ord($c) > 96 && ord($n) < 91) {
            $hold .= $c . " ";
        } else {
            $hold .= $c;
        }
    }

    $hold .= substr($str, -1, 1);
    $hold = trim(ucwords($hold));

    $hold = str_replace(['  '], [' '], $hold);

    return $hold;
}
?>