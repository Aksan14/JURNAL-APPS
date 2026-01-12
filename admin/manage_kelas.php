<?php
/* File: admin/manage_kelas.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';

// --- LOGIKA TAMBAH KELAS ---
if (isset($_POST['add_kelas'])) {
    $nama_kelas = strtoupper(trim($_POST['nama_kelas']));
    $id_wali_kelas = !empty($_POST['id_wali_kelas']) ? $_POST['id_wali_kelas'] : null;
    
    if (!empty($nama_kelas)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tbl_kelas (nama_kelas, id_wali_kelas) VALUES (?, ?)");
            $stmt->execute([$nama_kelas, $id_wali_kelas]);
            
            // Update role user jadi walikelas
            if ($id_wali_kelas) {
                $stmt_role = $pdo->prepare("UPDATE tbl_users SET role = 'walikelas' WHERE id = (SELECT user_id FROM tbl_guru WHERE id = ?)");
                $stmt_role->execute([$id_wali_kelas]);
            }
            
            header("Location: manage_kelas.php?status=added");
            exit;
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Error: Nama kelas mungkin sudah ada.</div>";
        }
    }
}

// --- LOGIKA UPDATE KELAS ---
if (isset($_POST['update_kelas'])) {
    $id = $_POST['id'];
    $nama_kelas = strtoupper(trim($_POST['nama_kelas']));
    $id_wali_kelas = isset($_POST['id_wali_kelas']) && $_POST['id_wali_kelas'] !== '' ? $_POST['id_wali_kelas'] : null;
    
    try {
        // Update role user lama jika ada
        $stmt_old = $pdo->prepare("SELECT id_wali_kelas FROM tbl_kelas WHERE id = ?");
        $stmt_old->execute([$id]);
        $old_wali = $stmt_old->fetchColumn();
        
        if ($old_wali && $old_wali != $id_wali_kelas) {
            // Cek apakah guru lama masih jadi wali kelas lain
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tbl_kelas WHERE id_wali_kelas = ? AND id != ?");
            $stmt_check->execute([$old_wali, $id]);
            if ($stmt_check->fetchColumn() == 0) {
                // Ubah role ke guru biasa
                $stmt_role = $pdo->prepare("UPDATE tbl_users SET role = 'guru' WHERE id = (SELECT user_id FROM tbl_guru WHERE id = ?)");
                $stmt_role->execute([$old_wali]);
            }
        }
        
        // Update kelas
        $stmt = $pdo->prepare("UPDATE tbl_kelas SET nama_kelas = ?, id_wali_kelas = ? WHERE id = ?");
        $stmt->execute([$nama_kelas, $id_wali_kelas, $id]);
        
        // Update role user baru jadi walikelas
        if ($id_wali_kelas) {
            $stmt_role = $pdo->prepare("UPDATE tbl_users SET role = 'walikelas' WHERE id = (SELECT user_id FROM tbl_guru WHERE id = ?)");
            $stmt_role->execute([$id_wali_kelas]);
        }
        
        header("Location: manage_kelas.php?status=updated");
        exit;
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- LOGIKA HAPUS KELAS ---
if (isset($_GET['delete'])) {
    $id_del = $_GET['delete'];
    try {
        // Cek apakah ada siswa di kelas ini
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tbl_siswa WHERE id_kelas = ?");
        $stmt_check->execute([$id_del]);
        if ($stmt_check->fetchColumn() > 0) {
            $message = "<div class='alert alert-danger'>Gagal hapus! Masih ada siswa di kelas ini.</div>";
        } else {
            $stmt = $pdo->prepare("DELETE FROM tbl_kelas WHERE id = ?");
            $stmt->execute([$id_del]);
            header("Location: manage_kelas.php?status=deleted");
            exit;
        }
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Gagal hapus! Kelas mungkin masih terhubung dengan data lain.</div>";
    }
}

// Pesan Status
$status = $_GET['status'] ?? '';
if ($status == 'added') $message = "<div class='alert alert-success'>Kelas berhasil ditambahkan!</div>";
if ($status == 'deleted') $message = "<div class='alert alert-warning'>Kelas berhasil dihapus!</div>";
if ($status == 'updated') $message = "<div class='alert alert-success'>Data kelas berhasil diperbarui!</div>";

// Ambil data kelas dengan wali kelas
$daftar_kelas = $pdo->query("
    SELECT k.*, g.nama_guru as wali_kelas, 
           (SELECT COUNT(*) FROM tbl_siswa WHERE id_kelas = k.id) as jumlah_siswa
    FROM tbl_kelas k 
    LEFT JOIN tbl_guru g ON k.id_wali_kelas = g.id 
    ORDER BY k.nama_kelas ASC
")->fetchAll();

// Ambil daftar guru untuk dropdown
$daftar_guru = $pdo->query("SELECT id, nip, nama_guru FROM tbl_guru ORDER BY nama_guru ASC")->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Kelola Data Kelas</h1>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus"></i> Tambah Kelas
        </button>
    </div>

    <?php echo $message; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Kelas</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th width="50">No</th>
                            <th>Nama Kelas</th>
                            <th>Wali Kelas</th>
                            <th class="text-center" width="100">Jml Siswa</th>
                            <th class="text-center" width="180">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar_kelas as $i => $k): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><strong><?php echo htmlspecialchars($k['nama_kelas']); ?></strong></td>
                            <td>
                                <?php if ($k['wali_kelas']): ?>
                                    <span class="text-success"><i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($k['wali_kelas']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted"><i class="fas fa-minus-circle me-1"></i>Belum ada</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info"><?php echo $k['jumlah_siswa']; ?> siswa</span>
                            </td>
                            <td class="text-center">
                                <a href="detail_kelas.php?id=<?php echo $k['id']; ?>" class="btn btn-sm btn-success" title="Lihat Detail Siswa">
                                    <i class="fas fa-users"></i>
                                </a>
                                <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#modalEdit<?php echo $k['id']; ?>" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="manage_kelas.php?delete=<?php echo $k['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus kelas ini?')" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        
                        <!-- Modal Edit Kelas -->
                        <div class="modal fade" id="modalEdit<?php echo $k['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="manage_kelas.php">
                                        <div class="modal-header bg-info text-white">
                                            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Kelas</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="id" value="<?php echo $k['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Nama Kelas</label>
                                                <input type="text" name="nama_kelas" class="form-control" value="<?php echo htmlspecialchars($k['nama_kelas']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Wali Kelas</label>
                                                <select name="id_wali_kelas" class="form-select">
                                                    <option value="">-- Belum Ada Wali Kelas --</option>
                                                    <?php foreach ($daftar_guru as $g): ?>
                                                        <option value="<?php echo $g['id']; ?>" <?php echo ($k['id_wali_kelas'] == $g['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($g['nama_guru']); ?> (<?php echo $g['nip']; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" name="update_kelas" class="btn btn-info">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Kelas -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Kelas Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Kelas</label>
                    <input type="text" name="nama_kelas" class="form-control" placeholder="Contoh: X RPL 1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Wali Kelas (Opsional)</label>
                    <select name="id_wali_kelas" class="form-select">
                        <option value="">-- Belum Ada Wali Kelas --</option>
                        <?php foreach ($daftar_guru as $g): ?>
                            <option value="<?php echo $g['id']; ?>">
                                <?php echo htmlspecialchars($g['nama_guru']); ?> (<?php echo $g['nip']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="add_kelas" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>