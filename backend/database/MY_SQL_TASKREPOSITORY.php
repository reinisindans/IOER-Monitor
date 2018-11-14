<?php

require_once("MYSQL_MANAGER.php");

class MY_SQL_TASKREPOSITORY extends MYSQL_MANAGER {
    protected static $instance = NULL;
    private $berechtigung = 3;
    public static function get_instance()
    {
        if ( NULL === self::$instance )
            self::$instance = new self;

        return self::$instance;
    }
    function getAllIndicatorsGebiete(){
        $sql= $sql_kategorie = "SELECT * FROM m_thematische_kategorien, m_them_kategorie_freigabe
                        WHERE m_thematische_kategorien.ID_THEMA_KAT = m_them_kategorie_freigabe.ID_THEMA_KAT
                        AND STATUS_KATEGORIE_FREIGABE >=  ".$this->berechtigung."
                        GROUP BY SORTIERUNG_THEMA_KAT";
        return $this->query($sql);
    }
    function getAllIndicatorsRaster(){
    $sql = "select * from m_thematische_kategorien, m_indikatoren, d_raster 
            where m_thematische_kategorien.ID_THEMA_KAT = m_indikatoren.ID_THEMA_KAT 
            and m_indikatoren.ID_INDIKATOR = d_raster.Indikator 
            and d_raster.freigabe_aussen = ".$this->berechtigung." 
            group by m_thematische_kategorien.id_thema_kat 
            order by m_thematische_kategorien.sortierung_thema_kat";
    return $this->query($sql);
    }
    function getAllIndicatorsByCategoryGebiete($kat, $modus){
        $sql = "SELECT * 
            FROM m_indikatoren, m_indikator_freigabe
            WHERE m_indikatoren.ID_THEMA_KAT =  '" . $kat . "'
            AND m_indikatoren.ID_INDIKATOR = m_indikator_freigabe.ID_INDIKATOR
            AND m_indikator_freigabe.STATUS_INDIKATOR_FREIGABE =  '".$this->berechtigung."'
            GROUP BY m_indikatoren.INDIKATOR_NAME_KURZ
            ORDER BY  m_indikatoren.MARKIERUNG DESC, m_indikatoren.SORTIERUNG ASC";

        if($modus==="raster"){
            $sql = "SELECT * 
                FROM m_indikatoren, d_raster
                WHERE ID_THEMA_KAT =  '".$kat."'
                AND m_indikatoren.ID_INDIKATOR = d_raster.INDIKATOR
                AND d_raster.Freigabe_AUSSEN >=  '".$this->berechtigung."'
                GROUP BY m_indikatoren.INDIKATOR_NAME_KURZ
                ORDER BY m_indikatoren.INDIKATOR_NAME_KURZ ASC";
        }

        return $this->query($sql);
    }
    function getIndicatorValueInSpatialExtend($year, $indikator_id,$length_ags ,$ags_user_array){
        $ags_extend = "";
        if(count($ags_user_array)>0) {
            $ags_extend .= " AND i.AGS REGEXP '";
            foreach ($ags_user_array as $value) {
                $ags_extend .= $value."|";
            }
            $ags_extend = substr($ags_extend,0,-1);
            $ags_extend = $ags_extend."'";
        }
        //build the sql query
        $sql = "SELECT i.INDIKATORWERT AS value, i.ID_INDIKATOR as ind, z.EINHEIT as einheit,i.FEHLERCODE as fc, i.HINWEISCODE as hc, i.AGS as ags, z.RUNDUNG_NACHKOMMASTELLEN as rundung
                                FROM m_indikatorwerte_" . $year . " i, m_indikator_freigabe f, m_indikatoren z
                                Where f.ID_INDIKATOR = i.ID_INDIKATOR AND f.ID_INDIKATOR =  '" . $indikator_id . "'
                                AND f.STATUS_INDIKATOR_FREIGABE = " . $this->berechtigung . "
                                And z.ID_INDIKATOR = f.ID_INDIKATOR
                                And LENGTH(i.AGS) = " .(strlen($length_ags))
                                .$ags_extend."
                                and not i.AGS='99'
                                Group by i.AGS";

        if($indikator_id ==='Z00AG'){
            $sql = "SELECT i.INDIKATORWERT AS value, i.ID_INDIKATOR as ind, i.ID_INDIKATORWERT as einheit,i.FEHLERCODE as fc, i.HINWEISCODE as hc, i.AGS as ags FROM m_indikatorwerte_".$year." i 
            Where i.ID_INDIKATOR = 'Z00AG' And LENGTH(i.AGS) = " .(strlen($length_ags)).$ags_extend;
        }
       return $this->query($sql);
    }
    function getIndicatorPossibleTimeArray($ind, $modus, $exclude_year)
    {
        $times = array();
        if ($modus === 'gebiete') {
            $ex_q = '';
            foreach ($exclude_year as $value) {
                $ex_q .= " And NOT JAHR =" . $value;
            }
            $query = "SELECT JAHR FROM m_indikator_freigabe
		  WHERE STATUS_INDIKATOR_FREIGABE >= '".$this->berechtigung."'
          AND ID_INDIKATOR = '" . $ind . "'" . $ex_q . "
          Order by JAHR DESC";
        } else {
            if ($exclude_year) {
                $query = "SELECT JAHR FROM d_raster
            WHERE d_raster.freigabe_aussen >= '".$this->berechtigung."'
            AND INDIKATOR = '" . $ind . "' AND NOT JAHR=" . $exclude_year . "
            group by JAHR
            Order by JAHR DESC";
            }
            else {
                $query = "SELECT JAHR FROM d_raster
            WHERE d_raster.freigabe_aussen >= '".$this->berechtigung."'
            AND INDIKATOR = '" . $ind . "'
            group by JAHR
            Order by JAHR DESC";
            }
        }
        $ergObject = $this->query($query);

       foreach($ergObject as $row){
            array_push($times, array("time" => $row->JAHR));
        }
        //needs to be sorted to make sure every column takes the correct place
        usort($times, function ($item1, $item2) {
            return $item1['time'] <=> $item2['time'];
        });
        return $times;
    }
    function checkIndicatorAvability($indikator,$modus)
    {
        $query = "SELECT ID_INDIKATOR, Jahr FROM m_indikator_freigabe WHERE ID_INDIKATOR = '" . $indikator . "' AND STATUS_INDIKATOR_FREIGABE ='".$this->berechtigung."' Group by Jahr";
        if($modus==='raster') {
            $query = "SELECT Indikator,Jahr FROM d_raster WHERE INDIKATOR = '" . $indikator . "' AND FREIGABE_AUSSEN ='".$this->berechtigung."' Group by JAHR";
        }
        $rs = $this->query($query);
            if (count($rs)>0) {
                return true;
            }
            else {
                return false;
            }
    }
    function getIndicatorGrundaktualitaet($ags, $year){
        $sql_year = "SELECT INDIKATORWERT as value FROM m_indikatorwerte_" . $year . " WHERE ID_INDIKATOR = 'Z00AG' and AGS ='" . $ags . "' AND INDIKATORWERT <= " . $year . " ";
        $sql_month = "SELECT INDIKATORWERT as value FROM m_indikatorwerte_" . $year . " WHERE ID_INDIKATOR = 'Z01AG' and AGS ='" . $ags . "' AND INDIKATORWERT <= " . $year . " ";

        $rs_akt_year = $this->query($sql_year);
        $rs_akt_mon = $this->query($sql_month);
        if($year >= date("Y")){
            return "1/".$year;
        }
        else if(count($rs_akt_year)==0){
            return "nicht verfügbar";
        }else {
            return $rs_akt_year[0]->value."/".$rs_akt_mon[0]->value;
        }
    }
    function getIndicatorValueByAGS($ind,$ags, $year){
        $sql = "SELECT m.INDIKATORWERT as value FROM m_indikatorwerte_" . $year . " m
            INNER JOIN m_fehlercodes f ON IFNULL(m.FEHLERCODE,0) = f.FEHLERCODE
            WHERE m.ags = '" . $ags . "' AND m.ID_INDIKATOR = '" . $ind . "' Group by m.Indikatorwert";
        $rs = $this->query($sql);
        return $rs[0]->value;
    }
    function getIndicatorRundung($indicator_id){
        $sql = "SELECT * FROM m_indikatoren WHERE ID_INDIKATOR='" . $indicator_id . "'";
        $rs = $this->query($sql);
        return $rs->RUNDUNG_NACHKOMMASTELLEN;
    }
    function getIndicatorValueForBRD($indicator_id,$year){
        $sql = "Select INDIKATORWERT as value FROM m_indikatorwerte_" . $year . " where ID_INDIKATOR ='".$indicator_id."' AND AGS = '99'" ;
        $rs = $this->query($sql);
        return $rs[0]->value;
    }
    function getIndicatorColors($ind){
        $sql = "SELECT FARBWERT_MAX as max,FARBWERT_MIN as min FROM m_zeichenvorschrift WHERE ID_INDIKATOR='".$ind."'";
        $rs = $this->query($sql);
        if(!$rs){

        }
        return $rs;
    }
    function getGrundaktState($ind){
        $sql = "SELECT MITTLERE_AKTUALITAET_IGNORE as value FROM m_indikatoren where ID_INDIKATOR = '".$ind."'";
        $rs = $this->query($sql);
        return intval($rs[0]->value);
    }
    function getPostGreYear($year){
        $sql = "select PostGIS_Tabelle_Jahr from v_geometrie_jahr_viewer_postgis where Jahr_im_Viewer =".$year;
        $rs = $this->query($sql);
        return intval($rs[0]->PostGIS_Tabelle_Jahr);
    }
}