<?php
/*
File: guru/export_jurnal_pdf.php
Export PDF Jurnal Bulanan untuk Guru
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
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Ambil ID Guru
$stmt_g = $pdo->prepare("SELECT id, nama_guru, nip FROM tbl_guru WHERE user_id = ?");
$stmt_g->execute([$user_id]);
$guru = $stmt_g->fetch();

if (!$guru) {
    die('Data guru tidak ditemukan.');
}

$id_guru = $guru['id'];

// Ambil Data Jurnal
$query = "
    SELECT j.*, k.nama_kelas, mp.nama_mapel,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'H') as hadir,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'S') as sakit,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'I') as izin,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'A') as alpa
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE m.id_guru = :id_guru AND MONTH(j.tanggal) = :bulan AND YEAR(j.tanggal) = :tahun
    ORDER BY j.tanggal ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute(['id_guru' => $id_guru, 'bulan' => $bulan, 'tahun' => $tahun]);
$laporan = $stmt->fetchAll();

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
        // Judul
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'LAPORAN JURNAL MENGAJAR', 0, 1, 'C');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 7, 'Periode: ' . $this->title_text, 0, 1, 'C');
        
        // Info Guru
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
        $this->Cell($w[1], 10, 'Tanggal', 1, 0, 'C', true);
        $this->Cell($w[2], 10, 'Jam Ke', 1, 0, 'C', true);
        $this->Cell($w[3], 10, 'Kelas', 1, 0, 'C', true);
        $this->Cell($w[4], 10, 'Mapel', 1, 0, 'C', true);
        $this->Cell($w[5], 10, 'Materi', 1, 0, 'C', true);
        $this->Cell($w[6], 10, 'H', 1, 0, 'C', true);
        $this->Cell($w[7], 10, 'S', 1, 0, 'C', true);
        $this->Cell($w[8], 10, 'I', 1, 0, 'C', true);
        $this->Cell($w[9], 10, 'A', 1, 1, 'C', true);
        
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
$pdf->SetTitleText($nama_bulan[$bulan] . ' ' . $tahun);
$pdf->SetGuruInfo($guru['nama_guru'], $guru['nip'] ?? '-');

// Kolom: No(10), Tgl(20), Jam(15), Kelas(25), Mapel(35), Materi(100), H(12), S(12), I(12), A(12) = 253mm
$colWidths = [10, 20, 15, 25, 35, 100, 12, 12, 12, 12];
$pdf->SetColWidths($colWidths);

$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

$leftMargin = $pdf->GetLeftMargin();
$w = $colWidths;
$rowHeight = 7;

$no = 1;
$total_h = 0;
$total_s = 0;
$total_i = 0;
$total_a = 0;

if (empty($laporan)) {
    $pdf->SetX($leftMargin);
    $pdf->Cell(array_sum($colWidths), 10, 'Tidak ada data jurnal untuk bulan ini.', 1, 1, 'C');
} else {
    foreach ($laporan as $row) {
        $pdf->CheckPageBreak($rowHeight + 5);
        
        $pdf->SetX($leftMargin);
        
        // Zebra striping
        if ($no % 2 == 0) {
            $pdf->SetFillColor(245, 245, 245);
            $fill = true;
        } else {
            $fill = false;
        }
        
        // Truncate materi jika terlalu panjang
        $materi = $row['topik_materi'] ?? '-';
        if (strlen($materi) > 65) {
            $materi = substr($materi, 0, 62) . '...';
        }
        
        $pdf->Cell($w[0], $rowHeight, $no, 1, 0, 'C', $fill);
        $pdf->Cell($w[1], $rowHeight, date('d/m/Y', strtotime($row['tanggal'])), 1, 0, 'C', $fill);
        $pdf->Cell($w[2], $rowHeight, $row['jam_ke'] ?? '-', 1, 0, 'C', $fill);
        $pdf->Cell($w[3], $rowHeight, substr($row['nama_kelas'], 0, 15), 1, 0, 'C', $fill);
        $pdf->Cell($w[4], $rowHeight, substr($row['nama_mapel'], 0, 22), 1, 0, 'L', $fill);
        $pdf->Cell($w[5], $rowHeight, $materi, 1, 0, 'L', $fill);
        $pdf->Cell($w[6], $rowHeight, $row['hadir'] ?? 0, 1, 0, 'C', $fill);
        $pdf->Cell($w[7], $rowHeight, $row['sakit'] ?? 0, 1, 0, 'C', $fill);
        $pdf->Cell($w[8], $rowHeight, $row['izin'] ?? 0, 1, 0, 'C', $fill);
        $pdf->Cell($w[9], $rowHeight, $row['alpa'] ?? 0, 1, 1, 'C', $fill);
        
        $total_h += $row['hadir'] ?? 0;
        $total_s += $row['sakit'] ?? 0;
        $total_i += $row['izin'] ?? 0;
        $total_a += $row['alpa'] ?? 0;
        $no++;
    }
    
    // Footer total
    $pdf->CheckPageBreak($rowHeight + 10);
    $pdf->Ln(2);
    $pdf->SetX($leftMargin);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(52, 58, 64);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell($w[0] + $w[1] + $w[2] + $w[3] + $w[4] + $w[5], $rowHeight, 'TOTAL ('. ($no-1) . ' Pertemuan)', 1, 0, 'C', true);
    $pdf->Cell($w[6], $rowHeight, $total_h, 1, 0, 'C', true);
    $pdf->Cell($w[7], $rowHeight, $total_s, 1, 0, 'C', true);
    $pdf->Cell($w[8], $rowHeight, $total_i, 1, 0, 'C', true);
    $pdf->Cell($w[9], $rowHeight, $total_a, 1, 1, 'C', true);
}

// Output
while (ob_get_level()) ob_end_clean();
$filename = 'Jurnal_Guru_' . $bulan . '_' . $tahun . '.pdf';
$pdf->Output('I', $filename);
exit;
