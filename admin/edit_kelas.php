<?php
/*
File: edit_kelas.php (FILE BARU)
Lokasi: /jurnal_app/admin/edit_kelas.php
*/

// 1. Panggil
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';
$kelas = null;

// ==========================================================
// LOGIKA 1: Ambil ID dari URL
// ==========================================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage_kelas.php');
    exit;
}
$id_kelas = $_GET['id'];

// ==========================================================
// LOGIKA 2: PROSES UPDATE DATA (SAAT FORM DI-SUBMIT)
// ==========================================================
if (isset($_POST['update_kelas'])) {
    $id_kelas_form = $_POST['id_kelas'];
    $nama_kelas = $_POST['nama_kelas'];
    $id_wali_kelas = !empty($_POST['id_wali_kelas']) ? $_POST['id_wali_kelas'] : NULL; // Handle jika "--Pilih--"

    try {
        // Siapkan query UPDATE
        $stmt = $pdo->prepare("UPDATE tbl_kelas SET nama_kelas = ?, id_wali_kelas = ? WHERE id = ?");
        $stmt->execute([$nama_kelas, $id_wali_kelas, $id_kelas_form]);

        // Set pesan sukses dan redirect
        $_SESSION['success_message'] = "Data Kelas berhasil diperbarui!";
        header('Location: manage_kelas.php');
        exit;

    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            $message = "<div class='alert alert-danger'>Gagal: Nama Kelas '$nama_kelas' sudah ada.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Gagal memperbarui: " . $e->getMessage() . "</div>";
        }
    }
}

// ==========================================================
// LOGIKA 3: AMBIL DATA LAMA (SAAT HALAMAN DIBUKA)
// ==========================================================
try {
    // Ambil data kelas yang mau di-edit
    $stmt_kelas = $pdo->prepare("SELECT * FROM tbl_kelas WHERE id = ?");
    $stmt_kelas->execute([$id_kelas]);
    $kelas = $stmt_kelas->fetch();

    if (!$kelas) {
        $_SESSION['success_message'] = "Data Kelas tidak ditemukan.";
        header('Location: manage_kelas.php');
        exit;
    }
    
    // Ambil SEMUA guru untuk dropdown
    $daftar_guru = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru ASC")->fetchAll();

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4>Edit Data Kelas</h4>
            </div>
            <div class="card-body">
                <?php echo $message; // Tampilkan pesan error jika update gagal ?>
                
                <?php if ($kelas): // Pastikan data kelas ada ?>
                    <form action="edit_kelas.php?id=<?php echo $kelas['id']; ?>" method="POST">
                        <input type="hidden" name="id_kelas" value="<?php echo $kelas['id']; ?>">
                        
                        <div class="mb-3">
                            <label for="nama_kelas" class="form-label">Nama Kelas</label>
                            <input type="text" class="form-control" id="nama_kelas" name="nama_kelas" 
                                   value="<?php echo htmlspecialchars($kelas['nama_kelas']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="id_wali_kelas" class="form-label">Wali Kelas</label>
                            <select class="form-select" id="id_wali_kelas" name="id_wali_kelas">
                                <option value="">-- Pilih Wali Kelas (Opsional) --</option>
                                <?php foreach ($daftar_guru as $guru): ?>
                                    <option value="<?php echo $guru['id']; ?>" 
                                        <?php echo ($guru['id'] == $kelas['id_wali_kelas']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($guru['nama_guru']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" name="update_kelas" class="btn btn-primary">Update Data</button>
                        <a href="manage_kelas.php" class="btn btn-secondary">Batal</a>
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