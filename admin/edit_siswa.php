<?php
/*
File: edit_siswa.php (FILE BARU)
Lokasi: /jurnal_app/admin/edit_siswa.php
*/

// 1. Panggil
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';
$siswa = null;

// ==========================================================
// LOGIKA 1: Ambil ID dari URL
// ==========================================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage_siswa.php');
    exit;
}
$id_siswa = $_GET['id'];

// ==========================================================
// LOGIKA 2: PROSES UPDATE DATA (SAAT FORM DI-SUBMIT)
// ==========================================================
if (isset($_POST['update_siswa'])) {
    // Ambil data dari form
    $id_siswa_form = $_POST['id_siswa'];
    $user_id = $_POST['user_id'];
    $nis = $_POST['nis'];
    $nama_siswa = $_POST['nama_siswa'];
    $id_kelas = $_POST['id_kelas'];
    $username = $_POST['username'];
    $password = $_POST['password']; // Password baru (jika diisi)

    // Mulai Transaksi
    $pdo->beginTransaction();
    try {
        // Step 1: Update data profil di tbl_siswa
        $stmt_siswa = $pdo->prepare("UPDATE tbl_siswa SET nis = ?, nama_siswa = ?, id_kelas = ? WHERE id = ?");
        $stmt_siswa->execute([$nis, $nama_siswa, $id_kelas, $id_siswa_form]);

        // Step 2: Update data login di tbl_users (username)
        $stmt_user = $pdo->prepare("UPDATE tbl_users SET username = ? WHERE id = ?");
        $stmt_user->execute([$username, $user_id]);

        // Step 3: (Opsional) Update password HANYA JIKA DIISI
        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_pass = $pdo->prepare("UPDATE tbl_users SET password_hash = ? WHERE id = ?");
            $stmt_pass->execute([$password_hash, $user_id]);
        }

        // Jika semua berhasil, commit
        $pdo->commit();
        
        // Set pesan sukses dan redirect
        $_SESSION['success_message'] = "Data Siswa berhasil diperbarui!";
        header('Location: manage_siswa.php');
        exit;

    } catch (PDOException $e) {
        // Jika ada error, batalkan semua
        $pdo->rollBack();
        if ($e->getCode() == '23000') {
            // Error duplikat (NIS atau Username)
            $message = "<div class='alert alert-danger'>Gagal: NIS atau Username '$username' sudah ada.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Gagal memperbarui: " . $e->getMessage() . "</div>";
        }
    }
}

// ==========================================================
// LOGIKA 3: AMBIL DATA LAMA (SAAT HALAMAN DIBUKA)
// ==========================================================
try {
    // Ambil data siswa + username untuk ditampilkan di form
    $stmt = $pdo->prepare("SELECT s.*, u.username 
                           FROM tbl_siswa s 
                           JOIN tbl_users u ON s.user_id = u.id 
                           WHERE s.id = ?");
    $stmt->execute([$id_siswa]);
    $siswa = $stmt->fetch();

    if (!$siswa) {
        // Jika ID tidak ditemukan
        $_SESSION['success_message'] = "Data Siswa tidak ditemukan.";
        header('Location: manage_siswa.php');
        exit;
    }
    
    // Ambil SEMUA kelas untuk dropdown
    $daftar_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC")->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4>Edit Data Siswa</h4>
            </div>
            <div class="card-body">
                <?php echo $message; // Tampilkan pesan error jika update gagal ?>
                
                <?php if ($siswa): // Pastikan data siswa ada ?>
                    <form action="edit_siswa.php?id=<?php echo $siswa['id']; ?>" method="POST">
                        
                        <input type="hidden" name="id_siswa" value="<?php echo $siswa['id']; ?>">
                        <input type="hidden" name="user_id" value="<?php echo $siswa['user_id']; ?>">

                        <div class="mb-3">
                            <label for="nis" class="form-label">NIS</label>
                            <input type="text" class="form-control" id="nis" name="nis" 
                                   value="<?php echo htmlspecialchars($siswa['nis']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="nama_siswa" class="form-label">Nama Siswa</label>
                            <input type="text" class="form-control" id="nama_siswa" name="nama_siswa" 
                                   value="<?php echo htmlspecialchars($siswa['nama_siswa']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="id_kelas" class="form-label">Kelas</label>
                            <select class="form-select" id="id_kelas" name="id_kelas" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($daftar_kelas as $kelas): ?>
                                    <option value="<?php echo $kelas['id']; ?>" 
                                        <?php echo ($kelas['id'] == $siswa['id_kelas']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <hr>
                        <p class="text-muted">Edit Akun Login:</p>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($siswa['username']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password.</small>
                        </div>
                        
                        <button type="submit" name="update_siswa" class="btn btn-primary">Update Data</button>
                        <a href="manage_siswa.php" class="btn btn-secondary">Batal</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// 5. Panggil footer
require_once '../includes/footer.php';
?>