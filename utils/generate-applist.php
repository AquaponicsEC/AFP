<?php
/**
 * Generate a JSON structure for applist
 * Last update: 2015/06/04
 * Version 1.0
 */
if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1' && $_SERVER['REMOTE_ADDR'] != '::1')
    die('Access not allowed.');
?>
<!doctype html>
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
        $docId = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
        $dbName = isset($_REQUEST['db']) ? $_REQUEST['db'] : '';
        $portalName = isset($_REQUEST['portal']) ? $_REQUEST['portal'] : $dbName;

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
            <form autocomplete="off">
                <table>
                    <tr><td colspan="2"><h3>MongoDB parameters</h3></td></tr>
                    <tr><td>Portal Name:</td><td><input name="portal" value="<?= $portalName ?>"/></td></tr>
                    <tr><td>Database:</td><td><input name="db" value="<?= $dbName ?>"/></td></tr>
                    <tr><td>Collection:</td><td><input name="collection" value="<?= $collectionName ?>"/></td></tr>
                    <tr><td>Type:</td><td><input name="type" placeholder="Separate with comma" value="<?= $type ?>"/></td></tr>
                    <tr><td>Document Id:</td><td><input name="id" value="<?= $docId ?>"/></td></tr>
                    <tr><td></td><td><input type="submit"/></td></tr>
                </table>
            </form><hr></div>
        <?php
        if ($collectionName == '' || $dbName == '')
            exit();

        echo '<div class="no-select" style="font-size:1.2em"><b>IMPORTANT:</b>The structure must be updated to match your specific needs and document structure.<br>Subdocuments with more than 2 levels are not supported.</div>';

        $db = $m->selectDB($dbName);
        $collection = new MongoCollection($db, $collectionName);

        echo '<pre class="prettyprint">';

        echo "{\n";
        echo "    \"label\": \"" . prettyString($collectionName) . "\",\n";
        echo "    \"database\": \"" . $dbName . "\",\n";
        echo "    \"scxml\" : \"" . $collectionName . "_sc\",\n";
        echo "    \"limit\" : 20,\n\n";
        echo "    \"view\":[\n";

        // output definitions based on received parameters
        if (empty($type) && empty($docId)):
            // no _TYPE nor _id provided: get all different types from collection
            $typeListing = $collection->distinct("_TYPE");

            $viewCount = 0;
            foreach ($typeListing as $type):
                $doc = $collection->findOne(["_TYPE" => $type]);

                if (!is_null($doc) && isset($doc['_TYPE'])):
                    echo ($viewCount) ? ",\n" : "\n";
                    outputDefinition($portalName, $collectionName, $doc);
                    $viewCount++;
                endif;

            endforeach;

        elseif (!empty($docId)):
            // only _id provided
            $doc = $collection->findOne(["_id" => $docId]);
            if (!is_null($doc)):
                outputDefinition($portalName, $collectionName, $doc);
            endif;

        else:
            // only type was provided
            $typeListing = explode(",", $type);

            $viewCount = 0;
            foreach ($typeListing as $type):
                $type = trim($type);
                $doc = $collection->findOne(["_TYPE" => $type]);

                if (!is_null($doc)):
                    echo ($viewCount) ? ",\n" : "\n";
                    outputDefinition($portalName, $collectionName, $doc);
                    $viewCount++;
                endif;
            endforeach;

        endif;
        echo "\n    ]\n}\n";
        echo "</pre>";
        ?></body>
</html>
<?php

function outputDefinition($portal, $collection, $doc) {
    $label = $doc['_TYPE'];
    $type = isset($doc['_TYPE']) ? "\"_TYPE\": \"" . $doc['_TYPE'] . "\"" : $collection;

    echo "        {\n";
    echo "            \"label\": \"" . prettyString($collection . '.' . $label) . "\",\n";
    echo "            \"collection\": \"" . $collection . "\",\n";
    echo "            \"columns\": [\n";

    $tmpField = [];
    $prevKey = '';
    foreach ($doc as $field => $value) :
        if (is_array($value)) {
            $prevKey = $field;
            foreach ($value as $subField => $value) :
                $valueType = (gettype($value) == 'integer' || gettype($value) == 'double') ? ', "' . gettype($value) . '"':'';
                $tmpField[] = "                [\"" . $prevKey . '.' . $subField . "\", \"" . prettyString($prevKey) . ': ' . prettyString($subField) . "\"" . $valueType . "]";
            endforeach;
        } else {
            $valueType = (gettype($value) == 'integer' || gettype($value) == 'double') ? ', "' . gettype($value) . '"':'';
            $tmpField[] = "                [\"" . $field . "\", \"" . prettyString($field) . "\"" . $valueType . "]";
        }
    endforeach;

    echo join(",\n", $tmpField);

    echo "\n            ],\n";
    echo "            \"query\": [\n";
    echo "                {\n";
    echo "                    \"label\": \"\",\n";
    echo "                    \"string\": {" . $type . "}\n";
    echo "                }\n";
    echo "            ]\n";
    echo "        }";
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
