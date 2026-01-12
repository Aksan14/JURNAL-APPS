<?php
/* File: admin/manage_siswa.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';

// 1. LOGIKA TAMBAH SISWA (langsung buat akun seperti guru)
if (isset($_POST['add_siswa'])) {
    $nis        = trim($_POST['nis']);
    $nama_siswa = trim($_POST['nama_siswa']);
    $id_kelas   = $_POST['id_kelas'];
    $username   = trim($_POST['username']);
    $password   = password_hash($username, PASSWORD_DEFAULT); // Default password = username

    if (!empty($nis) && !empty($nama_siswa) && !empty($username)) {
        try {
            $pdo->beginTransaction();
            
            // 1. Insert ke tbl_users
            $stmt1 = $pdo->prepare("INSERT INTO tbl_users (username, password_hash, role) VALUES (?, ?, 'siswa')");
            $stmt1->execute([$username, $password]);
            $user_id = $pdo->lastInsertId();

            // 2. Insert ke tbl_siswa dengan user_id
            $stmt2 = $pdo->prepare("INSERT INTO tbl_siswa (user_id, nis, nama_siswa, id_kelas) VALUES (?, ?, ?, ?)");
            $stmt2->execute([$user_id, $nis, $nama_siswa, $id_kelas]);

            $pdo->commit();
            header("Location: manage_siswa.php?status=added&kelas_filter=$id_kelas");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Error: Username atau NIS mungkin sudah terdaftar.</div>";
        }
    }
}

// 2. LOGIKA HAPUS SISWA
if (isset($_GET['delete'])) {
    $id_del = $_GET['delete'];
    $kelas_back = $_GET['kelas_filter'] ?? '';
    $stmt = $pdo->prepare("DELETE FROM tbl_siswa WHERE id = ?");
    $stmt->execute([$id_del]);
    header("Location: manage_siswa.php?status=deleted&kelas_filter=$kelas_back");
    exit;
}

// 3. LOGIKA BUAT AKUN SISWA
if (isset($_POST['buat_akun_siswa'])) {
    $id_siswa = $_POST['id_siswa'];
    $username = trim($_POST['username']);
    $password = password_hash($username, PASSWORD_DEFAULT); // Default password = username
    $kelas_back = $_POST['kelas_filter'] ?? '';

    try {
        $pdo->beginTransaction();
        
        // Insert ke tbl_users
        $stmt1 = $pdo->prepare("INSERT INTO tbl_users (username, password_hash, role) VALUES (?, ?, 'siswa')");
        $stmt1->execute([$username, $password]);
        $user_id = $pdo->lastInsertId();

        // Update tbl_siswa dengan user_id
        $stmt2 = $pdo->prepare("UPDATE tbl_siswa SET user_id = ? WHERE id = ?");
        $stmt2->execute([$user_id, $id_siswa]);

        $pdo->commit();
        header("Location: manage_siswa.php?status=akun_created&kelas_filter=$kelas_back");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Error: Username mungkin sudah digunakan.</div>";
    }
}

// 4. AMBIL DATA KELAS (Untuk Dropdown Filter & Form)
$list_kelas = $pdo->query("SELECT * FROM tbl_kelas ORDER BY nama_kelas ASC")->fetchAll();

// 5. LOGIKA FILTER & AMBIL DATA SISWA
$kelas_filter = $_GET['kelas_filter'] ?? '';
$query_siswa = "SELECT s.*, k.nama_kelas, u.username 
                FROM tbl_siswa s 
                JOIN tbl_kelas k ON s.id_kelas = k.id
                LEFT JOIN tbl_users u ON s.user_id = u.id";

if (!empty($kelas_filter)) {
    $query_siswa .= " WHERE s.id_kelas = :id_kelas";
}
$query_siswa .= " ORDER BY k.nama_kelas ASC, s.nama_siswa ASC";

$stmt_siswa = $pdo->prepare($query_siswa);
if (!empty($kelas_filter)) {
    $stmt_siswa->bindParam(':id_kelas', $kelas_filter);
}
$stmt_siswa->execute();
$daftar_siswa = $stmt_siswa->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Kelola Data Siswa</h1>
        <button class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalSiswa">
            <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Siswa
        </button>
    </div>

    <?php 
    if (isset($_GET['status']) && $_GET['status'] == 'added') echo "<div class='alert alert-success'>Siswa berhasil ditambahkan!</div>";
    if (isset($_GET['status']) && $_GET['status'] == 'deleted') echo "<div class='alert alert-warning'>Siswa berhasil dihapus!</div>";
    if (isset($_GET['status']) && $_GET['status'] == 'akun_created') echo "<div class='alert alert-success'>Akun siswa berhasil dibuat! Password default = username.</div>";
    echo $message; 
    ?>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label>Filter per Kelas:</label>
                </div>
                <div class="col-auto">
                    <select name="kelas_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Kelas --</option>
                        <?php foreach($list_kelas as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= ($kelas_filter == $k['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <a href="manage_siswa.php" class="btn btn-sm btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th width="50">No</th>
                            <th width="60">Foto</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Username</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($daftar_siswa) > 0): ?>
                            <?php foreach ($daftar_siswa as $i => $s): 
                                $foto_path = "../assets/img/profile/" . ($s['foto'] ?? '');
                                $has_foto = !empty($s['foto']) && file_exists($foto_path);
                            ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td class="text-center">
                                    <?php if ($has_foto): ?>
                                        <img src="<?php echo htmlspecialchars($foto_path); ?>" 
                                             class="rounded-circle" 
                                             style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                                             style="width: 40px; height: 40px; font-size: 16px;">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($s['nis']) ?></td>
                                <td><?= htmlspecialchars($s['nama_siswa']) ?></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($s['nama_kelas']) ?></span></td>
                                <td class="text-center">
                                    <?php if (!empty($s['username'])): ?>
                                        <span class="text-success"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($s['username']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Belum ada akun</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (empty($s['user_id'])): ?>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalBuatAkun<?= $s['id'] ?>" title="Buat Akun">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a href="manage_siswa.php?delete=<?= $s['id'] ?>&kelas_filter=<?= $kelas_filter ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Yakin hapus siswa ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            
                            <!-- Modal Buat Akun Siswa -->
                            <div class="modal fade" id="modalBuatAkun<?= $s['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="POST" class="modal-content">
                                        <div class="modal-header bg-success text-white">
                                            <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Buat Akun Siswa</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="id_siswa" value="<?= $s['id'] ?>">
                                            <input type="hidden" name="kelas_filter" value="<?= $kelas_filter ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Nama Siswa</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($s['nama_siswa']) ?>" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">NIS</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($s['nis']) ?>" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Username (untuk Login)</label>
                                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($s['nis']) ?>" required>
                                                <div class="form-text">Default: NIS siswa. Bisa diubah sesuai kebutuhan.</div>
                                            </div>
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle"></i> Password default = Username
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" name="buat_akun_siswa" class="btn btn-success">Buat Akun</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Data siswa tidak ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSiswa" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Data Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">NIS (Nomor Induk Siswa)</label>
                    <input type="text" name="nis" id="nis_input" class="form-control" required oninput="document.getElementById('username_input').value = this.value">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_siswa" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kelas</label>
                    <select name="id_kelas" class="form-select" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach($list_kelas as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username (untuk Login)</label>
                    <input type="text" name="username" id="username_input" class="form-control" required>
                    <div class="form-text">Default: NIS siswa. Bisa diubah sesuai kebutuhan.</div>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> Password default sama dengan username.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="add_siswa" class="btn btn-primary">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>