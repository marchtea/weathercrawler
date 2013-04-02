<?php 
//include
require("simple_html_dom.php");

//config
$cities = array(    //nmc   ,   weather
	'深圳' => array('nmc' => array('GD', 'shenzhen'),
				'weather' => '101280601' //http://www.weather.com.cn/weather/xxxxxxxxx.shtml?
			  ),
	'厦门' => array('nmc' => array('FJ', 'shamen'),
				'weather' => '101230201'
			  ),
	'北京' => array('nmc' => array('BJ', 'beijing'),
				'weather' => '101010100'
			  )
);

foreach($cities as $cityname => $city)
{
	echo "抓取:$cityname 状态:\n";
	$status = getWeather($city['weather']);	
		
}

function getWeather($id)
{
	$add = "http://www.weather.com.cn/weather/$id.shtml";
	$nowaddr = "http://www.weather.com.cn/data/sk/$id.html";
	echo '获取网页ing..';
	$html = file_get_html($add);
	$curstate = file_get_contents($nowaddr);
	echo "获取完毕\n";
	if (!$html || !$curstate)
	{
		echo "获取网页失败";
		return false;
	}


	//6小时精细化预报
	$sixharray = $html->find('div#weather6h', 0);
	$header = $sixharray->find('h1#weatheH1', 0);
	preg_match('/\d{4}-\d{2}-\d{2}/', $header->innertext, $foredate);
	preg_match('/\d{2}\s+:\d{2}/', $header->innertext, $foretime);
		//每6小时的数据
	$tables = $sixharray->find('table');
	$forecast = array();
	foreach($tables as $table)
	{
		$fs = new Status;
		$to = $table->find('tr th');
		$fs->updateTime =superTrim($to[0]->innertext);//发布时间
		$tmp = $table->find('tr td a');//获取温度信息以及风向信息
		$fs->state = superTrim($tmp[1]->innertext);
		$fs->max = superTrim($tmp[2]->innertext);
		$fs->min = superTrim($tmp[3]->innertext);
		$w = $tmp[4]->innertext;
		$wa = preg_split('/\n/', $w);
		$fs->wd = superTrim($wa[0]);
		$fs->ws = superTrim($wa[1]);

		//echo "time: $fs->updateTime, 状态: $fs->state, 温度: $fs->min~$fs->max, wind: $fs->wd  $fs->ws\n";
		$forecast[] = $fs;
	}
	$current = json_decode($curstate);
	$currentStatus = new Status;
	$currentStatus->updateTime = $current->weatherinfo->time;
	$currentStatus->temp = $current->weatherinfo->temp;
	$currentStatus->wd = $current->weatherinfo->WD;
	$currentStatus->ws = $current->weatherinfo->WS;
	$currentStatus->hu = $current->weatherinfo->SD;
	$cs = new CityStatus;
	$cs->current = $currentStatus;
	$cs->foreTime = $foredate.$foretime;
	$cs->nextsix = $forecast;
	return $cs;
}

function getNmc($prv, $city)
{

}


function superTrim($str)
{
	$str = trim($str);
	return	preg_replace('/\s+/', '', $str);
}

class CityStatus
{
	public $current;
	public $foreTime; //预测发布的时间
	public $nextsix;
}


class Status
{
	public $updateTime;
	public $state;
	public $temp;
	public $min;
	public $max;
	public $wd; //风向
	public $ws;//风速
	public $wt;//降水量
	public $hu;//湿度
}




?> 
