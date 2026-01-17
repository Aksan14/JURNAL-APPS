<?php
/*
File: guru/export_jurnal_csv.php
Export Laporan Jurnal Guru ke CSV
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

$user_id = $_SESSION['user_id'];
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Ambil data guru
$stmt_g = $pdo->prepare("SELECT id, nama_guru, nip FROM tbl_guru WHERE user_id = ?");
$stmt_g->execute([$user_id]);
$guru = $stmt_g->fetch();

// Validasi: Pastikan guru ditemukan
if (!$guru) {
    die('Data guru tidak ditemukan. Silakan hubungi administrator.');
}

$id_guru = $guru['id'];

// Nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Query jurnal
$sql = "
    SELECT j.tanggal, j.jam_ke, j.topik_materi, k.nama_kelas, mp.nama_mapel,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'H') as hadir,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'S') as sakit,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'I') as izin,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'A') as alpa
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE m.id_guru = ? AND MONTH(j.tanggal) = ? AND YEAR(j.tanggal) = ?
    ORDER BY j.tanggal ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_guru, $bulan, $tahun]);
$data = $stmt->fetchAll();

// Output CSV
$filename = "Jurnal_" . str_replace(' ', '_', $guru['nama_guru']) . "_" . $nama_bulan[$bulan] . "_" . $tahun . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Info header
fputcsv($output, ["LAPORAN JURNAL MENGAJAR"], ';');
fputcsv($output, ["Nama Guru: " . $guru['nama_guru']], ';');
fputcsv($output, ["NIP: " . ($guru['nip'] ?? '-')], ';');
fputcsv($output, ["Periode: " . $nama_bulan[$bulan] . " " . $tahun], ';');
fputcsv($output, [], ';');

// Header tabel
fputcsv($output, ['No', 'Tanggal', 'Kelas', 'Mata Pelajaran', 'Jam Ke', 'Materi', 'Hadir', 'Sakit', 'Izin', 'Alpa'], ';');

// Data
$no = 1;
foreach ($data as $row) {
    fputcsv($output, [
        $no++,
        date('d/m/Y', strtotime($row['tanggal'])),
        $row['nama_kelas'],
        $row['nama_mapel'],
        $row['jam_ke'],
        $row['topik_materi'],
        $row['hadir'],
        $row['sakit'],
        $row['izin'],
        $row['alpa']
    ], ';');
}

// Summary
fputcsv($output, [], ';');
fputcsv($output, ["Total Pertemuan: " . count($data)], ';');

fclose($output);
exit;
