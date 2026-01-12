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
            'total_jam_roster' => 0
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
        $this->SetFont('Arial', 'B', 8);
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
        
        // Jam Roster (colspan 2)
        $this->Cell($w[3] + $w[4], 6, 'Jam Roster', 1, 0, 'C', true);
        
        // Jam Bulan Ini (colspan 2)
        $this->Cell($w[5] + $w[6], 6, 'Jam Bulan Ini', 1, 0, 'C', true);
        
        // Total Rekap Guru (rowspan 2)
        $this->Cell($w[7], 12, 'Total Rekap Guru', 1, 1, 'C', true);
        
        // Baris 2: Sub-header
        $this->SetX($x + $w[0] + $w[1] + $w[2]);
        $this->Cell($w[3], 6, 'Per Minggu', 1, 0, 'C', true);
        $this->Cell($w[4], 6, 'Seharusnya', 1, 0, 'C', true);
        $this->Cell($w[5], 6, 'Terlaksana', 1, 0, 'C', true);
        $this->Cell($w[6], 6, 'Selisih', 1, 1, 'C', true);
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

// Kolom widths - Total harus pas untuk center (sekitar 257mm untuk margin 20mm kiri-kanan)
// No(10), Nama(45), Mapel(60), PerMg(20), Seharusnya(22), Terlaksana(22), Selisih(18), Total(50) = 247mm
$colWidths = [10, 45, 60, 20, 22, 22, 18, 50];
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
    $blockHeight = $numRows * $rowHeight;
    
    // Cek page break
    $pdf->CheckPageBreak($blockHeight + 5);
    
    $startY = $pdf->GetY();
    $total_selisih = $data['total_jam_terlaksana'] - ($data['total_jam_seharusnya'] ?? 0);
    $total_selisih_text = ($total_selisih > 0 ? '+' : '') . $total_selisih;
    
    // Kolom No - dengan border manual untuk simulasi rowspan
    $pdf->SetXY($leftMargin, $startY);
    $pdf->Rect($leftMargin, $startY, $w[0], $blockHeight);
    $pdf->Cell($w[0], $blockHeight, $no, 0, 0, 'C');
    
    // Kolom Nama Guru
    $x1 = $leftMargin + $w[0];
    $pdf->Rect($x1, $startY, $w[1], $blockHeight);
    $pdf->SetXY($x1 + 1, $startY + 1);
    $pdf->MultiCell($w[1] - 2, 3.5, $data['nama_guru'], 0, 'L');
    
    // Kolom Total Rekap
    $xTotal = $leftMargin + $w[0] + $w[1] + $w[2] + $w[3] + $w[4] + $w[5] + $w[6];
    $pdf->Rect($xTotal, $startY, $w[7], $blockHeight);
    $pdf->SetXY($xTotal + 1, $startY + 1);
    $totalText = "Roster/Mg: " . $data['total_jam_roster'] . "\n";
    $totalText .= "Seharusnya: " . ($data['total_jam_seharusnya'] ?? 0) . "\n";
    $totalText .= "Terlaksana: " . $data['total_jam_terlaksana'] . "\n";
    $totalText .= "Selisih: " . $total_selisih_text;
    $pdf->MultiCell($w[7] - 2, 3.5, $totalText, 0, 'L');
    
    // Kolom Assignment (Mapel-Kelas, PerMg, Seharusnya, Terlaksana, Selisih)
    if (count($assignments) > 0) {
        $currentY = $startY;
        foreach ($assignments as $ass) {
            $jam_seharusnya = $ass['jam_roster'] * $total_minggu_penuh;
            $selisih = $ass['jam_terlaksana'] - $jam_seharusnya;
            $selisih_text = ($selisih > 0 ? '+' : '') . $selisih;
            
            $xAss = $leftMargin + $w[0] + $w[1];
            $pdf->SetXY($xAss, $currentY);
            
            // Mapel - Kelas
            $pdf->Cell($w[2], $rowHeight, substr($ass['mapel_kelas'], 0, 40), 1, 0, 'L');
            // Per Minggu
            $pdf->Cell($w[3], $rowHeight, $ass['jam_roster'], 1, 0, 'C');
            // Seharusnya
            $pdf->Cell($w[4], $rowHeight, $jam_seharusnya, 1, 0, 'C');
            // Terlaksana
            $pdf->Cell($w[5], $rowHeight, $ass['jam_terlaksana'], 1, 0, 'C');
            
            // Selisih dengan warna
            if ($selisih < 0) {
                $pdf->SetTextColor(255, 0, 0);
            } elseif ($selisih > 0) {
                $pdf->SetTextColor(0, 128, 0);
            }
            $pdf->Cell($w[6], $rowHeight, $selisih_text, 1, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
            
            $currentY += $rowHeight;
        }
    } else {
        // Tidak ada assignment
        $xAss = $leftMargin + $w[0] + $w[1];
        $pdf->SetXY($xAss, $startY);
        $mergedW = $w[2] + $w[3] + $w[4] + $w[5] + $w[6];
        $pdf->SetFont('Arial', 'I', 7);
        $pdf->Cell($mergedW, $blockHeight, 'Tidak ada jadwal/jurnal', 1, 0, 'C');
        $pdf->SetFont('Arial', '', 8);
    }
    
    $pdf->SetY($startY + $blockHeight);
    $no++;
}

// Output
while (ob_get_level()) ob_end_clean();
$filename = 'Rekap_Bulanan_Guru_Detail_' . $filter_tahun . '-' . $filter_bulan . '.pdf';
$pdf->Output('I', $filename);
exit;
