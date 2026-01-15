<?php
/*
File: export_excel_bulanan_guru_detail.php
Export Excel Rekap Jurnal Bulanan Per Guru (Detail)
Menggunakan CSV untuk kompatibilitas maksimal
*/

ob_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

// Fungsi Helper
function calculateHours($jam_ke_str) {
    $jam_ke_str = trim($jam_ke_str);
    if (strpos($jam_ke_str, '-') !== false) { 
        $parts = explode('-', $jam_ke_str); 
        if (count($parts) == 2) { 
            $start = (int)trim($parts[0]); 
            $end = (int)trim($parts[1]); 
            if ($end >= $start) return ($end - $start) + 1;
        } 
    }
    if (is_numeric($jam_ke_str) && (int)$jam_ke_str > 0) return 1;
    if (strpos($jam_ke_str, ',') !== false) return count(explode(',', $jam_ke_str));
    return 0;
}

// Filter
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$tanggal_mulai = date('Y-m-01', strtotime("$filter_tahun-$filter_bulan-01"));
$tanggal_selesai = date('Y-m-t', strtotime("$filter_tahun-$filter_bulan-01"));

// Hitung minggu
$start_date = new DateTime($tanggal_mulai);
$end_date = new DateTime($tanggal_selesai);
$diff = $start_date->diff($end_date);
$total_hari_filter = $diff->days + 1;
$total_minggu_penuh = floor($total_hari_filter / 7);

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

function getJumlahLiburExcel($hari, $id_kelas, $libur_data) {
    $libur_semua = $libur_data[$hari]['all'] ?? 0;
    $libur_kelas = $libur_data[$hari][$id_kelas] ?? 0;
    return $libur_semua + $libur_kelas;
}

// Ambil detail libur per hari dan kelas
$libur_detail_per_hari_excel = [];
$stmt_libur_detail = $pdo->prepare("
    SELECT h.tanggal, h.nama_libur, h.id_kelas,
           DATE_FORMAT(h.tanggal, '%W') as hari_en,
           DATE_FORMAT(h.tanggal, '%d') as tgl
    FROM tbl_hari_libur h
    WHERE h.tanggal BETWEEN ? AND ?
    ORDER BY h.tanggal
");
$stmt_libur_detail->execute([$tanggal_mulai, $tanggal_selesai]);
while ($ld = $stmt_libur_detail->fetch()) {
    $hari_indo = $hari_mapping[$ld['hari_en']] ?? $ld['hari_en'];
    $kelas_key = $ld['id_kelas'] ?? 'all';
    if (!isset($libur_detail_per_hari_excel[$hari_indo])) {
        $libur_detail_per_hari_excel[$hari_indo] = [];
    }
    if (!isset($libur_detail_per_hari_excel[$hari_indo][$kelas_key])) {
        $libur_detail_per_hari_excel[$hari_indo][$kelas_key] = [];
    }
    $libur_detail_per_hari_excel[$hari_indo][$kelas_key][] = [
        'tgl' => $ld['tgl'],
        'nama' => $ld['nama_libur']
    ];
}

// Ambil detail jam khusus per hari dan kelas
$jk_detail_per_hari_excel = [];
$stmt_jk_detail = $pdo->prepare("
    SELECT jk.tanggal, jk.alasan, jk.max_jam, jk.id_kelas,
           DATE_FORMAT(jk.tanggal, '%W') as hari_en,
           DATE_FORMAT(jk.tanggal, '%d') as tgl
    FROM tbl_jam_khusus jk
    WHERE jk.tanggal BETWEEN ? AND ?
    ORDER BY jk.tanggal
");
$stmt_jk_detail->execute([$tanggal_mulai, $tanggal_selesai]);
while ($jk = $stmt_jk_detail->fetch()) {
    $hari_indo = $hari_mapping[$jk['hari_en']] ?? $jk['hari_en'];
    $kelas_key = $jk['id_kelas'] ?? 'all';
    if (!isset($jk_detail_per_hari_excel[$hari_indo])) {
        $jk_detail_per_hari_excel[$hari_indo] = [];
    }
    if (!isset($jk_detail_per_hari_excel[$hari_indo][$kelas_key])) {
        $jk_detail_per_hari_excel[$hari_indo][$kelas_key] = [];
    }
    $jk_detail_per_hari_excel[$hari_indo][$kelas_key][] = [
        'tgl' => $jk['tgl'],
        'alasan' => $jk['alasan'],
        'max_jam' => $jk['max_jam']
    ];
}

function getLiburDetailExcel($hari, $id_kelas, $libur_detail) {
    $result = [];
    if (isset($libur_detail[$hari]['all'])) {
        $result = array_merge($result, $libur_detail[$hari]['all']);
    }
    if (isset($libur_detail[$hari][$id_kelas])) {
        $result = array_merge($result, $libur_detail[$hari][$id_kelas]);
    }
    return $result;
}

function getJamKhususDetailExcel($hari, $id_kelas, $jk_detail) {
    $result = [];
    if (isset($jk_detail[$hari]['all'])) {
        $result = array_merge($result, $jk_detail[$hari]['all']);
    }
    if (isset($jk_detail[$hari][$id_kelas])) {
        $result = array_merge($result, $jk_detail[$hari][$id_kelas]);
    }
    return $result;
}

// Ambil semua roster mengajar dengan info hari dan kelas
$sql_roster = "
    SELECT g.id AS id_guru, g.nama_guru, m.id AS id_mengajar,
           mp.nama_mapel, k.nama_kelas, m.jumlah_jam_mingguan AS jam_roster_mingguan,
           m.hari, m.id_kelas
    FROM tbl_mengajar m
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    ORDER BY g.nama_guru ASC, mp.nama_mapel ASC
";
$semua_roster = $pdo->query($sql_roster)->fetchAll();

// Ambil jurnal bulan ini
$sql_jurnal = "SELECT j.id_mengajar, j.jam_ke FROM tbl_jurnal j WHERE j.tanggal BETWEEN ? AND ?";
$stmt_jurnal = $pdo->prepare($sql_jurnal);
$stmt_jurnal->execute([$tanggal_mulai, $tanggal_selesai]);
$data_jurnal = $stmt_jurnal->fetchAll();

// Hitung jam terlaksana
$jam_terlaksana_per_mengajar = [];
foreach ($data_jurnal as $jurnal) {
    $id_mengajar = $jurnal['id_mengajar'];
    $jam = calculateHours($jurnal['jam_ke']);
    $jam_terlaksana_per_mengajar[$id_mengajar] = ($jam_terlaksana_per_mengajar[$id_mengajar] ?? 0) + $jam;
}

// Proses data per guru
$hasil_rekap = [];
foreach ($semua_roster as $roster) {
    $id_guru = $roster['id_guru'];
    $nama_guru = $roster['nama_guru'];
    $id_mengajar = $roster['id_mengajar'];
    $mapel_kelas = $roster['nama_mapel'] . ' - ' . $roster['nama_kelas'];
    $jam_roster = (int)$roster['jam_roster_mingguan'];
    $jam_terlaksana = $jam_terlaksana_per_mengajar[$id_mengajar] ?? 0;
    
    // Hitung pengurangan libur
    $jumlah_libur = getJumlahLiburExcel($roster['hari'], $roster['id_kelas'], $libur_per_hari_kelas);
    $jam_seharusnya = max(0, ($jam_roster * $total_minggu_penuh) - ($jam_roster * $jumlah_libur));
    
    // Ambil detail libur dan jam khusus
    $detail_libur = getLiburDetailExcel($roster['hari'], $roster['id_kelas'], $libur_detail_per_hari_excel);
    $detail_jk = getJamKhususDetailExcel($roster['hari'], $roster['id_kelas'], $jk_detail_per_hari_excel);

    if (!isset($hasil_rekap[$id_guru])) {
        $hasil_rekap[$id_guru] = [
            'nama_guru' => $nama_guru,
            'assignments' => [],
            'total_jam_terlaksana' => 0,
            'total_jam_roster' => 0,
            'total_jam_seharusnya' => 0
        ];
    }

    $hasil_rekap[$id_guru]['assignments'][] = [
        'mapel_kelas' => $mapel_kelas,
        'hari' => $roster['hari'],
        'jam_roster' => $jam_roster,
        'jam_terlaksana' => $jam_terlaksana,
        'jam_seharusnya' => $jam_seharusnya,
        'detail_libur' => $detail_libur,
        'detail_jk' => $detail_jk
    ];

    $hasil_rekap[$id_guru]['total_jam_roster'] += $jam_roster;
    $hasil_rekap[$id_guru]['total_jam_terlaksana'] += $jam_terlaksana;
    $hasil_rekap[$id_guru]['total_jam_seharusnya'] += $jam_seharusnya;
}

// Tambah guru tanpa jadwal
$semua_guru = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($semua_guru as $guru) {
    if (!isset($hasil_rekap[$guru['id']])) {
        $hasil_rekap[$guru['id']] = [
            'nama_guru' => $guru['nama_guru'],
            'assignments' => [],
            'total_jam_terlaksana' => 0,
            'total_jam_roster' => 0,
            'total_jam_seharusnya' => 0
        ];
    }
}

uasort($hasil_rekap, function($a, $b) { return strcmp($a['nama_guru'], $b['nama_guru']); });

$daftar_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$nama_bulan_tahun = $daftar_bulan[$filter_bulan] . ' ' . $filter_tahun;

// ==========================================================
// GENERATE CSV (100% kompatibel dengan Excel)
// ==========================================================

// Clear semua output buffer
while (ob_get_level()) {
    ob_end_clean();
}

$filename = 'Rekap_Bulanan_Guru_Detail_' . $filter_tahun . '-' . $filter_bulan . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// BOM untuk UTF-8 agar Excel membaca karakter dengan benar
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Judul
fputcsv($output, ['REKAP JURNAL BULANAN PER GURU (DETAIL)']);
fputcsv($output, ['Bulan: ' . $nama_bulan_tahun]);
fputcsv($output, []); // Baris kosong

// Header
fputcsv($output, ['No', 'Nama Guru', 'Mapel - Kelas', 'Hari', 'Jam Roster Per Minggu', 'Jam Roster Seharusnya', 'Keterangan Libur', 'Jam Terlaksana', 'Selisih', 'Total Rekap Guru']);

// Data
$no = 1;
foreach ($hasil_rekap as $data) {
    $assignments = $data['assignments'];
    $total_selisih = $data['total_jam_terlaksana'] - $data['total_jam_seharusnya'];
    $total_selisih_text = ($total_selisih > 0 ? '+' : '') . $total_selisih;
    
    $totalRekap = "Roster/Mg: " . $data['total_jam_roster'] . " | Seharusnya: " . $data['total_jam_seharusnya'] . " | Terlaksana: " . $data['total_jam_terlaksana'] . " | Selisih: " . $total_selisih_text;
    
    if (count($assignments) > 0) {
        $firstRow = true;
        foreach ($assignments as $ass) {
            $jam_seharusnya = $ass['jam_seharusnya'];
            $selisih = $ass['jam_terlaksana'] - $jam_seharusnya;
            $selisih_text = ($selisih > 0 ? '+' : '') . $selisih;
            
            // Format Libur/Jam Khusus
            $libur_jk_info = [];
            if (!empty($ass['detail_libur'])) {
                foreach ($ass['detail_libur'] as $lib) {
                    $libur_jk_info[] = "Libur Tgl " . $lib['tgl'] . " (" . $lib['nama'] . ")";
                }
            }
            if (!empty($ass['detail_jk'])) {
                foreach ($ass['detail_jk'] as $jk) {
                    $libur_jk_info[] = "Jam Khusus Tgl " . $jk['tgl'] . " Max " . $jk['max_jam'] . " (" . $jk['alasan'] . ")";
                }
            }
            $libur_jk_text = !empty($libur_jk_info) ? implode('; ', $libur_jk_info) : '-';
            
            fputcsv($output, [
                $firstRow ? $no : '',
                $firstRow ? $data['nama_guru'] : '',
                $ass['mapel_kelas'],
                $ass['hari'] ?? '-',
                $ass['jam_roster'],
                $jam_seharusnya,
                $libur_jk_text,
                $ass['jam_terlaksana'],
                $selisih_text,
                $firstRow ? $totalRekap : ''
            ]);
            $firstRow = false;
        }
    } else {
        fputcsv($output, [
            $no,
            $data['nama_guru'],
            'Tidak ada jadwal/jurnal',
            '-',
            '-',
            '-',
            '-',
            '-',
            '-',
            $totalRekap
        ]);
    }
    $no++;
}

fclose($output);
exit;
