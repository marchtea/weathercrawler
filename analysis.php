<?php
require("public.php");

//config
//percentage of elements
$pertemp = 0.3;//temperature
$perstat = 0.5;//status
$perhum = 0.1;//humidity
$perwd = 0.05;//wind direction
$perws = 0.05;//wind speed 

$db;

if (!dbInit())//init db
{
	die();
}

$qqresult = getLatestQQResult();
foreach($qqresult as $citystatus)
{
	getWeatherResult($citystatus);
	getNmcResult($citystatus);
	calcScore($citystatus);
}

foreach($qqresult as $result)
{
	print $result->name.": score: $result->score isValid: $result->isValid";
	if (!$result->isValid)
		print " $result->error";
	print "\n";
	$qq = $result->current['qq'];
	$weather = $result->current['weather'];
	$nmc = $result->current['nmc'];
	print "\t\tqq: ($qq->updateTime) $qq->state $qq->temp $qq->hu $qq->wd $qq->ws\n";
	print "\t\tweather:($weather->updateTime) $weather->state $weather->temp $weather->hu $weather->wd $weather->ws\n";
	print "\t\tnmc:($nmc->updateTime)  $nmc->state $nmc->temp $nmc->hu $nmc->wd $nmc->ws\n";
	print "\n";
}


function getLatestQQResult()
{
	global $db;
	$result = $db->query("select * from (select * from current where source=3 order by updateTime desc) tmp group by city");
	if (!$result)
	{
		print "query error $db->error\n";
		return false;
	}

	while($row = $result->fetch_row())
	{
		$ct = new CityStatus;
		$ct->name = $row[0];
		$ct->current['qq'] = json_decode($row[3]);
		$qqresult[] = $ct;
	}
	return $qqresult;
}

function getWeatherResult($citystatus)
{
	global $db;
	$result = $db->query("select * from current where source=1 and city='$citystatus->name' and updateTime <= addtime('{$citystatus->current['qq']->updateTime}','00:30:00') order by updateTime desc limit 0,1");
	while($row = $result->fetch_row())
	{
		$citystatus->current['weather'] = json_decode($row[3]);
	}

	$result = $db->query("select * from forecast where source=1 and city='$citystatus->name' and updateTime <= '{$citystatus->current['weather']->updateTime}' order by updateTime desc limit 0,1");
	if (!$result)
	{
		print "query error $db->error\n";
		return false;
	}

	while($row = $result->fetch_row())
	{
		$citystatus->updateTime['weather'] = $row[1];
		$citystatus->forecast['weather'] = json_decode($row[3]);
	}
	getState($citystatus->current['weather'], $citystatus->updateTime['weather'], $citystatus->forecast['weather']);
}

function getNmcResult($citystatus)
{
	global $db;
	$result = $db->query("select * from current where source=2 and city='$citystatus->name' and updateTime <= addtime('{$citystatus->current['qq']->updateTime}', '00:30:00') order by updateTime desc limit 0,1");
	while($row = $result->fetch_row())
	{
		$citystatus->current['nmc'] = json_decode($row[3]);
	}
	
	$result = $db->query("select * from forecast where source=2 and city='$citystatus->name' and updateTime <= '{$citystatus->current['weather']->updateTime}' order by updateTime desc limit 0,1");
	if (!$result)
	{
		print "query error $db->error\n";
		return false;
	}

	while($row = $result->fetch_row())
	{
		$citystatus->updateTime['nmc'] = $row[1];
		$citystatus->forecast['nmc'] = json_decode($row[3]);
	}

	getState($citystatus->current['nmc'], $citystatus->updateTime['nmc'], $citystatus->forecast['nmc']);
}

function calcScore($citystatus)
{
	global $pertemp;
	global $perhum;
	global $perwd;
	global $perws;
	global $perstat;

	$citystatus->isValid = true;
	$citystatus->score = 0;
	$citystatus->error = '';
	$qq = $citystatus->current['qq'];	
	$weather = $citystatus->current['weather'];
	$nmc = $citystatus->current['nmc'];

	$useweather = true;
	$usenmc = true;
	if (abs(strtotime($qq->updateTime) - strtotime($weather->updateTime)) >= 3*3600)//二者数据误差超过三个小时，没有参考价值
	{
		$useweather = false;
	}
	if (abs(strtotime($qq->updateTime) - strtotime($nmc->updateTime)) >= 3*3600)//二者数据误差超过三个小时，没有参考价值
	{
		$usenmc = false;
	}
	if (!$useweather && !$usenmc)
		return;

	//temperature
	$tempscore = 0;
	if ($useweather)
	{
		preg_match('/\d+/', $weather->temp, $tempa);
		$temp = (int)$tempa[0];
		$qqtemp = (int)$qq->temp;
		$res = calcTempScore($temp, $qqtemp);
		if ($res === false)
		{
			$citystatus->isValid = false;
			$citystatus->error = "温度温差过大: weather.com.cn:$temp, qq:$qqtemp";
		}else{
			$tempscore += $res;
		}
	}		
	
	if ($usenmc)
	{
		$temp = (int)$nmc->temp;
		$res = calcTempScore((int)$nmc->temp, (int)$qq->temp);
		if ($res === false)
		{
			$citystatus->isValid = false;
			$citystatus->error .= "  温度温差过大: weather.com.cn:$temp, qq:$qqtemp";
		}else{
			$tempscore += $res;
		}
	}
	if (!$citystatus->isValid)
	{
		return;
	}

	if ($useweather && $usenmc)
	{
		$citystatus->score += (double)$pertemp*$tempscore/2;
	}else{
		$citystatus->score += $pertemp*$tempscore;
	}
	//temperature done

	//humidity
	$tempscore = 0;
	if ($useweather)
	{
		preg_match('/\d+/', $weather->hu, $hua);
		$hu = (double)$hua[0];
		$res = calcHumidScore($hu, (double)$qq->hu);
		$tempscore += $res;
	}

	if ($usenmc)
	{
		preg_match('/\d+/', $nmc->hu, $hua);
		$hu = (double)$hua[0];
		$res = calcHumidScore($hu, (double)$qq->hu);
		$tempscore += $res;
	}
	if ($useweather && $usenmc)
	{
		$citystatus->score += (double)$perhum*$tempscore/2;
	}else{
		$citystatus->score += $perhum*$tempscore;
	}
	//humidity done

	//wind direction
	$tempscore = 0;
	if ($useweather)
	{
		$res = calcWindDirec($weather->wd, $qq->wd);	
		if ($res === false)
		{
			//未知风向
		}else{
			$tempscore += $res;
		}
	}	
	if ($usenmc)
	{
		$res = calcWindDirec($nmc->wd, $qq->wd);	
		if ($res === false)
		{
			//未知风向
		}else{
			$tempscore += $res;
		}
	}	
	if ($useweather && $usenmc)
	{
		$citystatus->score += (double)$perwd*$tempscore/2;
	}else{
		$citystatus->score += $perwd*$tempscore;
	}
	//humidity done
	
	//wind speed 
	$tempscore = 0;
	if ($useweather)
	{
		$res = calcWindSpd($weather->ws, $qq->ws);	
		$tempscore += $res;
	}	
	if ($usenmc)
	{
		$res = calcWindSpd($nmc->ws, $qq->ws);	
		$tempscore += $res;
	}	
	if ($useweather && $usenmc)
	{
		$citystatus->score += (double)$perws*$tempscore/2;
	}else{
		$citystatus->score += $perws*$tempscore;
	}
	//wind spped done
	
	//state
	$tempscore = 0;
	if ($useweather)
	{
		$res = calcStateScore($weather->state, $qq->state);	
		if ($res === false)
		{
			$citystatus->isValid = false;
			$citystatus->error = "预报状态有误: weather.com.cn:$weather->state, qq: $qq->state";
		}else{
			$tempscore += $res;
		}
	}	
	if ($usenmc)
	{
		$res = calcStateScore($nmc->state, $qq->state);	
		if ($res === false)
		{
			$citystatus->isValid = false;
			$citystatus->error .= "  预报状态有误: nmc: $nmc->state, qq: $qq->state";
		}else{
			$tempscore += $res;
		}
	}	
	if (!$citystatus->isValid)
	{
		return;
	}
	if ($useweather && $usenmc)
	{
		$citystatus->score += (double)$perstat*$tempscore/2;
	}else{
		$citystatus->score += $perstat*$tempscore;
	}
	//state done
}

//input: (int)tempa (int)tempb
//false代表数据有问题
function calcTempScore($tempa, $tempb)
{
	$diff = abs($tempa - $tempb);
	if ($diff == 0)
		return 0;
	if ($diff == 1)
		return 1;
	if ($diff <= 3)
		return 2;
	if ($diff <= 5)
		return 3;
	return false;
}

//input: (double)hua (double)hub
function calcHumidScore($hua, $hub)
{
	$diff = abs($hua - $hub);
	if ($diff <= 1)
		return 0;
	if ($diff <= 3)
		return 1;
	if ($diff <= 5)
		return 2;
	return 3;
}

//input: (string)wd*
function calcWindDirec($wda, $wdb)
{
	global $winddirec;
	if (array_key_exists($wda, $winddirec))
		$wdaa = $winddirec[$wda];
	else
	{
		print "风向不存在：$wda\n";
		return false;	
	}
	if (array_key_exists($wdb, $winddirec))
		$wdbb = $winddirec[$wdb];
	else{
		print "风向不存在: $wdb\n";
		return false;
	}

	$diff = $wdaa - $wdbb;
	if ($diff < -4)
		$diff += 8;
	if ($diff > 4)
		$diff = 8 - $diff;

	if ($diff < 3)
		return $diff;
	return 3;
}

function calcWindSpd($spda, $spdb)
{
	$spdaa = translateWs($spda);
	$spdbb = translateWs($spdb);	

	$diff = abs($spdaa - $spdbb);
	if ($diff < 3)
		return $diff;
	return 3;
}

function translateWs($spd)
{
	if ($spd == '微风') 
		return 0;
	preg_match_all('/\d+/', $spd, $spa);

	$sp = (int)$spa[0][0];
	if ($sp < 3)
		return 0;
	return $sp-2;
}

//温度以及降水量都为参考量
function calcStateScore($sta, $stb)
{
	global $weathercatalog;
	if (array_key_exists($sta, $weathercatalog))
	{
		$staa = $weathercatalog[$sta];	
	}else{
		print "天气现象不存在: $sta\n";
		return false;
	}

	if (array_key_exists($stb, $weathercatalog))
	{
		$stbb = $weathercatalog[$stb];
	}else{
		print "天气现象不存在: $stb\n";
		return false;
	}

	//统一化
	if ($stbb[0] < $staa[0] || 
		($stbb[0] == $staa[0] && count($staa) == 2 && $stbb[1] < $staa[1])
    )
	{
		$tmp = $staa;
		$staa = $stbb;
		$stbb = $tmp;
	}

	//两者天气情况一样
	if ($staa[0] == $stbb[0])
	{
		if (count($staa) == 1 || $staa[1] == $stbb[1])
			return 0;
	}
	//如果一个是晴天，多云
	//另一个是雨，雪，冰雹，则上报
	if (
		($staa[0] == 0 || $staa[0] == 1) &&
		($stbb[0] == 3 || $stbb[0] == 4 || $stbb[0] == 6)
	){
		return false;
	}
	//如果一个是阴天，另一个下冰雹了
	if ($staa[0] == 2 && $stbb[0] == 6)
	{
		return false;
	}
	//一个下雪天一个雷阵雨
	if ($staa[0] == 3 && $staa[1] == 1 && $stbb[0] == 4)
		return false;
	
	//雨天、雪起沙尘暴
	if (($staa[0] == 3||$stbb[0] == 4) && $stbb[0] == 7)
		return false;

	//雾天有沙尘暴
	if ($staa[0] == 5 && $stbb[0] == 7)
		return false;
	//冰雹天有沙尘暴
	if ($staa[0] == 6 && $stbb[0] == 7)
		return false;

	switch($staa[0])
	{
	case 0://晴天
		if ($stbb[0] == 1)//多云
			return 1;
		if ($stbb[0] == 2 || $stbb[0] == 5)//阴,雾
			return 2;
		if ($stbb[0] == 7)//沙尘暴
			return 3;
	case 1://多云
		if ($stbb[0] == 2 || $stbb[0] == 5)//阴
			return 2;
		if ($stbb[0] == 7)
			return 3;
	case 2://阴
		if ($stbb[0] == 3)//雨天的情况
		{
			if ($stbb[1] <= 2)//如果是阵雨或者小雨
				return 1;
			if ($stbb[1] <= 4)//中雨，大雨
				return 2;
			return 3;//大雨以上

		}
		if ($stbb[0] == 4)//雪天
		{
			if ($stbb[1] <= 3)//小雪或者阵雪或者雨夹雪
				return 1;
			if ($stbb[1] <= 4)
				return 2;
			return 3;
		}
		if ($stbb[0] == 5)//雾
			return 2;
		if ($stbb[0] == 7)
			return 3;
	case 3://雨天
		if ($stbb[0] == 3)//都为雨天的情况
		{
			$diff = $stbb[1] - $staa[1];
			if ($staa[1] < 2)//阵雨情况
				return 1;
			if ($diff < 3)
				return $diff;
			return 3;
		}
		if ($stbb[0] == 4)//雪天
		{
			if ($staa[1] == 0 && $stbb[1] < 4)//一边阵雨一边小雪一下是有可能的
				return 1;
			if ($staa[1] == 0)//一边阵雨一边中雪以上就不正常了
				return 3;
			if ($staa[1] == 2 && $stbb[1] == 2)//小雪和小雨也是可能的
				return 1;
			//一边中雨一边中雪就不大可能了
			return 3;
		}
		if ($stbb[0] == 5)//雾
		{
			//下雨天同时又是雾天,认为有问题
			return 3;
		}
		if ($stbb[0] == 6)//冰雹
			return 3;
	case 4://雪天
		if ($stbb[0] == 4)//都为雪天
		{
			$diff = $stbb[1] - $staa[1];
			if ($staa[1] == 0 && $diff <=3)//雨夹雪与小雪之类差别不大)
				return 1;
			if ($staa[1] == 0)//与剩下的来说就不大可能了
				return 3;
			if ($staa[1] == 1 && $diff <= 2)//冻雨小雪差别也不大
				return 1;
			if ($staa[1] == 1)//与剩下来说也不大可能
				return 3;
			if ($staa[1] == 2 && $diff <= 2)//阵雪与中雪相比还是有可能的
				return 1;
			if ($staa[1] == 2)//与剩下差别较大
				return 2;
			return $diff;//剩下就是小雪，中雪，大雪，暴雪之间的区别了
		}
		//雪天与雾天
		if ($stbb[0] == 5)
		{
			return 2;
		}
		//雪天下冰雹
		if ($stbb[0] == 6)
		{
			return 2;
		}
	case 5://雾
		if ($stbb[0] == 6)//冰雹
			return 3;	
	case 7://沙尘暴
		$diff = $stbb[1] - $staa[1];
		return $diff;
	}	


	echo "error: $sta $stb\n";
	return 3;
}


//补全状态
function getState($current, $foreTime, $forecast)
{
	$ct = $current->updateTime;
	$rt = str2sec(date('H:i', strtotime($ct)));
	if ((int)date('z', strtotime($ct)) > (int)date('z', strtotime($foreTime)))
	{
		//超过一天了
		$rt += 3600*24;
	}
	$daypass = false;
	foreach($forecast as $f)
	{
		$t = $f->updateTime;
		preg_match_all('/\d{2}:\d{2}/', $t, $tr);
		$ta[0] = str2sec($tr[0][0]);
		$ta[1] = str2sec($tr[0][1]);
		if ($daypass)
		{
			$ta[0] += 3600*24;
			$ta[1] += 3600*24;
		}
		if ($ta[1] < $ta[0])//跨过一天了
		{
			$daypass = true;
			$ta[1] += 3600*24;
		}
		if ($rt <= $ta[1]) //如果比最小的都小,则用这个
		{
			$current->state = $f->state;	
			break;
		}
	}	
}


function str2sec($str)
{
	$ta = explode(':', $str);
	if (count($ta) == 2)
		return 60*60*(int)$ta[0]+60*(int)$ta[1];
	else
		return false;
}

function dbInit()
{
	global $db;
	global $dbaddr;
	global $dbusr;
	global $dbpwd;
	global $dbschema;
	global $charset;
	$db = new mysqli($dbaddr, $dbusr, $dbpwd, $dbschema);
	if ($db)
	{
		$db->set_charset($charset);
		return true;
	}
	return false;
	
}

?>
