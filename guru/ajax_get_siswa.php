<?php
/*
File: ajax_get_siswa.php (UPDATED - THE REAL FIX)
Lokasi: /jurnal_app/guru/ajax_get_siswa.php
*/

// Tampilkan semua error untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==========================================================
// PERBAIKAN: Gunakan __DIR__ agar path-nya 100% benar
// Ini akan memuat config.php dan $pdo
// ==========================================================
require_once __DIR__ . '/../config.php'; 
require_once __DIR__ . '/../includes/auth_check.php';

// Set header output ke JSON (harus ada SEBELUM 'echo' apapun)
header('Content-Type: application/json');

// 2. Pastikan yang akses adalah guru
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'guru' && $_SESSION['role'] != 'walikelas')) {
    echo json_encode(['error' => 'Akses ditolak (Bukan Guru).']);
    exit;
}

// 3. Pastikan ID Mengajar dikirim
if (!isset($_GET['id_mengajar']) || empty($_GET['id_mengajar'])) {
    echo json_encode(['error' => 'ID Mengajar tidak valid.']);
    exit;
}

// Pastikan $pdo ada
if (!$pdo) {
     echo json_encode(['error' => 'Koneksi database (PDO) gagal dimuat. Cek config.php.']);
    exit;
}

$id_mengajar = $_GET['id_mengajar'];

try {
    // 4. Ambil ID Kelas dari ID Mengajar
    $stmt_kelas = $pdo->prepare("SELECT id_kelas FROM tbl_mengajar WHERE id = ?");
    $stmt_kelas->execute([$id_mengajar]);
    $kelas = $stmt_kelas->fetch();

    if (!$kelas) {
        echo json_encode(['error' => 'Data mengajar (id_mengajar) tidak ditemukan.']);
        exit;
    }
    
    $id_kelas_target = $kelas['id_kelas'];

    // 5. Ambil semua siswa di kelas tersebut
    $stmt_siswa = $pdo->prepare("
        SELECT id, nis, nama_siswa 
        FROM tbl_siswa 
        WHERE id_kelas = ? 
        ORDER BY nama_siswa ASC
    ");
    $stmt_siswa->execute([$id_kelas_target]);
    $siswa = $stmt_siswa->fetchAll();

    // 6. Kembalikan data siswa sebagai JSON
    echo json_encode($siswa);
    exit;

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>