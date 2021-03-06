<?php
header('Access-Control-Allow-Origin: *');
header('Content-type: application/json; charset=utf-8');
require("database/MYSQL_TASKREPOSITORY.php");
require("HELPER.php");
require('JSON.php');
require("OVERLAY.php");
require('CLASSIFY.php');
require('EXPAND.php');
require('SEARCH.php');
require('TREND.php');
require_once('CACHE_MANAGER.php');

$q =  $_POST["values"];
$json_obj = json_decode($q, true);
$modus = $json_obj['format']['id'];
$indicator = $json_obj['ind']['id'];
$year =$json_obj['ind']['time'];
$raumgliederung =$json_obj['ind']['raumgliederung'];
$klassifizierung = $json_obj['ind']['klassifizierung'];
$klassenanzahl = $json_obj['ind']['klassenzahl'];
$ags_user = trim($json_obj['ind']['ags_array']);
$colors =(object)$json_obj['ind']['colors'];
$query = strtolower($json_obj['query']);

try{
    //set the ags_array
    $ags_array = array();
    if(strlen($ags_user)>0){
        $ags_array = explode(",",$ags_user);
    }
    //----------------------------------Queries--------------------------------------//
    //get the JSON
    if($query==='getjson'){
        //check if the json exist in the database
        $cache_manager = new CACHE_MANAGER($indicator,$year,$raumgliederung,$klassifizierung,$klassenanzahl);
        try{
            if (!$cache_manager->check_cached($ags_array,$colors)) {
                $indicator_json = new JSON($indicator,$year,$raumgliederung,$ags_array);
                $geometry_values = $indicator_json->createJSON();
                $class_manager = new CLASSIFY($geometry_values,$klassenanzahl,$colors,$indicator,$klassifizierung);
                $classes = $class_manager->classify();
                //save the cache but not avaliable user colors
                if(count((array)$colors)==0 and count($ags_array)==0){
                    $cache_manager->insert(json_encode(array_merge($geometry_values,array("classes"=>$classes))));
                }
                echo json_encode(array_merge($geometry_values,array("classes"=>$classes),array("state"=>"generated")));
            }else{
                echo json_encode(array_merge($cache_manager->get_cached(),array("state"=>"cached")));
            }
        }catch(Error $e){
            $trace = $e->getTrace();
            echo $e->getMessage().' in '.$e->getFile().' on line '.$e->getLine().' called from '.$trace[0]['file'].' on line '.$trace[0]['line'];
        }
    }
    //get all possible Extends for a indictaor
    else if($query==="getspatialextend"){
        $dictionary = MYSQL_TASKREPOSITORY::get_instance()->getSpatialExtendDictionary();
        $possibilities = MYSQL_TASKREPOSITORY::get_instance()->getSpatialExtend($modus,$year,$indicator);
        $result = array();
        if($modus==="gebiete"){
            foreach($dictionary as $value){
                $id = "RAUMEBENE_".strtoupper($value->id);
                $avaliable = str_replace(array("1","0"),array("enabled","disabled"),(string)$possibilities[0]->{$id});
                $name = $value->name;
                array_push($result,array("id"=>$value->id,"name"=>$name,"name_en"=>$value->name_en,"state"=>$avaliable));
            }
        }else{
            foreach($possibilities as $value){
                array_push($result,$value->RAUMGLIEDERUNG);
            }
        }
        echo json_encode($result);
    }
    //get all possible Indicators
    else if($query==='getallindicators'){
        $language = $json_obj['format']['language'];
        $json = '{';
        $kategories = MYSQL_TASKREPOSITORY::get_instance()->getAllCategoriesGebiete();
        if($modus=='raster') {
            $kategories = MYSQL_TASKREPOSITORY::get_instance()->getAllCategoriesRaster();
        }

        foreach($kategories as $row){

            $erg_indikator = MYSQL_TASKREPOSITORY::get_instance()->getAllIndicatorsByCategoryGebiete($row->ID_THEMA_KAT,$modus);

            //only if indicators are avaliabke
            if (count($erg_indikator) != 0) {

                $json .= '"' . $row->ID_THEMA_KAT . '":{"cat_name":"' . $row->THEMA_KAT_NAME . '","cat_name_en":"'.$row->THEMA_KAT_NAME_EN.'","indicators":{';

                foreach($erg_indikator as $row_ind){
                    $grundakt_state = "verfügbar";
                    if ($row_ind-> MITTLERE_AKTUALITAET_IGNORE== 1) {
                        $grundakt_state = "nicht verfügbar";
                    }
                    $significant = 'false';
                    if (intval($row_ind->MARKIERUNG) == 1) {
                        $significant = 'true';
                    }
                    //get all possible times
                    $time_string = '';
                    $times = MYSQL_TASKREPOSITORY::get_instance()->getIndicatorPossibleTimeArray($row_ind->ID_INDIKATOR,$modus,false);
                    foreach($times as $value){$time_string .= $value["time"].",";};
                    $time_string = substr($time_string,0,-1);
                    //extend the json
                    $json .= '"' . $row_ind->ID_INDIKATOR . '":{"ind_name":"' . str_replace('"', "'", $row_ind->INDIKATOR_NAME) .
                        '","ind_name_en":"' . str_replace('"', "'", $row_ind->INDIKATOR_NAME_EN) .
                        '","ind_name_short":"' . str_replace('"', "'", $row_ind->INDIKATOR_NAME_KURZ) .
                        '","basic_actuality_state":"' . $grundakt_state .
                        '","significant":"' . $significant .
                        '","atkis":"' . $row_ind->DATENGRUNDLAGE_ATKIS.
                        '","ogc":{' .
                            '"wfs":"' . $row_ind->WFS.
                            '","wcs":"' . $row_ind->WCS.
                            '","wms":"' . $row_ind->WMS.
                        '"},"unit":"' . $row_ind->EINHEIT .
                        '","spatial_extends":{'.
                            '"bld":"' . $row_ind->RAUMEBENE_BLD.
                            '","krs":"' . $row_ind->RAUMEBENE_KRS.
                            '","gem":"' . $row_ind->RAUMEBENE_GEM .
                            '","g50":"' . $row_ind->RAUMEBENE_G50 .
                            '","stt":"' . $row_ind->RAUMEBENE_STT .
                            '","ror":"' . $row_ind->RAUMEBENE_ROR .
                        '"},"literatur":"' . preg_replace('/\s+/', ' ',str_replace('"',"'",htmlentities($row_ind->LITERATUR))) .
                        '","verweise":"' . preg_replace('/\s+/', ' ',str_replace('"',"'",htmlentities($row_ind->VERWEISE))) .
                        '","verweise_en":"' . preg_replace('/\s+/', ' ',str_replace('"',"'",htmlentities($row_ind->VERWEISE_EN))) .
                        '","interpretation":"' . trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->BEDEUTUNG_INTERPRETATION))) .
                        '","interpretation_en":"' . trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->BEDEUTUNG_INTERPRETATION_EN))) .
                        '","methodik":"' . trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->METHODIK))) .
                        '","bemerkungen":"' . trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->BEMERKUNGEN))) .
                        '","bemerkungen_en":"' . trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->BEMERKUNGEN_EN))) .
                        '","methodik_en":"' . trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->METHODIK_EN))) .
                        '","info":"' . trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->INFO_VIEWER_ZEILE_1." ".$row_ind->INFO_VIEWER_ZEILE_2." ".$row_ind->INFO_VIEWER_ZEILE_3." ".$row_ind->INFO_VIEWER_ZEILE_4." ".$row_ind->INFO_VIEWER_ZEILE_5." ".$row_ind->INFO_VIEWER_ZEILE_6))) .
                        '","info_en":"' . trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->INFO_VIEWER_ZEILE_1_EN." ".$row_ind->INFO_VIEWER_ZEILE_2_EN." ".$row_ind->INFO_VIEWER_ZEILE_3_EN." ".$row_ind->INFO_VIEWER_ZEILE_4_EN." ".$row_ind->INFO_VIEWER_ZEILE_5_EN." ".$row_ind->INFO_VIEWER_ZEILE_6_EN))) .
                        '","datengrundlage":"' . trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->DATENGRUNDLAGE_ZEILE_1))) .
                        " ".
                        trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->DATENGRUNDLAGE_ZEILE_2))).
                        '","datengrundlage_en":"' . trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->DATENGRUNDLAGE_ZEILE_1_EN))) .
                        " ".
                        trim(preg_replace('/\s+/', ' ', str_replace('"', "'", $row_ind->DATENGRUNDLAGE_ZEILE_2_EN))).
                        '","times":"' . $time_string . '"},';
                }
                $json = substr($json, 0, -1);
                $json .= '}},';
            }
        }
        $json = substr($json, 0, -1);
        $json .="}";
        header('Content-type: application/json; charset=utf-8');
        echo HELPER::get_instance()->escapeJsonString($json);
    }
    //get all possible years
    else if($query=='getyears'){
        $jahre = array();
        $years = MYSQL_TASKREPOSITORY::get_instance()->getIndicatorPossibleTimeArray($indicator,$modus);
        foreach ($years as $x){
                array_push($jahre,intval($x["time"]));
        }
        echo json_encode($jahre);
    }
    //check avability
    else if($query=="getavability"){
        $array = array();
            array_push($array, array(
                "ind" => $indicator,
                "avability" => MYSQL_TASKREPOSITORY::get_instance()->checkIndicatorAvability($indicator,$modus))
            );
        echo json_encode($array);
    }
    //get the color Range for a given count
    else if ($query=="getcolorschema"){
        $min = $colors->min;
        $max = $colors->max;
        $color_range = COLORS::get_instance()->buildColorPalette($klassenanzahl,$min,$max);
        echo json_encode($color_range);
    }
    //counte the amount of geometries, which will be generated
    else if($query=="countgeometries"){
        $count = POSTGRESQL_TASKRESPOSITORY::get_instance()->countGeometries($year,$raumgliederung,$ags_array);
        echo json_encode($count);
    }
    //get the map overlay
    else if($query=="getzusatzlayer"){
        $zusatzlayer = $json_obj['ind']['zusatzlayer'];
        $overlay = new OVERLAY($zusatzlayer);
        echo json_encode($overlay->getJSON());
    }
    //get the values to Expand the Table by the given values
    else if($query=="gettableexpandvalues"){
        $zusatz_values = $json_obj['expand_values'];
        $ags_array =  $json_obj['ags_array'];
        $expand = new EXPAND($indicator,$year,$raumgliederung);
        $rs = $expand->getExpandValues($zusatz_values,$ags_array);
        echo json_encode($rs);
    }
    //search for a indicator or place
    else if($query=="search"){
        $search_string = $json_obj['q'];
        $option = $json_obj['option'];
        $search = new SEARCH($search_string,$option);
        echo json_encode($search->query());
    }
    //get the values to create the chart
    else if($query=="gettrend"){
        //example setting: set":{"all_points":"true","forecast":"true","compare":"true"}
        $settings = (object)$json_obj['set'];
        $forecast = HELPER::get_instance()->extractBoolen($settings->forecast);
        $compare = HELPER::get_instance()->extractBoolen($settings->compare);
        $all_points = HELPER::get_instance()->extractBoolen($settings->all_points);
        $trend = new TREND($ags_array[0],$indicator,$all_points,$compare,$forecast);
        echo json_encode($trend->getTrendValues(),JSON_UNESCAPED_UNICODE);
        //echo json_encode($trend->toJSON());
    }
    /*get all indicator values for a gives ags with differences to BRD and KRS if set*/
    else if($query=="getvaluesags"){
        //takes exactly one ags value
        $ags =$json_obj['ind']['ags'];
        $values=MYSQL_TASKREPOSITORY::get_instance()->getAllIndicatorValuesInAGS($year,$ags,true,true);
        $keys = array();
        foreach(MYSQL_TASKREPOSITORY::get_instance()->getAllCategoriesGebiete() as $k){array_push($keys,$k->ID_THEMA_KAT);}
        $result = array_fill_keys($keys,array());
        //foreach($result as $value)
        foreach($result as $key=>$value){
            foreach($values as $v){
                if($key==$v->category){
                    unset($v->category);
                    $result[$key][] = $v;
                }
            }
        }
        echo json_encode($result);
    }


}catch(Error $e){
    $trace = $e->getTrace();
    echo $e->getMessage().' in '.$e->getFile().' on line '.$e->getLine().' called from '.$trace[0]['file'].' on line '.$trace[0]['line'];
}
