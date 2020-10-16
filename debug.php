<?php
require_once("core/koneksi.php");
require_once("core/function.php");
require_once("core/nazief.php");
require_once("core/analisis.php");

// proses stemming
$query = 'selalu semangat pagi ketika mengajar tidak pernah terlambat';
$katadasar = stem($query, true);
// panggil data training
$data = get_data_latih($katadasar, true);
$data['item'][0] = $query;
$kata = $data['item'];
$sentimen = $data['sentimen'];
$jumlah_kata = count($data) - 1;
// buat token
$token = create_token($data);
?>