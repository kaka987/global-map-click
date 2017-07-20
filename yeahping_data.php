<?php
/**
 * Yeahmobi data looker script
 *
 * It just get data and parse data
 *
 * @author      zhangy<Young@yeahmobi.com>
 * @package     Yeahmobi
 * @since       Version 1.0.1 @20140408
 * @copyright   Copyright (c) 2014, Yeahmobi, Inc.
 */

//----------------------------------------------------------------------
// Config start >>>

//ip2location db config
$host_ip = 'ip2location.cgs2bjzqxcxl.us-east-1.rds.amazonaws.com';
$user_ip = 'ip2location';
$pass_ip = '7oAQi7OO71f7bSy';
$database_ip = 'ip2location';

//clickdata db config
$host_click = 'clickdata.cgs2bjzqxcxl.us-east-1.rds.amazonaws.com';
$user_click = 'clickdata';
$pass_click = 'tLD8gEbwTjOm6K';
$database_click = 'clickdata';
$table_click = 'yeahpingtable';
//$table_click = 'clicktabletest';

// include logger
include 'Logger.php';
$logConfig = array('logPath'=>'./', 'logFile'=>'click', 'handler'=>'file', 'ifCacheHandler'=>TRUE);
Ym_Logger::init($logConfig);

// click mongodb data
//$mongo_db = "54.85.58.139:40001";
$mongo_db = "10.1.0.30:40001";

$table_index = isset($argv[1]) ? $argv[1] : 0;

$timefile = '/tmp/'.basename($argv[0], ".php").'.time.'.$table_index;

// data time interval
$len = 1;

//----------------------------------------------------------------------
// process start >>>

$table = 'click_log'.$table_index;

$conn = new Mongo($mongo_db);
$collection = $conn->report->$table;

if ( ! file_exists($timefile)) {
    $start = time()-$len;
    $end   = time();
}else{
    //get the time
    $thetime = @(int)file_get_contents($timefile);
    if ( ! empty($thetime) ) {
        $start = $thetime;
        $end   = $start + $len;
    }
}

$begin = new MongoDate($start);
$stop  = new MongoDate($end);

$where  = array('clt'=>array('$gt'=>$begin, '$lte'=>$stop));
$items = array('offer' => true, 'ip' => true, 'clt' => true, 'country' => true, 'dty' => true, 'plat' => true, 'browser' => true, 'conversions' => true);
$sorted = array('clt'=>1);

$clickdata = array();
for($i=0; $i<30; $i++) {

    if ($end < getLastTimeFromMongo($collection)) {

        $clickdata = $collection->find($where)->fields($items)->sort($sorted);
        break;
    }

    sleep(1);
}

// get click data over

$array = array();
$value = array();
foreach ($clickdata as $id => $value) {
        $array[] = $value;
}

// close mongodb
$conn->close();

if ( empty($array) OR ( ! isset($value['clt']))) {
    @file_put_contents($timefile, $end);
    die("Click data is empty!\n");
}

@file_put_contents($timefile, $end);
//var_dump($array);exit;

$conn1 = mysql_connect($host_ip, $user_ip, $pass_ip);
$conn2 = mysql_connect($host_click, $user_click, $pass_click);
$sqld = "insert into ".$database_click.".".$table_click." values";
$sqld1 = "insert into clickdata.offer_amount values";
$amount = array();
foreach ($array as $key => $click) {

    $clt = $click['clt']->sec;
    $country = isset($click['country']) ? $click['country'] : 0;
    $dty = isset($click['dty']) ? $click['dty'] : 0;
    $plat = isset($click['plat']) ? $click['plat'] : 0;
    $browser = isset($click['browser']) ? $click['browser'] : 0;
    $conversions = isset($click['conversions']) ? $click['conversions'] : 0;

    #$sql = "select ip_from from ".$database_ip.".ip2location where ip_from<INET_ATON('".$click['ip']."') and ip_to>INET_ATON('".$click['ip']."') limit 1";
    #$sql = "select ip_from from ".$database_ip.".ip2location where ip_from<INET_ATON('".$click['ip']."') and ip_to>INET_ATON('".$click['ip']."') limit 1";
    $sql = "select latitude,longitude from ".$database_ip.".ip2location_db24 where ip_from<INET_ATON('".$click['ip']."') order by ip_from desc limit 1";
    //echo $sql,"\n";
    $result = mysql_query($sql,$conn1);
    if (!$result) {
            $msg = 'Could not run query: ' . mysql_error();
            Ym_Logger::error($msg);
    }

    $row = mysql_fetch_row($result);

    if (isset($row[0]))
            $sqld .= "('',".$clt.",".$click['offer'].",".$row[0].",".$row[1].",'".$country."',".$dty.",".$plat.",".$browser.",'clicks',".$conversions.",".$table_index."),";
    //echo $sqld;exit;

    $sql1 = "select category_id from clickdata.offer_category where offer_id=".$click['offer'];
    $result1 = mysql_query($sql1,$conn2);
    $row1 = mysql_fetch_row($result1);
    if (isset($row1[0]))
            isset($amount[$row1[0]]) ? $amount[$row1[0]]++ : $amount[$row1[0]] = 1;

}
$sqld = rtrim($sqld,',');

foreach ($amount as $category_id => $num_category) {
    $sqld1 .= "(".$clt.",".$category_id.",".$num_category."),";
}
$sqld1 = rtrim($sqld1,',');

//record the last time
$msg = $table_index.'#'.$end;
Ym_Logger::info($msg);


$result = mysql_query($sqld,$conn2);
if (!$result) {
        $msg = 'Could not run query: ' . mysql_error();
            Ym_Logger::error($msg);
}

$result1 = mysql_query($sqld1,$conn2);
if (!$result1) {
        $msg = 'Could not run query: ' . mysql_error();
            Ym_Logger::error($msg);
}

function getLastTimeFromMongo($collection) {
    
    $lastTime = '';
    $queryData = $collection->find()->fields(array("clt"=>true))->sort(array("clt"=>-cl))->limit(1);
    foreach($queryData as $v) {
        $lastTime = $v['clt']->sec;
        break;
    }
        
    if ($lastTime) return $lastTime;
    return FALSE;
}
/* End of file yeahmobi_datalooker.php */
/* Location: ./yeahmobi_datalooker.php */
