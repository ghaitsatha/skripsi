<?php 
require_once("core/koneksi.php");
require_once("core/function.php");
require_once("core/nazief.php");
require_once("core/analisis.php");
	$mysqli = new mysqli("localhost","root","","kamus");
	// Perform query
	if ($result = $mysqli -> query("SELECT id, teks FROM sms")) {
		while($row = $result->fetch_assoc())
		{
			$hasil_stem = stem($row['teks']);
			$imploded = implode(" ",$hasil_stem);
			// print_r($imploded);
			$id = $row['id'];
			if($mysqli->query("UPDATE sms SET stem='$imploded' WHERE id='$id'")){
				echo 'sukses';
			}
			else{
				echo 'gagal';
				echo(mysqli_error($mysqli));
			}
		}
	}
 ?>