<?php
/* File: admin/edit_guru.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';

// 1. Ambil Data Guru Berdasarkan ID (dari tbl_guru)
$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: manage_guru.php");
    exit;
}

// Query join untuk mendapatkan data profil dan data akun login
$stmt = $pdo->prepare("
    SELECT g.*, u.username, u.role 
    FROM tbl_guru g 
    JOIN tbl_users u ON g.user_id = u.id 
    WHERE g.id = ?
");
$stmt->execute([$id]);
$guru = $stmt->fetch();

if (!$guru) {
    header("Location: manage_guru.php");
    exit;
}

// 2. LOGIKA UPDATE DATA
if (isset($_POST['update_guru'])) {
    $nip = trim($_POST['nip']);
    $nama = trim($_POST['nama_guru']);
    $username = trim($_POST['username']);
    $role = $_POST['role']; // guru atau walikelas
    $user_id = $guru['user_id'];

    try {
        $pdo->beginTransaction();

        // Update Tabel Users (Username & Role)
        $upd_user = $pdo->prepare("UPDATE tbl_users SET username = ?, role = ? WHERE id = ?");
        $upd_user->execute([$username, $role, $user_id]);

        // Update Tabel Guru (NIP & Nama)
        $upd_guru = $pdo->prepare("UPDATE tbl_guru SET nip = ?, nama_guru = ? WHERE id = ?");
        $upd_guru->execute([$nip, $nama, $id]);

        $pdo->commit();
        header("Location: manage_guru.php?status=updated");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Error: Username atau NIP mungkin sudah digunakan pengguna lain.</div>";
    }
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Data Guru</h1>
        <a href="manage_guru.php" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <?php echo $message; ?>

    <div class="row">
        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">Form Perubahan Data</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">NIP</label>
                                <input type="text" name="nip" class="form-control" value="<?php echo htmlspecialchars($guru['nip']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username (Login)</label>
                                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($guru['username']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_guru" class="form-control" value="<?php echo htmlspecialchars($guru['nama_guru']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Peran / Role</label>
                            <select name="role" class="form-select">
                                <option value="guru" <?php echo ($guru['role'] == 'guru') ? 'selected' : ''; ?>>Guru Mata Pelajaran</option>
                                <option value="walikelas" <?php echo ($guru['role'] == 'walikelas') ? 'selected' : ''; ?>>Wali Kelas</option>
                            </select>
                            <div class="form-text">Wali Kelas memiliki menu tambahan rekap absensi.</div>
                        </div>

                        <hr>
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="update_guru" class="btn btn-primary px-4">
                                <i class="fas fa-save me-1"></i> Simpan Perubahan
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="alert('Fitur reset password bisa Anda tambahkan di sini.')">
                                <i class="fas fa-key me-1"></i> Reset Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-left-info shadow py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Status Akun</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Aktif</div>
                            <p class="mt-2 small text-muted">ID User: #<?php echo $guru['user_id']; ?><br>ID Guru: #<?php echo $guru['id']; ?></p>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-info-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>