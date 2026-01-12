<?php
/*
File: index.php
Lokasi: /jurnal_app/walikelas/index.php
*/

// 1. Panggil
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
checkRole(['walikelas', 'admin']); // Admin juga boleh

// Langsung arahkan ke halaman utama walikelas
header('Location: rekap_absensi.php');
exit;

?>