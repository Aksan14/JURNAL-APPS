<?php
/*
File: kepsek/lihat_siswa.php
Lihat Data Siswa (Read Only)
*/
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['kepsek']);

// Filter kelas
$filter_kelas = $_GET['kelas'] ?? '';

// Ambil daftar kelas untuk filter
$daftar_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas")->fetchAll();

// Query siswa
$sql = "
    SELECT s.*, k.nama_kelas, u.username
    FROM tbl_siswa s 
    JOIN tbl_kelas k ON s.id_kelas = k.id 
    LEFT JOIN tbl_users u ON s.user_id = u.id
";
if (!empty($filter_kelas)) {
    $sql .= " WHERE s.id_kelas = :kelas";
}
$sql .= " ORDER BY k.nama_kelas, s.nama_siswa ASC";

$stmt = $pdo->prepare($sql);
if (!empty($filter_kelas)) {
    $stmt->bindParam(':kelas', $filter_kelas, PDO::PARAM_INT);
}
$stmt->execute();
$daftar_siswa = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-graduate me-2"></i>Data Siswa</h1>
        <span class="badge bg-primary p-2">Total: <?= count($daftar_siswa) ?> Siswa</span>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label class="form-label">Filter Kelas</label>
                    <select name="kelas" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Semua Kelas --</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $filter_kelas == $k['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['nama_kelas']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Siswa</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="50">No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Username</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar_siswa as $i => $s): ?>
                        <tr>
                            <td class="text-center"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($s['nis']) ?></td>
                            <td><strong><?= htmlspecialchars($s['nama_siswa']) ?></strong></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($s['nama_kelas']) ?></span></td>
                            <td>
                                <?php if ($s['username']): ?>
                                    <span class="text-success"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($s['username']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
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
