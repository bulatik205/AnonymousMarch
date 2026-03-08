<?php
# this is clone config.php without configurations keys. paste your keys before using

define("API_BOT_TOKEN", "");
$main_host = "localhost";
$main_user = "";
$main_pass = "";
$main_db = "";

$pdo = new PDO(
    "mysql:host=$main_host;dbname=$main_db;charset=utf8mb4",
    $main_user,
    $main_pass, 
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);