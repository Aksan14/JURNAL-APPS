<?php
/*
File: export_pdf_bulanan_guru_detail.php
Export PDF Rekap Jurnal Bulanan Per Guru (Detail)
*/

ob_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

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

function getJumlahLiburPDFDetail($hari, $id_kelas, $libur_data) {
    $libur_semua = $libur_data[$hari]['all'] ?? 0;
    $libur_kelas = $libur_data[$hari][$id_kelas] ?? 0;
    return $libur_semua + $libur_kelas;
}

// Ambil detail libur per hari dan kelas
$libur_detail_per_hari_pdf = [];
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
    if (!isset($libur_detail_per_hari_pdf[$hari_indo])) {
        $libur_detail_per_hari_pdf[$hari_indo] = [];
    }
    if (!isset($libur_detail_per_hari_pdf[$hari_indo][$kelas_key])) {
        $libur_detail_per_hari_pdf[$hari_indo][$kelas_key] = [];
    }
    $libur_detail_per_hari_pdf[$hari_indo][$kelas_key][] = [
        'tgl' => $ld['tgl'],
        'nama' => $ld['nama_libur']
    ];
}

// Ambil detail jam khusus per hari dan kelas
$jk_detail_per_hari_pdf = [];
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
    if (!isset($jk_detail_per_hari_pdf[$hari_indo])) {
        $jk_detail_per_hari_pdf[$hari_indo] = [];
    }
    if (!isset($jk_detail_per_hari_pdf[$hari_indo][$kelas_key])) {
        $jk_detail_per_hari_pdf[$hari_indo][$kelas_key] = [];
    }
    $jk_detail_per_hari_pdf[$hari_indo][$kelas_key][] = [
        'tgl' => $jk['tgl'],
        'alasan' => $jk['alasan'],
        'max_jam' => $jk['max_jam']
    ];
}

function getLiburDetailPDF($hari, $id_kelas, $libur_detail) {
    $result = [];
    if (isset($libur_detail[$hari]['all'])) {
        $result = array_merge($result, $libur_detail[$hari]['all']);
    }
    if (isset($libur_detail[$hari][$id_kelas])) {
        $result = array_merge($result, $libur_detail[$hari][$id_kelas]);
    }
    return $result;
}

function getJamKhususDetailPDF($hari, $id_kelas, $jk_detail) {
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
    $jumlah_libur = getJumlahLiburPDFDetail($roster['hari'], $roster['id_kelas'], $libur_per_hari_kelas);
    $jam_seharusnya = max(0, ($jam_roster * $total_minggu_penuh) - ($jam_roster * $jumlah_libur));
    
    // Ambil detail libur dan jam khusus
    $detail_libur = getLiburDetailPDF($roster['hari'], $roster['id_kelas'], $libur_detail_per_hari_pdf);
    $detail_jk = getJamKhususDetailPDF($roster['hari'], $roster['id_kelas'], $jk_detail_per_hari_pdf);

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
// BUAT PDF - Posisi tengah dengan header yang benar
// ==========================================================
class PDF extends FPDF {
    protected $title_text;
    protected $colWidths;
    protected $tableWidth;
    protected $leftMargin;
    
    function SetTitleText($t) { $this->title_text = $t; }
    function SetColWidths($w) { 
        $this->colWidths = $w; 
        $this->tableWidth = array_sum($w);
        // Hitung margin kiri untuk center tabel (A4 Landscape = 297mm, margin default 10mm)
        $this->leftMargin = (297 - $this->tableWidth) / 2;
    }
    
    function Header() {
        // Judul di tengah halaman
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'REKAP JURNAL BULANAN PER GURU (DETAIL)', 0, 1, 'C');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 7, 'Bulan: ' . $this->title_text, 0, 1, 'C');
        $this->Ln(5);
        
        // Gambar header tabel di tengah
        $this->DrawTableHeader();
    }
    
    function DrawTableHeader() {
        $w = $this->colWidths;
        $x = $this->leftMargin;
        
        $this->SetX($x);
        $this->SetFont('Arial', 'B', 7);
        $this->SetFillColor(220, 220, 220);
        $this->SetDrawColor(0, 0, 0);
        
        // Simpan posisi Y
        $y = $this->GetY();
        
        // Baris 1: Header utama dengan rowspan
        // No (rowspan 2)
        $this->SetXY($x, $y);
        $this->Cell($w[0], 12, 'No', 1, 0, 'C', true);
        
        // Nama Guru (rowspan 2)
        $this->Cell($w[1], 12, 'Nama Guru', 1, 0, 'C', true);
        
        // Mapel - Kelas (rowspan 2)
        $this->Cell($w[2], 12, 'Mapel - Kelas', 1, 0, 'C', true);
        
        // Hari (rowspan 2)
        $this->Cell($w[3], 12, 'Hari', 1, 0, 'C', true);
        
        // Jam Roster (colspan 2)
        $this->Cell($w[4] + $w[5], 6, 'Jam Roster', 1, 0, 'C', true);
        
        // Libur/JK (rowspan 2)
        $this->Cell($w[6], 12, 'Ket. Libur', 1, 0, 'C', true);
        
        // Jam Bulan Ini (colspan 2)
        $this->Cell($w[7] + $w[8], 6, 'Jam Bulan Ini', 1, 0, 'C', true);
        
        // Total Rekap Guru (rowspan 2)
        $this->Cell($w[9], 12, 'Total Rekap', 1, 1, 'C', true);
        
        // Baris 2: Sub-header
        $this->SetX($x + $w[0] + $w[1] + $w[2] + $w[3]);
        $this->Cell($w[4], 6, 'Per Mg', 1, 0, 'C', true);
        $this->Cell($w[5], 6, 'Seharus', 1, 0, 'C', true);
        $this->SetX($x + $w[0] + $w[1] + $w[2] + $w[3] + $w[4] + $w[5] + $w[6]);
        $this->Cell($w[7], 6, 'Terlaks', 1, 0, 'C', true);
        $this->Cell($w[8], 6, 'Selisih', 1, 1, 'C', true);
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
    
    function GetLeftMargin() {
        return $this->leftMargin;
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape
$pdf->AliasNbPages();
$pdf->SetTitleText($nama_bulan_tahun);

// Kolom widths - Total harus pas untuk center (A4 Landscape = 297mm)
// No(8), Nama(32), Mapel(45), Hari(14), PerMg(14), Seharusnya(14), Ket.Libur(45), Terlaksana(14), Selisih(14), Total(40) = 240mm
$colWidths = [8, 32, 45, 14, 14, 14, 45, 14, 14, 40];
$pdf->SetColWidths($colWidths);

$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

$leftMargin = $pdf->GetLeftMargin();
$w = $colWidths;
$rowHeight = 5;

// Isi data
$no = 1;
foreach ($hasil_rekap as $data) {
    $assignments = $data['assignments'];
    $numRows = max(1, count($assignments));
    // Minimum blockHeight = 15mm untuk Total Rekap (4 baris x 3.5mm)
    $blockHeight = max($numRows * $rowHeight, 15);
    
    // Cek page break
    $pdf->CheckPageBreak($blockHeight + 5);
    
    $startY = $pdf->GetY();
    $total_selisih = $data['total_jam_terlaksana'] - ($data['total_jam_seharusnya'] ?? 0);
    $total_selisih_text = ($total_selisih > 0 ? '+' : '') . $total_selisih;
    
    // Kolom No - dengan border manual untuk simulasi rowspan
    $pdf->SetXY($leftMargin, $startY);
    $pdf->Rect($leftMargin, $startY, $w[0], $blockHeight);
    $pdf->Cell($w[0], $blockHeight, $no, 0, 0, 'C');
    
    // Kolom Nama Guru - hanya gambar rect dan text tanpa MultiCell
    $x1 = $leftMargin + $w[0];
    $pdf->Rect($x1, $startY, $w[1], $blockHeight);
    $pdf->SetXY($x1 + 1, $startY + 1);
    $pdf->SetFont('Arial', '', 7);
    $namaGuru = substr($data['nama_guru'], 0, 20);
    $pdf->Cell($w[1] - 2, 4, $namaGuru, 0, 0, 'L');
    $pdf->SetFont('Arial', '', 8);
    
    // Kolom Total Rekap - format dengan newline (gambar terakhir setelah assignments)
    $xTotal = $leftMargin + $w[0] + $w[1] + $w[2] + $w[3] + $w[4] + $w[5] + $w[6] + $w[7] + $w[8];
    $pdf->Rect($xTotal, $startY, $w[9], $blockHeight);
    
    // Kolom Assignment (Mapel-Kelas, Hari, PerMg, Seharusnya, Libur/JK, Terlaksana, Selisih)
    if (count($assignments) > 0) {
        $currentY = $startY;
        foreach ($assignments as $ass) {
            $jam_seharusnya = $ass['jam_seharusnya'];
            $selisih = $ass['jam_terlaksana'] - $jam_seharusnya;
            $selisih_text = ($selisih > 0 ? '+' : '') . $selisih;
            
            // Format Libur/Jam Khusus
            $libur_jk_parts = [];
            if (!empty($ass['detail_libur'])) {
                foreach ($ass['detail_libur'] as $lib) {
                    $libur_jk_parts[] = "Libur:" . $lib['tgl'];
                }
            }
            if (!empty($ass['detail_jk'])) {
                foreach ($ass['detail_jk'] as $jk) {
                    $libur_jk_parts[] = "JamKhusus:" . $jk['tgl'];
                }
            }
            $libur_jk_text = !empty($libur_jk_parts) ? implode(', ', $libur_jk_parts) : '-';
            
            $xAss = $leftMargin + $w[0] + $w[1];
            $pdf->SetXY($xAss, $currentY);
            
            // Mapel - Kelas
            $pdf->Cell($w[2], $rowHeight, substr($ass['mapel_kelas'], 0, 30), 1, 0, 'L');
            // Hari
            $pdf->Cell($w[3], $rowHeight, substr($ass['hari'] ?? '-', 0, 6), 1, 0, 'C');
            // Per Minggu
            $pdf->Cell($w[4], $rowHeight, $ass['jam_roster'], 1, 0, 'C');
            // Seharusnya
            $pdf->Cell($w[5], $rowHeight, $jam_seharusnya, 1, 0, 'C');
            // Libur/JK
            $pdf->SetFont('Arial', '', 6);
            $pdf->Cell($w[6], $rowHeight, substr($libur_jk_text, 0, 30), 1, 0, 'L');
            $pdf->SetFont('Arial', '', 8);
            // Terlaksana
            $pdf->Cell($w[7], $rowHeight, $ass['jam_terlaksana'], 1, 0, 'C');
            
            // Selisih dengan warna
            if ($selisih < 0) {
                $pdf->SetTextColor(255, 0, 0);
            } elseif ($selisih > 0) {
                $pdf->SetTextColor(0, 128, 0);
            }
            $pdf->Cell($w[8], $rowHeight, $selisih_text, 1, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
            
            $currentY += $rowHeight;
        }
    } else {
        // Tidak ada assignment
        $xAss = $leftMargin + $w[0] + $w[1];
        $pdf->SetXY($xAss, $startY);
        $mergedW = $w[2] + $w[3] + $w[4] + $w[5] + $w[6] + $w[7] + $w[8];
        $pdf->SetFont('Arial', 'I', 7);
        $pdf->Cell($mergedW, $blockHeight, 'Tidak ada jadwal/jurnal', 1, 0, 'C');
        $pdf->SetFont('Arial', '', 8);
    }
    
    // Gambar text Total Rekap setelah assignments selesai
    // Simpan posisi Y berikutnya
    $nextY = $startY + $blockHeight;
    
    // Ambil nilai dengan default 0
    $roster = $data['total_jam_roster'] ?? 0;
    $seharusnya = $data['total_jam_seharusnya'] ?? 0;
    $terlaksana = $data['total_jam_terlaksana'] ?? 0;
    $selisih = $terlaksana - $seharusnya;
    $selisih_text = ($selisih > 0 ? '+' : '') . $selisih;
    
    $pdf->SetXY($xTotal + 1, $startY + 1);
    $pdf->SetFont('Arial', '', 6);
    $totalText = "Roster: " . $roster . "\n";
    $totalText .= "Seharusnya: " . $seharusnya . "\n";
    $totalText .= "Terlaksana: " . $terlaksana . "\n";
    $totalText .= "Selisih: " . $selisih_text;
    $pdf->MultiCell($w[9] - 2, 3.5, $totalText, 0, 'L');
    $pdf->SetFont('Arial', '', 8);
    
    // Reset posisi ke Y yang benar untuk guru berikutnya
    $pdf->SetXY($leftMargin, $nextY);
    $no++;
}

// Output
while (ob_get_level()) ob_end_clean();
$filename = 'Rekap_Bulanan_Guru_Detail_' . $filter_tahun . '-' . $filter_bulan . '.pdf';
$pdf->Output('I', $filename);
exit;
