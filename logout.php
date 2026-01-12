<?php
/*
File: logout.php
Lokasi: /jurnal_app/logout.php
*/

// 1. Panggil file config untuk mendefinisikan BASE_URL dan memulai sesi
require_once 'config.php';

// 2. Hapus semua data variabel di dalam Sesi
$_SESSION = array();

// 3. Hancurkan Sesi
// Ini akan menghapus cookie sesi di browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Terakhir, hancurkan sesi di server
session_destroy();

// 4. Arahkan (redirect) pengguna kembali ke halaman login
// Kita gunakan BASE_URL dari config.php agar path-nya selalu benar
header('Location: ' . BASE_URL . '/login.php');
exit;

?>