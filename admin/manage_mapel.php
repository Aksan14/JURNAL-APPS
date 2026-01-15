<?php
/*
File: admin/manage_mapel.php
Deskripsi: Halaman kelola data mata pelajaran (Tampil, Tambah, Hapus).
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';

// --- 1. LOGIKA TAMBAH MAPEL ---
if (isset($_POST['add_mapel'])) {
    $kode_mapel = trim($_POST['kode_mapel'] ?? '');
    $nama_mapel = trim($_POST['nama_mapel']);
    if (!empty($nama_mapel)) {
        $stmt = $pdo->prepare("INSERT INTO tbl_mapel (kode_mapel, nama_mapel) VALUES (?, ?)");
        $stmt->execute([$kode_mapel ?: null, $nama_mapel]);
        header("Location: manage_mapel.php?status=added");
        exit;
    }
}

// --- 2. LOGIKA HAPUS MAPEL ---
if (isset($_GET['delete'])) {
    $id_delete = $_GET['delete'];
    // Cek dulu apakah mapel ini sedang digunakan di tabel mengajar (opsional tapi aman)
    $check = $pdo->prepare("SELECT COUNT(*) FROM tbl_mengajar WHERE id_mapel = ?");
    $check->execute([$id_delete]);
    if ($check->fetchColumn() > 0) {
        $message = "<div class='alert alert-danger'>Gagal! Mapel ini masih digunakan dalam jadwal mengajar.</div>";
    } else {
        $stmt = $pdo->prepare("DELETE FROM tbl_mapel WHERE id = ?");
        $stmt->execute([$id_delete]);
        header("Location: manage_mapel.php?status=deleted");
        exit;
    }
}

// --- 3. TANGKAP STATUS PESAN DARI URL ---
$status = $_GET['status'] ?? '';
if ($status == 'added') $message = "<div class='alert alert-success'>Mata pelajaran berhasil ditambahkan!</div>";
if ($status == 'updated') $message = "<div class='alert alert-success'>Mata pelajaran berhasil diperbarui!</div>";
if ($status == 'deleted') $message = "<div class='alert alert-warning'>Mata pelajaran berhasil dihapus!</div>";

// --- 4. AMBIL SEMUA DATA MAPEL ---
$stmt = $pdo->query("SELECT * FROM tbl_mapel ORDER BY nama_mapel ASC");
$daftar_mapel = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Kelola Mata Pelajaran</h1>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus"></i> Tambah Mapel
        </button>
    </div>

    <?php echo $message; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Mata Pelajaran</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="50">No</th>
                            <th width="120">Kode Mapel</th>
                            <th>Nama Mata Pelajaran</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($daftar_mapel) > 0): ?>
                            <?php foreach ($daftar_mapel as $index => $row): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($row['kode_mapel'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['nama_mapel']); ?></td>
                                <td>
                                    <a href="edit_mapel.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="manage_mapel.php?delete=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus mapel ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Belum ada data.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Mapel -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Mata Pelajaran Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Kode Mata Pelajaran</label>
                    <input type="text" name="kode_mapel" class="form-control" placeholder="Contoh: MTK, IPA, BIG (opsional)">
                    <small class="text-muted">Kode singkat untuk identifikasi mapel (boleh kosong)</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Mata Pelajaran</label>
                    <input type="text" name="nama_mapel" class="form-control" placeholder="Contoh: Matematika" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="add_mapel" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>