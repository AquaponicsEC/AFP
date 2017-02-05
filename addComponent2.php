<?php
if(isset($_POST)==true && empty($_POST)==false){
    $HTTP_HOST = "http://".$_SERVER['HTTP_HOST']."/AFP";
    $drive = substr(__FILE__, 0, 1);
    
    $collection = $_POST['collection'];
    $loc_udir = $drive.":/Aquaponics/DataSheets";
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
    
    
    $idx = 1;

    foreach ($_FILES as $key => $file) {

        if ($file["error"] == 0) {

            //Save file in server

            $exts[0] = 'pdf';
            if (isset($_REQUEST['ext' . $idx]) && !empty($_REQUEST['ext' . $idx])) {
                $exts = array_map('trim', explode("|", $_REQUEST['ext' . $idx]));
            }

            $ext = strtolower(substr(strrchr($file["name"], "."), 1));


            if (in_array($ext, $exts)) {
//                $file["name"] = str_replace('.', '_', substr($file["name"],0,-4)).'.pdf';
            } else {
                exit_error(6);
            }

            $doc['attachment'][$key]['fileName'] = $file["name"];
            $doc['attachment'][$key]['createdDateTime'] = $date;
            //$doc['attachment'][$key]['createdBy'] = $user;
            $doc['attachment'][$key]['type'] = 'pdfFile';

            if(isset($atch[$key])){
                foreach ($atch[$key] as $atchK => $atchV) {
                    $doc['attachment'][$key][$atchK] = $atchV;
                }
            }

            if (isset($doc['attachment']) && isset($doc['attachment'][$key])) {
                $docFileName = $doc['attachment'][$key]['fileName'];
            }

            saveFile($loc_udir, $id, $file["tmp_name"], $file["name"], $docFileName,$collection);

            $idx++;
        }
    }

        if ($file["error"] == 4) {
            $error4++;
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
function saveFile($loc_udir, $id, $tmp_name, $fileName, $docFileName = '',$collection ) {

    if (isset($docFileName)) {
        $destinationFolder = explode('-',$collection);
        $prevname = $loc_udir . '/'.$destinationFolder[1].'/' . $id . '_' . $docFileName;
        if (file_exists($prevname)) {
            unlink($prevname);
        }
    }

    $outname = $loc_udir . '/'.$destinationFolder[1].'/' . $id . '_' . $fileName;
    move_uploaded_file($tmp_name, $outname);
}
?>
<html style="background-color: #FCFCFC;">
<head>
    <title>ADD COMPONENTS TO THE DATABASE</title>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    
        <style>
            body {font-family: Arial}
            .tdFile {
                max-width: 100px;
                padding-top:0px; padding-bottom:0px; 
            }

            .tdFile p{
                text-overflow: ellipsis; 
                white-space: nowrap; 
                overflow: hidden;
            }

            .tdFile .pfile:hover {cursor: pointer; text-decoration: underline;}
            .tdFile p {margin: 0; color: black;}

            input[type="file"] {
                display: block;
            }

            .custom-file-upload {
                display: inline-block;
                padding: 1px 0px;
                cursor: pointer;
            }

            .custom-file-upload i { font-size: 14px; padding-right: 5px; color: #006ABC;}

            h2 {
                border-bottom: 1px dotted #C0C0C0;
                color: #1F3A63;
            }

            .checkList ul{padding: 0 20px 20px 20px;}
            .checkList li{padding: 5px 0px; text-align: justify;}

            .checkList input[type='checkbox']{
                float: left;
                margin-left: -30px;
            }
            .checkList li label{
                display: block;
                margin: 0 20px 0 0px;
            }

            .centerBody a{
                color: #006ABC;
                cursor: pointer;
                text-decoration: none;
            }

            table { table-layout: fixed;}
            /*//table tr th:nth-child(2){ width: 80%;}*/
            table td{
                white-space:pre-wrap ; 
                word-wrap: break-word;       /* Internet Explorer 5.5+, Chrome, Firefox 3+ */
                overflow-wrap: break-word;   /* CSS3 standard: Chrome & Opera 2013+ */
            }

        </style>
    </head>
<body class="centerBody">
    <form id="uploads" method="post" enctype="multipart/form-data">
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
     <div class="row">
         <div class="cell title">
             <h2>Add Data Sheet</h2>
         </div>
            Select file to upload:
                    <input type="file" name="fileToUpload" id="fileToUpload">
                 
     </div>
    </fieldset>
     <fieldset class="row4">
    <input type="submit" value="Upload Form" name="submit">
     </fieldset>
</form>
   
</body>
<!-- Start of StatCounter Code for Default Guide -->
<script type="text/javascript" src="js/script.js"></script>
</html>