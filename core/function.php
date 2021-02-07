<?php
function dump($var, $multiple=false){
		echo "<textarea style='width:100%; height:400px'>";
		if($multiple == true){
			foreach($var as $itm){
				print_r($itm);
				echo "\n\n\n";
			}
		}
		else
			var_dump($var);
		echo "</textarea>";
}

function is_same($a, $b, $out = ""){
	if($a == $b){
		echo $out;
	}
}

function get_extension($filename){
	$exp = explode(".",$filename);
	$n = count($exp);
	return strtolower($exp[$n-1]);
}

function single_process($s, $debug = false){

	// stemming
	$string = stem($s);
	if($string){
		$data = get_data_latih($string);
		$data['item'][0] = $string;

		$kata = $data['item'];
		$sentimen = $data['sentimen'];

		$jumlah_data = count($kata);
		$token = create_token($data);
		// hitung tf

		$tf = cari_tf($token, $kata);
		if ($debug == true){
			var_dump('---------- TF -----------');
			print_r($tf);
		}
		$df = cari_df($tf);

		// menghitung IDF (log(jumlah data / $df))
		foreach($tf as $kk=>$vv){//sori kalau nama variabelnya ga jelas
			$ddf[$kk] = ($df[$kk] == 0) ? 1 : ($jumlah_data / $df[$kk]);
			$idf[$kk] = log10($ddf[$kk]);
		}
		if ($debug == true){
			var_dump('---------- IDF -----------');
			print_r(['jumlah_data' => $jumlah_data]);
			print_r($idf);
		}

		// panggil fungsi tfidf
		$tfidf = hitung_tfidf($tf, $idf);
		if ($debug == true){
			var_dump('---------- TFIDF -----------');
			print_r($tfidf);
		}


		// panggil metode kmeauclidean distanceans buatan sendiri
		// iterasi 1 disini
		$euclidean_distance = hitung_euclidean_distance($tfidf, $kata);
		$cluster = bagi_cluster($euclidean_distance['distance']);
		// print_r($cluster);

		// die();
		

		// iterasi ke-2 dst
		$max_iterasi = 500;
		$c1_temp = $cluster['c1'];
		$c2_temp = $cluster['c2'];
		$bobot = $euclidean_distance['dokumenList'];
		// print_r($bobot);
		// $sama = 0;
		for($i=0;$i<$max_iterasi;$i++){

			// print_r(['c1_temp' => $c1_temp, 'bobot' => $bobot]);
			// die();
			$pusat1 = means($c1_temp, $bobot);
			// print_r($pusat1);

			$jarak1 = hitung_jarak($pusat1, $bobot, $c1_temp);
			// print_r($jarak1);

			$pusat2 = means($c2_temp, $bobot);
			// print_r($pusat2);
			$jarak2 = hitung_jarak($pusat2, $bobot, $c2_temp);
			// print_r($jarak2);
			// die();



			$new_euclid['jarak1'] = $jarak1;
			$new_euclid['jarak2'] = $jarak2;
			$clusters = bagi_cluster($new_euclid);



			// if(count($clusters["c1"]) == count($c1_temp)){
			// 	if($sama == ceil($max_iterasi/10)){
			// 		break;
			// 		}
			// 	else{
			// 		$sama++;
			// 	}
			// }
			// else{
			// 	$sama = 0;
			// }

			$sama = 0;
			if(count($clusters["c1"]) == count($c1_temp)){
				foreach ($c1_temp as $key => $value) {
					if(array_key_exists($value, $c1_temp)){
						$sama++;
					}
				}
			}

			



			$c1_temp = $clusters["c1"];
			$c2_temp = $clusters["c2"];
			if ($debug == true){
				var_dump('---------- ITERASI KE '.$i.' -----------');
				print_r([
					'pusat1' => $pusat1,
					'jarak1' => $jarak1,
					'pusat2' => $pusat2,
					'jarak2' => $jarak2, 
				]);
				var_dump('---------- CLUSTER setelah ke '.$i.' -----------');
				print_r(['c1' => $clusters["c1"], 'c2' => $clusters["c2"]]);
			}

			if($sama == count($c1_temp)){
				break;
			}

		}


		// //sampai disini perulangan berakhir
		// //cari cluster dengan value 0 ada dimana
		// if(in_array(0, $cluster["c1"])){
		// 	$c_final = $cluster["c1"];
		// 	$pusat = $pusat1;
		// 	$outp = "Cluster 1";
		// }
		// else{
		// 	$c_final = $cluster["c2"];
		// 	$pusat = $pusat2;
		// 	$outp = "Cluster 2";
		// }

		// $final_sentiment = means($c_final, $sentimen, $pusat, $bobot);

		// print_r($final_sentiment);
		// print_r('sdfsdfdsf');

		// C1 = 0 atau spam
		// C2 = 1 atau bukan spam

		if(in_array(0, $clusters["c1"])){
			$final_sentiment = 'real';
		}
		else if(in_array(0, $clusters["c2"])){
			$final_sentiment = 'spam';
		}
		print_r(['c1' => $clusters["c1"], 'c2' => $clusters["c2"]]);

		return $final_sentiment;

	}
	else{
		return -1;
	}

	return $final_sentiment;
}