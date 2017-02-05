<?php
/**
 * Document Forms  
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
            table{ border-collapse: collapse;}
        </style>
    </head>
    <body><?php
        $collection = isset($_REQUEST['collection']) ? $_REQUEST['collection'] : '';
        $portal = isset($_REQUEST['portal']) ? $_REQUEST['portal'] : '';
        $form = isset($_REQUEST['form']) ? $_REQUEST['form'] : '';
        $procForm = isset($_REQUEST['proc']) ? $_REQUEST['proc'] : '';
        ?><div class="no-select">
            <form autocomplete="off">
                <table>
                    <tr><td colspan="2"><h3>MongoDB parameters</h3></td></tr>
                    <tr><td>Portal Name:</td><td><input name="portal" value="<?= $portal ?>"/></td></tr>
                    <tr><td>Collection:</td><td><input name="collection" value="<?= $collection ?>"/></td></tr>
                    <tr><td>Form:</td><td><input name="form" value="<?= $form ?>"/></td></tr>
                    <tr><td>Proc:</td><td><input name="proc" value="<?= $procForm ?>"/></td></tr>
                    <tr><td></td><td><input type="submit"/></td></tr>
                </table>
            </form><hr></div>
<?php
$formPath = $_SERVER['DOCUMENT_ROOT'] . '/se/eapp/forms/' . $portal . '/' . $form . '_config.php';
$pageConfig['collection'] = $collection;


echo $procForm;
echo $formPath;

session_start();
include ($_SERVER['DOCUMENT_ROOT'] . '/se/dbconnect.php');
include ('func.php');
include($formPath);

echo '<pre/>';
//print_r($objDefinition);

echo '<table border="1">';
foreach ($objDefinition['tabs'] as $tabName => $tabDescription) {

    $fields = getTabFields($tabName);
    
    
    $i = 0;
    $j = -1;
    $k = 0;
    $num = array();
    foreach($fields['fields'] as $fieldName => $fieldConf){
        //echo substr($fieldName,0,3)
        $i++;
        if($fieldConf['inputType'] == 'separator'){
            $j++;
            $num[$j] = ($i-1);
            $i = 0;
        }else{
            $k++;
        }
    }
    
    $num2 = array();
    foreach($num as $sec){
        if($sec != 0){
            $num2[] = $sec;
        }
    }
    
    $lasNum = $k - array_sum($num2);
    array_push($num2, $lasNum);
    
    echo '<tr>';
    echo '<td>' . $tabDescription['label'] . '</td>';
    echo '<td> '.$k.'</td>';
    
    //echo '<td rowspan="'.$k.'">' . $tabDescription['label'] . '</td>';
    //echo '<td rowspan="'.$k.'"> '.$k.'</td>';

    echo '<td><table border="1">';
    
    foreach($fields['fields'] as $fieldName => $fieldConf){
        echo '<tr>';
        
        if($fieldConf['inputType'] == 'separator'){
            $idx = array_search($fieldName, $fields['sections']);
            echo '<td rowspan="'.($num2[$idx] + 1).'">'.$fieldConf['label'].'</td>';
            echo '<td rowspan="'.($num2[$idx] + 1).'">'.$num2[$idx].'</td>';
        }else{
            echo '<td>'.$fieldName.'</td>';
            echo '<td>'.$fieldConf['label'].'</td>';

            $inputType = isset($fieldConf['inputType']) ? $fieldConf['inputType']:'text';
            echo '<td>'.$inputType.'</td>';

            $dataType = isset($fieldConf['dataType']) ? $fieldConf['dataType']: 'string';
            echo '<td>'.$dataType.'</td>';

            $units = isset($fieldConf['units']) ? $fieldConf['units']:'';
            echo '<td>'.$units.'</td>';

            $readOnly = isset($fieldConf['readonly']) ? 'readonly':'';
            echo '<td>'.$readOnly.'</td>';

            $disabled = isset($fieldConf['disabled']) ? 'disabled':'';
            echo '<td>'.$disabled.'</td>';
        }
        echo '</tr>';
        
    }  
    echo '</table></td>';
    
    
    echo '</tr>';
}
echo '</table>';

function getTabFields($tabName){
    global $objDefinition;
    
    $fnum = -1;
    $sections = array();
    $fields = array();
    
    foreach ($objDefinition['fields'] as $fieldName => $fieldConf) {
        
        $inputType = isset($fieldConf['inputType']) ? $fieldConf['inputType'] : '';
        
        if($fieldConf['tab'] == $tabName && $inputType != 'hidden'){
            $fnum++;
            $fields[$fieldName] = $fieldConf;
        }
        
        if($fieldConf['tab'] == $tabName && $inputType == 'separator'){
            $sections[] = $fieldName;
        }
        
    }
    
    $return['fnum'] = $fnum;
    $return['fields'] = $fields;
    $return['sections'] = $sections;
    
    return $return;
    
}
