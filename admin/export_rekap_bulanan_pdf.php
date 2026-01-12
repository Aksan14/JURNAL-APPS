<?php
/*
File: admin/export_rekap_bulanan_pdf.php
Export PDF Rekap Bulanan Per Guru (Ringkasan) - Format Tabel Rapi
*/

ob_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php';

// Filter
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$tanggal_mulai = "$filter_tahun-$filter_bulan-01";
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

$nama_bulan_tahun = $nama_bulan[$filter_bulan] . ' ' . $filter_tahun;

// ==========================================================
// BUAT PDF
// ==========================================================
class PDF extends FPDF {
    protected $title_text;
    protected $periode_text;
    protected $colWidths;
    protected $tableWidth;
    protected $leftMargin;
    
    function SetTitleText($t) { $this->title_text = $t; }
    function SetPeriodeText($t) { $this->periode_text = $t; }
    function SetColWidths($w) { 
        $this->colWidths = $w; 
        $this->tableWidth = array_sum($w);
        // Hitung margin kiri untuk center tabel (A4 Landscape = 297mm)
        $this->leftMargin = (297 - $this->tableWidth) / 2;
    }
    
    function Header() {
        // Judul di tengah halaman
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'REKAP JURNAL BULANAN PER GURU', 0, 1, 'C');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 7, 'Periode: ' . $this->title_text, 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, $this->periode_text, 0, 1, 'C');
        $this->Ln(5);
        
        // Gambar header tabel di tengah
        $this->DrawTableHeader();
    }
    
    function DrawTableHeader() {
        $w = $this->colWidths;
        $x = $this->leftMargin;
        
        $this->SetX($x);
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(52, 58, 64); // Dark gray
        $this->SetTextColor(255, 255, 255); // White
        $this->SetDrawColor(0, 0, 0);
        
        // Header tabel
        $this->Cell($w[0], 10, 'No', 1, 0, 'C', true);
        $this->Cell($w[1], 10, 'NIP', 1, 0, 'C', true);
        $this->Cell($w[2], 10, 'Nama Guru', 1, 0, 'C', true);
        $this->Cell($w[3], 10, 'Jml Kelas', 1, 0, 'C', true);
        $this->Cell($w[4], 10, 'Roster/Minggu', 1, 0, 'C', true);
        $this->Cell($w[5], 10, 'Target Bulan', 1, 0, 'C', true);
        $this->Cell($w[6], 10, 'Terlaksana', 1, 0, 'C', true);
        $this->Cell($w[7], 10, 'Selisih', 1, 0, 'C', true);
        $this->Cell($w[8], 10, 'Pertemuan', 1, 0, 'C', true);
        $this->Cell($w[9], 10, 'Persentase', 1, 1, 'C', true);
        
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
    
    function GetLeftMargin() {
        return $this->leftMargin;
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape
$pdf->AliasNbPages();
$pdf->SetTitleText($nama_bulan_tahun);
$pdf->SetPeriodeText("Jumlah Minggu: $minggu minggu | Tanggal Export: " . date('d/m/Y H:i:s'));

// Kolom widths - Total ~257mm untuk center di A4 landscape
// No(10), NIP(25), Nama(55), JmlKelas(18), Roster(25), Target(25), Terlaksana(25), Selisih(20), Pertemuan(22), Persentase(25) = 250mm
$colWidths = [10, 25, 55, 18, 25, 25, 25, 20, 22, 25];
$pdf->SetColWidths($colWidths);

$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

$leftMargin = $pdf->GetLeftMargin();
$w = $colWidths;
$rowHeight = 7;

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
    
    // Cek page break
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
    $pdf->Cell($w[1], $rowHeight, $row['nip'] ?? '-', 1, 0, 'C', $fill);
    $pdf->Cell($w[2], $rowHeight, substr($row['nama_guru'], 0, 35), 1, 0, 'L', $fill);
    $pdf->Cell($w[3], $rowHeight, $row['jumlah_kelas'], 1, 0, 'C', $fill);
    $pdf->Cell($w[4], $rowHeight, $row['total_roster_mingguan'], 1, 0, 'C', $fill);
    $pdf->Cell($w[5], $rowHeight, $target, 1, 0, 'C', $fill);
    $pdf->Cell($w[6], $rowHeight, $row['jam_terlaksana'], 1, 0, 'C', $fill);
    
    // Selisih dengan warna
    if ($selisih < 0) {
        $pdf->SetTextColor(255, 0, 0);
    } elseif ($selisih > 0) {
        $pdf->SetTextColor(0, 128, 0);
    }
    $pdf->Cell($w[7], $rowHeight, ($selisih >= 0 ? '+' : '') . $selisih, 1, 0, 'C', $fill);
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->Cell($w[8], $rowHeight, $row['total_pertemuan'], 1, 0, 'C', $fill);
    
    // Persentase dengan warna
    if ($persen < 80) {
        $pdf->SetTextColor(255, 0, 0);
    } elseif ($persen >= 100) {
        $pdf->SetTextColor(0, 128, 0);
    } else {
        $pdf->SetTextColor(255, 165, 0); // Orange
    }
    $pdf->Cell($w[9], $rowHeight, $persen . '%', 1, 1, 'C', $fill);
    $pdf->SetTextColor(0, 0, 0);
    
    $total_roster += $row['total_roster_mingguan'];
    $total_target += $target;
    $total_terlaksana += $row['jam_terlaksana'];
    $total_pertemuan += $row['total_pertemuan'];
    $no++;
}

// Footer total
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
$pdf->Cell($w[6], $rowHeight, $total_terlaksana, 1, 0, 'C', true);
$pdf->Cell($w[7], $rowHeight, ($total_selisih >= 0 ? '+' : '') . $total_selisih, 1, 0, 'C', true);
$pdf->Cell($w[8], $rowHeight, $total_pertemuan, 1, 0, 'C', true);
$pdf->Cell($w[9], $rowHeight, $total_persen . '%', 1, 1, 'C', true);

// Output
while (ob_get_level()) ob_end_clean();
$filename = 'Rekap_Bulanan_Guru_' . $filter_tahun . '-' . $filter_bulan . '.pdf';
$pdf->Output('I', $filename);
exit;
