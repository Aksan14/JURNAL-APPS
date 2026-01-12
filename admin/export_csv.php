<?php
/*
File: admin/export_csv.php
Export Laporan Jurnal ke CSV
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

// Filter
$tanggal_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tanggal_selesai = $_GET['tanggal_selesai'] ?? date('Y-m-t');
$filter_id_guru = $_GET['id_guru'] ?? '';
$filter_id_kelas = $_GET['id_kelas'] ?? '';

// Build query
$filter_sql = " WHERE j.tanggal BETWEEN ? AND ?";
$params = [$tanggal_mulai, $tanggal_selesai];

if (!empty($filter_id_guru)) {
    $filter_sql .= " AND m.id_guru = ?";
    $params[] = $filter_id_guru;
}
if (!empty($filter_id_kelas)) {
    $filter_sql .= " AND m.id_kelas = ?";
    $params[] = $filter_id_kelas;
}

// Query
$sql = "
    SELECT j.tanggal, g.nama_guru, k.nama_kelas, mp.nama_mapel, j.jam_ke, j.topik_materi,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'H') as hadir,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'S') as sakit,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'I') as izin,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'A') as alpa
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    $filter_sql
    ORDER BY j.tanggal ASC, g.nama_guru ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Output CSV
$filename = "Laporan_Jurnal_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// BOM untuk Excel agar UTF-8 terbaca dengan benar
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Header
fputcsv($output, ['No', 'Tanggal', 'Guru', 'Kelas', 'Mata Pelajaran', 'Jam Ke', 'Materi', 'Hadir', 'Sakit', 'Izin', 'Alpa'], ';');

// Data
$no = 1;
foreach ($data as $row) {
    fputcsv($output, [
        $no++,
        date('d/m/Y', strtotime($row['tanggal'])),
        $row['nama_guru'],
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

fclose($output);
exit;
