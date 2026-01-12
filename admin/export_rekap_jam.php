<?php
/*
File: admin/export_rekap_jam.php
Lokasi: /jurnal_app/admin/export_rekap_jam.php
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

// 1. Beri tahu browser bahwa ini adalah file Excel
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Rekap_Jam_Mengajar_Guru_" . date('Y-m-d') . ".xls");

// 2. Ambil data dari database (sama dengan query di halaman rekap)
try {
    $query = "
        SELECT 
            g.nip, 
            g.nama_guru, 
            SUM(m.jam_per_minggu) as total_jam,
            GROUP_CONCAT(DISTINCT mp.nama_mapel SEPARATOR ', ') as daftar_mapel
        FROM tbl_guru g
        LEFT JOIN tbl_mengajar m ON g.id = m.id_guru
        LEFT JOIN tbl_mapel mp ON m.id_mapel = mp.id
        GROUP BY g.id
        ORDER BY g.nama_guru ASC
    ";
    $stmt = $pdo->query($query);
    $rekap = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<center>
    <h2>REKAP BEBAN KERJA GURU</h2>
    <h4>Tanggal Ekspor: <?php echo date('d-m-Y'); ?></h4>
</center>

<table border="1">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th>No</th>
            <th>NIP</th>
            <th>Nama Guru</th>
            <th>Mata Pelajaran yang Diampu</th>
            <th>Total Jam/Minggu</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rekap as $index => $row): 
            $total = $row['total_jam'] ?? 0;
            $status = ($total >= 24) ? 'Terpenuhi' : (($total > 0) ? 'Kurang' : 'Belum Isi');
        ?>
            <tr>
                <td><?php echo $index + 1; ?></td>
                <td>'<?php echo $row['nip']; ?></td> <td><?php echo $row['nama_guru']; ?></td>
                <td><?php echo $row['daftar_mapel'] ?: '-'; ?></td>
                <td align="center"><?php echo $total; ?></td>
                <td><?php echo $status; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>