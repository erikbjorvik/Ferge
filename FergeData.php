<?php

class FergeData {
	
	private $db;

	function __construct() {
		$this->db = new mysqli("localhost","root","","fergetider");
	}

	/**
	*
	* Returnerer true dersom rute-iden finnes og ruten er aktiv
	*
	*/
	function finnesRuteId($id) {
		$stmt = $this->db->prepare("SELECT id FROM Ruter WHERE id=? AND aktiv='1'");
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$stmt->store_result();

		if ($stmt->num_rows>0)
			return true;
		else 
			return false;
	}

	/**
	*
	*
	* Returnerer hvor mange samband det er i gitt rute.
	*
	*
	*/
	function antallSambandIRute($ruteId) {

		$stmt = $this->db->prepare("SELECT COUNT(id) FROM Samband WHERE id=?");
		$stmt->bind_param("i", $ruteId);
		$stmt->execute();
		$stmt->bind_result($antall);
		$stmt->fetch();

		return $antall;

	}
	/**
	*
	* Bruker ruten den gitte dagsiden?
	*
	* 
	*
	* ruteId: 1 -> Ordinær
	* ruteId: 2 -> Lørdag
	* ruteId: 3 -> Søndag
	*
	*/
	function erDetDagsIdIRute($dagsId, $ruteId) {
		$stmt = $this->db->prepare("SELECT COUNT(Avganger.dagType) FROM Avganger 
									INNER JOIN Samband ON Avganger.samband = Samband.id
									WHERE Avganger.dagType=? AND Samband.ruteId=?");
		$stmt->bind_param("ii", $dagsId, $ruteId);
		$stmt->execute();
		$stmt->bind_result($antall);
		$stmt->fetch();

		return ($antall>0);
	}

	/**
	*
	* Sjekker om det er spesialdag (i så fall hvilken) på
	* gitt rute og gitt dato.
	*
	* Skal ikke brukes på ruteId 1-3. Her er dato=NULL
	*
	*/
	function spesialDagRute($ruteId, $dato) {
		$stmt = $this->db->prepare("SELECT DISTINCT Avganger.dagType FROM Avganger
									INNER JOIN Samband ON Avganger.samband = Samband.id
									INNER JOIN Dagstyper ON Avganger.dagType = Dagstyper.id
									WHERE Dagstyper.dato=? AND Samband.ruteId=?");

		$stmt->bind_param("si", $dato, $ruteId);
		$stmt->execute();
		$stmt->bind_result($spesDag);
		$stmt->fetch();

		return $spesDag;
	}

	/**
	*
	* Returnerer avgangsdata for gitt rute og dag. 
	* Se hvilke data som returneres i whileløkken.
	*
	*/
	function hentRutetider($ruteId, $dagId, $fraTid='00:00:00', $bareAktive=1) {

		$stmt = $this->db->prepare("SELECT Ruter.ruteNavn AS Rutenavn, Ruter.vei AS Vei, Ruter.id AS RuteID, Ruter.lat AS lat, Ruter.lng AS lng,
							Ruter.zoom, Ruter.op, Ruter.op_wb, Ruter.overfartstid, 
							Samband.fra AS FraDestinasjonID, Samband.til AS TilDestinasjonID, Samband.init AS SambandInit, 
							Avganger.dagType AS dagId, Avganger.id AS AvgangID, Avganger.tid AS Avgang, Avganger.notat AS notat
							FROM Ruter INNER JOIN Samband
							ON Ruter.id = Samband.ruteId
							INNER JOIN Avganger
							ON Avganger.samband = Samband.id
							WHERE Ruter.id=? AND Avganger.dagType=? AND Avganger.tid>=? AND Ruter.aktiv=? 
							ORDER BY Ruter.id ASC, Avganger.tid ASC");
		
		$stmt->bind_param("iisi", $ruteId, $dagId, $fraTid, $bareAktive);
		$stmt->execute();
		$stmt->bind_result($rutenavn, $vei, $ruteId, $lat, $long, $zoom, $operator, $operator_web, $overfartstid, $fraDestinasjonId, $tilDestinasjonId, $sambandInit, $dagId, $avgangId, $avgangTid, $avgangNotat);

		$r = array();
		$i = 0;

		while ($stmt->fetch()) {
			$r[$i][0] = $avgangId;
			$r[$i][1] = $avgangTid;
			$r[$i][2] = utf8_encode($avgangNotat);
			$r[$i][3] = $fraDestinasjonId;
			$r[$i][4] = $tilDestinasjonId;
			$r[$i][5] = $sambandInit;
			$r[$i][6] = $dagId;
			$r[$i][7] = $vei;
			$r[$i][8] = utf8_encode($rutenavn);
			$r[$i][9] = $lat;
			$r[$i][10] = $long;
			$r[$i][11] = $zoom;
			$r[$i][12] = $operator;
			$r[$i][13] = $operator_web;
			$r[$i][14] = $overfartstid;
			$i++;
		} 

		if ($i==0)
			return -1;
		else
			return $r;
	}

	/**
	*
	*
	* Returnerer hvilken dagtype det er for en gitt dato.
	*
	*
	*/
	public function dagForDato($rute, $dato) {
		$spsDag = $this->spesialDagRute($rute, $dato);

		if ($spsDag>3) {
			return $spsDag;
		}

		else if ((date('D', strtotime($dato))=='Sat') && ($this->erDetDagsIdIRute(2, $rute))) {
			return 2;
		}

		else if ((date('D', strtotime($dato))=='Sun') && ($this->erDetDagsIdIRute(3, $rute))) {
			return 3;
		}

		else {
			return 1;
		}
	}

	/**
	*
	*
	* Returnerer full liste av avgangsData for gitt dag.
	* Dersom $dato=0 får man ruten for samme dag. 
	* Kan defineres selv. 
	*
	*/
	public function avgangerDag($rute, $dato=0) {
		
		if ($dato == 0) {
			$t = $this->getTid();
			$dato = $t[0];
		}
		return $this->hentRutetider($rute, $this->dagForDato($rute,$dato));
		
	}

	/**
	*
	* Returnerer avgangsdata for neste avgang.
	* Dersom ikke $dato og $tid er definert vil neste avgang
	* basert på dato og tid når metoden blir kalt.
	* 
	* Disse parametrene kan man også sette selv.
	*
	*/
	public function nesteAvgang($rute, $dato=0, $tid=0) {

		$t = $this->getTid();

		if ($dato == 0) {
			$dato = $t[0];
		}

		if ($tid == 0) {
			$tid = $t[1];
		}

		$r = $this->hentRutetider($rute, $this->dagForDato($rute,$dato), $tid);
		$omDager = 0;

		while ($r==-1){
			$omDager++;
			$d = strtotime("+".$omDager." day",strtotime($dato));
			$dato = date("Y-m-d",$d); //Neste dag.
			$tid = "00:00:00";
			$r = $this->hentRutetider($rute, $this->dagForDato($rute,$dato), $tid);
		}

		//echo "dag:" . $dato . "om dager: " . $omDager;

		$r[0][15] = $dato;
		$r[0][16] = $omDager;

		//print_r($r[0]);*/

		return $r[0];
		

	}

	public function fartoyForRute($rute) {
		$stmt = $this->db->prepare("SELECT Fartoy.id, Fartoy.navn, Fartoy.pasKap, Fartoy.bilkap, Fartoy.maxHoyde, Fartoy.handicap, Fartoy.kafeteria, 
									Fartoy.wifi, Fartoy.marineTraffic
									FROM FartoyRuter INNER JOIN Fartoy ON FartoyRuter.fartoyid=Fartoy.id WHERE FartoyRuter.ruteId = ?");
		$stmt->bind_param("i", $rute);
		$stmt->execute();
		$stmt->bind_result($id, $navn, $pasKap, $bilKap, $maxHoyde, $handicap, $kafeteria, $wifi, $marineTraffic);

		$r = array();
		$i=0;
		while ($stmt->fetch()) {
			$r[$i][0] = $id;
			$r[$i][1] = utf8_encode($navn);
			$r[$i][2] = $pasKap;
			$r[$i][3] = $bilKap;
			$r[$i][4] = $maxHoyde;
			$r[$i][5] = $handicap;
			$r[$i][6] = $kafeteria;
			$r[$i][7] = $wifi;
			$r[$i][8] = $marineTraffic;
			$i++;
		}

		return $r;
	}

	public function fartoyById($id) {
		$stmt = $this->db->prepare("SELECT Fartoy.id, Fartoy.navn, Fartoy.pasKap, Fartoy.bilkap, Fartoy.maxHoyde, Fartoy.handicap, Fartoy.kafeteria, 
									Fartoy.wifi, Fartoy.marineTraffic
									FROM FartoyRuter INNER JOIN Fartoy ON FartoyRuter.fartoyid=Fartoy.id WHERE Fartoy.id = ?");
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$stmt->bind_result($id, $navn, $pasKap, $bilKap, $maxHoyde, $handicap, $kafeteria, $wifi, $marineTraffic);
		$stmt->fetch();

		$r[0] = $id;
		$r[1] = utf8_encode($navn);
		$r[2] = $pasKap;
		$r[3] = $bilKap;
		$r[4] = $maxHoyde;
		$r[5] = $handicap;
		$r[6] = $kafeteria;
		$r[7] = $wifi;
		$r[8] = $marineTraffic;

		return $r;
	}

	public function hentLokFraMT($p) {
		$str = file_get_contents('http://www.marinetraffic.com/en/ais/details/ships/'.$p);
		$pos = strpos ($str, 'details_data_link">'); 
		$ak = substr($str, $pos+19);
		$ak = str_replace("&deg;" ,"", $ak);
		$ak = str_replace("<" ,"", $ak);
		$bit = explode("/", $ak, 3);
		unset($bit[2]);
		return $bit;
	}

	public function getFartoyLokasjon($fartoy) {

		$f = $this->fartoyById($fartoy);

		$stmt = $this->db->prepare("SELECT lat, lng, oppdatert FROM FartoyLokasjoner WHERE fartoyId=?");
		$stmt->bind_param("i", $fartoy);
		$stmt->execute();
		$stmt->store_result();
		$stmt->bind_result($lat, $lng, $oppdatert);
		$stmt->fetch();

		if ($stmt->num_rows!=0) {
			if ($oppdatert<(time()-(60*2))) { 

				$l = $this->hentLokFraMT($f[8]);
				$stmt2 = $this->db->prepare("UPDATE FartoyLokasjoner SET lat=?, lng=?, oppdatert=? WHERE fartoyId=?");
				$stmt2->bind_param("ddii", $l[0], $l[1], $this->getTid()[2], $fartoy);
				$stmt2->execute();
				$l[2] = 1;
				return $l;

			}//Gammel oppføring

			else {

				$r = array();
				$r[0] = $lat;
				$r[1] = $lng;

				return $r;

			}//Gyldig oppføring

		}//Oppføring fantes

		else {
			$l = $this->hentLokFraMT($f[8]);
			$stmt2 = $this->db->prepare("INSERT INTO FartoyLokasjoner (lat, lng, oppdatert, fartoyId) 
				VALUES (?, ?, ?, ?)");

			$stmt2->bind_param("ddii", $l[0], $l[1], $this->getTid()[2], $fartoy);
			$stmt2->execute();

			return $l;

		}//Oppføring fantes ikke

	}


	/**
	*
	* Returnerer array med riktig tidssone.
	* 0 -> Dato
	* 1 -> Tid
	*
	*/
	public function getTid() {

	    $DT = new DateTime();
	    $DT->setTimezone(new DateTimeZone('Europe/Copenhagen'));

	    $r = array();
	    $r[0] = $DT->format('Y-m-d');
	    $r[1] = $DT->format('H:i:s');
	    $r[2] = $DT->getTimestamp();
/*
	    $r[0] = date('Y-m-d');
	    $r[1] = date('H:i:s');
*/
	    return $r;
    
  	}

}
?>
<?php
/*
<html>
<head>
<meta charset="UTF-8">
</head>

<body>
<?php
$fd = new FergeData();

$s = $fd->avgangerDag(2);

foreach ($s as $d) {
	echo $d[6] . " - " . $d[1] . "<br />";
}

echo "<br/>Neste avgang:" . $fd->nesteAvgang(2)[1];

echo "<br />Fartøy i rute: ";
$f = $fd->fartoyForRute(2);

foreach ($f as $fa) {
	$l = $fd->getFartoyLokasjon($fa[0]);
	echo "<p>".$fa[1] . ", lokasjon: " . $l[0]."/".$l[1] ."</p>";
}

</body>
</html>
*/
?>
