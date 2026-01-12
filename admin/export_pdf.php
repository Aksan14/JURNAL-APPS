<?php
/*
File: export_pdf.php (FIXED - Removed duplicate function)
Lokasi: /jurnal_app/admin/export_pdf.php
*/

// 1. Panggil file config dan auth_check
require_once '../includes/auth_check.php';
require_once '../config.php';
checkRole(['admin']);

// 2. Panggil autoloader dari Composer (FPDF)
require_once __DIR__ . '/../vendor/autoload.php';

// ==========================================================
// FUNGSI HELPER (Pastikan ini HANYA ADA SATU KALI)
// ==========================================================
function calculateHours($jam_ke_str) {
    $jam_ke_str = trim($jam_ke_str);
    if (strpos($jam_ke_str, '-') !== false) {
        $parts = explode('-', $jam_ke_str);
        if (count($parts) == 2) {
            $start = (int)trim($parts[0]);
            $end = (int)trim($parts[1]);
            if ($end >= $start) {
                return ($end - $start) + 1;
            }
        }
    }
    if (is_numeric($jam_ke_str) && (int)$jam_ke_str > 0) return 1;
    if (strpos($jam_ke_str, ',') !== false) return count(explode(',', $jam_ke_str));
    return 0;
}
// ==========================================================
// AKHIR FUNGSI HELPER
// ==========================================================


// 3. Logika Filter (Sama persis)
$filter_sql = "";
$roster_filter_sql = "";
$params = [];
$roster_params = [];
$tanggal_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tanggal_selesai = $_GET['tanggal_selesai'] ?? date('Y-m-t');
$filter_sql .= " WHERE j.tanggal BETWEEN ? AND ?";
array_push($params, $tanggal_mulai, $tanggal_selesai);
$filter_id_guru = $_GET['id_guru'] ?? '';
if (!empty($filter_id_guru)) {
    $filter_sql .= " AND m.id_guru = ?";
    array_push($params, $filter_id_guru);
    $roster_filter_sql .= " WHERE m.id_guru = ?";
    array_push($roster_params, $filter_id_guru);
}
$filter_id_kelas = $_GET['id_kelas'] ?? '';
if (!empty($filter_id_kelas)) {
    $filter_sql .= " AND m.id_kelas = ?";
    array_push($params, $filter_id_kelas);
    $roster_filter_sql .= (!empty($roster_filter_sql) ? " AND " : " WHERE ") . "m.id_kelas = ?";
    array_push($roster_params, $filter_id_kelas);
}

// 4. Query SQL untuk Laporan Jurnal (Halaman 1)
$sql_laporan = "
    SELECT j.tanggal, j.jam_ke, j.topik_materi, j.catatan_guru,
           g.nama_guru, mp.nama_mapel, k.nama_kelas
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    $filter_sql
    ORDER BY j.tanggal ASC, g.nama_guru ASC, j.jam_ke ASC
";
$stmt_laporan = $pdo->prepare($sql_laporan);
$stmt_laporan->execute($params);
$laporan = $stmt_laporan->fetchAll();

// 5. Logika Kalkulasi Rekap (Halaman 1)
$total_pertemuan_terlaksana = count($laporan);
$total_jam_terlaksana = 0;
foreach ($laporan as $j) { $total_jam_terlaksana += calculateHours($j['jam_ke']); }
$stmt_roster = $pdo->prepare("SELECT SUM(m.jumlah_jam_mingguan) AS total_jam_roster FROM tbl_mengajar m $roster_filter_sql");
$stmt_roster->execute($roster_params);
$roster_data = $stmt_roster->fetch();
$total_jam_roster_mingguan = $roster_data['total_jam_roster'] ?? 0;
$start_date = new DateTime($tanggal_mulai);
$end_date = new DateTime($tanggal_selesai);
$diff = $start_date->diff($end_date);
$total_hari_filter = $diff->days + 1;
$total_minggu_penuh = floor($total_hari_filter / 7);
$total_jam_seharusnya = $total_jam_roster_mingguan * $total_minggu_penuh;
$recap_data = [
    'pertemuan' => $total_pertemuan_terlaksana . ' Kali',
    'jam_terlaksana' => $total_jam_terlaksana . ' Jam',
    'jam_roster' => $total_jam_roster_mingguan . ' Jam/Mg',
    'jam_seharusnya' => $total_jam_seharusnya . ' Jam'
];
// ==========================================================

// 6. Kustomisasi FPDF (Tetap sama)
class PDF extends FPDF
{
    protected $widths;
    protected $header;
    protected $filter_text;
    protected $recap_data;

    function SetHeaderData($header, $filter_text, $recap_data = [])
    {
        $this->header = $header;
        $this->filter_text = $filter_text;
        $this->recap_data = $recap_data;
    }
    function SetWidths($w) { $this->widths = $w; }

    function Header()
    {
        $this->SetFont('Arial', 'B', 15);
        if ($this->PageNo() == 1) {
            $this->Cell(0, 10, 'LAPORAN JURNAL PEMBELAJARAN', 0, 1, 'C');
        } else {
            $this->Cell(0, 10, 'LAPORAN REKAP ABSENSI SISWA', 0, 1, 'C');
        }
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 8, $this->filter_text, 0, 1, 'C');
        $this->Ln(2);

        if ($this->PageNo() == 1 && !empty($this->recap_data)) {
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(50, 6, 'Pertemuan Terlaksana:', 0, 0, 'R');
            $this->SetFont('Arial', '', 9);
            $this->Cell(60, 6, $this->recap_data['pertemuan'], 0, 0, 'L');
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(60, 6, 'Beban Jam Roster:', 0, 0, 'R');
            $this->SetFont('Arial', '', 9);
            $this->Cell(60, 6, $this->recap_data['jam_roster'], 0, 1, 'L');
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(50, 6, 'Jam Terlaksana:', 0, 0, 'R');
            $this->SetFont('Arial', '', 9);
            $this->Cell(60, 6, $this->recap_data['jam_terlaksana'], 0, 0, 'L');
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(60, 6, 'Estimasi Jam Seharusnya:', 0, 0, 'R');
            $this->SetFont('Arial', '', 9);
            $this->Cell(60, 6, $this->recap_data['jam_seharusnya'], 0, 1, 'L');
            $this->Ln(5);
        }

        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0);
        $this->SetDrawColor(0);
        for ($i = 0; $i < count($this->header); $i++) {
            $this->Cell($this->widths[$i], 8, $this->header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
    }
    function Footer(){$this->SetY(-15);$this->SetFont('Arial','I',8);$this->Cell(0,10,'Halaman '.$this->PageNo().'/{nb}',0,0,'C');}
    function Row($data){$nb=0;for($i=0;$i<count($data);$i++)$nb=max($nb,$this->NbLines($this->widths[$i],$data[$i]));$h=5*$nb;$this->CheckPageBreak($h);for($i=0;$i<count($data);$i++){$w=$this->widths[$i];$a=isset($this->aligns[$i])?$this->aligns[$i]:'L';$x=$this->GetX();$y=$this->GetY();$this->Rect($x,$y,$w,$h);$this->MultiCell($w,5,$data[$i],0,$a);$this->SetXY($x+$w,$y);}$this->Ln($h);}
    function CheckPageBreak($h){if($this->GetY()+$h>$this->PageBreakTrigger)$this->AddPage($this->CurOrientation);}
    function NbLines($w,$txt){$cw=&$this->CurrentFont['cw'];if($w==0)$w=$this->w-$this->rMargin-$this->x;$wmax=($w-2*$this->cMargin)*1000/$this->FontSize;$s=str_replace("\r",'',$txt);$nb=strlen($s);if($nb>0 and $s[$nb-1]=="\n")$nb--;$sep=-1;$i=0;$j=0;$l=0;$nl=1;while($i<$nb){$c=$s[$i];if($c=="\n"){$i++;$sep=-1;$j=$i;$l=0;$nl++;continue;}if($c==' ')$sep=$i;$l+=$cw[$c];if($l>$wmax){if($sep==-1){if($i==$j)$i++;}else $i=$sep+1;$sep=-1;$j=$i;$l=0;$nl++;}else $i++;}return $nl;}
}
// ==========================================================

// 7. MEMBUAT DOKUMEN PDF (HALAMAN 1: REKAP JURNAL)
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();

$header1 = ['No', 'Tanggal', 'Guru', 'Kelas', 'Mapel', 'Jam Ke-', 'Topik Materi', 'Catatan Guru'];
$widths1 = [10, 22, 45, 30, 35, 15, 80, 40]; // Total 277mm
$pdf->SetWidths($widths1);

$filter_text = 'Periode: ' . htmlspecialchars(date('d-m-Y', strtotime($tanggal_mulai))) . ' s/d ' . htmlspecialchars(date('d-m-Y', strtotime($tanggal_selesai)));
$pdf->SetHeaderData($header1, $filter_text, $recap_data); // Kirim data rekap
$pdf->AddPage();

$pdf->SetFont('Arial', '', 8);
$no = 1;
if (empty($laporan)) {
    $pdf->Cell(277, 10, 'Tidak ada data laporan jurnal untuk filter ini.', 1, 1, 'C');
} else {
    foreach ($laporan as $j) {
        $rowData = [ $no++, date('d-m-Y', strtotime($j['tanggal'])), $j['nama_guru'], $j['nama_kelas'], $j['nama_mapel'], $j['jam_ke'], $j['topik_materi'], $j['catatan_guru'] ?? '-' ];
        $pdf->Row($rowData);
    }
}

// 8. QUERY DAN BUAT HALAMAN 2 (REKAP ABSENSI)
$sql_rekap_absensi = "
    SELECT s.nis, s.nama_siswa, k.nama_kelas,
        SUM(CASE WHEN p.status_kehadiran = 'H' THEN 1 ELSE 0 END) AS total_h,
        SUM(CASE WHEN p.status_kehadiran = 'S' THEN 1 ELSE 0 END) AS total_s,
        SUM(CASE WHEN p.status_kehadiran = 'I' THEN 1 ELSE 0 END) AS total_i,
        SUM(CASE WHEN p.status_kehadiran = 'A' THEN 1 ELSE 0 END) AS total_a
    FROM tbl_presensi_siswa p
    JOIN tbl_siswa s ON p.id_siswa = s.id
    JOIN tbl_kelas k ON s.id_kelas = k.id
    JOIN tbl_jurnal j ON p.id_jurnal = j.id
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    $filter_sql 
    GROUP BY s.id, s.nis, s.nama_siswa, k.nama_kelas
    ORDER BY k.nama_kelas, s.nama_siswa
";
$stmt_rekap = $pdo->prepare($sql_rekap_absensi);
$stmt_rekap->execute($params);
$rekap_absensi = $stmt_rekap->fetchAll();

$header2 = ['No', 'NIS', 'Nama Siswa', 'Kelas', 'Hadir (H)', 'Sakit (S)', 'Izin (I)', 'Alfa (A)'];
$widths2 = [10, 30, 77, 40, 30, 30, 30, 30]; // Total 277mm
$pdf->SetWidths($widths2);
$pdf->SetHeaderData($header2, $filter_text); // Kirim header baru, TANPA rekap
$pdf->AddPage(); // Tambah Halaman Baru

$pdf->SetFont('Arial', '', 8);
$no = 1;
if (empty($rekap_absensi)) {
    $pdf->Cell(277, 10, 'Tidak ada data absensi siswa untuk filter ini.', 1, 1, 'C');
} else {
    foreach ($rekap_absensi as $ra) {
        $rowData = [ $no++, $ra['nis'], $ra['nama_siswa'], $ra['nama_kelas'], $ra['total_h'], $ra['total_s'], $ra['total_i'], $ra['total_a'] ];
        $pdf->Row($rowData);
    }
}
// ==========================================================

// 9. Output PDF ke Browser
if (ob_get_contents()) ob_end_clean();
$filename = 'Laporan_Jurnal_Lengkap_' . date('Y-m-d') . '.pdf';
$pdf->Output('I', $filename);
exit;

?>