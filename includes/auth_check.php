<?php
/*
File: auth_check.php
Lokasi: /jurnal_app/includes/auth_check.php
*/

// Panggil config.php untuk memulai sesi
require_once __DIR__ . '/../config.php';

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Jika belum, tendang ke halaman login
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// (Opsional) Fungsi untuk cek role spesifik
function checkRole($allowedRoles) {
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        // Jika rolenya tidak diizinkan
        echo "<div style='text-align:center; padding: 50px;'>
                <h1>Akses Ditolak</h1>
                <p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>
                <a href='" . BASE_URL . "'>Kembali ke Beranda</a>
              </div>";
        exit;
    }
}
?>