<?php
/**
 * File: download_template.php
 * Download template Excel untuk import data
 * PENTING: File ini tidak boleh ada output apapun sebelum Excel
 */

// Mulai output buffering segera
ob_start();

// Load config untuk koneksi database
require_once __DIR__ . '/../config.php';

// Cek session admin manual (tanpa include auth_check.php yang mungkin output HTML)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

// Load autoloader Composer (untuk PhpSpreadsheet)
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Tentukan tipe template dari URL
$tipe = $_GET['tipe'] ?? 'siswa'; 

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$filename = 'TEMPLATE.xlsx';

// ===================================
// TEMPLATE SISWA
// ===================================
if ($tipe == 'siswa') {
    $sheet->setTitle('Template Import Siswa');
    $sheet->setCellValue('A1', 'NIS');
    $sheet->setCellValue('B1', 'NAMA_SISWA');
    $sheet->setCellValue('C1', 'ID_KELAS');
    $sheet->setCellValue('D1', 'USERNAME');
    $sheet->setCellValue('E1', 'PASSWORD');
    $sheet->getStyle('A1:E1')->getFont()->setBold(true);
    $sheet->getColumnDimension('B')->setWidth(35);
    $sheet->getColumnDimension('D')->setWidth(20);
    $filename = 'TEMPLATE_IMPORT_SISWA.xlsx';
    
    // Ambil referensi ID Kelas
    try {
        $daftar_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC")->fetchAll();
        if ($daftar_kelas) {
            $refSheet = $spreadsheet->createSheet();
            $refSheet->setTitle('Referensi ID Kelas');
            $refSheet->setCellValue('A1', 'ID'); $refSheet->setCellValue('B1', 'NAMA KELAS');
            $refSheet->getStyle('A1:B1')->getFont()->setBold(true);
            $row = 2;
            foreach ($daftar_kelas as $kelas) {
                $refSheet->setCellValue('A' . $row, $kelas['id']);
                $refSheet->setCellValue('B' . $row, $kelas['nama_kelas']);
                $row++;
            }
            $refSheet->getColumnDimension('B')->setWidth(30);
            $spreadsheet->setActiveSheetIndex(0);
        }
    } catch (Exception $e) {}

// ===================================
// TEMPLATE GURU
// ===================================
} elseif ($tipe == 'guru') {
    $sheet->setTitle('Template Import Guru');
    $sheet->setCellValue('A1', 'NIP');
    $sheet->setCellValue('B1', 'NAMA_GURU');
    $sheet->setCellValue('C1', 'EMAIL');
    $sheet->setCellValue('D1', 'USERNAME');
    $sheet->setCellValue('E1', 'PASSWORD');
    $sheet->getStyle('A1:E1')->getFont()->setBold(true);
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(35);
    $sheet->getColumnDimension('C')->setWidth(30);
    $sheet->getColumnDimension('D')->setWidth(20);
    $filename = 'TEMPLATE_IMPORT_GURU.xlsx';
    $sheet->setCellValue('G2', 'NAMA_GURU, USERNAME, dan PASSWORD wajib diisi.');
    $sheet->getStyle('G2')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFF0000'));

// ===================================
// TEMPLATE MAPEL
// ===================================
} elseif ($tipe == 'mapel') {
    $sheet->setTitle('Template Import Mapel');
    $sheet->setCellValue('A1', 'KODE_MAPEL');
    $sheet->setCellValue('B1', 'NAMA_MAPEL');
    $sheet->getStyle('A1:B1')->getFont()->setBold(true);
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(35);
    $filename = 'TEMPLATE_IMPORT_MAPEL.xlsx';

// ===================================
// TEMPLATE KELAS
// ===================================
} elseif ($tipe == 'kelas') {
    $sheet->setTitle('Template Import Kelas');
    $sheet->setCellValue('A1', 'NAMA_KELAS');
    $sheet->setCellValue('B1', 'ID_WALI_KELAS');
    $sheet->getStyle('A1:B1')->getFont()->setBold(true);
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(15);
    $filename = 'TEMPLATE_IMPORT_KELAS.xlsx';
    $sheet->setCellValue('D2', 'ID_WALI_KELAS adalah opsional & harus ID guru yang valid.');
    $sheet->getStyle('D2')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFF0000'));
    
    // Ambil referensi ID Guru
    try {
        $daftar_guru = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru ASC")->fetchAll();
        if ($daftar_guru) {
            $refSheet = $spreadsheet->createSheet();
            $refSheet->setTitle('Referensi ID Guru');
            $refSheet->setCellValue('A1', 'ID'); $refSheet->setCellValue('B1', 'NAMA GURU');
            $refSheet->getStyle('A1:B1')->getFont()->setBold(true);
            $row = 2;
            foreach ($daftar_guru as $guru) {
                $refSheet->setCellValue('A' . $row, $guru['id']);
                $refSheet->setCellValue('B' . $row, $guru['nama_guru']);
                $row++;
            }
            $refSheet->getColumnDimension('B')->setWidth(35);
            $spreadsheet->setActiveSheetIndex(0);
        }
    } catch (Exception $e) {}

// ===================================
// TEMPLATE PLOTTING MENGAJAR
// ===================================
} elseif ($tipe == 'mengajar') {
    $sheet->setTitle('Template Import Plotting');
    $sheet->setCellValue('A1', 'ID_GURU');
    $sheet->setCellValue('B1', 'ID_MAPEL');
    $sheet->setCellValue('C1', 'ID_KELAS');
    $sheet->setCellValue('D1', 'HARI');
    $sheet->setCellValue('E1', 'JAM_KE');
    $sheet->setCellValue('F1', 'JUMLAH_JAM_MINGGU');
    $sheet->getStyle('A1:F1')->getFont()->setBold(true);
    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(12);
    $sheet->getColumnDimension('D')->setWidth(12);
    $sheet->getColumnDimension('E')->setWidth(12);
    $sheet->getColumnDimension('F')->setWidth(20);
    $filename = 'TEMPLATE_IMPORT_PLOTTING.xlsx';
    
    $sheet->setCellValue('H2', 'Isi ID sesuai dengan sheet Referensi');
    $sheet->setCellValue('H3', 'HARI: Senin, Selasa, Rabu, Kamis, Jumat, Sabtu');
    $sheet->setCellValue('H4', 'JAM_KE: contoh 1-2, 3-4, 5');
    $sheet->getStyle('H2:H4')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFF0000'));
    
    // Referensi Guru
    try {
        $daftar_guru = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru ASC")->fetchAll();
        if ($daftar_guru) {
            $refSheet = $spreadsheet->createSheet();
            $refSheet->setTitle('Referensi Guru');
            $refSheet->setCellValue('A1', 'ID'); $refSheet->setCellValue('B1', 'NAMA GURU');
            $refSheet->getStyle('A1:B1')->getFont()->setBold(true);
            $row = 2;
            foreach ($daftar_guru as $guru) {
                $refSheet->setCellValue('A' . $row, $guru['id']);
                $refSheet->setCellValue('B' . $row, $guru['nama_guru']);
                $row++;
            }
            $refSheet->getColumnDimension('B')->setWidth(35);
        }
    } catch (Exception $e) {}
    
    // Referensi Mapel
    try {
        $daftar_mapel = $pdo->query("SELECT id, nama_mapel FROM tbl_mapel ORDER BY nama_mapel ASC")->fetchAll();
        if ($daftar_mapel) {
            $refSheet2 = $spreadsheet->createSheet();
            $refSheet2->setTitle('Referensi Mapel');
            $refSheet2->setCellValue('A1', 'ID'); $refSheet2->setCellValue('B1', 'NAMA MAPEL');
            $refSheet2->getStyle('A1:B1')->getFont()->setBold(true);
            $row = 2;
            foreach ($daftar_mapel as $mapel) {
                $refSheet2->setCellValue('A' . $row, $mapel['id']);
                $refSheet2->setCellValue('B' . $row, $mapel['nama_mapel']);
                $row++;
            }
            $refSheet2->getColumnDimension('B')->setWidth(35);
        }
    } catch (Exception $e) {}
    
    // Referensi Kelas
    try {
        $daftar_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC")->fetchAll();
        if ($daftar_kelas) {
            $refSheet3 = $spreadsheet->createSheet();
            $refSheet3->setTitle('Referensi Kelas');
            $refSheet3->setCellValue('A1', 'ID'); $refSheet3->setCellValue('B1', 'NAMA KELAS');
            $refSheet3->getStyle('A1:B1')->getFont()->setBold(true);
            $row = 2;
            foreach ($daftar_kelas as $kelas) {
                $refSheet3->setCellValue('A' . $row, $kelas['id']);
                $refSheet3->setCellValue('B' . $row, $kelas['nama_kelas']);
                $row++;
            }
            $refSheet3->getColumnDimension('B')->setWidth(25);
        }
    } catch (Exception $e) {}
    
    $spreadsheet->setActiveSheetIndex(0);
}

// ===================================
// PROSES DOWNLOAD
// ===================================
// Bersihkan semua output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers untuk download Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

// Tulis file Excel
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;