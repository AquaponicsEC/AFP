<?php

include_once('func.php');

function _translate($str) {
    global $lang;
    return (isset($lang[$str]) ? $lang[$str] : $str);
}

class Workflow {

    public $scXML; //scxml content
    private $tableOptions = array();

    public function __construct($scXMLFilePath = null, $tableOptions) {

        $this->tableOptions = $tableOptions;

        if (empty($scXMLFilePath)) {
            throw new Exception('File name not provided');
        }
        if (!file_exists($scXMLFilePath)) {
            throw new Exception('File does not found');
        }

        $tempXML = file_get_contents($scXMLFilePath);
        $xmlobj = simplexml_load_string($tempXML); //Loading XML

        for ($i = 0; $xmlobj->state[$i]; $i++) :
            $stId = (string) $xmlobj[0]->state[$i]->attributes()->id; //State Id
            $stLabel = (string) $xmlobj[0]->state[$i]->attributes()->name; //State Name
            $scXMLArray[$stId]['label'] = $stLabel;

            for ($j = 0; $xmlobj->state[$i]->transition[$j]; $j++): //transitions attributes

                $tEvent = (string) $xmlobj->state[$i]->transition[$j]->attributes()->event;
                $tTarget = (string) $xmlobj->state[$i]->transition[$j]->attributes()->target;
                $tLabel = (string) $xmlobj->state[$i]->transition[$j]->attributes()->label;
                $tAccess = (string) $xmlobj->state[$i]->transition[$j]->attributes()->access;

                $tAccess = explode(',', $tAccess);
                $roles = '';
                foreach ($tAccess as $access):
                    $roles[] = $access;
                endforeach;

                $scXMLArray[$stId]['transitions'][$j]['event'] = $tEvent;
                $scXMLArray[$stId]['transitions'][$j]['target'] = $tTarget;
                $scXMLArray[$stId]['transitions'][$j]['label'] = $tLabel;
                $scXMLArray[$stId]['transitions'][$j]['access'] = $roles;

            endfor;

        endfor;

        $this->scXML = $scXMLArray;
    }

    public function tableHeader() {
        
        $scxml = $this->scXML;
        $tableOptions = $this->tableOptions;
        
        foreach ($scxml as $state => $st){
            $tableOptions['columns']['Status.state']['filter']['options'][$state] = ['label' => $st['label'] . ' (' . $state . ')'];
        }

        echo "<thead>\n";

        // output columns headers
        echo '<tr>';
        foreach ($tableOptions['columns'] as $key => $attr):
            echo '<th>';
            if ($key != '_actions')
                echo "<a onclick=\"setSort('" . $key . "');return false\">";

            echo isset($attr['label']) ? $attr['label'] : $key;

            if ($key != '_actions') :
                echo ' <i id="sort_' . $key . '" class="icon-sort"></i>';
                echo "</a>";
            endif;

            echo '</th>';
        endforeach;
        echo "</tr>\n";

        // output column filters
        if (isset($tableOptions['showFilter']) && $tableOptions['showFilter']) :
            echo '<tr id="filterInput">';

            foreach ($tableOptions['columns'] as $key => $columnAttr):
                // preset some parameters
                $htmlKey = str_replace('.', '_', $key);
                if (!isset($columnAttr['filter']))
                    $columnAttr['filter']['type'] = 'string';

                echo '<th>';

                if ($key == '_actions') :
                    // output drop down for filter row
                    echo '<select style="width: 150px;"><option>' . _translate('Clear Filter') . '</option></select>';
                    echo '<button class="pointer btnaction" onclick="location.reload(); return false;"><span class="icon-caret-right" style="font-size:14px; color:gray;"></span></button>';
                else:
                    // output filter fields
                    switch ($columnAttr['filter']['type']):
                        case 'string':
                            echo '<input id="q_' . $htmlKey . '" name="q[' . $key . ']" type="search" ';
                            echo ' onkeypress="if(event.keyCode==13){updateTable(0); return false;}">';
                            break;

                        case 'select':
                            echo '<select id="q_' . $htmlKey . '" name="q[' . $key . ']" onchange="updateTable(); return false;">';
                            foreach ($columnAttr['filter']['options'] as $optKey => $optionAttr):
                                echo '<option value="' . $optKey . '"';
                                echo isset($optionAttr['selected']) ? ' selected' : '';
                                echo '>' . $optionAttr['label'] . '</option>';
                            endforeach;
                            echo '</select>';
                            break;
                    endswitch;
                endif;

                echo '</th>';
            endforeach;

            echo "</tr>\n";
        endif;

        echo "</thead>\n";
    }

    public function tableBody($cursor, $loc_rows_per_page, $pageOffset) {

        $scxml = $this->scXML;
        $tableOptions = $this->tableOptions;

        $pageOffset = isset($_REQUEST['offset']) ? intval($_REQUEST['offset']) : 0;

        $response = ['<tbody id="tbody">'];
        $count = $cursor->count();

        if ($count) {
            //check $pageOffset boundaries
            $pageOffset = max(0, $pageOffset);
            $pageOffset = min($pageOffset, ceil($count / $loc_rows_per_page) - 1);

            foreach ($cursor as $doc) {
                //get workflow id and status name
                $stateId = isset($doc['Status']['state']) ? $doc['Status']['state'] : '';  //status id - TODO
                $stateName = $stateId . ' ' . getArrayValue('' . $stateId . '.label', $scxml); //Get Label from State Id

                $printed_selected = false;
                $stateOptions = '';

                foreach ($scxml as $state => $st) {
                    if ($state == $stateId) {
                        //Show options by role (transition in SCXML (coming from SC Array)
                        foreach ($st['transitions'] as $action) {
                            if (in_array($_SESSION['usrrole'], $action['access'])) {
                                $selected = '';
                                if (!$printed_selected) {
                                    $selected = 'selected="selected"';
                                    $printed_selected = true;
                                }
                                $stateOptions.= '<option value="' . $action['event'] . '" ' . $selected . '>' . $action['label'] . '</option>';
                            }
                        }
                    }
                }

                $tRow = '';
                $tRow .= '<tr>';
                $tRow .= '<td>';
                $tRow .= '<select class="actionmenu" id="sel_' . $doc['_id'] . '" style="width: 150px;">';
                $tRow .= $stateOptions;
                $tRow .= '</select>';
                $tRow .= '<button class="pointer btnaction" onclick="transition(\'' . $doc['_id'] . '\',\'' . $doc['_TYPE'] . '\',\'' . $stateId . '\'); return false;"><span class="icon-caret-right" style="font-size:14px; color:gray;"></span></button>';
                $tRow .= '</td>';

                $tRow .= '<td>' . $stateName . '</td>';

                unset($tableOptions['columns']['_actions']);
                unset($tableOptions['columns']['Status.state']);
                
                foreach ($tableOptions['columns'] as $keyRow => $row) {
                    $value = getArrayValue($keyRow, $doc);
                    $tRow .= '<td>' . (isset($value) ? $value : '') . '</td>';
                }

                $tRow .= "</tr>\n";

                $response[] = $tRow;
            }

            // show page & record count
            $tRowCount = '<tr class="docCount">';
            $tRowCount .= '<td colspan="10">';
            $lowCount = number_format(($pageOffset * $loc_rows_per_page) + 1, 0);
            $topCount = number_format(min(($pageOffset + 1) * $loc_rows_per_page, $count), 0);

            $prevPageOffset = max($pageOffset - 1, 0);
            $prevBtnStatus = $pageOffset == 0 ? 'disabled' : '';

            $nextPageOffset = $pageOffset + 1;
            $nextBtnStatus = ($count <= ($nextPageOffset * $loc_rows_per_page)) ? 'disabled' : '';

            $tRowCount .= " <b> $lowCount - $topCount</b> " . _translate('of') . " " . number_format($count, 0) . ' - ';
            $tRowCount .= _translate('Page') . ' <input name="" value="' . ($pageOffset + 1) . '" size="2" onkeypress="if(event.keyCode==13){updateTable(this.value-1); return false;}"> ' . _translate('of') . ' ' . number_format(ceil($count / $loc_rows_per_page), 0);
            $tRowCount .= '<button class="btn-apps" ' . $prevBtnStatus . ' onclick="updateTable(' . $prevPageOffset . ');return false;" style="margin-left: 40px">' . _translate('Previous') . '</button>';
            $tRowCount .= '<button class="btn-apps" ' . $nextBtnStatus . ' onclick="updateTable(' . $nextPageOffset . ');return false;" style="margin-left: 20px">' . _translate('Next') . '</button>';
            $tRowCount .= '</td>';
            $tRowCount .= '</tr>';

            $response[] = $tRowCount;
        } else {
            $tRowCount = '<tr><td colspan="11">' . _translate('Records not found') . '</td></tr>';
            $response[] = $tRowCount;
        }

        $response[] = "</tbody>";

        return join("\n", $response);
    }

}
