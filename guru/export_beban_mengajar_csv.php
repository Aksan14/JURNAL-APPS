<?php
/*
File: guru/export_beban_mengajar_csv.php
Export Rekap Beban Mengajar Guru ke CSV
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

$user_id = $_SESSION['user_id'];

// Ambil ID dan data Guru
$stmt_guru = $pdo->prepare("SELECT id, nama_guru, nip FROM tbl_guru WHERE user_id = ?");
$stmt_guru->execute([$user_id]);
$guru = $stmt_guru->fetch();
if (!$guru) die('Akses ditolak');
$id_guru = $guru['id'];

// Filter
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$tanggal_mulai = date('Y-m-01', strtotime("$filter_tahun-$filter_bulan-01"));
$tanggal_selesai = date('Y-m-t', strtotime("$filter_tahun-$filter_bulan-01"));

// Nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Hitung minggu
$start_date = new DateTime($tanggal_mulai);
$end_date = new DateTime($tanggal_selesai);
$diff = $start_date->diff($end_date);
$total_hari = $diff->days + 1;
$total_minggu = floor($total_hari / 7);

// Hitung libur per hari
$hari_mapping = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
];

$stmt_libur = $pdo->prepare("
    SELECT DATE_FORMAT(h.tanggal, '%W') as nama_hari_en, h.id_kelas, COUNT(*) as jumlah_libur
    FROM tbl_hari_libur h
    WHERE h.tanggal BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(h.tanggal, '%W'), h.id_kelas
");
$stmt_libur->execute([$tanggal_mulai, $tanggal_selesai]);
$libur_per_hari_kelas = [];
while ($row = $stmt_libur->fetch()) {
    $hari_id = $hari_mapping[$row['nama_hari_en']] ?? $row['nama_hari_en'];
    $kelas_id = $row['id_kelas'] ?? 'all';
    if (!isset($libur_per_hari_kelas[$hari_id])) $libur_per_hari_kelas[$hari_id] = [];
    $libur_per_hari_kelas[$hari_id][$kelas_id] = $row['jumlah_libur'];
}

// Detail libur
$libur_detail = [];
$stmt_libur_d = $pdo->prepare("
    SELECT h.tanggal, h.nama_libur, h.id_kelas, DATE_FORMAT(h.tanggal, '%W') as hari_en, DATE_FORMAT(h.tanggal, '%d') as tgl
    FROM tbl_hari_libur h
    WHERE h.tanggal BETWEEN ? AND ?
    ORDER BY h.tanggal
");
$stmt_libur_d->execute([$tanggal_mulai, $tanggal_selesai]);
while ($ld = $stmt_libur_d->fetch()) {
    $hari_indo = $hari_mapping[$ld['hari_en']] ?? $ld['hari_en'];
    $kelas_key = $ld['id_kelas'] ?? 'all';
    if (!isset($libur_detail[$hari_indo])) $libur_detail[$hari_indo] = [];
    if (!isset($libur_detail[$hari_indo][$kelas_key])) $libur_detail[$hari_indo][$kelas_key] = [];
    $libur_detail[$hari_indo][$kelas_key][] = ['tgl' => $ld['tgl'], 'nama' => $ld['nama_libur']];
}

// Detail jam khusus
$jk_detail = [];
$stmt_jk_d = $pdo->prepare("
    SELECT jk.tanggal, jk.alasan, jk.max_jam, jk.id_kelas, DATE_FORMAT(jk.tanggal, '%W') as hari_en, DATE_FORMAT(jk.tanggal, '%d') as tgl
    FROM tbl_jam_khusus jk
    WHERE jk.tanggal BETWEEN ? AND ?
    ORDER BY jk.tanggal
");
$stmt_jk_d->execute([$tanggal_mulai, $tanggal_selesai]);
while ($jk = $stmt_jk_d->fetch()) {
    $hari_indo = $hari_mapping[$jk['hari_en']] ?? $jk['hari_en'];
    $kelas_key = $jk['id_kelas'] ?? 'all';
    if (!isset($jk_detail[$hari_indo])) $jk_detail[$hari_indo] = [];
    if (!isset($jk_detail[$hari_indo][$kelas_key])) $jk_detail[$hari_indo][$kelas_key] = [];
    $jk_detail[$hari_indo][$kelas_key][] = ['tgl' => $jk['tgl'], 'alasan' => $jk['alasan'], 'max_jam' => $jk['max_jam']];
}

function getJumlahLibur($hari, $id_kelas, $data) {
    return ($data[$hari]['all'] ?? 0) + ($data[$hari][$id_kelas] ?? 0);
}

function getLiburText($hari, $id_kelas, $detail) {
    $result = [];
    if (isset($detail[$hari]['all'])) {
        foreach ($detail[$hari]['all'] as $lib) {
            $result[] = "Libur Tgl " . $lib['tgl'] . " (" . $lib['nama'] . ")";
        }
    }
    if (isset($detail[$hari][$id_kelas])) {
        foreach ($detail[$hari][$id_kelas] as $lib) {
            $result[] = "Libur Tgl " . $lib['tgl'] . " (" . $lib['nama'] . ")";
        }
    }
    return $result;
}

function getJKText($hari, $id_kelas, $detail) {
    $result = [];
    if (isset($detail[$hari]['all'])) {
        foreach ($detail[$hari]['all'] as $jk) {
            $result[] = "Jam Khusus Tgl " . $jk['tgl'] . " Max " . $jk['max_jam'] . " (" . $jk['alasan'] . ")";
        }
    }
    if (isset($detail[$hari][$id_kelas])) {
        foreach ($detail[$hari][$id_kelas] as $jk) {
            $result[] = "Jam Khusus Tgl " . $jk['tgl'] . " Max " . $jk['max_jam'] . " (" . $jk['alasan'] . ")";
        }
    }
    return $result;
}

// Rekap data
$stmt_rekap = $pdo->prepare("
    SELECT 
        m.id as id_mengajar, m.id_kelas, mp.nama_mapel, k.nama_kelas, m.jumlah_jam_mingguan as roster_mingguan, m.hari,
        COALESCE(SUM(
            CASE WHEN j.jam_ke LIKE '%-%' THEN 
                CAST(SUBSTRING_INDEX(j.jam_ke, '-', -1) AS UNSIGNED) - CAST(SUBSTRING_INDEX(j.jam_ke, '-', 1) AS UNSIGNED) + 1
            ELSE CASE WHEN j.jam_ke IS NOT NULL AND j.jam_ke != '' THEN 1 ELSE 0 END END
        ), 0) as jam_terlaksana
    FROM tbl_mengajar m
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    LEFT JOIN tbl_jurnal j ON j.id_mengajar = m.id AND j.tanggal BETWEEN ? AND ?
    WHERE m.id_guru = ?
    GROUP BY m.id, m.id_kelas, mp.nama_mapel, k.nama_kelas, m.jumlah_jam_mingguan, m.hari
    ORDER BY mp.nama_mapel, k.nama_kelas
");
$stmt_rekap->execute([$tanggal_mulai, $tanggal_selesai, $id_guru]);
$rekap_data = $stmt_rekap->fetchAll();

// Output CSV
$filename = "Beban_Mengajar_" . str_replace(' ', '_', $guru['nama_guru']) . "_" . $nama_bulan[$filter_bulan] . "_" . $filter_tahun . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'w');

// Info header
fputcsv($output, ["REKAP BEBAN MENGAJAR GURU"], ';');
fputcsv($output, ["Nama Guru: " . $guru['nama_guru']], ';');
fputcsv($output, ["NIP: " . ($guru['nip'] ?? '-')], ';');
fputcsv($output, ["Periode: " . $nama_bulan[$filter_bulan] . " " . $filter_tahun], ';');
fputcsv($output, ["Jumlah Minggu: " . $total_minggu . " minggu"], ';');
fputcsv($output, [], ';');

// Header tabel
fputcsv($output, ['No', 'Mata Pelajaran', 'Kelas', 'Hari', 'Roster/Minggu', 'Target Bulan', 'Keterangan Libur', 'Terlaksana', 'Selisih', 'Persentase'], ';');

// Data
$no = 1;
$total_roster = 0;
$total_target = 0;
$total_terlaksana = 0;

foreach ($rekap_data as $row) {
    $roster = (int)$row['roster_mingguan'];
    $hari = $row['hari'];
    $id_kelas = $row['id_kelas'];
    $jumlah_libur = getJumlahLibur($hari, $id_kelas, $libur_per_hari_kelas);
    
    $target_awal = $roster * $total_minggu;
    $pengurangan = $roster * $jumlah_libur;
    $target = max(0, $target_awal - $pengurangan);
    
    $terlaksana = (int)$row['jam_terlaksana'];
    $selisih = $terlaksana - $target;
    $persen = $target > 0 ? round(($terlaksana / $target) * 100, 1) : 0;
    
    // Format Libur/Jam Khusus
    $libur_texts = getLiburText($hari, $id_kelas, $libur_detail);
    $jk_texts = getJKText($hari, $id_kelas, $jk_detail);
    $libur_jk_info = array_merge($libur_texts, $jk_texts);
    $libur_jk_text = !empty($libur_jk_info) ? implode('; ', $libur_jk_info) : '-';
    
    fputcsv($output, [
        $no++,
        $row['nama_mapel'],
        $row['nama_kelas'],
        $hari,
        $roster,
        $target,
        $libur_jk_text,
        $terlaksana,
        ($selisih >= 0 ? '+' : '') . $selisih,
        $persen . '%'
    ], ';');
    
    $total_roster += $roster;
    $total_target += $target;
    $total_terlaksana += $terlaksana;
}

// Total
fputcsv($output, [], ';');
$total_selisih = $total_terlaksana - $total_target;
$total_persen = $total_target > 0 ? round(($total_terlaksana / $total_target) * 100, 1) : 0;
fputcsv($output, ['', '', '', 'TOTAL', $total_roster, $total_target, '', $total_terlaksana, ($total_selisih >= 0 ? '+' : '') . $total_selisih, $total_persen . '%'], ';');

fclose($output);
exit;
