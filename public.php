<?php

class CityStatus
{
	public $name;
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

//config
$dbaddr = '127.0.0.1';
$dbusr = 'summer';
$dbpwd = '';
$dbschema = 'weather';
$charset = 'utf8';

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
			),
	'银川' => array('nmc' => array('NX', 'yinchuan'),
				'weather' => '101170101',
				'qqpanel' => '01012104'
			 ),
	'太原' => array('nmc' => array('SX', 'taiyuan'),
				'weather' => '101100101',
				'qqpanel' => '01012408'
			),
	'兰州' => array('nmc' => array('GS', 'lanzhou'),
				'weather' => '101160101',
				'qqpanel' => '01010607'
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

$weathercatalog = array(
	'晴' => array(0),
	'多云' => array(1),
	'阴' => array(2),
	'阵雨' => array(3, 0),
	'雷阵雨' => array(3, 1),
	'小雨' => array(3, 2),
	'中雨' => array(3, 3),
	'大雨' => array(3, 4),
	'暴雨' => array(3, 5),
	'大暴雨' => array(3, 6),
	'特大暴雨' => array(3, 7),
	'小雨-中雨' => array(3, 2),
	'中雨-大雨' => array(3, 3),
	'大雨-暴雨' => array(3, 4),
	'暴雨-大暴雨' => array(3, 5),
	'大暴雨-特大暴雨' => array(3, 6),
	'雨夹雪' => array(4, 0),
	'冻雨' => array(4, 1),
	'阵雪' => array(4, 2),
	'小雪' => array(4, 3),
	'中雪' => array(4, 4),
	'大雪' => array(4, 5),
	'暴雪' => array(4, 6),
	'小雪-中雪' => array(4, 2),
	'中雪-大雪' => array(4, 3),
	'大雪-暴雪' => array(4, 4),
	'雾' => array(5),
	'冰雹' => array(6),
	'浮尘' => array(7, 0),
	'扬沙' => array(7, 1),
	'沙尘暴' => array(7, 2),
	'强沙尘暴' => array(7, 3)
);

$qqwinddirec = array(
	'0' => '无持续风向',
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

$winddirec = array(
	'无持续风向' =>0,
	'东北风' =>1,
	'东风' =>2,
	'东南风' =>3,
	'南风' => 4,
	'西南风' =>5,
	'西风' =>6,
	'西北风' =>7,
	'北风' =>8,
	'旋转不定' =>9
);



?>
