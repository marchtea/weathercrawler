<?php 
//include
require("simple_html_dom.php");
require("public.php");


$start = microtime(true);
//start crawling
foreach($cities as $cityname => $city)
{
	echo "抓取:$cityname 状态:\n";
	$status = getWeather($city['weather']);	
	if ($status)
	{
		showStatus($status);
		save2DB($cityname, $status, 'weather');
	}

	$nmc = getNmc($city['nmc'][0], $city['nmc'][1]);		
	if ($nmc)
	{
		showStatus($nmc);
		save2DB($cityname, $nmc, 'nmc');
	}
	$qq = getQQPanel($city['qqpanel']);
	if ($qq)
	{
		showStatus($qq);
		save2DB($cityname, $qq, 'qq');
	}
	print "\n";	
}
$end = microtime(true);
echo "运行时间: ".($end-$start)."s\n";

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
	//$curstate = superIconv($curstate);


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
		$w = trim($tmp[4]->innertext);

		$wa = preg_split('/\s/', $w, -1, PREG_SPLIT_NO_EMPTY);
		if (strlen($wa[0]) == 2)
		{
			$fs->wd = superTrim($wa[1]);
			$fs->ws = superTrim($wa[2]);
		}else{
			$fs->wd = superTrim($wa[0]);
			$fs->ws = superTrim($wa[1]);
		}

		//echo "time: $fs->updateTime, 状态: $fs->state, 温度: $fs->min~$fs->max, wind: $fs->wd  $fs->ws\n";
		$forecast[] = $fs;
	}
	$current = json_decode($curstate);
	//修正时间
	$nh = date('H');
	$ta =  preg_split('/:/', $current->weatherinfo->time);
	if ((int)$ta[0] > (int)$nh)
	{
		$current->weatherinfo->time = date('Y-m-d H:i', time() - ((int)$nh+24-(int)$ta[0])*60*60);	
	}else{//同一天
		$current->weatherinfo->time = date('Y-m-d').' '.$current->weatherinfo->time;
	}

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

function save2DB($city, $citystatus, $src)
{
	global $dbaddr;
	global $dbusr;
	global $dbpwd;
	global $dbschema;
	global $charset;
	$db = new mysqli($dbaddr, $dbusr, $dbpwd, $dbschema);
	$db->set_charset($charset);
	$db->autocommit(true);

	if (strcmp($src, 'nmc') == 0)
	{
		$source = 1;
	}else if (strcmp($src,'weather') == 0)
	{
		$source = 2;
	}else if (strcmp($src ,'qq') == 0){
		$source = 3;
	}else{
		return false;
	}

	save2Current($db, $city, $source, $citystatus->current);
	save2Forecast($db, $city, $source, $citystatus->foreTime, $citystatus->nextsix);
	$db->close();
	return true;
}

function save2Current($db, $city, $source,  $current)
{
	$stmt = $db->prepare("REPLACE INTO current(city, updateTime, source, result) VALUES(?, ?, ?, ?)");
	if (!$stmt)
	{
		echo $stmt->error."\n";
		return false;
	}

	$stmt->bind_param('ssis', $city, $current->updateTime, $source, json_encode($current));
	$stmt->execute();
	if ($stmt->affected_rows)
	{
		$stmt->close();
		return true;
	}
	echo "current: false".$stmt->error."\n";
	return false;
}

function save2Forecast($db, $city, $source,$updateTime, $forecast)
{
	$stmt = $db->prepare("REPLACE INTO forecast(city, updateTime, source, result) VALUES(?, ?, ?, ?)");
	if (!$stmt)
	{
		return false;
	}

	$stmt->bind_param('ssis', $city, $updateTime, $source, json_encode($forecast));

	$stmt->execute();
	if ($stmt->affected_rows)
	{
		$stmt->close();
		return true;
	}
	return false;
}






?> 
