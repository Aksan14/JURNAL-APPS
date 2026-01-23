<?php
/*
File: admin/export_rekap_kehadiran_guru_csv.php
Export CSV Laporan Kehadiran Guru
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

// Filter
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_guru = $_GET['guru'] ?? '';
$filter_status = $_GET['status'] ?? '';

$nama_bulan = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];

$status_labels = [
    'tidak_hadir' => 'Tidak Hadir',
    'sakit' => 'Sakit',
    'izin' => 'Izin',
    'cuti' => 'Cuti'
];

// Build query
$where = "WHERE MONTH(k.tanggal) = :bulan AND YEAR(k.tanggal) = :tahun";
$params = ['bulan' => $filter_bulan, 'tahun' => $filter_tahun];

if (!empty($filter_guru)) {
    $where .= " AND k.id_guru = :guru";
    $params['guru'] = $filter_guru;
}

if (!empty($filter_status)) {
    $where .= " AND k.status_kehadiran = :status";
    $params['status'] = $filter_status;
}

// Query data
$sql = "
    SELECT k.tanggal, k.status_kehadiran, k.keterangan, k.created_at,
           g.nama_guru, g.nip,
           u.username as admin_username
    FROM tbl_kehadiran_guru k
    JOIN tbl_guru g ON k.id_guru = g.id
    LEFT JOIN tbl_users u ON k.created_by = u.id
    $where
    ORDER BY k.tanggal DESC, g.nama_guru ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Set header untuk download CSV
$filename = "Laporan_Kehadiran_Guru_" . $nama_bulan[$filter_bulan] . "_" . $filter_tahun . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Output CSV
$output = fopen('php://output', 'w');

// BOM untuk UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header
fputcsv($output, ['LAPORAN KEHADIRAN GURU']);
fputcsv($output, ['Periode: ' . $nama_bulan[$filter_bulan] . ' ' . $filter_tahun]);
fputcsv($output, ['Tanggal Export: ' . date('d/m/Y H:i:s')]);
fputcsv($output, []); // Baris kosong

// Header tabel
fputcsv($output, ['No', 'Tanggal', 'Nama Guru', 'NIP', 'Status', 'Keterangan', 'Diinput Oleh', 'Waktu Input']);

// Data
$no = 1;
foreach ($data as $row) {
    fputcsv($output, [
        $no++,
        date('d/m/Y', strtotime($row['tanggal'])),
        $row['nama_guru'],
        $row['nip'] ?? '-',
        $status_labels[$row['status_kehadiran']],
        $row['keterangan'] ?: '-',
        $row['admin_username'] ?? 'System',
        date('d/m/Y H:i', strtotime($row['created_at']))
    ]);
}

fclose($output);
exit;
