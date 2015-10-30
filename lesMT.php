<?php
	$str = file_get_contents('http://www.marinetraffic.com/en/ais/details/ships/shipid:313477/mmsi:259094000/vessel:SELBJORNSFJORD');
	$pos = strpos ($str, 'details_data_link">'); 
	$ak = substr($str, $pos+19);
	$ak = str_replace("&deg;" ,"", $ak);
	$ak = str_replace("<" ,"", $ak);
	$bit = explode("/", $ak);
	echo "{";
	echo '"lat" : ' . $bit[0] . ', "lng" : ' . $bit[1].'}';
?>