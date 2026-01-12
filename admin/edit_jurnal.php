<?php
/*
File: admin/edit_jurnal.php (NEW FILE)
Lokasi: /jurnal_app/admin/edit_jurnal.php
*/

// 1. Panggil
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
checkRole(['admin']); // Hanya admin

$message = '';
$jurnal = null;
$id_jurnal = null;

// 2. Validasi ID Jurnal dari URL
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_jurnal = $_GET['id'];
} else {
    $_SESSION['error_message'] = "ID Jurnal tidak valid."; // Gunakan error message
    header('Location: laporan.php');
    exit;
}

// 3. LOGIKA UPDATE (saat form disubmit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_jurnal'])) {
    $id_jurnal_form = $_POST['id_jurnal'];
    $tanggal = $_POST['tanggal'];
    $jam_ke = $_POST['jam_ke'];
    $topik_materi = $_POST['topik_materi'];
    $catatan_guru = $_POST['catatan_guru'];

    // Validasi sederhana (bisa ditambahkan lebih detail)
    if (empty($tanggal) || empty($jam_ke) || empty($topik_materi)) {
        $message = "<div class='alert alert-danger'>Tanggal, Jam Ke-, dan Topik Materi wajib diisi.</div>";
    } else {
        try {
            $stmt_update = $pdo->prepare("
                UPDATE tbl_jurnal 
                SET tanggal = ?, jam_ke = ?, topik_materi = ?, catatan_guru = ?
                WHERE id = ?
            ");
            $stmt_update->execute([
                $tanggal, 
                $jam_ke, 
                $topik_materi, 
                $catatan_guru, 
                $id_jurnal_form
            ]);

            $_SESSION['success_message'] = "Jurnal (ID: $id_jurnal_form) berhasil diperbarui.";
            header('Location: laporan.php'); // Kembali ke laporan admin
            exit;

        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Gagal memperbarui jurnal: " . $e->getMessage() . "</div>";
        }
    }
}

// 4. LOGIKA READ (ambil data jurnal yang mau diedit)
try {
    $stmt_jurnal = $pdo->prepare("
        SELECT 
            j.id, j.tanggal, j.jam_ke, j.topik_materi, j.catatan_guru,
            k.nama_kelas, mp.nama_mapel, g.nama_guru
        FROM tbl_jurnal j
        JOIN tbl_mengajar m ON j.id_mengajar = m.id
        JOIN tbl_kelas k ON m.id_kelas = k.id
        JOIN tbl_mapel mp ON m.id_mapel = mp.id
        JOIN tbl_guru g ON m.id_guru = g.id
        WHERE j.id = ?
    ");
    $stmt_jurnal->execute([$id_jurnal]);
    $jurnal = $stmt_jurnal->fetch();

    if (!$jurnal) {
        $_SESSION['error_message'] = "Jurnal dengan ID $id_jurnal tidak ditemukan.";
        header('Location: laporan.php');
        exit;
    }
} catch (PDOException $e) {
    die("Error mengambil data jurnal: " . $e->getMessage());
}

?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Edit Jurnal Pembelajaran</h4>
            </div>
            <div class="card-body">
                <?php echo $message; // Tampilkan pesan error jika update gagal ?>

                <?php if ($jurnal): ?>
                    <form action="edit_jurnal.php?id=<?php echo $jurnal['id']; ?>" method="POST">
                        <input type="hidden" name="id_jurnal" value="<?php echo $jurnal['id']; ?>">

                        <div class="alert alert-secondary">
                            <p class="mb-1"><strong>Guru:</strong> <?php echo htmlspecialchars($jurnal['nama_guru']); ?></p>
                            <p class="mb-1"><strong>Kelas:</strong> <?php echo htmlspecialchars($jurnal['nama_kelas']); ?></p>
                            <p class="mb-0"><strong>Mapel:</strong> <?php echo htmlspecialchars($jurnal['nama_mapel']); ?></p>
                        </div>
                        <hr>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tanggal" class="form-label">Tanggal</label>
                                <input type="date" class="form-control" id="tanggal" name="tanggal" 
                                       value="<?php echo htmlspecialchars($jurnal['tanggal']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="jam_ke" class="form-label">Jam Ke-</label>
                                <input type="text" class="form-control" id="jam_ke" name="jam_ke" 
                                       value="<?php echo htmlspecialchars($jurnal['jam_ke']); ?>" placeholder="Misal: 1-2" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="topik_materi" class="form-label">Topik / Materi Pembelajaran</label>
                            <textarea class="form-control" id="topik_materi" name="topik_materi" rows="4" required><?php echo htmlspecialchars($jurnal['topik_materi']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="catatan_guru" class="form-label">Catatan Guru (Opsional)</label>
                            <textarea class="form-control" id="catatan_guru" name="catatan_guru" rows="3"><?php echo htmlspecialchars($jurnal['catatan_guru']); ?></textarea>
                        </div>

                        <button type="submit" name="update_jurnal" class="btn btn-primary">Simpan Perubahan</button>
                        <a href="laporan.php" class="btn btn-secondary">Batal</a>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">Data jurnal tidak ditemukan.</div>
                    <a href="laporan.php" class="btn btn-secondary">Kembali ke Laporan</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
// 5. Panggil footer
require_once '../includes/footer.php';
?>