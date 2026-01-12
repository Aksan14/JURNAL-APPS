<?php
/*
File: index.php
Lokasi: /jurnal_app/siswa/index.php
*/

// 1. Panggil config dan auth check SEBELUM header
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['siswa']); // Hanya siswa

// Langsung arahkan ke halaman utama siswa
header('Location: lihat_jurnal.php');
exit;

?>