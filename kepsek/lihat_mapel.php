<?php
/*
File: kepsek/lihat_mapel.php
Lihat Data Mata Pelajaran (Read Only)
*/
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['kepsek']);

// Ambil Data Mapel
$query = "
    SELECT mp.*,
           (SELECT COUNT(DISTINCT id_guru) FROM tbl_mengajar WHERE id_mapel = mp.id) as jumlah_guru,
           (SELECT COUNT(DISTINCT id_kelas) FROM tbl_mengajar WHERE id_mapel = mp.id) as jumlah_kelas
    FROM tbl_mapel mp 
    ORDER BY mp.nama_mapel ASC
";
$daftar_mapel = $pdo->query($query)->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book me-2"></i>Data Mata Pelajaran</h1>
        <span class="badge bg-primary p-2">Total: <?= count($daftar_mapel) ?> Mapel</span>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Mata Pelajaran</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="50">No</th>
                            <th>Kode Mapel</th>
                            <th>Nama Mata Pelajaran</th>
                            <th>Diampu oleh</th>
                            <th>Di Kelas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar_mapel as $i => $m): ?>
                        <tr>
                            <td class="text-center"><?= $i + 1 ?></td>
                            <td><code><?= htmlspecialchars($m['kode_mapel'] ?? '-') ?></code></td>
                            <td><strong><?= htmlspecialchars($m['nama_mapel']) ?></strong></td>
                            <td class="text-center">
                                <span class="badge bg-info"><?= $m['jumlah_guru'] ?> Guru</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary"><?= $m['jumlah_kelas'] ?> Kelas</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
