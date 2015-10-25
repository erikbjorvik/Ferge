<?php

class FergeData {
	
	private $db;

	function __construct() {
		$this->db = new mysqli("localhost","root","","fergetider");
	}

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

	function hentRutetider($ruteId, $dagId) {
		$stmt = $this->db->prepare("SELECT Ruter.ruteNavn AS Rutenavn, Ruter.vei AS Vei, Ruter.id AS RuteID, 
							Samband.fra AS FraDestinasjonID, Samband.til AS TilDestinasjonID, Samband.init AS SambandInit, 
							Avganger.dagType AS dagId, Avganger.id AS AvgangID, Avganger.tid AS Avgang, Avganger.notat AS notat
							FROM Ruter INNER JOIN Samband
							ON Ruter.id = Samband.ruteId
							INNER JOIN Avganger
							ON Avganger.samband = Samband.id
							WHERE Ruter.id=? AND Avganger.dagType=?
							ORDER BY Ruter.id ASC, Avganger.tid ASC");
		
		$stmt->bind_param("ii", $ruteId, $dagId);
		$stmt->execute();
		$stmt->bind_result($rutenavn, $vei, $ruteId, $fraDestinasjonId, $tilDestinasjonId, $sambandInit, $dagId, $avgangId, $avgangTid, $avgangNotat);

		$r = array();
		$i = 0;

		while ($stmt->fetch()) {
			$r[$i][0] = $avgangId;
			$r[$i][1] = $avgangTid;
			$r[$i][2] = $avgangNotat;
			$r[$i][3] = $fraDestinasjonId;
			$r[$i][4] = $tilDestinasjonId;
			$r[$i][5] = $sambandInit;
			$r[$i][6] = $dagId;
			$r[$i][7] = $vei;
			$r[$i][8] = $rutenavn;
			$i++;
		} 

		return $r;
	}

	public function avgangerDag($rute, $dato=0) {
		
		if ($dato == 0) {
			$t = $this->getTid();
			$dato = $t[0];
		}

		//Sjekke om det er spesialdag.
		$spsDag = $this->spesialDagRute($rute, $dato);

		if ($spsDag>3) {
			return $this->hentRutetider($rute, $spsDag);
		}

		else if ((date('D', strtotime($dato))=='Sat') && ($this->erDetDagsIdIRute(2, $rute))) {
			return $this->hentRutetider($rute, 2);
		}

		else if ((date('D', strtotime($dato))=='Sun') && ($this->erDetDagsIdIRute(3, $rute))) {
			return $this->hentRutetider($rute, 3);
		}

		else {
			return $this->hentRutetider($rute, 1);
		}
		
	}

	public function getTid() {

	    $DT = new DateTime();
	    $DT->setTimezone(new DateTimeZone('Europe/Copenhagen'));

	    $r = array();
	    $r[0] = $DT->format('Y-m-d');
	    $r[1]= $DT->format('H:i:s');

	    return $r;
    
  	}

}
?>
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


?>
</body>
</html>