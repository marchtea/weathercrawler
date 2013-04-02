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

var_dump($cities);

foreach($cities as $cityname => $city)
{
	echo $cityname."状态:\n";

		
}





?> 
