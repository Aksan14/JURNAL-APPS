<?php
/*
File: index.php
Lokasi: /jurnal_app/index.php
Tugas: Mencegah akses ke halaman manapun sebelum login
       dan mengarahkan user yang sudah login ke dasbornya.
*/

// 1. Panggil file konfigurasi.
// File ini akan otomatis memulai session (session_start())
// dan menyediakan variabel BASE_URL.
require_once 'config.php';

// 2. Cek apakah pengguna sudah login
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    
    // 3. Jika YA, arahkan berdasarkan 'role'
    $role = $_SESSION['role'];

    if ($role == 'admin') {
        // Arahkan ke dasbor admin
        header('Location: ' . BASE_URL . '/admin/index.php');
    
    } elseif ($role == 'guru') {
        // Arahkan ke dasbor guru
        header('Location: ' . BASE_URL . '/guru/index.php');
    
    } elseif ($role == 'walikelas') {
        // Arahkan ke fitur utama wali kelas
        header('Location: ' . BASE_URL . '/walikelas/index.php'); // Arahkan ke index walikelas
    
    } elseif ($role == 'siswa') {
        // Arahkan ke fitur utama siswa
        header('Location: ' . BASE_URL . '/siswa/index.php'); // Arahkan ke index siswa
    
    } else {
        // Role tidak dikenal, fallback, lempar ke login
        header('Location: ' . BASE_URL . '/login.php');
    }

} else {
    // 4. Jika TIDAK, paksa pengguna ke halaman login
    header('Location: ' . BASE_URL . '/login.php');
}

// 5. Wajib ada exit; setelah header location
exit;

?>