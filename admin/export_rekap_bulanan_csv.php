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

// Hitung jumlah hari libur per hari dalam bulan ini
$stmt_libur = $pdo->prepare("
    SELECT 
        DATE_FORMAT(h.tanggal, '%W') as nama_hari_en,
        h.id_kelas,
        COUNT(*) as jumlah_libur
    FROM tbl_hari_libur h
    WHERE h.tanggal BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(h.tanggal, '%W'), h.id_kelas
");
$stmt_libur->execute([$tanggal_mulai, $tanggal_selesai]);

$hari_mapping = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
];

$libur_per_hari_kelas = [];
while ($row_libur = $stmt_libur->fetch()) {
    $hari_id = $hari_mapping[$row_libur['nama_hari_en']] ?? $row_libur['nama_hari_en'];
    $kelas_id = $row_libur['id_kelas'] ?? 'all';
    if (!isset($libur_per_hari_kelas[$hari_id])) {
        $libur_per_hari_kelas[$hari_id] = [];
    }
    $libur_per_hari_kelas[$hari_id][$kelas_id] = $row_libur['jumlah_libur'];
}

function getJumlahLiburCSV($hari, $id_kelas, $libur_data) {
    $libur_semua = $libur_data[$hari]['all'] ?? 0;
    $libur_kelas = $libur_data[$hari][$id_kelas] ?? 0;
    return $libur_semua + $libur_kelas;
}

// Query rekap per guru dengan detail jadwal
$sql = "
    SELECT 
        g.id as id_guru,
        g.nip,
        g.nama_guru,
        m.id_kelas,
        m.hari,
        m.jumlah_jam_mingguan,
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
            WHERE j2.id_mengajar = m.id AND j2.tanggal BETWEEN ? AND ?
        ) as jam_terlaksana_jadwal,
        (
            SELECT COUNT(DISTINCT j3.id)
            FROM tbl_jurnal j3
            WHERE j3.id_mengajar = m.id AND j3.tanggal BETWEEN ? AND ?
        ) as pertemuan_jadwal
    FROM tbl_guru g
    LEFT JOIN tbl_mengajar m ON g.id = m.id_guru
    WHERE m.id IS NOT NULL
    ORDER BY g.nama_guru ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$tanggal_mulai, $tanggal_selesai, $tanggal_mulai, $tanggal_selesai]);
$raw_data = $stmt->fetchAll();

// Proses dan kelompokkan per guru dengan perhitungan libur
$data = [];
foreach ($raw_data as $row) {
    $id_guru = $row['id_guru'];
    
    if (!isset($data[$id_guru])) {
        $data[$id_guru] = [
            'id_guru' => $id_guru,
            'nip' => $row['nip'],
            'nama_guru' => $row['nama_guru'],
            'jumlah_kelas' => 0,
            'total_roster_mingguan' => 0,
            'target_bulan' => 0,
            'jam_terlaksana' => 0,
            'total_pertemuan' => 0
        ];
    }
    
    // Hitung libur untuk jadwal ini
    $jumlah_libur = getJumlahLiburCSV($row['hari'], $row['id_kelas'], $libur_per_hari_kelas);
    $roster = (int)$row['jumlah_jam_mingguan'];
    $target = max(0, ($roster * $minggu) - ($roster * $jumlah_libur));
    
    $data[$id_guru]['jumlah_kelas']++;
    $data[$id_guru]['total_roster_mingguan'] += $roster;
    $data[$id_guru]['target_bulan'] += $target;
    $data[$id_guru]['jam_terlaksana'] += (int)$row['jam_terlaksana_jadwal'];
    $data[$id_guru]['total_pertemuan'] += (int)$row['pertemuan_jadwal'];
}

$data = array_values($data);

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
    $target = $row['target_bulan']; // Sudah dihitung dengan pengurangan libur
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
    $total_target += $row['target_bulan'];
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
