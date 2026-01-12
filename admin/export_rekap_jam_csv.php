<?php
/*
File: admin/export_rekap_jam_csv.php
Export Rekap Jam Mengajar Guru ke CSV
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

// Query rekap jam guru
$sql = "
    SELECT 
        g.nip, 
        g.nama_guru,
        COUNT(DISTINCT m.id) as jumlah_kelas,
        COALESCE(SUM(m.jumlah_jam_mingguan), 0) as total_jam_mingguan,
        GROUP_CONCAT(DISTINCT CONCAT(mp.nama_mapel, ' (', k.nama_kelas, ')') SEPARATOR ', ') as daftar_mengajar
    FROM tbl_guru g
    LEFT JOIN tbl_mengajar m ON g.id = m.id_guru
    LEFT JOIN tbl_mapel mp ON m.id_mapel = mp.id
    LEFT JOIN tbl_kelas k ON m.id_kelas = k.id
    GROUP BY g.id, g.nip, g.nama_guru
    ORDER BY g.nama_guru ASC
";

$data = $pdo->query($sql)->fetchAll();

// Output CSV
$filename = "Rekap_Jam_Guru_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Header
fputcsv($output, ['No', 'NIP', 'Nama Guru', 'Jumlah Kelas', 'Total Jam/Minggu', 'Mengajar'], ';');

// Data
$no = 1;
foreach ($data as $row) {
    fputcsv($output, [
        $no++,
        $row['nip'] ?? '-',
        $row['nama_guru'],
        $row['jumlah_kelas'],
        $row['total_jam_mingguan'],
        $row['daftar_mengajar'] ?? '-'
    ], ';');
}

fclose($output);
exit;
