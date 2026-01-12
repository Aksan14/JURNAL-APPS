<?php
// config.php

// Set timezone ke Asia/Jakarta (WIB) agar sesuai dengan waktu lokal
date_default_timezone_set('Asia/Jakarta');

$host = 'localhost';
$db   = 'jurnal_app';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

define('BASE_URL', 'http://localhost/jurnal_app');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Mulai Sesi di setiap halaman yang butuh login
if (!session_id()) {
    session_start();
}
?>