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
            $doc['attachment'][$key]['userSubType'] = $userSubType;
            $doc['attachment'][$key]['createdDateTime'] = $date;
            $doc['attachment'][$key]['createdBy'] = $user;
            $doc['attachment'][$key]['type'] = 'pdfFile';

            if(isset($atch[$key])){
                foreach ($atch[$key] as $atchK => $atchV) {
                    $doc['attachment'][$key][$atchK] = $atchV;
                }
            }

            if (isset($doc['attachment']) && isset($doc['attachment'][$key])) {
                $docFileName = $doc['attachment'][$key]['fileName'];
            }

            saveFile($loc_udir, $id, $file["tmp_name"], $file["name"], $docFileName);

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
function saveFile($loc_udir, $id, $tmp_name, $fileName, $docFileName = '') {

    if (isset($docFileName)) {
        $prevname = $loc_udir . '/_docs/' . $id . '_' . $docFileName;
        if (file_exists($prevname)) {
            unlink($prevname);
        }
    }

    $outname = $loc_udir . '/_docs/' . $id . '_' . $fileName;
    move_uploaded_file($tmp_name, $outname);
}
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

