<?php

$user = "root";
$pass = "";
$db = "kamus";
$host = "localhost";
$port = 3306;

$db = new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8;port='.$port, $user ,$pass);

