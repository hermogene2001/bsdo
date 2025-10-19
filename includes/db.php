<?php
$host = 'localhost';
$user = 'root';
$dbname = 'bsdo_sale';
$pass = '';

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Set the timezone
ini_set('date.timezone', 'Africa/Kigali');  // Set to Kigali time (UTC+3)
// $now = date('Y-m-d H:i:s');
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}


// $password = 'wsgEKCuNXazXhZXKNeMw';
// $db_name = 'alswlwpn_alpha_investment';

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$dbh = mysqli_connect($host, $user, $pass, $dbname);

if (!$dbh) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
