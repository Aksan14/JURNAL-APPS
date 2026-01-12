<?php
/*
File: admin/rekap_jam_guru.php
Lokasi: /jurnal_app/admin/rekap_jam_guru.php
*/

require_once '../includes/header.php';
require_once '../includes/auth_check.php';
checkRole(['admin']); // Hanya untuk Admin

// Query untuk mengambil total jam dan daftar mapel per guru
try {
    $query = "
        SELECT 
            g.nip, 
            g.nama_guru, 
            SUM(m.jumlah_jam_mingguan) as total_jam,
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

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Rekap Beban Kerja Guru</h1>
        <div class="d-flex gap-2">
            <a href="export_rekap_jam_csv.php" class="btn btn-sm btn-success shadow-sm">
                <i class="fas fa-file-csv fa-sm text-white-50"></i> Export CSV
            </a>
            <button onclick="window.print()" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-print fa-sm text-white-50"></i> Cetak Laporan
            </button>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-primary text-white">
            <h6 class="m-0 font-weight-bold">Total Jam Mengajar Per Minggu</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>NIP</th>
                            <th>Nama Guru</th>
                            <th>Mata Pelajaran yang Diampu</th>
                            <th class="text-center">Total Jam/Minggu</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rekap as $index => $row): 
                            $total = $row['total_jam'] ?? 0;
                            // Tampilkan badge berdasarkan jumlah jam (tanpa batasan minimum)
                            if ($total > 0) {
                                $badge = '<span class="badge bg-success">Aktif</span>';
                            } else {
                                $badge = '<span class="badge bg-secondary">Belum Ada Jadwal</span>';
                            }
                        ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($row['nip']); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['nama_guru']); ?></strong></td>
                                <td><small><?php echo htmlspecialchars($row['daftar_mapel'] ?: '-'); ?></small></td>
                                <td class="text-center"><span class="badge bg-primary fs-6"><?php echo $total; ?> Jam</span></td>
                                <td class="text-center"><?php echo $badge; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle"></i> Informasi:</h5>
        <ul class="mb-0">
            <li>Jam mengajar guru tidak memiliki batasan minimum atau maksimum.</li>
            <li>Data jadwal mengajar ditentukan oleh Admin pada menu <em>Kelola Jadwal</em>.</li>
        </ul>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>