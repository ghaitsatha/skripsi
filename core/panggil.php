<?php
    // panggil file
    include 'credentials.php';
    include 'prosescrud.php';
    // cara panggil class di koneksi php
    //$db = new Koneksi();
    global $db;
    // cara panggil koneksi di fungsi DBConnect()
   // $db =  $db->DBConnect();
    // panggil class prosesCrud di file prosescrud.php
    $proses = new prosesCrud($db);
    // menghilangkan pesan error
    error_reporting(0);
    // panggil session ID
    $id = $_SESSION['ADMIN']['id_login'];
    $sesi = $proses->tampil_data_id('tbl_user','id_login',$id);
?>