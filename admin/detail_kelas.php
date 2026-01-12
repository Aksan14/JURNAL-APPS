<?php
/* File: admin/detail_kelas.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$id_kelas = $_GET['id'] ?? 0;

if (!$id_kelas) {
    header("Location: manage_kelas.php");
    exit;
}

// Ambil data kelas
$stmt_kelas = $pdo->prepare("
    SELECT k.*, g.nama_guru as wali_kelas 
    FROM tbl_kelas k 
    LEFT JOIN tbl_guru g ON k.id_wali_kelas = g.id 
    WHERE k.id = ?
");
$stmt_kelas->execute([$id_kelas]);
$kelas = $stmt_kelas->fetch();

if (!$kelas) {
    header("Location: manage_kelas.php");
    exit;
}

// Ambil daftar siswa di kelas ini
$stmt_siswa = $pdo->prepare("
    SELECT s.*, u.username 
    FROM tbl_siswa s 
    LEFT JOIN tbl_users u ON s.user_id = u.id 
    WHERE s.id_kelas = ? 
    ORDER BY s.nama_siswa ASC
");
$stmt_siswa->execute([$id_kelas]);
$daftar_siswa = $stmt_siswa->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <a href="manage_kelas.php" class="btn btn-secondary btn-sm mb-2">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
            <h1 class="h3 mb-0 text-gray-800">Detail Kelas: <?php echo htmlspecialchars($kelas['nama_kelas']); ?></h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-info-circle me-2"></i>Informasi Kelas</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td width="40%"><strong>Nama Kelas</strong></td>
                            <td><?php echo htmlspecialchars($kelas['nama_kelas']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Wali Kelas</strong></td>
                            <td>
                                <?php if ($kelas['wali_kelas']): ?>
                                    <span class="text-success"><i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($kelas['wali_kelas']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Belum ada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Jumlah Siswa</strong></td>
                            <td><span class="badge bg-info fs-6"><?php echo count($daftar_siswa); ?> siswa</span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-users me-2"></i>Daftar Siswa</h6>
                    <span class="badge bg-light text-success"><?php echo count($daftar_siswa); ?> siswa</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th width="50">No</th>
                                    <th width="60">Foto</th>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Username</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($daftar_siswa) > 0): ?>
                                    <?php foreach ($daftar_siswa as $i => $s): 
                                        $foto_path = "../assets/img/profile/" . ($s['foto'] ?? '');
                                        $has_foto = !empty($s['foto']) && file_exists($foto_path);
                                    ?>
                                    <tr>
                                        <td><?php echo $i+1; ?></td>
                                        <td class="text-center">
                                            <?php if ($has_foto): ?>
                                                <img src="<?php echo htmlspecialchars($foto_path); ?>" 
                                                     class="rounded-circle" 
                                                     style="width: 35px; height: 35px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                                                     style="width: 35px; height: 35px; font-size: 14px;">
                                                    <i class="fas fa-user-graduate"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($s['nis']); ?></td>
                                        <td><?php echo htmlspecialchars($s['nama_siswa']); ?></td>
                                        <td>
                                            <?php if (!empty($s['username'])): ?>
                                                <span class="text-success"><i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($s['username']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Belum ada akun</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                                            <p class="mb-0">Belum ada siswa di kelas ini.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
