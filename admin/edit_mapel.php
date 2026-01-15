<?php
/*
File: admin/edit_mapel.php
Deskripsi: Mengedit nama mata pelajaran oleh Admin.
*/

// 1. Panggil config dan pengecekan otentikasi (WAJIB DI PALING ATAS)
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';

// 2. PROSES LOGIKA UPDATE (Dilakukan sebelum memanggil header.php)
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $kode_mapel = trim($_POST['kode_mapel'] ?? '');
    $nama_mapel = trim($_POST['nama_mapel']);

    if (!empty($nama_mapel)) {
        try {
            $stmt = $pdo->prepare("UPDATE tbl_mapel SET kode_mapel = ?, nama_mapel = ? WHERE id = ?");
            if ($stmt->execute([$kode_mapel ?: null, $nama_mapel, $id])) {
                // Redirect ke halaman manage_mapel setelah sukses
                header("Location: manage_mapel.php?status=updated");
                exit; // Hentikan script agar redirect berjalan sempurna
            }
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Nama mata pelajaran tidak boleh kosong!</div>";
    }
}

// 3. AMBIL DATA UNTUK FORM (Berdasarkan ID di URL)
$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: manage_mapel.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tbl_mapel WHERE id = ?");
$stmt->execute([$id]);
$mapel = $stmt->fetch();

// Jika ID tidak ditemukan di database
if (!$mapel) {
    header("Location: manage_mapel.php");
    exit;
}

// 4. BARU PANGGIL HEADER (Awal Output HTML)
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Mata Pelajaran</h1>
        <a href="manage_mapel.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Kembali
        </a>
    </div>

    <?php echo $message; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Form Edit Mapel</h6>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($mapel['id']); ?>">

                        <div class="mb-3">
                            <label for="kode_mapel" class="form-label">Kode Mata Pelajaran</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="kode_mapel" 
                                   name="kode_mapel" 
                                   value="<?php echo htmlspecialchars($mapel['kode_mapel'] ?? ''); ?>" 
                                   placeholder="Contoh: MTK, IPA, BIG">
                            <div class="form-text">Kode singkat untuk identifikasi mapel (boleh kosong)</div>
                        </div>

                        <div class="mb-3">
                            <label for="nama_mapel" class="form-label">Nama Mata Pelajaran</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nama_mapel" 
                                   name="nama_mapel" 
                                   value="<?php echo htmlspecialchars($mapel['nama_mapel']); ?>" 
                                   required>
                            <div class="form-text">Contoh: Matematika, Bahasa Indonesia, dsb.</div>
                        </div>

                        <hr>
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                        <a href="manage_mapel.php" class="btn btn-light border">Batal</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// 5. Panggil Footer
require_once '../includes/footer.php'; 
?>