<?php
/* File: admin/manage_mengajar.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';
$messageType = '';

// 1. LOGIKA TAMBAH PLOTTING (MENGAJAR)
if (isset($_POST['add_mengajar'])) {
    $id_guru  = $_POST['id_guru'];
    $id_mapel = $_POST['id_mapel'];
    $id_kelas = $_POST['id_kelas'];
    $jumlah_jam = isset($_POST['jumlah_jam']) ? (int)$_POST['jumlah_jam'] : 0;

    // Cek apakah plotting yang sama sudah ada (biar tidak duplikat)
    $check = $pdo->prepare("SELECT COUNT(*) FROM tbl_mengajar WHERE id_guru = ? AND id_mapel = ? AND id_kelas = ?");
    $check->execute([$id_guru, $id_mapel, $id_kelas]);
    
    if ($check->fetchColumn() > 0) {
        $message = "Data sudah ada! Guru tersebut sudah di-plot untuk mapel dan kelas ini.";
        $messageType = "warning";
    } else {
        $stmt = $pdo->prepare("INSERT INTO tbl_mengajar (id_guru, id_mapel, id_kelas, jumlah_jam_mingguan) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_guru, $id_mapel, $id_kelas, $jumlah_jam]);
        header("Location: manage_mengajar.php?status=added");
        exit;
    }
}

// 2. LOGIKA EDIT PLOTTING
if (isset($_POST['edit_mengajar'])) {
    $id = $_POST['edit_id'];
    $id_mapel = $_POST['edit_id_mapel'];
    $id_kelas = $_POST['edit_id_kelas'];
    $jumlah_jam = (int)$_POST['edit_jumlah_jam'];
    
    $stmt = $pdo->prepare("UPDATE tbl_mengajar SET id_mapel = ?, id_kelas = ?, jumlah_jam_mingguan = ? WHERE id = ?");
    $stmt->execute([$id_mapel, $id_kelas, $jumlah_jam, $id]);
    header("Location: manage_mengajar.php?status=updated");
    exit;
}

if (isset($_GET['delete'])) {
    $id_del = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM tbl_mengajar WHERE id = ?");
    $stmt->execute([$id_del]);
    header("Location: manage_mengajar.php?status=deleted");
    exit;
}

// 4. AMBIL DATA UNTUK FORM DROPDOWN
$list_guru  = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru ASC")->fetchAll();
$list_mapel = $pdo->query("SELECT id, nama_mapel FROM tbl_mapel ORDER BY nama_mapel ASC")->fetchAll();
$list_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC")->fetchAll();

// 5. FILTER
$filter_guru = $_GET['filter_guru'] ?? '';
$filter_mapel = $_GET['filter_mapel'] ?? '';
$filter_kelas = $_GET['filter_kelas'] ?? '';
$view_mode = $_GET['view'] ?? 'guru'; 

// Build WHERE clause
$where = [];
$params = [];
if ($filter_guru) {
    $where[] = "m.id_guru = ?";
    $params[] = $filter_guru;
}
if ($filter_mapel) {
    $where[] = "m.id_mapel = ?";
    $params[] = $filter_mapel;
}
if ($filter_kelas) {
    $where[] = "m.id_kelas = ?";
    $params[] = $filter_kelas;
}
$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// 6. AMBIL DATA DENGAN FILTER
$stmt = $pdo->prepare("
    SELECT m.id, m.id_guru, m.id_mapel, m.id_kelas,
           g.nama_guru, mp.nama_mapel, k.nama_kelas, m.jumlah_jam_mingguan
    FROM tbl_mengajar m
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    $whereClause
    ORDER BY g.nama_guru ASC, k.nama_kelas ASC, mp.nama_mapel ASC
");
$stmt->execute($params);
$daftar_mengajar = $stmt->fetchAll();

// 7. KELOMPOKKAN DATA
$grouped_by_guru = [];
$grouped_by_kelas = [];
foreach ($daftar_mengajar as $d) {
    $grouped_by_guru[$d['nama_guru']][] = $d;
    $grouped_by_kelas[$d['nama_kelas']][] = $d;
}
ksort($grouped_by_kelas);

// 8. STATISTIK
$total_plotting = count($daftar_mengajar);
$total_guru = count($grouped_by_guru);
$total_kelas = count($grouped_by_kelas);
$total_jam = array_sum(array_column($daftar_mengajar, 'jumlah_jam_mingguan'));

require_once '../includes/header.php';
?>

<style>
.guru-card {
    border-left: 4px solid #4e73df;
    margin-bottom: 1rem;
}
.guru-card .card-header {
    background-color: #4e73df;
    color: white;
    font-weight: 600;
}
.kelas-card {
    border-left: 4px solid #1cc88a;
    margin-bottom: 1rem;
}
.kelas-card .card-header {
    background-color: #1cc88a;
    color: white;
    font-weight: 600;
}
.view-toggle .btn.active {
    background-color: #4e73df;
    color: white;
    border-color: #4e73df;
}
.badge-jam {
    font-size: 0.8rem;
}
.mapel-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px dashed #dee2e6;
}
.mapel-item:last-child {
    border-bottom: none;
}
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Kelola Jadwal Mengajar</h1>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus"></i> Tambah Plotting
        </button>
    </div>

    <?php 
    if (isset($_GET['status'])) {
        $status = $_GET['status'];
        if ($status == 'added') echo "<div class='alert alert-success'>Plotting berhasil ditambahkan!</div>";
        if ($status == 'updated') echo "<div class='alert alert-info'>Plotting berhasil diupdate!</div>";
        if ($status == 'deleted') echo "<div class='alert alert-warning'>Plotting berhasil dihapus!</div>";
    }
    if (!empty($message)) echo "<div class='alert alert-{$messageType}'>{$message}</div>";
    ?>

    <!-- Filter & View Toggle -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter & Tampilan</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label"><i class="fas fa-user"></i> Filter Guru</label>
                    <select name="filter_guru" class="form-select">
                        <option value="">-- Semua Guru --</option>
                        <?php foreach($list_guru as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $filter_guru == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_guru']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label"><i class="fas fa-book"></i> Filter Mapel</label>
                    <select name="filter_mapel" class="form-select">
                        <option value="">-- Semua Mapel --</option>
                        <?php foreach($list_mapel as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $filter_mapel == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label"><i class="fas fa-building"></i> Filter Kelas</label>
                    <select name="filter_kelas" class="form-select">
                        <option value="">-- Semua Kelas --</option>
                        <?php foreach($list_kelas as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $filter_kelas == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <input type="hidden" name="view" value="<?= $view_mode ?>">
                    <button type="submit" class="btn btn-primary btn-sm me-1"><i class="fas fa-filter"></i> Filter</button>
                    <a href="manage_mengajar.php?view=<?= $view_mode ?>" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Reset</a>
                </div>
            </form>

            <!-- View Toggle -->
            <div class="mt-3 pt-3 border-top d-flex align-items-center">
                <span class="me-2"><strong>Tampilan:</strong></span>
                <div class="btn-group view-toggle">
                    <a href="?view=guru&filter_guru=<?= $filter_guru ?>&filter_mapel=<?= $filter_mapel ?>&filter_kelas=<?= $filter_kelas ?>" 
                       class="btn btn-outline-primary btn-sm <?= $view_mode == 'guru' ? 'active' : '' ?>">
                        <i class="fas fa-user-tie"></i> Per Guru
                    </a>
                    <a href="?view=table&filter_guru=<?= $filter_guru ?>&filter_mapel=<?= $filter_mapel ?>&filter_kelas=<?= $filter_kelas ?>" 
                       class="btn btn-outline-primary btn-sm <?= $view_mode == 'table' ? 'active' : '' ?>">
                        <i class="fas fa-table"></i> Tabel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($view_mode == 'guru'): ?>
    <!-- View Per Guru -->
    <div class="row">
        <?php foreach($grouped_by_guru as $guru => $items): 
            $total_jam_guru = array_sum(array_column($items, 'jumlah_jam_mingguan'));
            $total_kelas_guru = count(array_unique(array_column($items, 'nama_kelas')));
        ?>
        <div class="col-xl-4 col-lg-6 col-md-6">
            <div class="card guru-card">
                <div class="card-header d-flex justify-content-between align-items-center py-3">
                    <span><i class="fas fa-user-circle me-2"></i><?= htmlspecialchars($guru) ?></span>
                    <div>
                        <span class="badge bg-light text-dark me-1" title="Total Kelas"><i class="fas fa-school"></i> <?= $total_kelas_guru ?></span>
                        <span class="badge bg-light text-dark" title="Total Jam/Minggu"><i class="fas fa-clock"></i> <?= $total_jam_guru ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach($items as $item): ?>
                    <div class="mapel-item">
                        <div>
                            <strong class="text-primary"><?= htmlspecialchars($item['nama_mapel']) ?></strong>
                            <br><small class="text-muted"><i class="fas fa-building"></i> <?= htmlspecialchars($item['nama_kelas']) ?></small>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary badge-jam me-2"><?= $item['jumlah_jam_mingguan'] ?> jam</span>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-warning btn-sm" 
                                        onclick="editMengajar(<?= $item['id'] ?>, <?= $item['id_mapel'] ?>, <?= $item['id_kelas'] ?>, <?= $item['jumlah_jam_mingguan'] ?>, '<?= addslashes($guru) ?>')"
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" 
                                        onclick="confirmDelete(<?= $item['id'] ?>, '<?= addslashes($guru) ?>', '<?= addslashes($item['nama_mapel']) ?>', '<?= addslashes($item['nama_kelas']) ?>')"
                                        title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- View Tabel -->
    <div class="card shadow">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-table me-2"></i>Data Plotting Mengajar</h6>
            <input type="text" id="searchInput" class="form-control form-control-sm" style="max-width: 250px;" placeholder="Cari...">
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped" id="dataTable">
                    <thead class="table-dark">
                        <tr>
                            <th width="50" class="text-center">No</th>
                            <th>Guru</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th width="100" class="text-center">Jam/Minggu</th>
                            <th width="100" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach($daftar_mengajar as $d): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><i class="fas fa-user text-primary me-1"></i> <?= htmlspecialchars($d['nama_guru']) ?></td>
                            <td><i class="fas fa-book text-success me-1"></i> <?= htmlspecialchars($d['nama_mapel']) ?></td>
                            <td><i class="fas fa-building text-info me-1"></i> <?= htmlspecialchars($d['nama_kelas']) ?></td>
                            <td class="text-center"><span class="badge bg-primary badge-jam"><?= $d['jumlah_jam_mingguan'] ?></span></td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm" 
                                        onclick="editMengajar(<?= $d['id'] ?>, <?= $d['id_mapel'] ?>, <?= $d['id_kelas'] ?>, <?= $d['jumlah_jam_mingguan'] ?>, '<?= addslashes($d['nama_guru']) ?>')"
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="confirmDelete(<?= $d['id'] ?>, '<?= addslashes($d['nama_guru']) ?>', '<?= addslashes($d['nama_mapel']) ?>', '<?= addslashes($d['nama_kelas']) ?>')"
                                        title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($daftar_mengajar)): ?>
    <div class="text-center py-5">
        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
        <p class="text-muted">Tidak ada data plotting yang ditemukan.</p>
        <?php if ($filter_guru || $filter_mapel || $filter_kelas): ?>
            <a href="manage_mengajar.php" class="btn btn-outline-primary">Reset Filter</a>
        <?php else: ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="fas fa-plus"></i> Tambah Plotting Pertama
            </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah Plotting -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Plotting Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Pilih Guru</label>
                    <select name="id_guru" class="form-select" required>
                        <option value="">-- Pilih Guru --</option>
                        <?php foreach($list_guru as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_guru']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pilih Mata Pelajaran</label>
                    <select name="id_mapel" class="form-select" required>
                        <option value="">-- Pilih Mapel --</option>
                        <?php foreach($list_mapel as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pilih Kelas</label>
                    <select name="id_kelas" class="form-select" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach($list_kelas as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Jumlah Jam per Minggu</label>
                    <input type="number" name="jumlah_jam" class="form-control" min="0" max="40" value="0">
                    <small class="text-muted">Jumlah jam pelajaran dalam seminggu</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="add_mengajar" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Plotting -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="edit_id" id="editId">
            <div class="modal-header">
                <h5 class="modal-title">Edit Plotting Mengajar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Guru</label>
                    <input type="text" class="form-control" id="editGuru" readonly>
                    <small class="text-muted">Guru tidak dapat diubah. Hapus dan buat baru jika ingin ganti guru.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mata Pelajaran</label>
                    <select name="edit_id_mapel" id="editMapel" class="form-select" required>
                        <?php foreach($list_mapel as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kelas</label>
                    <select name="edit_id_kelas" id="editKelas" class="form-select" required>
                        <?php foreach($list_kelas as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Jumlah Jam per Minggu</label>
                    <input type="number" name="edit_jumlah_jam" id="editJam" class="form-control" min="0" max="40">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="edit_mengajar" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus plotting ini?</p>
                <ul class="list-unstyled">
                    <li><strong>Guru:</strong> <span id="deleteGuru"></span></li>
                    <li><strong>Mapel:</strong> <span id="deleteMapel"></span></li>
                    <li><strong>Kelas:</strong> <span id="deleteKelas"></span></li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="btnConfirmDelete" class="btn btn-danger">Ya, Hapus!</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, guru, mapel, kelas) {
    document.getElementById('deleteGuru').textContent = guru;
    document.getElementById('deleteMapel').textContent = mapel;
    document.getElementById('deleteKelas').textContent = kelas;
    document.getElementById('btnConfirmDelete').href = 'manage_mengajar.php?delete=' + id;
    var modal = new bootstrap.Modal(document.getElementById('modalHapus'));
    modal.show();
}

function editMengajar(id, idMapel, idKelas, jam, guru) {
    document.getElementById('editId').value = id;
    document.getElementById('editGuru').value = guru;
    document.getElementById('editMapel').value = idMapel;
    document.getElementById('editKelas').value = idKelas;
    document.getElementById('editJam').value = jam;
    var modal = new bootstrap.Modal(document.getElementById('modalEdit'));
    modal.show();
}

// Search functionality for table view
var searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        var searchValue = this.value.toLowerCase();
        var tableRows = document.querySelectorAll('#dataTable tbody tr');
        tableRows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchValue) ? '' : 'none';
        });
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
