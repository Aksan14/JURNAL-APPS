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

// Ambil semua roster mengajar
$sql_roster = "
    SELECT g.id AS id_guru, g.nama_guru, m.id AS id_mengajar,
           mp.nama_mapel, k.nama_kelas, m.jumlah_jam_mingguan AS jam_roster_mingguan
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
        'jam_roster' => $jam_roster,
        'jam_terlaksana' => $jam_terlaksana
    ];

    $hasil_rekap[$id_guru]['total_jam_roster'] += $jam_roster;
    $hasil_rekap[$id_guru]['total_jam_terlaksana'] += $jam_terlaksana;
}

// Hitung seharusnya
foreach ($hasil_rekap as $id => &$data) {
    $data['total_jam_seharusnya'] = $data['total_jam_roster'] * $total_minggu_penuh;
}
unset($data);

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
fputcsv($output, ['No', 'Nama Guru', 'Mapel - Kelas', 'Jam Roster Per Minggu', 'Jam Roster Seharusnya', 'Jam Terlaksana', 'Selisih', 'Total Rekap Guru']);

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
            $jam_seharusnya = $ass['jam_roster'] * $total_minggu_penuh;
            $selisih = $ass['jam_terlaksana'] - $jam_seharusnya;
            $selisih_text = ($selisih > 0 ? '+' : '') . $selisih;
            
            fputcsv($output, [
                $firstRow ? $no : '',
                $firstRow ? $data['nama_guru'] : '',
                $ass['mapel_kelas'],
                $ass['jam_roster'],
                $jam_seharusnya,
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
            $totalRekap
        ]);
    }
    $no++;
}

fclose($output);
exit;
