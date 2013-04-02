<?php
require('simple_html_dom.php');

//to get weather.com.cn

//add comment again
/*
$id = '101010100';
$add = "http://www.weather.com.cn/weather/$id.shtml";
$nowaddr = "http://www.weather.com.cn/data/sk/$id.html";
$html = file_get_html($add);
echo "get html\n";
$curstate = file_get_contents($nowaddr);
echo "get current state\n";


//6小时精细化预报
$sixharray = $html->find('div#weather6h');
foreach($sixharray as $sixh)
{
    //每6小时的数据
    $tables = $sixh->find('table');
    foreach($tables as $table)
    {
	$to = $table->find('tr th');
	$rtime = $to[0]->innertext;//发布时间
	$tmp = $table->find('tr td a');//获取温度信息以及风向信息
	$st = $tmp[1]->innertext;
	$h = $tmp[2]->innertext;
	$l = $tmp[3]->innertext;
	$w = $tmp[4]->innertext;
	
	echo "time: $rtime, 状态: $st, 温度: $l~$h, other:$w\n";
		
    }
}
echo $curstate;
$cs = json_decode($curstate);

*/

//get nmc
$prv = "GD";
$city = "shenzhen";
$add = "http://www.nmc.gov.cn/publish/forecast/A$prv/$city.html";
$ifadd = "http://www.nmc.gov.cn/publish/forecast/A$prv/{$city}_iframe.html";
echo $add."\t".$ifadd."\n";

echo "before get main\n";
$mainhtml = file_get_html($add);
echo "get main\n";

$ifm = file_get_html($ifadd);
echo "get iframe\n";

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
echo preg_match("/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/", $rjs,  $matches); 
$updateTime = $matches[0];
echo "update time: ".$updateTime."\n";
$curstate = $ifm->find('div.city_wind div.temp_pic');
foreach($curstate as $cur)
{
	$texts = $cur->find('text');
	foreach($texts as $text)
	{
	    if ($text->parent() == $cur){
		$tmp = trim(iconv("gbk", "utf8", $text));
		if (strlen($tmp))
		   echo $tmp."\n";
	    }
	}
/*
	foreach($cur->children() as $child)
	{
		echo gettype($child)." ".iconv("gbk", "utf8", $child->innertext)."\n";
	}
*/
}

//得到6小时精细数据
$foretable = $mainhtml->find('table#snwfd tr');
//header
preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $foretable[0].innertext, $matches);
$updateTime = $matches[0];
echo "update time: $updateTime\n";


foreach($foretable as $t)
{
	//pass header
	if ($t->id == 'snwfd_head')
		continue;
	$tds = $t->find('td');
	preg_match('/\d{2}:\d{2}-\d{2}:\d{2}/', $tds[0]->innertext, $matches);
	$timerange = $matches[0];
	$status = $tds[1]->find('text', 0);
	$max = $tds[2]->innertext;
	$min = $tds[3]->innertext;
	$wd = $tds[4]->innertext;
	$ws = $tds[5]->innertext;
	$wt = $tds[6]->innertext;

	echo iconv("gb2312", "utf8", "($timerange): $status, $min~$max, $wd, $ws, $wt\n");
}


?>
