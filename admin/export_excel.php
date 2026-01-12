<?php
/*
File: export_excel.php (UPDATED with Student Rekap Sheet)
Lokasi: /jurnal_app/admin/export_excel.php
*/

// 1. Panggil file config dan auth_check
require_once '../includes/auth_check.php';
require_once '../config.php';
checkRole(['admin']);

// 2. Panggil autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

// 3. Gunakan class dari PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// 4. Fungsi Helper
function calculateHours($jam_ke_str) {
    $jam_ke_str = trim($jam_ke_str);
    if (strpos($jam_ke_str, '-') !== false) {
        $parts = explode('-', $jam_ke_str);
        if (count($parts) == 2) {
            $start = (int)trim($parts[0]);
            $end = (int)trim($parts[1]);
            if ($end >= $start) {
                return ($end - $start) + 1;
            }
        }
    }
    if (is_numeric($jam_ke_str) && (int)$jam_ke_str > 0) return 1;
    if (strpos($jam_ke_str, ',') !== false) return count(explode(',', $jam_ke_str));
    return 0;
}

// 5. Logika Filter (Sama persis)
$filter_sql = "";
$roster_filter_sql = "";
$params = [];
$roster_params = [];

$tanggal_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tanggal_selesai = $_GET['tanggal_selesai'] ?? date('Y-m-t');
$filter_sql .= " WHERE j.tanggal BETWEEN ? AND ?";
array_push($params, $tanggal_mulai, $tanggal_selesai);

$filter_id_guru = $_GET['id_guru'] ?? '';
if (!empty($filter_id_guru)) {
    $filter_sql .= " AND m.id_guru = ?";
    array_push($params, $filter_id_guru);
    $roster_filter_sql .= " WHERE m.id_guru = ?";
    array_push($roster_params, $filter_id_guru);
}
$filter_id_kelas = $_GET['id_kelas'] ?? '';
if (!empty($filter_id_kelas)) {
    $filter_sql .= " AND m.id_kelas = ?";
    array_push($params, $filter_id_kelas);
    $roster_filter_sql .= (!empty($roster_filter_sql) ? " AND " : " WHERE ") . "m.id_kelas = ?";
    array_push($roster_params, $filter_id_kelas);
}

// 6. Query SQL untuk Laporan Jurnal (Sheet 1)
$sql_laporan = "
    SELECT j.tanggal, j.jam_ke, j.topik_materi, j.catatan_guru,
           g.nama_guru, mp.nama_mapel, k.nama_kelas
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    $filter_sql
    ORDER BY j.tanggal ASC, g.nama_guru ASC, j.jam_ke ASC
";
$stmt_laporan = $pdo->prepare($sql_laporan);
$stmt_laporan->execute($params);
$laporan = $stmt_laporan->fetchAll();

// 7. Logika Kalkulasi Rekap (Sheet 1)
$total_pertemuan_terlaksana = count($laporan);
$total_jam_terlaksana = 0;
foreach ($laporan as $j) {
    $total_jam_terlaksana += calculateHours($j['jam_ke']);
}
$stmt_roster = $pdo->prepare("SELECT SUM(m.jumlah_jam_mingguan) AS total_jam_roster FROM tbl_mengajar m $roster_filter_sql");
$stmt_roster->execute($roster_params);
$roster_data = $stmt_roster->fetch();
$total_jam_roster_mingguan = $roster_data['total_jam_roster'] ?? 0;
$start_date = new DateTime($tanggal_mulai);
$end_date = new DateTime($tanggal_selesai);
$diff = $start_date->diff($end_date);
$total_hari_filter = $diff->days + 1;
$total_minggu_penuh = floor($total_hari_filter / 7); 
$total_jam_seharusnya = $total_jam_roster_mingguan * $total_minggu_penuh; 

// ==========================================================
// PROSES PEMBUATAN EXCEL (SHEET 1: REKAP JURNAL)
// ==========================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap Jurnal Terlaksana');

// 8. Judul & Info Filter (Sheet 1)
$sheet->mergeCells('A1:H1');
$sheet->setCellValue('A1', 'LAPORAN JURNAL PEMBELAJARAN');
// ... (styling judul) ...
$sheet->mergeCells('A2:H2');
$sheet->setCellValue('A2', 'Periode: ' . htmlspecialchars(date('d-m-Y', strtotime($tanggal_mulai))) . ' s/d ' . htmlspecialchars(date('d-m-Y', strtotime($tanggal_selesai))));
// ... (styling info) ...

// 9. Statistik Rekap (Sheet 1)
$sheet->mergeCells('B4:C4'); $sheet->setCellValue('B4', 'Pertemuan Terlaksana:'); $sheet->setCellValue('D4', $total_pertemuan_terlaksana . ' Kali');
$sheet->mergeCells('B5:C5'); $sheet->setCellValue('B5', 'Jam Terlaksana:'); $sheet->setCellValue('D5', $total_jam_terlaksana . ' Jam');
$sheet->mergeCells('F4:G4'); $sheet->setCellValue('F4', 'Beban Jam Roster:'); $sheet->setCellValue('H4', $total_jam_roster_mingguan . ' Jam/Mg');
$sheet->mergeCells('F5:G5'); $sheet->setCellValue('F5', 'Estimasi Jam Seharusnya:'); $sheet->setCellValue('H5', $total_jam_seharusnya . ' Jam');
// ... (styling rekap) ...

// 10. Header Tabel (Sheet 1)
$sheet->setCellValue('A7', 'No');
$sheet->setCellValue('B7', 'Tanggal');
// ... (header lainnya) ...
$sheet->setCellValue('H7', 'Catatan Guru');
// ... (styling header) ...

// 11. Isi Data Laporan (Sheet 1)
$row = 8;
$no = 1;
foreach ($laporan as $j) {
    $sheet->setCellValue('A' . $row, $no++);
    // ... (isi data lainnya) ...
    $sheet->setCellValue('H' . $row, $j['catatan_guru']);
    $row++;
}
// ... (autowidth kolom) ...


// ==========================================================
// 12. KODE BARU: QUERY REKAP ABSENSI SISWA (untuk Sheet 2)
// ==========================================================
// Kita menggunakan $filter_sql dan $params yang sama dari filter utama
$sql_rekap_absensi = "
    SELECT 
        s.nis, 
        s.nama_siswa, 
        k.nama_kelas,
        SUM(CASE WHEN p.status_kehadiran = 'H' THEN 1 ELSE 0 END) AS total_h,
        SUM(CASE WHEN p.status_kehadiran = 'S' THEN 1 ELSE 0 END) AS total_s,
        SUM(CASE WHEN p.status_kehadiran = 'I' THEN 1 ELSE 0 END) AS total_i,
        SUM(CASE WHEN p.status_kehadiran = 'A' THEN 1 ELSE 0 END) AS total_a
    FROM tbl_presensi_siswa p
    JOIN tbl_siswa s ON p.id_siswa = s.id
    JOIN tbl_kelas k ON s.id_kelas = k.id
    JOIN tbl_jurnal j ON p.id_jurnal = j.id
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    $filter_sql 
    GROUP BY s.id, s.nis, s.nama_siswa, k.nama_kelas
    ORDER BY k.nama_kelas, s.nama_siswa
";
$stmt_rekap = $pdo->prepare($sql_rekap_absensi);
$stmt_rekap->execute($params); // Menggunakan $params yang sama
$rekap_absensi = $stmt_rekap->fetchAll();


// ==========================================================
// 13. KODE BARU: BUAT SHEET BARU DAN ISI DATA ABSENSI
// ==========================================================
$rekapSheet = $spreadsheet->createSheet();
$rekapSheet->setTitle('Rekap Absensi Siswa');

// Header
$rekapSheet->setCellValue('A1', 'REKAP ABSENSI SISWA');
$rekapSheet->setCellValue('A2', 'Periode: ' . htmlspecialchars(date('d-m-Y', strtotime($tanggal_mulai))) . ' s/d ' . htmlspecialchars(date('d-m-Y', strtotime($tanggal_selesai))));
$rekapSheet->mergeCells('A1:G1');
$rekapSheet->mergeCells('A2:G2');
$rekapSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$rekapSheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header Tabel (Mulai baris 4)
$rekapSheet->setCellValue('A4', 'No');
$rekapSheet->setCellValue('B4', 'NIS');
$rekapSheet->setCellValue('C4', 'Nama Siswa');
$rekapSheet->setCellValue('D4', 'Kelas');
$rekapSheet->setCellValue('E4', 'Hadir (H)');
$rekapSheet->setCellValue('F4', 'Sakit (S)');
$rekapSheet->setCellValue('G4', 'Izin (I)');
$rekapSheet->setCellValue('H4', 'Alfa (A)');

// Styling Header
$rekapSheet->getStyle('A4:H4')->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEEEEE']],
    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
]);

// Isi Data Rekap Absensi
$row = 5;
$no = 1;
foreach ($rekap_absensi as $ra) {
    $rekapSheet->setCellValue('A' . $row, $no++);
    $rekapSheet->setCellValue('B' . $row, $ra['nis']);
    $rekapSheet->setCellValue('C' . $row, $ra['nama_siswa']);
    $rekapSheet->setCellValue('D' . $row, $ra['nama_kelas']);
    $rekapSheet->setCellValue('E' . $row, $ra['total_h']);
    $rekapSheet->setCellValue('F' . $row, $ra['total_s']);
    $rekapSheet->setCellValue('G' . $row, $ra['total_i']);
    $rekapSheet->setCellValue('H' . $row, $ra['total_a']);
    $row++;
}

// Atur lebar kolom
$rekapSheet->getColumnDimension('A')->setWidth(5);
$rekapSheet->getColumnDimension('B')->setWidth(20);
$rekapSheet->getColumnDimension('C')->setWidth(35);
$rekapSheet->getColumnDimension('D')->setWidth(15);
$rekapSheet->getColumnDimension('E')->setWidth(10);
$rekapSheet->getColumnDimension('F')->setWidth(10);
$rekapSheet->getColumnDimension('G')->setWidth(10);
$rekapSheet->getColumnDimension('H')->setWidth(10);

// ==========================================================

// 14. Atur Sheet aktif ke yang pertama
$spreadsheet->setActiveSheetIndex(0);

// 15. Perintahkan Browser untuk Download
$filename = 'Laporan_Jurnal_Lengkap_' . date('Y-m-d') . '.xlsx';
if (ob_get_contents()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

?>