<?php
/*
File: guru/export_beban_mengajar_pdf.php
Export Rekap Beban Mengajar Guru ke PDF
*/

ob_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] !== 'guru' && $_SESSION['role'] !== 'walikelas')) {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php';

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

function getLiburJKText($hari, $id_kelas, $libur_detail, $jk_detail) {
    $result = [];
    if (isset($libur_detail[$hari]['all'])) {
        foreach ($libur_detail[$hari]['all'] as $lib) {
            $result[] = "Libur:" . $lib['tgl'];
        }
    }
    if (isset($libur_detail[$hari][$id_kelas])) {
        foreach ($libur_detail[$hari][$id_kelas] as $lib) {
            $result[] = "Libur:" . $lib['tgl'];
        }
    }
    if (isset($jk_detail[$hari]['all'])) {
        foreach ($jk_detail[$hari]['all'] as $jk) {
            $result[] = "JamKhusus:" . $jk['tgl'];
        }
    }
    if (isset($jk_detail[$hari][$id_kelas])) {
        foreach ($jk_detail[$hari][$id_kelas] as $jk) {
            $result[] = "JamKhusus:" . $jk['tgl'];
        }
    }
    return !empty($result) ? implode(', ', $result) : '-';
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

// ==========================================================
// BUAT PDF
// ==========================================================
class PDF extends FPDF {
    protected $title_text;
    protected $guru_name;
    protected $guru_nip;
    protected $colWidths;
    protected $tableWidth;
    protected $leftMargin;
    
    function SetTitleText($t) { $this->title_text = $t; }
    function SetGuruInfo($name, $nip) { 
        $this->guru_name = $name; 
        $this->guru_nip = $nip; 
    }
    function SetColWidths($w) { 
        $this->colWidths = $w; 
        $this->tableWidth = array_sum($w);
        $this->leftMargin = (297 - $this->tableWidth) / 2;
    }
    
    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'REKAP BEBAN MENGAJAR', 0, 1, 'C');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 7, 'Periode: ' . $this->title_text, 0, 1, 'C');
        
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(30, 6, 'Nama Guru', 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, ': ' . $this->guru_name, 0, 1, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(30, 6, 'NIP', 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, ': ' . $this->guru_nip, 0, 1, 'L');
        
        $this->Ln(5);
        $this->DrawTableHeader();
    }
    
    function DrawTableHeader() {
        $w = $this->colWidths;
        $x = $this->leftMargin;
        
        $this->SetX($x);
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(52, 58, 64);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
        
        $this->Cell($w[0], 10, 'No', 1, 0, 'C', true);
        $this->Cell($w[1], 10, 'Mata Pelajaran', 1, 0, 'C', true);
        $this->Cell($w[2], 10, 'Kelas', 1, 0, 'C', true);
        $this->Cell($w[3], 10, 'Hari', 1, 0, 'C', true);
        $this->Cell($w[4], 10, 'Roster/Mg', 1, 0, 'C', true);
        $this->Cell($w[5], 10, 'Target', 1, 0, 'C', true);
        $this->Cell($w[6], 10, 'Ket. Libur', 1, 0, 'C', true);
        $this->Cell($w[7], 10, 'Terlaksana', 1, 0, 'C', true);
        $this->Cell($w[8], 10, 'Selisih', 1, 0, 'C', true);
        $this->Cell($w[9], 10, '%', 1, 1, 'C', true);
        
        $this->SetTextColor(0, 0, 0);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    function CheckPageBreak($h) {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }
    
    function GetLeftMargin() { return $this->leftMargin; }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetTitleText($nama_bulan[$filter_bulan] . ' ' . $filter_tahun);
$pdf->SetGuruInfo($guru['nama_guru'], $guru['nip'] ?? '-');

// No(10), Mapel(45), Kelas(22), Hari(18), Roster(16), Target(16), Ket.Libur(50), Terlaksana(18), Selisih(15), %(15) = 225mm
$colWidths = [10, 45, 22, 18, 16, 16, 50, 18, 15, 15];
$pdf->SetColWidths($colWidths);

$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

$leftMargin = $pdf->GetLeftMargin();
$w = $colWidths;
$rowHeight = 7;

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
    
    $libur_jk_text = getLiburJKText($hari, $id_kelas, $libur_detail, $jk_detail);
    
    $pdf->CheckPageBreak($rowHeight + 5);
    $pdf->SetX($leftMargin);
    
    // Zebra striping
    if ($no % 2 == 0) {
        $pdf->SetFillColor(245, 245, 245);
        $fill = true;
    } else {
        $fill = false;
    }
    
    $pdf->Cell($w[0], $rowHeight, $no, 1, 0, 'C', $fill);
    $pdf->Cell($w[1], $rowHeight, substr($row['nama_mapel'], 0, 28), 1, 0, 'L', $fill);
    $pdf->Cell($w[2], $rowHeight, $row['nama_kelas'], 1, 0, 'C', $fill);
    $pdf->Cell($w[3], $rowHeight, $hari, 1, 0, 'C', $fill);
    $pdf->Cell($w[4], $rowHeight, $roster, 1, 0, 'C', $fill);
    $pdf->Cell($w[5], $rowHeight, $target, 1, 0, 'C', $fill);
    $pdf->SetFont('Arial', '', 6);
    $pdf->Cell($w[6], $rowHeight, substr($libur_jk_text, 0, 35), 1, 0, 'L', $fill);
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell($w[7], $rowHeight, $terlaksana, 1, 0, 'C', $fill);
    
    // Selisih dengan warna
    if ($selisih < 0) {
        $pdf->SetTextColor(255, 0, 0);
    } elseif ($selisih > 0) {
        $pdf->SetTextColor(0, 128, 0);
    }
    $pdf->Cell($w[8], $rowHeight, ($selisih >= 0 ? '+' : '') . $selisih, 1, 0, 'C', $fill);
    $pdf->SetTextColor(0, 0, 0);
    
    // Persentase dengan warna
    if ($persen < 80) {
        $pdf->SetTextColor(255, 0, 0);
    } elseif ($persen >= 100) {
        $pdf->SetTextColor(0, 128, 0);
    } else {
        $pdf->SetTextColor(255, 165, 0);
    }
    $pdf->Cell($w[9], $rowHeight, $persen . '%', 1, 1, 'C', $fill);
    $pdf->SetTextColor(0, 0, 0);
    
    $total_roster += $roster;
    $total_target += $target;
    $total_terlaksana += $terlaksana;
    $no++;
}

// Total
$pdf->CheckPageBreak($rowHeight + 10);
$pdf->Ln(2);
$pdf->SetX($leftMargin);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(52, 58, 64);
$pdf->SetTextColor(255, 255, 255);

$total_selisih = $total_terlaksana - $total_target;
$total_persen = $total_target > 0 ? round(($total_terlaksana / $total_target) * 100, 1) : 0;

$pdf->Cell($w[0] + $w[1] + $w[2] + $w[3], $rowHeight, 'TOTAL', 1, 0, 'C', true);
$pdf->Cell($w[4], $rowHeight, $total_roster, 1, 0, 'C', true);
$pdf->Cell($w[5], $rowHeight, $total_target, 1, 0, 'C', true);
$pdf->Cell($w[6], $rowHeight, '', 1, 0, 'C', true);
$pdf->Cell($w[7], $rowHeight, $total_terlaksana, 1, 0, 'C', true);
$pdf->Cell($w[8], $rowHeight, ($total_selisih >= 0 ? '+' : '') . $total_selisih, 1, 0, 'C', true);
$pdf->Cell($w[9], $rowHeight, $total_persen . '%', 1, 1, 'C', true);

// Output
while (ob_get_level()) ob_end_clean();
$filename = 'Beban_Mengajar_' . str_replace(' ', '_', $guru['nama_guru']) . '_' . $filter_tahun . '-' . $filter_bulan . '.pdf';
$pdf->Output('I', $filename);
exit;
