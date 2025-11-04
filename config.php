<?php
$host = 'localhost';
$dbname = 'u421017040_bsdo_sale';
$username = 'u421017040_bsdo';
$password = 'Hermogene$25';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>