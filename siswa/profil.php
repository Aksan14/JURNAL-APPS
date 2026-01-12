<?php
/* File: siswa/profil.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['siswa']);

$user_id = $_SESSION['user_id'];
$message = '';

// 1. Ambil Data Siswa Saat Ini
$stmt = $pdo->prepare("
    SELECT s.*, k.nama_kelas 
    FROM tbl_siswa s 
    LEFT JOIN tbl_kelas k ON s.id_kelas = k.id 
    WHERE s.user_id = ?
");
$stmt->execute([$user_id]);
$siswa = $stmt->fetch();

// 2. LOGIKA UPDATE PROFIL & FOTO
if (isset($_POST['update_profil'])) {
    $nama = trim($_POST['nama_siswa']);
    $foto_name = $siswa['foto'] ?? null;

    // Cek jika ada file foto yang diunggah
    if (!empty($_FILES['foto']['name'])) {
        $file_name = $_FILES['foto']['name'];
        $file_size = $_FILES['foto']['size'];
        $file_tmp  = $_FILES['foto']['tmp_name'];
        $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
        
        $allowed_type = ['jpg', 'jpeg', 'png'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array(strtolower($file_type), $allowed_type)) {
            $message = "<div class='alert alert-danger'>Format file harus JPG atau PNG!</div>";
        } elseif ($file_size > $max_size) {
            $message = "<div class='alert alert-danger'>Ukuran file maksimal 2MB!</div>";
        } else {
            // Beri nama unik untuk foto
            $foto_name = "siswa_" . $user_id . "_" . time() . "." . $file_type;
            $target_dir = "../assets/img/profile/" . $foto_name;

            // Buat folder jika belum ada
            if (!is_dir("../assets/img/profile/")) {
                mkdir("../assets/img/profile/", 0777, true);
            }

            // Hapus foto lama jika ada
            if (!empty($siswa['foto']) && file_exists("../assets/img/profile/" . $siswa['foto'])) {
                unlink("../assets/img/profile/" . $siswa['foto']);
            }

            move_uploaded_file($file_tmp, $target_dir);
        }
    }

    // Update Database
    if (empty($message)) {
        $update = $pdo->prepare("UPDATE tbl_siswa SET nama_siswa = ?, foto = ? WHERE user_id = ?");
        $update->execute([$nama, $foto_name, $user_id]);
        header("Location: profil.php?status=updated");
        exit;
    }
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Profil Saya</h1>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'updated') echo "<div class='alert alert-success'>Profil berhasil diperbarui!</div>"; ?>
    <?php echo $message; ?>

    <?php 
    // Set foto default jika tidak ada
    $foto_path = "../assets/img/profile/" . ($siswa['foto'] ?? '');
    $has_foto = !empty($siswa['foto']) && file_exists($foto_path);
    ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow mb-4 text-center">
                <div class="card-body">
                    <?php if ($has_foto): ?>
                        <img src="<?php echo htmlspecialchars($foto_path); ?>" 
                             class="img-profile rounded-circle mb-3" 
                             style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #e3e6f0;">
                    <?php else: ?>
                        <div class="rounded-circle mb-3 mx-auto d-flex align-items-center justify-content-center bg-primary text-white" 
                             style="width: 150px; height: 150px; border: 3px solid #e3e6f0; font-size: 60px;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    <?php endif; ?>
                    <h5 class="font-weight-bold"><?php echo htmlspecialchars($siswa['nama_siswa'] ?? ''); ?></h5>
                    <p class="text-muted mb-1">NIS: <?php echo htmlspecialchars($siswa['nis'] ?? ''); ?></p>
                    <span class="badge bg-info"><?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?></span>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Informasi Profil</h6>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">NIS</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($siswa['nis'] ?? ''); ?>" readonly disabled>
                            <div class="form-text">NIS tidak dapat diubah.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kelas</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?>" readonly disabled>
                            <div class="form-text">Kelas tidak dapat diubah. Hubungi admin jika ada kesalahan.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_siswa" class="form-control" value="<?php echo htmlspecialchars($siswa['nama_siswa'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ubah Foto Profil</label>
                            <input type="file" name="foto" class="form-control">
                            <div class="form-text">Format: JPG/PNG, Maksimal 2MB.</div>
                        </div>
                        <hr>
                        <button type="submit" name="update_profil" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Profil
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
