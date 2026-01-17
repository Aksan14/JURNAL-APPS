<?php
/*
File: kepsek/lihat_kelas.php
Lihat Data Kelas (Read Only)
*/
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['kepsek']);

// Ambil Data Kelas
$query = "
    SELECT k.*, g.nama_guru as wali_kelas,
           (SELECT COUNT(*) FROM tbl_siswa WHERE id_kelas = k.id) as jumlah_siswa
    FROM tbl_kelas k 
    LEFT JOIN tbl_guru g ON k.id_wali_kelas = g.id 
    ORDER BY k.nama_kelas ASC
";
$daftar_kelas = $pdo->query($query)->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-school me-2"></i>Data Kelas</h1>
        <span class="badge bg-primary p-2">Total: <?= count($daftar_kelas) ?> Kelas</span>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Kelas</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="50">No</th>
                            <th>Nama Kelas</th>
                            <th>Wali Kelas</th>
                            <th>Jumlah Siswa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar_kelas as $i => $k): ?>
                        <tr>
                            <td class="text-center"><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($k['nama_kelas']) ?></strong></td>
                            <td><?= htmlspecialchars($k['wali_kelas'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="badge bg-success"><?= $k['jumlah_siswa'] ?> Siswa</span>
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
