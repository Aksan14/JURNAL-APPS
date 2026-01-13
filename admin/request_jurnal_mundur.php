<?php
/*
File: request_jurnal_mundur.php
Lokasi: /jurnal_app/admin/request_jurnal_mundur.php
Fungsi: Halaman admin untuk melihat dan memproses permintaan jurnal mundur
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = $_POST['request_id'] ?? '';
    $catatan_admin = trim($_POST['catatan_admin'] ?? '');
    
    if ($action === 'approve' && $request_id) {
        try {
            $stmt = $pdo->prepare("
                UPDATE tbl_request_jurnal_mundur 
                SET status = 'approved', catatan_admin = ?, approved_by = ?, approved_at = NOW(), notified_guru = 0
                WHERE id = ?
            ");
            $stmt->execute([$catatan_admin, $_SESSION['user_id'], $request_id]);
            $message = "<div class='alert alert-success alert-dismissible fade show'>
                <i class='bi bi-check-circle me-2'></i>Permintaan berhasil disetujui!
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Gagal: " . $e->getMessage() . "</div>";
        }
    }
    
    if ($action === 'reject' && $request_id) {
        try {
            $stmt = $pdo->prepare("
                UPDATE tbl_request_jurnal_mundur 
                SET status = 'rejected', catatan_admin = ?, approved_by = ?, approved_at = NOW(), notified_guru = 0
                WHERE id = ?
            ");
            $stmt->execute([$catatan_admin, $_SESSION['user_id'], $request_id]);
            $message = "<div class='alert alert-warning alert-dismissible fade show'>
                <i class='bi bi-x-circle me-2'></i>Permintaan ditolak.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Gagal: " . $e->getMessage() . "</div>";
        }
    }
}

// Filter status
$filter_status = $_GET['status'] ?? 'pending';

// Ambil data request
$sql = "
    SELECT r.*, g.nama_guru, g.nip, k.nama_kelas, mp.nama_mapel, m.hari, m.jam_ke as jam_jadwal,
           DATE_FORMAT(r.tanggal_jurnal, '%W, %d %M %Y') as tanggal_format,
           DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as created_format,
           DATE_FORMAT(r.approved_at, '%d/%m/%Y %H:%i') as approved_format,
           u.username as approved_by_name
    FROM tbl_request_jurnal_mundur r
    JOIN tbl_guru g ON r.id_guru = g.id
    JOIN tbl_mengajar m ON r.id_mengajar = m.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    LEFT JOIN tbl_users u ON r.approved_by = u.id
    WHERE 1=1
";

if ($filter_status !== 'all') {
    $sql .= " AND r.status = :status";
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
if ($filter_status !== 'all') {
    $stmt->bindParam(':status', $filter_status);
}
$stmt->execute();
$requests = $stmt->fetchAll();

// Hitung total pending
$stmt_pending = $pdo->query("SELECT COUNT(*) FROM tbl_request_jurnal_mundur WHERE status = 'pending'");
$total_pending = $stmt_pending->fetchColumn();

require_once '../includes/header.php';
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold">
                <i class="bi bi-envelope-paper me-2"></i>Permintaan Jurnal Mundur
            </h4>
            <small class="text-muted">Kelola permintaan izin pengisian jurnal lebih dari <?= MAX_HARI_MUNDUR_JURNAL ?> hari</small>
        </div>
        <?php if ($total_pending > 0): ?>
        <span class="badge bg-danger fs-6"><?= $total_pending ?> Menunggu</span>
        <?php endif; ?>
    </div>

    <?= $message ?>

    <!-- Filter -->
    <div class="btn-group mb-4" role="group">
        <a href="?status=pending" class="btn btn-<?= $filter_status === 'pending' ? 'warning' : 'outline-warning' ?>">
            <i class="bi bi-clock me-1"></i>Pending
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

    <?php if (empty($requests)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>Tidak ada permintaan dengan status ini.
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($requests as $req): ?>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                            <div>
                                <strong class="text-primary"><?= htmlspecialchars($req['nama_guru']) ?></strong>
                                <small class="text-muted d-block"><?= $req['nip'] ?: 'NIP: -' ?></small>
                            </div>
                            <?php if ($req['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>
                            <?php elseif ($req['status'] === 'approved'): ?>
                                <span class="badge bg-success"><i class="bi bi-check me-1"></i>Disetujui</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x me-1"></i>Ditolak</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Kelas & Mapel</small>
                                        <strong><?= htmlspecialchars($req['nama_kelas']) ?> - <?= htmlspecialchars($req['nama_mapel']) ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Jadwal</small>
                                        <strong><?= $req['hari'] ?>, Jam <?= $req['jam_jadwal'] ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3 p-2 bg-light rounded">
                                <small class="text-muted d-block">Tanggal Jurnal yang Diminta</small>
                                <strong class="text-primary fs-6">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?= htmlspecialchars($req['tanggal_format']) ?>
                                </strong>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted d-block">Alasan/Pesan dari Guru</small>
                                <div class="border rounded p-2 bg-white">
                                    <i class="bi bi-chat-quote text-secondary me-1"></i>
                                    <?= nl2br(htmlspecialchars($req['alasan'])) ?>
                                </div>
                            </div>
                            
                            <small class="text-muted">
                                <i class="bi bi-clock-history me-1"></i>Diajukan: <?= $req['created_format'] ?>
                            </small>
                            
                            <?php if ($req['status'] !== 'pending'): ?>
                                <div class="mt-2 pt-2 border-top">
                                    <small class="text-muted">
                                        <i class="bi bi-person-check me-1"></i>
                                        Diproses oleh: <?= $req['approved_by_name'] ?? '-' ?> 
                                        (<?= $req['approved_format'] ?>)
                                    </small>
                                    <?php if ($req['catatan_admin']): ?>
                                        <div class="mt-1 p-2 bg-light rounded small">
                                            <strong>Catatan Admin:</strong> <?= htmlspecialchars($req['catatan_admin']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($req['status'] === 'pending'): ?>
                        <div class="card-footer bg-white">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success btn-sm flex-fill" 
                                        data-bs-toggle="modal" data-bs-target="#modalApprove<?= $req['id'] ?>">
                                    <i class="bi bi-check-lg me-1"></i>Setujui
                                </button>
                                <button type="button" class="btn btn-danger btn-sm flex-fill"
                                        data-bs-toggle="modal" data-bs-target="#modalReject<?= $req['id'] ?>">
                                    <i class="bi bi-x-lg me-1"></i>Tolak
                                </button>
                            </div>
                        </div>
                        
                        <!-- Modal Approve -->
                        <div class="modal fade" id="modalApprove<?= $req['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                        <div class="modal-header bg-success text-white">
                                            <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Setujui Permintaan</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Setujui permintaan dari <strong><?= htmlspecialchars($req['nama_guru']) ?></strong> untuk mengisi jurnal tanggal:</p>
                                            <p class="fs-5 text-center text-primary fw-bold"><?= $req['tanggal_format'] ?></p>
                                            <div class="mb-3">
                                                <label class="form-label">Catatan (Opsional)</label>
                                                <textarea class="form-control" name="catatan_admin" rows="2" placeholder="Tambahkan catatan jika perlu..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-check me-1"></i>Ya, Setujui
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal Reject -->
                        <div class="modal fade" id="modalReject<?= $req['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Tolak Permintaan</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Tolak permintaan dari <strong><?= htmlspecialchars($req['nama_guru']) ?></strong>?</p>
                                            <div class="mb-3">
                                                <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                                                <textarea class="form-control" name="catatan_admin" rows="2" required placeholder="Berikan alasan penolakan..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-danger">
                                                <i class="bi bi-x me-1"></i>Ya, Tolak
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
