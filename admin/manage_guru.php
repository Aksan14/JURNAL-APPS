<?php
/* File: admin/manage_guru.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';

// --- LOGIKA TAMBAH GURU ---
if (isset($_POST['add_guru'])) {
    $nip = trim($_POST['nip']);
    $nama = trim($_POST['nama_guru']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['username'], PASSWORD_DEFAULT); // Default password = username

    try {
        $pdo->beginTransaction();
        
        // 1. Insert ke tbl_users
        $stmt1 = $pdo->prepare("INSERT INTO tbl_users (username, password_hash, role) VALUES (?, ?, 'guru')");
        $stmt1->execute([$username, $password]);
        $user_id = $pdo->lastInsertId();

        // 2. Insert ke tbl_guru
        $stmt2 = $pdo->prepare("INSERT INTO tbl_guru (user_id, nip, nama_guru) VALUES (?, ?, ?)");
        $stmt2->execute([$user_id, $nip, $nama]);

        $pdo->commit();
        header("Location: manage_guru.php?status=added");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Error: Username atau NIP mungkin sudah ada.</div>";
    }
}

// --- LOGIKA BUAT AKUN GURU (untuk guru yang sudah ada tapi belum punya akun) ---
if (isset($_POST['buat_akun_guru'])) {
    $id_guru = $_POST['id_guru'];
    $username = trim($_POST['username']);
    $password = password_hash($username, PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();
        
        // Insert ke tbl_users
        $stmt1 = $pdo->prepare("INSERT INTO tbl_users (username, password_hash, role) VALUES (?, ?, 'guru')");
        $stmt1->execute([$username, $password]);
        $user_id = $pdo->lastInsertId();

        // Update tbl_guru dengan user_id
        $stmt2 = $pdo->prepare("UPDATE tbl_guru SET user_id = ? WHERE id = ?");
        $stmt2->execute([$user_id, $id_guru]);

        $pdo->commit();
        header("Location: manage_guru.php?status=akun_created");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Error: Username mungkin sudah digunakan.</div>";
    }
}

// Ambil Data Guru (LEFT JOIN agar guru tanpa akun juga muncul)
$query = "SELECT g.*, u.username FROM tbl_guru g LEFT JOIN tbl_users u ON g.user_id = u.id ORDER BY g.nama_guru ASC";
$daftar_guru = $pdo->query($query)->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Kelola Data Guru</h1>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalGuru">
            <i class="fas fa-plus"></i> Tambah Guru Baru
        </button>
    </div>

    <?php 
    if (isset($_GET['status']) && $_GET['status'] == 'added') echo "<div class='alert alert-success'>Guru berhasil ditambahkan!</div>";
    if (isset($_GET['status']) && $_GET['status'] == 'akun_created') echo "<div class='alert alert-success'>Akun guru berhasil dibuat! Password default = username.</div>";
    echo $message; 
    ?>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Foto</th>
                            <th>NIP</th>
                            <th>Nama Guru</th>
                            <th>Username</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar_guru as $i => $g): 
                            $foto_path = "../assets/img/profile/" . ($g['foto'] ?? '');
                            $has_foto = !empty($g['foto']) && file_exists($foto_path);
                        ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td class="text-center">
                                <?php if ($has_foto): ?>
                                    <img src="<?php echo htmlspecialchars($foto_path); ?>" 
                                         class="rounded-circle" 
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px; font-size: 16px;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($g['nip'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($g['nama_guru']); ?></td>
                            <td class="text-center">
                                <?php if (!empty($g['username'])): ?>
                                    <span class="text-success"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($g['username']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Belum ada akun</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (empty($g['user_id'])): ?>
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalBuatAkunGuru<?= $g['id'] ?>" title="Buat Akun">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                <?php endif; ?>
                                <a href="edit_guru.php?id=<?php echo $g['id']; ?>" class="btn btn-sm btn-info text-white"><i class="fas fa-edit"></i></a>
                            </td>
                        </tr>
                        
                        <!-- Modal Buat Akun Guru -->
                        <?php if (empty($g['user_id'])): ?>
                        <div class="modal fade" id="modalBuatAkunGuru<?= $g['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" class="modal-content">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Buat Akun Guru</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id_guru" value="<?= $g['id'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Guru</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($g['nama_guru']) ?>" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">NIP</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($g['nip'] ?? '-') ?>" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Username (untuk Login)</label>
                                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($g['nip'] ?? '') ?>" required>
                                            <div class="form-text">Default: NIP guru. Bisa diubah sesuai kebutuhan.</div>
                                        </div>
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle"></i> Password default = Username
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" name="buat_akun_guru" class="btn btn-success">Buat Akun</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGuru" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header"><h5>Tambah Guru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label>NIP</label><input type="text" name="nip" class="form-control" required></div>
                <div class="mb-3"><label>Nama Lengkap</label><input type="text" name="nama_guru" class="form-control" required></div>
                <div class="mb-3"><label>Username (untuk Login)</label><input type="text" name="username" class="form-control" required></div>
                <small class="text-muted text-italic">*Password default sama dengan username.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="add_guru" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>