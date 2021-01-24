<?php
function stem($txt, $debug=false){
	// case folding html tag removal
	$input = filter_var(strtolower($txt), FILTER_SANITIZE_STRING);
	// tokenizing
	$result = preg_replace("/[^a-zA-Z0-9 ]/", "", $input);
	$pecah = explode(" ",$result);
	foreach($pecah as $item){
		if(strlen($item) > 0){
			$hasil = nazief($item);
			$save[] = $hasil;
		}
	}

	if(empty($save))
		return false;

	if($debug == true){
		var_dump('---------- PREPROCESSING -----------');
		print_r(stopword($save));
	}

	// panggil filtering
	return stopword($save);

}
// filtering 
function stopword($arr){
	global $db;

	if(count($arr) > 0){
		$imp = implode("','",$arr);
		$query = $db->query("SELECT stopword FROM stopword_list WHERE stopword IN ('$imp')");
		if($query->rowCount() > 0){
			foreach($query as $ck){
				foreach (array_keys($arr, $ck['stopword'], true) as $key) {
				    unset($arr[$key]);
				}
			}
		}
		return $arr;
	}
	else{
		return false;
	}
}

function get_data_latih($arr, $debug=true){
	global $db;

	$addQuery = "";
	foreach($arr as $st){
		$addQuery .= "(stem LIKE ".$db->quote("$st,%")." OR stem LIKE ".$db->quote("%,$st,%")." OR ".$db->quote(",$st%").") OR ";
	}
	$addQuery = substr($addQuery, 0, -4);
	// $get_data_latih = $db->query("SELECT * FROM skripsi_komentar WHERE $addQuery");
	$get_data_latih = $db->query("SELECT * FROM sms");

	$n = 1;
	// 0 itu disediakan untuk query
	$send[0] = null;
	$sentimen[0] = null;
	foreach($get_data_latih as $row){
		$item = explode(",",$row['stem']);
		$sentimen[$n] = $row['sentimen'];
		$send[$n] = $item;
		$n++;
	}

	if($n == 1){
		//alias tidak ada perubahan
		$send = null;
		$sentimen = null;
	}

	$output = array(
		"item" => $send,
		"sentimen" => $sentimen
	);

	if($debug == true){
		var_dump('---------- GET TRAINING -----------');
		print_r($output);
	}

	return $output;
}

// function update_database(){
// 	global $db;
// 	$sql = $db->query("SELECT * FROM skripsi_komentar");
// 	$upd = "";
// 	foreach($sql as $row){
// 		$stem = stem($row['komentar']);
// 		$imp = "";
// 		if(count($stem) > 0){
// 			$imp = implode(",",$stem);
// 		}
// 		$upd .= "UPDATE skripsi_komentar SET stem = ".$db->quote($imp)." WHERE no = ".$db->quote($row['no'])."; ";
// 	}
// 	$run = $db->query($upd);
// 	return true;
// }

function create_token($data, $debug=true){
	$token = array();
	foreach($data['item'] as $itm){
		$token = array_merge($token, array_values($itm));
	}
	$token = array_unique($token);
	if ($debug == true){
		var_dump('---------- TOKEN -----------');
		print_r($token);
	}
	return $token;
}

function cari_tf($token, $data, $debug=true){
	$tf = array();
	foreach($token as $kata){
		foreach($data as $key=>$value){
			$val = array_count_values($value);
			if(isset($val[$kata])){
				$tf[$kata][$key] = $val[$kata];
			}
			else{
				$tf[$kata][$key] = 0;
			}
		}
	}

	// if ($debug == true){
	// 	var_dump('---------- TF -----------');
	// 	print_r($tf);
	// }


	return $tf;
}

function cari_df($tf, $debug=true){
	//menghitung jumlah kemunculan kata dalam dokumen
	foreach($tf as $key=>$value){
		$df = 0;
		$n = count($value);
		for($i=0;$i<$n;$i++){
			if($value[$i] > 0)
				$df++;
		}

		$retdf[$key] = $df;
	}
	if ($debug == true){
		var_dump('---------- DF -----------');
		print_r($retdf);
	}
	return $retdf;
}

function hitung_tfidf($tf, $idf, $debug=false){
	foreach ($idf as $termIdf => $valueIdf) {
		foreach ($tf as $termTf => $valueTf) {
			foreach ($valueTf as $doc => $nilai) {
				if ($termIdf == $termTf){
					$tf[$termIdf][$doc] = $nilai * $valueIdf;
				}
			}
		}
	}
	return $tf;
}

function hitung_bobot($tf, $idf, $debug=true){
	$bobot = array();
	foreach($idf as $key=>$value){
		//just try
		if(!isset($tf[$key])){
			$n = 0;
		}
		else{
			$n = count($tf[$key]);
		}
		for($i=0;$i<$n;$i++){
			if(!isset($bobot[$i]))
				$bobot[$i] = 0;
			$bobot[$i] += ($tf[$key][$i] * $value);
		}
	}
	if ($debug == true){
		var_dump('---------- BOBOT -----------');
		print_r($bobot);
	}
	return $bobot;
}

function hitung_jarak($x1, $y1){
	$n = count($y1);
	for($i=0;$i<$n;$i++){
		$jarak[$i] = abs($x1-$y1[$i]);
	}
	return $jarak;
}

function bagi_cluster($euclidean_distance){
	$n = count($euclidean_distance['jarak1']);
	$c1 = array();
	$c2 = array();

	for($i=0;$i<$n;$i++){
		if($euclidean_distance['jarak1'][$i] < $euclidean_distance['jarak2'][$i])
			$c1[] = $i;
		else
			$c2[] = $i;
	}

	return array("c1" => $c1, "c2" => $c2);
}

function hitung_euclidean_distance($tfidf, $kata, $debug=true){
	
	foreach ($tfidf as $key => $value) {
		for ($i=0; $i < count($value); $i++) { 
			$dokumenList[$i] = [];
		}
	}

	foreach ($kata as $key => $value) {
		foreach ($value as $key1 => $value1) {
			// print_r(['key' => $key, 'key1' => $key1, 'value1' => $value1]);
			$dokumenList[$key] += [$value1 => 0];
		}
	}

	// menyalin tfidf ke dokumenList
	foreach ($tfidf as $termnya => $value) {
		foreach ($value as $id_doc => $value1) {
			// print_r(['termnya' => $termnya, 'id_doc' => $id_doc, 'value1' => $value1]);

			foreach ($dokumenList as $id_doc_list => $value_doc_list) {
				foreach ($value_doc_list as $term_doc_list => $value_0) {
					if($id_doc == $id_doc_list && $termnya == $term_doc_list){
						$dokumenList[$id_doc_list][$term_doc_list] = $value1;
					}
				}
			}

		}
	}

	// perhitungan euclidean distance
	// menentukan dimana centroidnya
	// sesuai contoh C1, C4
	$last_id = mysqli_insert_id($db);
	$c1 = 0;
	$c2 = $last_id;

	// menentukan pusat di tfidf
	$euclids = [];
	
	// looping dokumen list
	$euclidean_distance = [];		
	// menghitung jarak dari c1
	foreach ($dokumenList as $id_doc => $value) {
		$temp = 0;	
		foreach ($value as $term => $nilainya) {
			// jarak dari c1
			// print_r([$id_doc => [$term => $nilainya]]);
			if($id_doc == $c1){
				if(array_key_exists($term, $dokumenList[$c1])){
					// print_r([$id_doc => ['sama dengan centroid' => [$term => $nilainya-$nilainya]]]);
					$temp +=($nilainya-$nilainya);
				}

			}
			else{
				// // kalau sama
				if(array_key_exists($term, $dokumenList[$c1])){
					print_r([$id_doc.'-'.$c1 => ['termnya sama' => [$term => pow(($nilainya - $dokumenList[$c1][$term]),2)]]]);
					$temp += pow(($nilainya - $dokumenList[$c1][$term]),2);
				}
				// // kalau di doclist ada, di pusat tidak ada1
				else if(!array_key_exists($term, $dokumenList[$c1])){
					print_r([$id_doc.'-'.$c1 => ['doclist ada' => [$term => pow($nilainya,2)]]]);
					$temp += pow($nilainya,2);
				}
			}
		}
		// // kalau di pusat ada, di doclist tidak ada
		foreach ($dokumenList[$c1] as $term1 => $value1) {
			if(!array_key_exists($term1, $dokumenList[$id_doc])){
				print_r([$id_doc.'-'.$c1 => ['pusat ada' => [$term1 => pow($dokumenList[$c1][$term1],2)]]]);
				$temp += pow($dokumenList[$c1][$term1],2);
			}
		}
		$euclidean_distance['jarak1'][$id_doc] = sqrt($temp);		
	}

	// menghitung jarak dari c2
	foreach ($dokumenList as $id_doc => $value) {
		$temp = 0;	
		foreach ($value as $term => $nilainya) {
			// jarak dari c2
			// print_r([$id_doc => [$term => $nilainya]]);
			if($id_doc == $c2){
				if(array_key_exists($term, $dokumenList[$c2])){
					// print_r([$id_doc => ['sama dengan centroid' => [$term => $nilainya-$nilainya]]]);
					$temp +=($nilainya-$nilainya);
				}

			}
			else{
				// // kalau sama
				if(array_key_exists($term, $dokumenList[$c2])){
					print_r([$id_doc.'-'.$c2 => ['termnya sama' => [$term => pow(($nilainya - $dokumenList[$c2][$term]),2)]]]);
					$temp += pow(($nilainya - $dokumenList[$c2][$term]),2);
				}
				// // kalau di doclist ada, di pusat tidak ada1
				else if(!array_key_exists($term, $dokumenList[$c2])){
					print_r([$id_doc.'-'.$c2 => ['doclist ada' => [$term => pow($nilainya,2)]]]);
					$temp += pow($nilainya,2);
				}
			}
		}
		// // kalau di pusat ada, di doclist tidak ada
		foreach ($dokumenList[$c2] as $term1 => $value1) {
			if(!array_key_exists($term1, $dokumenList[$id_doc])){
				print_r([$id_doc.'-'.$c2 => ['pusat ada' => [$term1 => pow($dokumenList[$c2][$term1],2)]]]);
				$temp += pow($dokumenList[$c2][$term1],2);
			}
		}
		$euclidean_distance['jarak2'][$id_doc] = sqrt($temp);		
	}

	





	if ($debug == true){
		var_dump('=========== euclid cluster ==========');
		print_r(['dokumenList' => $dokumenList]);
		print_r(['euclidean distance' => $euclidean_distance]);
	}

	return $euclidean_distance;
}

function means($index, $bobot){
	$sum = 0;
	$n = 0;
	foreach($index as $key=>$value){
		$sum += $bobot[$value];
		$n++;
	}

	$means = ($n == 0) ? 1 : $sum / $n;

	return $means;
}

function cari_sentimen($cluster, $sentimen, $pusat, $bobot, $debug=true){
	//golongkan nilai sentimen yang ada di dalam cluster
	$spam = 0;
	$real = 0;
	foreach($cluster as $c){
//		if($c <> 0){
			if($sentimen[$c] == 1){
				$spam++;
			}
			else{
				$real++;
			}
//		}
	}

	if($debug){
		echo "
		<span class='label label-success'>Data Spam : $spam</span>
		<span class='label label-danger'>Data Real : $real</span>
		<br>
		";
	}



	//Metode K-NN ditentukan di baris ini.
	//jika ingin dijalankan secara default, hapus baris IF dibawah ini.
	// if($spam == $real){
		//kalau jumlah sentimen positif dan negatifnya sama, maka sentimennya adalah data terdekat
		// foreach($cluster as $c){
		// 	$jarak[$c] = abs($pusat-$bobot[$c]);
		// 	if($debug)
		// 		echo "Jarak <strong>K-$c</strong> ke pusat data = ".$pusat." - ".$bobot[$c]." = <strong>".$jarak[$c]."</strong><br>";
		// }
		$jarak_min = array_keys($jarak, min($jarak));
		$hasil = $sentimen[$jarak_min[0]];

		if($debug){
			if($hasil==0)
				$cl = "danger";
			else
				$cl = "success";
			echo "Sentimen ditentukan berdasarkan jarak terdekat yaitu di <span class='label label-$cl'>K-".$jarak_min[0]."</span><br>";
		}

		if($hasil == 1)
			$spam++;
		else
			$real++;
	// }


	// if($positif > $negatif){
	// 	return 1;
	// }
	// else{
	// 	return 0;
	// }
}



// function knn($cluster, $sentimen, $pusat, $bobot, $debug=true){
	//golongkan nilai sentimen yang ada di dalam cluster
	// $positif = 0;
	// $negatif = 0;
	// foreach($cluster as $c){
	// 	if($c <> 0){
	// 		if($sentimen[$c] == 1){
	// 			$positif++;
	// 		}
	// 		else{
	// 			$negatif++;
	// 		}
	// 	}
	// }

	// if($debug){
	// 	echo "
	// 	<span class='label label-success'>Data positif : $positif</span>
	// 	<span class='label label-danger'>Data negatif : $negatif</span>
	// 	<br>
	// 	";
	// }



	//jalan tengah
	//kalau selisih data positif dan negatif tidak lebih dari 6.66%, maka KNN baru dijalankan untuk mencari tetangga terdekat
	//selebihnya, sentimen kebanyakan dalam sebuah cluster seharusnya sudah mewakili.
	// $total_coba = $positif + $negatif;
	// $selisih_coba = abs($positif - $negatif);
	// if($selisih_coba < ($total_coba / 15)){

		//Metode K-NN ditentukan di baris ini.
		// $jarak = array();
		// foreach($cluster as $c){
			//jadikan data uji sebagai pusat data
		// 	if($c == 0){
		// 		$pusat = $bobot[$c];
		// 		continue;
		// 	}
		// 	$jarak[$c] = abs($pusat-$bobot[$c]);
		// 	if($debug)
		// 		echo "Jarak <strong>K-$c</strong> ke pusat data = ".$pusat." - ".$bobot[$c]." = <strong>".$jarak[$c]."</strong><br>";
		// }

		// if(count($jarak) > 0){
			//nggak ada data apapun di cluster tersebut
// 			$jarak_min = array_keys($jarak, min($jarak));
// 			$hasil = $sentimen[$jarak_min[0]];

// 			if($hasil==0)
// 				$cl = "danger";
// 			else
// 				$cl = "success";

// 			$dbug_text = "Sentimen ditentukan berdasarkan jarak terdekat yaitu di <span class='label label-$cl'>K-".$jarak_min[0]."</span><br>";
// 		}
// 		else{
// 			$hasil = -1;
// 			$dbug_text = "Tidak ada data apapun yang dapat dijadikan dasar penentuan sentimen.";
// 		}

// 	}
// 	else{
// 		if($positif > $negatif)
// 			$hasil = 1;
// 		else
// 			$hasil = 0;

// 		if($positif == 0 and $negatif == 0){
// 			$hasil = -1;
// 		}
// 		$dbug_text = "Metode KNN tidak dijalankan karena mengikuti sentimen terbanyak di cluster tersebut";
// 	}


// 	if($debug){
// 		echo $dbug_text;
// 	}

// 	return intval($hasil);
// }










// function revise($id){
// 	global $db;
// 	$cek = $db->query("SELECT * FROM skripsi_rekap WHERE no = ".intval($id)." AND flag = 0");

// 	if($cek->rowCount() >= 1){
// 		$row = $cek->fetch();
// 		if($row['sentimen'] == 0)
// 			$to = 1;
// 		else
// 			$to = 0;

// 		$upd = $db->query("UPDATE skripsi_rekap SET flag = 1, sentimen = $to WHERE no = ".intval($id));
// 	}

// 	return true;
// }