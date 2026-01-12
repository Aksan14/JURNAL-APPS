<?php
/*
File: admin/export_rekap_bulanan_csv.php
Export Rekap Bulanan Per Guru ke CSV (Format Tabel Rapi)
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

// Filter
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$tanggal_mulai = "$tahun-$bulan-01";
$tanggal_selesai = date('Y-m-t', strtotime($tanggal_mulai));

// Nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Hitung minggu
$start = new DateTime($tanggal_mulai);
$end = new DateTime($tanggal_selesai);
$minggu = max(1, floor($start->diff($end)->days / 7));

// Query rekap per guru
$sql = "
    SELECT 
        g.id as id_guru,
        g.nip,
        g.nama_guru,
        COUNT(DISTINCT m.id) as jumlah_kelas,
        COALESCE(SUM(m.jumlah_jam_mingguan), 0) as total_roster_mingguan,
        (
            SELECT COALESCE(SUM(
                CASE 
                    WHEN j2.jam_ke LIKE '%-%' THEN 
                        CAST(SUBSTRING_INDEX(j2.jam_ke, '-', -1) AS UNSIGNED) - 
                        CAST(SUBSTRING_INDEX(j2.jam_ke, '-', 1) AS UNSIGNED) + 1
                    ELSE 1
                END
            ), 0)
            FROM tbl_jurnal j2
            JOIN tbl_mengajar m2 ON j2.id_mengajar = m2.id
            WHERE m2.id_guru = g.id AND j2.tanggal BETWEEN ? AND ?
        ) as jam_terlaksana,
        (
            SELECT COUNT(DISTINCT j3.id)
            FROM tbl_jurnal j3
            JOIN tbl_mengajar m3 ON j3.id_mengajar = m3.id
            WHERE m3.id_guru = g.id AND j3.tanggal BETWEEN ? AND ?
        ) as total_pertemuan
    FROM tbl_guru g
    LEFT JOIN tbl_mengajar m ON g.id = m.id_guru
    GROUP BY g.id, g.nip, g.nama_guru
    ORDER BY g.nama_guru ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$tanggal_mulai, $tanggal_selesai, $tanggal_mulai, $tanggal_selesai]);
$data = $stmt->fetchAll();

// Output CSV
$filename = "Rekap_Bulanan_Guru_" . $nama_bulan[$bulan] . "_" . $tahun . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Header info
fputcsv($output, ["REKAP JURNAL BULANAN PER GURU"], ';');
fputcsv($output, ["Periode: " . $nama_bulan[$bulan] . " " . $tahun], ';');
fputcsv($output, ["Jumlah Minggu: " . $minggu . " minggu"], ';');
fputcsv($output, ["Tanggal Export: " . date('d/m/Y H:i:s')], ';');
fputcsv($output, [], ';');

// Header tabel
fputcsv($output, ['No', 'NIP', 'Nama Guru', 'Jml Kelas', 'Roster/Minggu', 'Target Bulan', 'Terlaksana', 'Selisih', 'Pertemuan', 'Persentase'], ';');

// Data
$no = 1;
$total_roster = 0;
$total_target = 0;
$total_terlaksana = 0;
$total_pertemuan = 0;

foreach ($data as $row) {
    $target = $row['total_roster_mingguan'] * $minggu;
    $selisih = $row['jam_terlaksana'] - $target;
    $persen = $target > 0 ? round(($row['jam_terlaksana'] / $target) * 100, 1) : 0;
    
    fputcsv($output, [
        $no++,
        $row['nip'] ?? '-',
        $row['nama_guru'],
        $row['jumlah_kelas'],
        $row['total_roster_mingguan'],
        $target,
        $row['jam_terlaksana'],
        ($selisih >= 0 ? '+' : '') . $selisih,
        $row['total_pertemuan'],
        $persen . '%'
    ], ';');
    
    $total_roster += $row['total_roster_mingguan'];
    $total_target += $target;
    $total_terlaksana += $row['jam_terlaksana'];
    $total_pertemuan += $row['total_pertemuan'];
}

// Footer total
fputcsv($output, [], ';');
$total_selisih = $total_terlaksana - $total_target;
$total_persen = $total_target > 0 ? round(($total_terlaksana / $total_target) * 100, 1) : 0;
fputcsv($output, ['', '', 'TOTAL', '', $total_roster, $total_target, $total_terlaksana, ($total_selisih >= 0 ? '+' : '') . $total_selisih, $total_pertemuan, $total_persen . '%'], ';');

fclose($output);
exit;
