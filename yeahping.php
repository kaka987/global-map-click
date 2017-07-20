<?php

/**
 * Click Class
 *
 * It just is click data api for yeahping 
 *
 * @author      zhangy<Young@yeahmobi.com>
 * @category    Libraries
 * @since       Version 1.0.2 @20140619
 * @copyright   Copyright (c) 2014, Yeahmobi, Inc.
 */
class Yeahping {

	//public $clicktable = 'yeahpingtable';
	public $clicktable = 'click_hasoffer_data';
	public $clickLen   = 10;
    public $realCount = 11;

	public function getOffersAction() {

		$after = isset($_GET["after"]) ? $_GET["after"] : time();
		$step = isset($_GET["step"]) ? $_GET["step"] : 30;
        
		$limit = 100;
		$trains  = 30;
		$res=array('data'=>'', 'nexttime'=>$after+1);
		$list = array();

        $category = isset($_GET["category"]) ? $_GET["category"] : 0;
        $offerWhere = '';
        if ($category != 0) {
            $offerList = $this->getOfferList1($category);        
            if ($offerList) $offerWhere = " AND offer_id IN(".$offerList.")";
        }

		$where = ' WHERE `time` > '.$after.' AND `time` <= '.($after + $step).$offerWhere;
		$result = $this->getOffersByTime($where);
        //var_dump($result);exit;

		if($result) {
			$data = $tmp = array();
			$t = '';
			foreach($result as $value) {
				if ($t != '' AND $t < $value['time']) {
					$data[$t] = array_values($data[$t]);
					unset($tmp);
				}

				if(! isset($tmp[$value['latitude'].'_'.$value['longitude']])) $tmp[$value['latitude'].'_'.$value['longitude']]=1;
				$tmp[$value['latitude'].'_'.$value['longitude']]++;
				$data[$value['time']][$value['latitude'].'_'.$value['longitude']] = array($value['latitude'], $value['longitude'], $tmp[$value['latitude'].'_'.$value['longitude']]);
				$t = $value['time'];
			}
			$data[$t] = array_values($data[$t]);
			$res['data'] = $data;
			$res['nexttime'] = $value['time'] +1;
			
		}
		
		$this->output($res);
	}

    public function getOfferList($category='All') {

        $sql = "SELECT offer_id FROM clickdata.category AS c,clickdata.offer_category AS o 
                WHERE c.id=o.category_id AND c.name='".$category."'";
        $result = $this->DB($sql, '');
        $offerList = '';
        if ($result) {

            foreach ($result as $offer) {
                $offerList .= $offer['offer_id'].',';
            }
        }
        return rtrim($offerList,',');
    }

    public function getOfferList1($category_id=0) {

        $sql = "SELECT offer_id FROM clickdata.offer_category AS o 
                WHERE o.category_id = ".$category_id;
        $result = $this->DB($sql, '');
        $offerList = '';
        if ($result) {

            foreach ($result as $offer) {
                $offerList .= $offer['offer_id'].',';
            }
        }
        return rtrim($offerList,',');
    }

	public function getOffersByTime($where) {

		$sql = ' SELECT time,offer_id,latitude,longitude FROM clickdata.'.$this->clicktable.' '.$where;
        $result = $this->DB($sql, '');
		return $result;
	}

	
	public function output($list, $status_code = 200){
        
        if(!empty($_SERVER['HTTP_ORIGIN'])) {
			header("Access-Control-Allow-Credentials: true");
			header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
			header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
		}


        $jsoncallback = $_GET['callback'];
        if($jsoncallback) {
            echo $jsoncallback."('".json_encode($list)."')";
        } else {
            echo json_encode($list);
        }
        exit;
		//http_response_code($status_code);
		echo json_encode($list);exit;
	}

    public function getClickAmount2Action() {

        date_default_timezone_set('Asia/Shanghai');
        $thisDayTime = isset($_GET["otime"]) ? $_GET["otime"] : strtotime(date('Y-m-d',time()));

        $category = isset($_GET["category"]) ? $_GET["category"] : 0;
        $offerWhere = '';
        if ($category != 0) {
            //$offerList = $this->getOfferList($category);        
            $offerWhere = " AND category_id =".$category;
        }
        $sql  = "select sum(num) as num from clickdata.offer_amount where time>=".$thisDayTime.$offerWhere;
        $row = $this->DB($sql);
        if (isset($row[0])) {
            $this->output(array('data'=>array('amount'=>$row[0]*10)));
        }


        $this->output(array('data'=>array('amount'=>0)));
    }

    public function getClickCountAction() {

		date_default_timezone_set('Asia/Shanghai');
        $OTime 	  = isset($_GET["otime"]) ? $_GET["otime"] : strtotime(date('Y-m-d',time()));
        $amount   = $conversion = 0;
        $after 	  = isset($_GET["time"]) ? $_GET["time"] : time();

        $start 	  = $OTime - $this->clickLen * 60;
        $end      = $OTime;
        $current  = time();
        $sql 	  = "select sum(num) as num from clickdata.offer_amount";
        $data     = array();
        $click    = $conver = 0;

       $category = isset($_GET["category"]) ? $_GET["category"] : 0;
        $offerWhere = '';
        if ($category != 0) {
            //$offerList = $this->getOfferList($category);        
            $offerWhere = " AND category_id =".$category;
        }

        for ($i=0; $i < 144; $i++) { 
        	
        	if ($end < $current) {
        		$queryClickSql  = $sql." where time >=".$start." and time<".$end.$offerWhere;
        		$click    = $this->DB($queryClickSql);

        		$data[$end] = array($click[0] * $this->realCount, 0);//$conver->num);

        		$start += $this->clickLen * 60;
        		$end   += $this->clickLen * 60;
        	
        	} else {

        		break;
        	}
        }

        $this->output($data);
    }

    public function DB($sql, $type = 'row') {

    	date_default_timezone_set('Asia/Shanghai');
        //$host = 'clickdata.cgs2bjzqxcxl.us-east-1.rds.amazonaws.com';
        //$user = 'clickdata';
        //$pass = 'tLD8gEbwTjOm6K';
        $host = '127.0.0.1';
        $user = 'root';
        $pass = '12354';
        $conn = mysql_connect($host, $user, $pass) or die(mysql_error());
        $result = mysql_query($sql);
        $return = '';

        $data = array();
        switch ($type) {
        	case 'row':
        		$return = mysql_fetch_row($result);
        		break;
        	
        	default:
        		while ($return = mysql_fetch_array($result, MYSQL_ASSOC)) {
                    $data[] = $return;
                }
                $return = $data;
        		break;
        }
    	mysql_close($conn);

    	return $return;
    }

}
