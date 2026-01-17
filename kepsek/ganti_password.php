<?php
/*
File: kepsek/ganti_password.php
Ganti Password Kepala Sekolah
*/
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['kepsek']);

$message = '';
$user_id = $_SESSION['user_id'];

// --- LOGIKA UPDATE PASSWORD ---
if (isset($_POST['change_pwd'])) {
    $pass_lama = $_POST['pass_lama'];
    $pass_baru = $_POST['pass_baru'];
    $konfirmasi = $_POST['konfirmasi'];

    // 1. Ambil password hash saat ini dari database
    $stmt = $pdo->prepare("SELECT password_hash FROM tbl_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // 2. Validasi
    if (!password_verify($pass_lama, $user['password_hash'])) {
        $message = "<div class='alert alert-danger'>Password lama salah!</div>";
    } elseif ($pass_baru !== $konfirmasi) {
        $message = "<div class='alert alert-warning'>Konfirmasi password baru tidak cocok!</div>";
    } elseif (strlen($pass_baru) < 6) {
        $message = "<div class='alert alert-warning'>Password baru minimal 6 karakter!</div>";
    } else {
        // 3. Hash password baru dan update
        $new_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE tbl_users SET password_hash = ? WHERE id = ?");
        $update->execute([$new_hash, $user_id]);
        
        $message = "<div class='alert alert-success'>Password berhasil diperbarui!</div>";
    }
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-lock me-2"></i>Ganti Password</h1>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">Form Ganti Password</h6>
                </div>
                <div class="card-body">
                    <?php echo $message; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Password Saat Ini</label>
                            <input type="password" name="pass_lama" class="form-control" required>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="pass_baru" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" name="konfirmasi" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="change_pwd" class="btn btn-primary">
                            <i class="fas fa-key me-1"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
