<?php
if(isset($_POST)==true && empty($_POST)==false){
    
    
    
    $collection = $_POST['collection'];
    $parentId = $_POST['parentId'];
    $BX_NAME=$_POST['BX_NAME'];
    $BX_content=$_POST['BX_content'];
    $doc=[];
    $id=uniqid();
    $doc['_id']= $id;
    $doc['parentId'] = $parentId;
    foreach ($BX_NAME as $key => $name){
        $doc[$BX_NAME[$key]]=$BX_content[$key];
    }
    $dbname = "2001-Aquaponics";
$bulk = new MongoDB\Driver\BulkWrite();
$bulk->insert($doc);
$dbL = $dbname.'.'.$collection;
$manager = new MongoDB\Driver\Manager('mongodb://localhost:27017');
$writeConcern = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 100);
$result = $manager->executeBulkWrite($dbL, $bulk, $writeConcern);

printf("Inserted %d document(s)\n", $result->getInsertedCount());
printf("Matched  %d document(s)\n", $result->getMatchedCount());
printf("Updated  %d document(s)\n", $result->getModifiedCount());
printf("Upserted %d document(s)\n", $result->getUpsertedCount());
printf("Deleted  %d document(s)\n", $result->getDeletedCount());

foreach ($result->getUpsertedIds() as $index => $id) {
    printf('upsertedId[%d]: ', $index);
    var_dump($id);
}

/* If the WriteConcern could not be fulfilled */
if ($writeConcernError = $result->getWriteConcernError()) {
    printf("%s (%d): %s\n", $writeConcernError->getMessage(), $writeConcernError->getCode(), var_export($writeConcernError->getInfo(), true));
}

/* If a write could not happen at all */
foreach ($result->getWriteErrors() as $writeError) {
    printf("Operation#%d: %s (%d)\n", $writeError->getIndex(), $writeError->getMessage(), $writeError->getCode());
}

  
//    $update = new MongoDB\Driver\BulkWrite(['ordered'=>true]);
//    $update->insert($doc);
//    
//    $m->executeBulkWrite($dbL, $update);

}
/*$query = new MongoDB\Driver\Query(['_id' => $id],[]);
$cursor = $manager->executeQuery($dbname .'.'.'2001B-Hardware', $query);
$cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
$doc =  current($cursor->toArray());*/
?>


<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>Dynamic Form Processing with PHP | Tech Stream</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <link rel="stylesheet" type="text/css" href="css/default.css"/>
</head>
<body>
<form action="" class="register" method="POST">
    <h1>Add Components to the Aquaponics database</h1>
    <fieldset class="row1">
        <legend>Storage info</legend>

        <p>
            <label>Select type:
            </label>
            <select name="collection" id="collectionStorage">
                <option value="2001A-Software">Software</option>
                <option value="2001B-Hardware">Hardware</option>
            </select>
            <label>
                Select storage:
            </label>
            <select name="parentId" id="collectionStorage">
                <option value="2001B1-Electrical">Electrical</option>
                <option value="2001B2-Mechanical">Mechanical</option>
            </select>
        </p>
        <div class="clear"></div>
    </fieldset>
    <fieldset class="row2">
        <p>
            <input type="button" value="Add Fields" onClick="addRow('dataTable')"/>
            <input type="button" value="Remove Fields" onClick="deleteRow('dataTable')"/>
        <p>(All acions apply only to entries with check marked check boxes only.)</p>
        </p>
        <table id="dataTable" class="form" border="1">
            <tbody>
            <tr>
                <p>
                <td><input type="checkbox" required="required" name="chk[]" checked="checked"/></td>

                <td>
                    <label>Name of field:</label>
                    <input type="text" name="BX_NAME[]">
                </td>
                <td>
                    <label for="BX_age">content of field</label>
                    <input type="text"  name="BX_content[]">
                </td>
                </p>
            </tr>
            </tbody>
        </table>
        <div class="clear"></div>

    </fieldset>
    <fieldset class="row4">
    <input type="button" value="Add DataSheet" onClick="addRow('dataTable')"/>
    <input class="submit" type="submit" value="Confirm &raquo;" />
    </fieldset>

</form>

</body>
<!-- Start of StatCounter Code for Default Guide -->
<script type="text/javascript" src="js/script.js">

</script>
<!-- End of StatCounter Code for Default Guide -->
</html>









