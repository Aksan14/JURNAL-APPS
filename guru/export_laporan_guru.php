<?php
/* File: guru/export_laporan_guru.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

$user_id = $_SESSION['user_id'];
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Ambil ID Guru
$stmt_g = $pdo->prepare("SELECT id, nama_guru, nip FROM tbl_guru WHERE user_id = ?");
$stmt_g->execute([$user_id]);
$guru = $stmt_g->fetch();

// Validasi: Pastikan guru ditemukan
if (!$guru) {
    die('Data guru tidak ditemukan. Silakan hubungi administrator.');
}

$id_guru = $guru['id'];

// Ambil Data Jurnal dengan statistik kehadiran
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

// Header Excel
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Jurnal_Guru_".$bulan."_".$tahun.".xls");

?>
<table border="1">
    <tr>
        <th colspan="9" style="font-size: 14pt;">LAPORAN JURNAL MENGAJAR GURU</th>
    </tr>
    <tr>
        <td colspan="2">Nama Guru</td>
        <td colspan="7">: <?= $guru['nama_guru'] ?></td>
    </tr>
    <tr>
        <td colspan="2">NIP</td>
        <td colspan="7">: '<?= $guru['nip'] ?></td>
    </tr>
    <tr>
        <td colspan="2">Bulan/Tahun</td>
        <td colspan="7">: <?= $bulan ?> / <?= $tahun ?></td>
    </tr>
    <thead>
        <tr style="background-color: #cccccc;">
            <th>No</th>
            <th>Tanggal</th>
            <th>Kelas</th>
            <th>Mata Pelajaran</th>
            <th>Materi / Kompetensi Dasar</th>
            <th>H</th>
            <th>S</th>
            <th>I</th>
            <th>A</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($laporan as $i => $row): ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
            <td><?= $row['nama_kelas'] ?></td>
            <td><?= $row['nama_mapel'] ?></td>
            <td><?= $row['topik_materi'] ?? '-' ?></td>
            <td align="center"><?= $row['hadir'] ?? 0 ?></td>
            <td align="center"><?= $row['sakit'] ?? 0 ?></td>
            <td align="center"><?= $row['izin'] ?? 0 ?></td>
            <td align="center"><?= $row['alpa'] ?? 0 ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>