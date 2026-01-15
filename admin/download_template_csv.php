<?php
/**
 * File: download_template_csv.php
 * Download template CSV untuk import data
 * Format CSV 100% kompatibel dengan semua aplikasi spreadsheet
 */

// Tidak boleh ada output apapun sebelum header
ob_start();
require_once __DIR__ . '/../config.php';

// Cek session admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

$tipe = $_GET['tipe'] ?? 'siswa';

// Bersihkan buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Set header untuk CSV download
$filename = 'TEMPLATE_IMPORT_' . strtoupper($tipe) . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// BOM untuk UTF-8 (agar Excel membaca karakter Indonesia dengan benar)
echo "\xEF\xBB\xBF";

// Buka output stream
$output = fopen('php://output', 'w');

// ===================================
// TEMPLATE SISWA
// ===================================
if ($tipe == 'siswa') {
    fputcsv($output, ['NIS', 'NAMA_SISWA', 'ID_KELAS', 'USERNAME', 'PASSWORD']);
    fputcsv($output, ['12345', 'Nama Siswa Contoh', '1', 'siswa12345', 'password123']);
    fputcsv($output, ['']);
    fputcsv($output, ['=== CATATAN ===']);
    fputcsv($output, ['NIS, NAMA_SISWA, dan ID_KELAS wajib diisi']);
    fputcsv($output, ['USERNAME - jika kosong akan menggunakan NIS']);
    fputcsv($output, ['PASSWORD - jika kosong akan sama dengan username']);
    fputcsv($output, ['']);
    fputcsv($output, ['=== REFERENSI ID KELAS ===']);
    fputcsv($output, ['ID', 'NAMA_KELAS']);
    
    $kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas")->fetchAll();
    foreach ($kelas as $k) {
        fputcsv($output, [$k['id'], $k['nama_kelas']]);
    }
}

// ===================================
// TEMPLATE GURU
// ===================================
elseif ($tipe == 'guru') {
    fputcsv($output, ['NIP', 'NAMA_GURU', 'EMAIL', 'USERNAME', 'PASSWORD']);
    fputcsv($output, ['1990123456', 'Nama Guru Contoh', 'email@contoh.com', 'gurucontoh', 'password123']);
    fputcsv($output, ['']);
    fputcsv($output, ['=== CATATAN ===']);
    fputcsv($output, ['NIP boleh kosong']);
    fputcsv($output, ['NAMA_GURU wajib diisi']);
    fputcsv($output, ['EMAIL boleh kosong']);
    fputcsv($output, ['USERNAME - jika kosong akan digenerate otomatis dari nama guru']);
    fputcsv($output, ['PASSWORD - jika kosong akan sama dengan username']);
}

// ===================================
// TEMPLATE MAPEL
// ===================================
elseif ($tipe == 'mapel') {
    fputcsv($output, ['KODE_MAPEL', 'NAMA_MAPEL']);
    fputcsv($output, ['MTK', 'Matematika']);
    fputcsv($output, ['IPA', 'Ilmu Pengetahuan Alam']);
}

// ===================================
// TEMPLATE KELAS
// ===================================
elseif ($tipe == 'kelas') {
    fputcsv($output, ['NAMA_KELAS', 'ID_WALI_KELAS']);
    fputcsv($output, ['X RPL 1', '1']);
    fputcsv($output, ['']);
    fputcsv($output, ['=== CATATAN: ID_WALI_KELAS opsional ===']);
    fputcsv($output, ['']);
    fputcsv($output, ['=== REFERENSI ID GURU ===']);
    fputcsv($output, ['ID', 'NAMA_GURU']);
    
    $guru = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru")->fetchAll();
    foreach ($guru as $g) {
        fputcsv($output, [$g['id'], $g['nama_guru']]);
    }
}

// ===================================
// TEMPLATE PLOTTING MENGAJAR
// ===================================
elseif ($tipe == 'mengajar') {
    fputcsv($output, ['ID_GURU', 'ID_MAPEL', 'ID_KELAS', 'JUMLAH_JAM_MINGGU']);
    fputcsv($output, ['1', '1', '1', '2']);
    fputcsv($output, ['']);
    fputcsv($output, ['=== REFERENSI ID GURU ===']);
    fputcsv($output, ['ID', 'NAMA_GURU']);
    $guru = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru")->fetchAll();
    foreach ($guru as $g) {
        fputcsv($output, [$g['id'], $g['nama_guru']]);
    }
    
    fputcsv($output, ['']);
    fputcsv($output, ['=== REFERENSI ID MAPEL ===']);
    fputcsv($output, ['ID', 'NAMA_MAPEL']);
    $mapel = $pdo->query("SELECT id, nama_mapel FROM tbl_mapel ORDER BY nama_mapel")->fetchAll();
    foreach ($mapel as $m) {
        fputcsv($output, [$m['id'], $m['nama_mapel']]);
    }
    
    fputcsv($output, ['']);
    fputcsv($output, ['=== REFERENSI ID KELAS ===']);
    fputcsv($output, ['ID', 'NAMA_KELAS']);
    $kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas")->fetchAll();
    foreach ($kelas as $k) {
        fputcsv($output, [$k['id'], $k['nama_kelas']]);
    }
}

fclose($output);
exit;
