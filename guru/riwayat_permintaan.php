<?php
/*
File: riwayat_permintaan.php
Lokasi: /jurnal_app/guru/riwayat_permintaan.php
Fungsi: Halaman guru untuk melihat riwayat permintaan jurnal mundur
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

$user_id = $_SESSION['user_id'];

// Ambil ID Guru
$stmt_guru = $pdo->prepare("SELECT id, nama_guru FROM tbl_guru WHERE user_id = ?");
$stmt_guru->execute([$user_id]);
$guru = $stmt_guru->fetch();
$id_guru_login = $guru['id'];

// Filter status
$filter_status = $_GET['status'] ?? 'all';

// Ambil data permintaan
$sql = "
    SELECT r.*, k.nama_kelas, mp.nama_mapel, m.hari,
           DATE_FORMAT(r.tanggal_jurnal, '%W, %d %M %Y') as tanggal_format,
           DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as created_format,
           DATE_FORMAT(r.approved_at, '%d/%m/%Y %H:%i') as approved_format
    FROM tbl_request_jurnal_mundur r
    JOIN tbl_mengajar m ON r.id_mengajar = m.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE r.id_guru = :id_guru
";

if ($filter_status !== 'all') {
    $sql .= " AND r.status = :status";
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id_guru', $id_guru_login, PDO::PARAM_INT);
if ($filter_status !== 'all') {
    $stmt->bindParam(':status', $filter_status);
}
$stmt->execute();
$requests = $stmt->fetchAll();

// Hitung statistik
$stmt_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM tbl_request_jurnal_mundur WHERE id_guru = ?
");
$stmt_stats->execute([$id_guru_login]);
$stats = $stmt_stats->fetch();

require_once '../includes/header.php';
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold">
                <i class="bi bi-envelope-paper me-2"></i>Riwayat Permintaan Jurnal Mundur
            </h4>
            <small class="text-muted">Daftar permintaan izin pengisian jurnal lebih dari <?= MAX_HARI_MUNDUR_JURNAL ?> hari</small>
        </div>
        <a href="isi_jurnal.php" class="btn btn-primary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <!-- Filter -->
    <div class="btn-group mb-4" role="group">
        <a href="?status=pending" class="btn btn-<?= $filter_status === 'pending' ? 'warning' : 'outline-warning' ?>">
            <i class="bi bi-clock me-1"></i>Pending
            <?php if ($stats['pending'] > 0): ?>
            <span class="badge bg-dark"><?= $stats['pending'] ?></span>
            <?php endif; ?>
        </a>
        <a href="?status=approved" class="btn btn-<?= $filter_status === 'approved' ? 'success' : 'outline-success' ?>">
            <i class="bi bi-check-circle me-1"></i>Disetujui
        </a>
        <a href="?status=rejected" class="btn btn-<?= $filter_status === 'rejected' ? 'danger' : 'outline-danger' ?>">
            <i class="bi bi-x-circle me-1"></i>Ditolak
        </a>
        <a href="?status=all" class="btn btn-<?= $filter_status === 'all' ? 'secondary' : 'outline-secondary' ?>">
            <i class="bi bi-list me-1"></i>Semua
        </a>
    </div>

    <?php if (count($requests) > 0): ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal Request</th>
                            <th>Kelas & Mapel</th>
                            <th>Tanggal Jurnal</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th>Catatan Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>
                                <small class="text-muted"><?= $req['created_format'] ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($req['nama_kelas']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($req['nama_mapel']) ?></small>
                            </td>
                            <td>
                                <i class="bi bi-calendar me-1 text-primary"></i>
                                <?= $req['tanggal_format'] ?>
                            </td>
                            <td style="max-width: 250px;">
                                <small><?= htmlspecialchars($req['alasan']) ?></small>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-clock"></i> Menunggu
                                    </span>
                                <?php elseif ($req['status'] === 'approved'): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Disetujui
                                    </span>
                                    <?php if ($req['approved_format']): ?>
                                    <br><small class="text-muted"><?= $req['approved_format'] ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="bi bi-x-circle"></i> Ditolak
                                    </span>
                                    <?php if ($req['approved_format']): ?>
                                    <br><small class="text-muted"><?= $req['approved_format'] ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 200px;">
                                <?php if (!empty($req['catatan_admin'])): ?>
                                    <small class="<?= $req['status'] === 'approved' ? 'text-success' : 'text-danger' ?>">
                                        <i class="bi bi-chat-quote"></i> <?= htmlspecialchars($req['catatan_admin']) ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="bi bi-inbox fs-1 text-muted"></i>
        <p class="text-muted mt-3">
            <?php if ($filter_status === 'all'): ?>
                Belum ada permintaan jurnal mundur
            <?php else: ?>
                Tidak ada permintaan dengan status "<?= $filter_status ?>"
            <?php endif; ?>
        </p>
        <a href="isi_jurnal.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Isi Jurnal Baru
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
