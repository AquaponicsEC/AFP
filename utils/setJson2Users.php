<?php

include '../rf_locals.php';

$loc_string = str_replace("/","\\",$loc_root);

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
                         
                    table { font: 11px/24px Verdana, Arial, Helvetica, sans-serif; border-collapse: collapse; table-layout: auto; border-top: 1px solid #a0a0a0; border-left: 1px solid #a0a0a0; border-bottom: 1px solid #a0a0a0; color: #000;}
                    tr {white-space:nowrap;}
                    th { text-align: left; font-weight: bold; color: #1F3A63; background: #F0F0F0; border: 1px solid #BFBFBF; padding: 0 2em;}
                    th:hover{ cursor: default; }
                    td { border: 1px solid #EEE; padding: 0 2em; white-space: nowrap; text-align: left;}
                    td:hover{ cursor: default;}
                    a {text-decoration: none;}
                    li {margin: 10px 0;}
                    ol {margin: 20px 0;}
        </style>
        <script src="https://google-code-prettify.googlecode.com/svn/loader/run_prettify.js"></script>
    </head>
    <body>

<?php

    $portalName = isset($_REQUEST['portal']) ? $_REQUEST['portal'] : '';
    $userType = isset($_REQUEST['usertType']) ? $_REQUEST['usertType'] : '';
    
?>
        <div class="no-select">
            <form autocomplete="off">
                <table>
                    <tr><td colspan="2"><h3>Copy JSONs configuration files</h3></td></tr>
                    <tr><td>Portal Name:</td><td><input name="portal" value="<?= $portalName ?>"/></td></tr>
                    <tr><td>User Role:</td><td><input name="usertType" value="<?= $userType ?>"/></td></tr>
                    <tr><td></td><td><input type="submit"/></td></tr>
                </table>
            </form><hr>
        </div>
<?php

   
    if($portalName != '' && $userType != ''){

       
    
        echo ('<form autocomplete="off">');
        echo ('<input type="hidden" name="portal" value="'.$portalName.'">');
        echo ('<input type="hidden" name="usertType" value="'.$userType.'">');
        echo('<table>');
        echo('<th><a onclick="selectAllUsers()">Select All</a></th><th>User</th>');
        
        $output = shell_exec('dir /b /ad '.$loc_string."\\Users");
        $folderLists = explode("\n", $output);
        foreach ($folderLists as $folder) {
            if(strpos($folder,"@")>0){
                echo('<tr><td><input type="checkbox" name="folder_'.str_replace(".","__",$folder).'"></td><td>'.$folder.'</td></tr>');
            }
        }
        echo('</table>');
        echo('<hr/>');

        echo('<table>');
        echo('<th><a onclick="selectAllJson()">Select All</a></th><th>Jsons</th>');
        $cmd = 'dir /b '.$loc_string."\\htdocs\\se\\portal\\".$portalName."\\utils\\workflow\\".$userType;
        $output = shell_exec($cmd);
        $jsonLists = explode("\n", $output);
        foreach ($jsonLists as $json) {
            if(strpos($json,".xml")>0 && strpos($json,"template")===0){
                echo('<tr><td><input type="checkbox" name="xml_'.str_replace(".","__",$json).'"></td><td>'.$json.'</td></tr>');
            } else if(strpos($json,".json")>0){
                echo('<tr><td><input type="checkbox" name="json_'.str_replace(".","__",$json).'"></td><td>'.$json.'</td></tr>');
            }
        }
        echo('</table>');
        echo('<hr/>');
        
        echo ('<input type="submit"/>');

        $folderToCopy = array();
        $jsonToCopy = array();

        foreach ($_REQUEST as $key => $value) {
            $jamon = strpos($key,"json_");
            if(strpos($key,"json_")===0 && strpos($key,"__json") > 0){
                $jsonTmp = substr($key,5,strpos($key,"__json")-5);
                if($jsonTmp == 'profile'){
                    $profile = str_replace("__",".",$jsonTmp);
                } else {
                    array_push($jsonToCopy, str_replace("__",".",$jsonTmp));
                }
            } else if(strpos($key,"folder_")===0){
                array_push($folderToCopy, str_replace("__",".",substr($key,7)));
            } else if(strpos($key,"xml_")===0 && strpos($key,"__xml") > 0){
                $jsonTmp = substr($key,4,strpos($key,"__xml")-4);
                if($jsonTmp == 'template'){
                    $template = str_replace("__",".",$jsonTmp);
                }
            }
        }
        
        if(sizeof($folderToCopy)>0 && sizeof($jsonToCopy)){
            
        }foreach ($folderToCopy as $fol) {
            foreach ($jsonToCopy as $jso) {
                $cmd = 'copy /y '.$loc_string."\\htdocs\\se\\portal\\".$portalName."\\utils\\workflow\\".$userType."\\".$jso."* "
                        . $loc_string."\\Users\\".$fol."\\utils\\workflow\\";
                $output = shell_exec($cmd);
            }
            if(isset($profile)){
                $cmd = 'copy /y '.$loc_string."\\htdocs\\se\\portal\\".$portalName."\\utils\\workflow\\".$userType."\\".$profile.".json "
                        . $loc_string."\\Users\\".$fol."\\profile_".$portalName.".json";
                $output = shell_exec($cmd);
            }
            if(isset($template)){
                $cmd = 'copy /y '.$loc_string."\\htdocs\\se\\portal\\".$portalName."\\utils\\workflow\\".$userType."\\".$template."* "
                        . $loc_string."\\Users\\".$fol."\\";
                $output = shell_exec($cmd);
            }
        }
        

    }
?>
        <script>
            function selectAllJson(){
                var allInputs = document.getElementsByTagName("input");
                for (var i = 0, max = allInputs.length; i < max; i++){
                    if (allInputs[i].type === 'checkbox' && (allInputs[i].name.indexOf('json_') === 0 || allInputs[i].name.indexOf('xml_') === 0))
                        allInputs[i].checked = true;
                }
            }
            
            function selectAllUsers(){
                var allInputs = document.getElementsByTagName("input");
                for (var i = 0, max = allInputs.length; i < max; i++){
                    if (allInputs[i].type === 'checkbox' && allInputs[i].name.indexOf('folder_') === 0)
                        allInputs[i].checked = true;
                }
            }
        </script>