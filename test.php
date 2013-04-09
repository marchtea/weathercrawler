<?php 
//include
require("simple_html_dom.php");

//config
$cities = array(    //nmc   ,   weather
	'深圳' => array('nmc' => array('GD', 'shenzhen'),
				'weather' => '101280601', //http://www.weather.com.cn/weather/xxxxxxxxx.shtml?
				'qqpanel' => '01010715' //qq面板上各个城市的ID
			  ),
	'广州' => array('nmc' => array('GD', 'guangzhou'),
				'weather' => '101280101',
				'qqpanel' => '01010704'
			  ),
	'厦门' => array('nmc' => array('FJ', 'shamen'),
				'weather' => '101230201',
				'qqpanel' => '01010508'
			  ),
	'武汉' => array('nmc' => array('HB', 'wuhan'),
				'weather' => '101200101',
				'qqpanel' => '01011410'
			  ),
	'上海' => array('nmc' => array('SH', 'shanghai'),
				'weather' => '101020100',
				'qqpanel' => '01012601',
			  ),
	'杭州' => array('nmc' => array('ZJ', 'hangzhou'),
				'weather' => '101210101',
				'qqpanel' => '01013401'
			  ),
	'北京' => array('nmc' => array('BJ', 'beijing'),
				'weather' => '101010100',
				'qqpanel' => '01010101'
			),
	'沈阳' => array('nmc' => array('LN', 'shenyang'),
				'weather' => '101070101',
				'qqpanel' => '01011912'
			),
	'哈尔滨' => array('nmc' => array('HL', 'haerbin'),
				'weather' => '101050101',
				'qqpanel' => '01011303'
			),
	'大连' => array('nmc' => array('LN', 'dalian'),
				'weather' => '101070201',
				'qqpanel' => '01011904'
			),
	'西安' => array('nmc' => array('SN', 'xian'),
				'weather' => '101110101',
				'qqpanel' => '01012507'
			),
	'成都' => array('nmc' => array('SC', 'chengdu'),
				'weather' => '101270101',
				'qqpanel' => '01012703'
			  ),
	'拉萨' => array('nmc' => array('XZ', 'lasa'),
				'weather' => '101140101',
				'qqpanel' => '01013003'
			  )
);

$qqcode2weather = array(
	'00' => '晴',
	'01' => '多云',
	'02' => '阴',
	'03' => '阵雨',
	'04' => '雷阵雨',
	'05' => '冰雹',
	'06' => '雨夹雪',
	'07' => '小雨',
	'08' => '中雨',
	'09' => '大雨',
	'10' => '暴雨',
	'11' => '大暴雨',
	'12' => '特大暴雨',
	'13' => '阵雪',
	'14' => '小雪',
	'15' => '中雪',
	'16' => '大雪',
	'17' => '暴雪',
	'18' => '雾',
	'19' => '冻雨',
	'20' => '沙尘暴',
	'21' => '小雨-中雨',
	'22' => '中雨-大雨',
	'23' => '大雨-暴雨',
	'24' => '暴雨-大暴雨',
	'25' => '大暴雨-特大暴雨',
	'26' => '小雪-中雪',
	'27' => '中雪-大雪',
	'28' => '大雪-暴雪',
	'29' => '浮尘',
	'30' => '扬沙',
	'31' => '强沙尘暴',
	'32' => '颮',
	'33' => '龙卷风',
	'34' => '弱高吹雪',
	'35' => '轻雾'
);

$qqwinddirec = array(
	'0' => '无风',
	'1' => '东北风',
	'2' => '东风',
	'3' => '东南风',
	'4' => '南风',
	'5' => '西南风',
	'6' => '西风',
	'7' => '西北风',
	'8' => '北风',
	'9' => '旋转不定'
);


//start crawling
foreach($cities as $cityname => $city)
{
	echo "抓取:$cityname 状态:\n";
	$status = getWeather($city['weather']);	
	if ($status)
		showStatus($status);

	$nmc = getNmc($city['nmc'][0], $city['nmc'][1]);		
	if ($nmc)
		showStatus($nmc);
	$qq = getQQPanel($city['qqpanel']);
	if ($qq)
		showStatus($qq);
	print "\n";	
}

function getWeather($id)
{
	$add = "http://www.weather.com.cn/weather/$id.shtml";
	$nowaddr = "compress.zlib://http://www.weather.com.cn/data/sk/$id.html";
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
	$header = $sixharray->find('h1.weatheH1', 0);
	preg_match('/\d{4}-\d{2}-\d{2}/', superTrim($header->innertext), $foredate);
	preg_match('/\d{2}:\d{2}/', superTrim($header->innertext), $foretime);
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
		$wa = preg_split('/\s/', $w, -1, PREG_SPLIT_NO_EMPTY);
		$fs->wd = superTrim($wa[1]);
		$fs->ws = superTrim($wa[2]);

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
	$cs->foreTime = $foredate[0]." ".$foretime[0];
	$cs->nextsix = $forecast;
	return $cs;
}

function getNmc($prv, $city)
{
	$add = "compress.zlib://http://www.nmc.gov.cn/publish/forecast/A$prv/$city.html";
	$ifadd = "compress.zlib://http://www.nmc.gov.cn/publish/forecast/A$prv/{$city}_iframe.html";

	echo "获取中央气象台数据ing   ";
	$mainhtml = file_get_html($add);

	$ifm = file_get_html($ifadd);
	if ($ifm)
		echo "获取完毕\n";
	else{
		print "获取数据失败";
		return false;
	}

	//得到实时数据
	$scr = $ifm->find('script');
	$rjs = '';
	foreach($scr as $s)
	{
		if (strlen(trim($s->innertext)))
		{
			$rjs = $rjs.$s->innertext;
		}
	}
	preg_match("/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/", $rjs,  $matches); 
	$updateTime = $matches[0];
	$curstate = $ifm->find('div.city_wind div.temp_pic');
	foreach($curstate as $cur)
	{
		$texts = $cur->find('text');
		foreach($texts as $text)
		{
			if ($text->parent() == $cur){
				$tmp = trim(iconv("gbk", "utf8", $text));
				if (strlen($tmp)){
					$tmparray[] = $tmp;
				}
			}
		}
	}
	$curstate = new Status;
	$curstate->updateTime = $updateTime;
	$curstate->temp = $tmparray[0];
	$curstate->wt = $tmparray[3];
	$curstate->hu = $tmparray[4];
	$sr =  preg_split('/\s/', $tmparray[5],  -1, PREG_SPLIT_NO_EMPTY);
	$curstate->wd = $sr[0];
	$curstate->ws = $sr[1];
	//实时数据获取结束

	//得到6小时精细数据
	$foretable = $mainhtml->find('table#snwfd tr');
	//header
	preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $foretable[0]->innertext, $matches);
	$foreut = $matches[0];


	foreach($foretable as $t)
	{
		//pass header
		if ($t->id == 'snwfd_head')
			continue;
		$st = new Status;
		$tds = $t->find('td');
		preg_match('/\d{2}:\d{2}-\d{2}:\d{2}/', $tds[0]->innertext, $matches);
		$st->updateTime = $matches[0];
		$st->state = superIconv($tds[1]->find('text', 0)->plaintext);
		$st->max = superIconv($tds[2]->innertext);
		$st->min = superIconv($tds[3]->innertext);
		$st->wd = superIconv($tds[4]->innertext);
		$st->ws = superIconv($tds[5]->innertext);
		$st->wt = superIconv($tds[6]->innertext);

		$forearray[] = $st;
	}
	$cs = new CityStatus;
	$cs->current = $curstate;
	$cs->foreTime = $foreut;
	$cs->nextsix = $forearray;
	return $cs;

}

function getQQPanel($id)
{
	global $qqcode2weather;
	global $qqwinddirec;
	$addr = "http://weather.gtimg.cn/city/$id.js?_ref=";
	print "获取qq数据";
	$html = file_get_contents("compress.zlib://".$addr);//由于该页面采用了gzip压缩，因此需要修改url
	if (!$html)
	{
		print "网络连接失败";
		return false;
	}
	print "获取成功\n";
	preg_match('/\{[\s\S]+\};/', $html, $jsonarray);
	$jsonarray = substr($jsonarray[0], 0, strlen($jsonarray[0]) - 1);
	$jsonarray = iconv('gbk', 'utf8', $jsonarray);//json_decode只支持utf8编码...
	$curstate = json_decode($jsonarray);
	$st = new Status;
	$st->updateTime = $curstate->sk_rt;
	$st->state = $qqcode2weather[$curstate->sk_wt];
	$st->temp = $curstate->sk_tp;
	$st->hu = $curstate->sk_hd;
	$st->wd = $qqwinddirec[$curstate->sk_wd];
	if ($curstate->sk_wp == '0')
	{
		$st->ws = '微风';
	}else{
		$tmp = (int)$curstate->sk_wp;
		$st->ws = ''.($tmp+2).'-'.($tmp+3).'级';
	}
	$cs = new CityStatus;
	$cs->current = $st;

	return $cs;
}


function superIconv($str)
{
	return	iconv('gb2312', 'utf8', $str);
}

function superTrim($str)
{
	$str = trim($str);
	return	preg_replace('/\s+/', '', $str);
}

function showStatus($status)
{
	$cur = $status->current;
	print "实时天气: $cur->updateTime $cur->state $cur->temp 风向:$cur->wd 风速: $cur->ws 降水量: $cur->wt 湿度: $cur->hu\n";
	if ($status->foreTime)
	{
		print "6h: $status->foreTime \n";
		$fore = $status->nextsix;
		foreach($fore as $f)
		{
			print "($f->updateTime): $f->state $f->min~$f->max 风向: $f->wd 风速: $f->ws 降水: $f->wt 湿度: $f->hu\n";
		}
	}

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
