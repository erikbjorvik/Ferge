<?php
require_once("FergeData.php");
$fd = new FergeData();

error_reporting(0);
$api_key = "123";
$api_keyIn = $_GET['key'];

$status = ($api_key == $api_keyIn ? 1 : 0);

$jsonRespons = '{"status" : '. $status;



if (1) {

	switch($_GET['request']) {

		case "nesteAvgang":
			$ar = $fd->nesteAvgang($_GET['rute'], $_GET['dato'], $_GET['tid']);

			$jsonRespons.= ', "avgang" : { 
				"avgangId" : '.$ar[0].', 
				"avgangTid" : "'.$ar[1].'",
				"avgangNotat" : "'.$ar[2].'",
				"fraDestinasjon" : '.$ar[3].',
				"tilDestinasjon" : '.$ar[4].', 
				"sambandInit" : '.$ar[5] . ',
				"dagId" : ' . $ar[6] . ',
				"vei" : "' . $ar[7] . '", 
				"rutenavn" : "'.$ar[8].'",
				"lat" : "'.$ar[9].'",
				"lng" : "'.$ar[10].'",
				"zoom" : "'.$ar[11].'",
				"dato" : "'.$ar[15].'"
				}';

		break;

		case "fartoyIRute":
			$f = $fd->fartoyForRute($_GET['rute']);
			$jsonRespons.= ', "fartoy" : ['; 

			$i = 0;
			foreach ($f as $fa) {
				$l = $fd->getFartoyLokasjon($fa[0]);
				$jsonRespons.= '{"id" : '.$fa[0].',
					"navn" : "'.$fa[1].'",
					"pasKap" : '.$fa[2].',
					"bilKap" : '.$fa[3].',
					"maxHoyde" : '.$fa[4].',
					"handicap" : '.$fa[5].',
					"kafeteria" : '.$fa[6].',
					"wifi" : '.$fa[7].',
					"marineTraffic" : "'.$fa[8].'",
					"lat" : '.$l[0].',
					"lng" : '.$l[1].'
					}';
				if ($i<(count($f)-1))
					$jsonRespons.= ",";

				$i++;

				//fa[1] . ", lokasjon: " . $l[0]."/".$l[1] ."}";
			}
			$jsonRespons.=']';

		break;

		case "avgangerDag":
			$s = $fd->avgangerDag($_GET['rute'], $_GET['dato']);
			$jsonRespons.= ', "avganger" : [';

			$i = 0;
			foreach ($s as $ar) {
				$jsonRespons.='{ 
				"avgangId" : '.$ar[0].', 
				"avgangTid" : "'.$ar[1].'",
				"avgangNotat" : "'.$ar[2].'",
				"dagId" : ' . $ar[6] . '
				}';

				if ($i<(count($s)-1))
					$jsonRespons.= ",";

				$i++;

			}

			$jsonRespons.=']'; 

		break;

	}

}

$jsonRespons.= '}';

echo $jsonRespons;

?>