<?php

session_start();
$portal = $_SESSION['portal'];
$docRoot = $_SERVER['DOCUMENT_ROOT'];

$role = isset($_REQUEST['role']) ? $_REQUEST['role'] : null;
$wfid = isset($_REQUEST['wfid']) ? $_REQUEST['wfid'] : null;

if ($role === null || $wfid === null){
    die('Debe asignar la variable "role" y/o "wfid" en el url <br/>'
            . '<b>Ejemplo:</b> http://localhost/se/utils/migrate-applist.php?role=representante_legal&wfid=mma');
}

include($docRoot . '/se/eapp/getConfigFile.php');
$confFileJson = getConfigFile('workflow','json', $wfid, $role);

// Views definition
$xmlObj = new SimpleXMLExtended('<root></root>');
$xmlJList = $xmlObj->addChild('jlist');
$xmlJList->addAttribute('title', $confFileJson['label']);
$xmlJList->addAttribute('subtitle', $confFileJson['subtitle']);
$xmlJList->addAttribute('database', $confFileJson['database']);
$xmlJList->addAttribute('eventHandler', '/se/eapp/events/' . $portal . '/eventHandler.php');
$xmlJList->addAttribute('limit', $confFileJson['limit']);
foreach ($confFileJson['view'] as $jsonKey => $jsonValue) {
    // columns
    $viewXml = $xmlJList->addChild('view');
    $viewXml->addAttribute('label', $jsonValue['label']);
    $viewXml->addAttribute('collection', $jsonValue['collection']);
    
    if(isset($jsonValue['state'])){
        $viewXml->addAttribute('showStateId', $jsonValue['state']);
    }

    // buttons
    if (isset($jsonValue['buttons'])) {
        foreach ($jsonValue['buttons'] as $key => $button) {
            $btnXml = $viewXml->addChild('button');
            $btnXml->addAttribute('name', $button[0]);
            $btnXml->addAttribute('label', $button[1]);

            if (isset($button[2])){
                $btnXml->addAttribute('icon', $button[2]);
            }
            
            if (isset($button[3])){
                $btnXml->addAttribute('states', $button[3]);
            }
        }
    }

    $keyXml = $viewXml->addChild('key');
    $keyXml->addAttribute('name', 'Status.state');
    $keyXml->addAttribute('label', 'Estado');
    
    foreach ($jsonValue['columns'] as $key => $column) {
        $keyXml = $viewXml->addChild('key');
        $keyXml->addAttribute('name', $column[0]);
        $keyXml->addAttribute('label', $column[1]);

        if (isset($column[2]) == 'double') {
            $keyXml->addAttribute('dataType', 'double');
        }

        if (isset($column[2]) == 'integer') {
            $keyXml->addAttribute('dataType', 'integer');
        }
    }

    foreach ($jsonValue['query'] as $query) {
        $queryXml = $viewXml->addChild('query');
        $queryXml->addAttribute('label', $query['label']);
        
        if($query['sfilter']){
            foreach ($query['sfilter'] as $sfilter){
                $sfilterXml = $queryXml->addChild('sfilter');
                $sfilterXml->addAttribute('key', $sfilter[0]);
                $sfilterXml->addAttribute('svar', $sfilter[1]);
            }
        }

        if (isset($jsonValue['dbDefaultSort'])) {
            $queryXml->addAttribute('sortby', array_keys($jsonValue['dbDefaultSort'])[0]);
        }

        $queryXml->mstring = NULL;
        $queryXml->mstring->addCData(json_encode($query['string'], JSON_PRETTY_PRINT));
        
        if($query['sort']){
            $queryXml->sort = NULL;
            $queryXml->sort->addCData(json_encode($query['sort'], JSON_PRETTY_PRINT));
        }
    }
    
    $msgXml = $queryXml->addChild('msg')->addCData($jsonValue['msg']);
}

header('Content-Type: text/xml');
echo $xmlObj->asXML();
/*
  $doc = new DomDocument('1.0');
  $doc->loadXML(join("\r\n", $r));
  $doc->preserveWhiteSpace = true;
  $doc->formatOutput = true;
  $xml_string = $doc->saveXML();

  echo '<pre>';
  echo htmlentities($xml_string);
  echo '</pre>';
 * 
 */

class SimpleXMLExtended extends SimpleXMLElement {

    public function addCData($cdata_text) {
        $node = dom_import_simplexml($this);
        $no = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($cdata_text));
    }

}
