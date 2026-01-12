<?php
/* 
File: admin/manage_mengajar.php 
Deskripsi: Kelola Jadwal Mengajar dengan Hari dan Jam
*/
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';
$messageType = '';

// Daftar hari
$daftar_hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

// Fungsi hitung jam dari format "1-2"
function hitungJumlahJam($jam_ke) {
    if (preg_match('/^(\d+)-(\d+)$/', $jam_ke, $matches)) {
        return $matches[2] - $matches[1] + 1;
    }
    return 1;
}

// 1. LOGIKA TAMBAH JADWAL MENGAJAR
if (isset($_POST['add_mengajar'])) {
    $id_guru  = $_POST['id_guru'];
    $id_mapel = $_POST['id_mapel'];
    $id_kelas = $_POST['id_kelas'];
    $hari = $_POST['hari'];
    $jam_ke = $_POST['jam_ke'];
    
    // Hitung jumlah jam dari jam_ke
    $jumlah_jam = hitungJumlahJam($jam_ke);

    // Validasi: Cek apakah jadwal sudah ada (kelas + hari + jam yang sama)
    $check = $pdo->prepare("SELECT m.*, g.nama_guru, mp.nama_mapel 
                            FROM tbl_mengajar m 
                            JOIN tbl_guru g ON m.id_guru = g.id
                            JOIN tbl_mapel mp ON m.id_mapel = mp.id
                            WHERE m.id_kelas = ? AND m.hari = ? AND m.jam_ke = ?");
    $check->execute([$id_kelas, $hari, $jam_ke]);
    $existing = $check->fetch();
    
    if ($existing) {
        $message = "Jadwal bentrok! Pada waktu tersebut sudah ada jadwal: <strong>" . 
                   htmlspecialchars($existing['nama_guru']) . "</strong> - " . 
                   htmlspecialchars($existing['nama_mapel']);
        $messageType = "danger";
    } else {
        // Validasi: Cek guru tidak double booking di waktu yang sama
        $check2 = $pdo->prepare("SELECT k.nama_kelas FROM tbl_mengajar m 
                                 JOIN tbl_kelas k ON m.id_kelas = k.id
                                 WHERE m.id_guru = ? AND m.hari = ? AND m.jam_ke = ?");
        $check2->execute([$id_guru, $hari, $jam_ke]);
        $guru_jadwal = $check2->fetch();
        
        if ($guru_jadwal) {
            $message = "Guru ini sudah mengajar di kelas <strong>" . htmlspecialchars($guru_jadwal['nama_kelas']) . 
                       "</strong> pada hari " . $hari . " jam ke-" . $jam_ke;
            $messageType = "danger";
        } else {
            // Validasi: Max 10 jam per kelas per hari
            $check3 = $pdo->prepare("SELECT COALESCE(SUM(
                CASE 
                    WHEN jam_ke LIKE '%-%' THEN 
                        CAST(SUBSTRING_INDEX(jam_ke, '-', -1) AS UNSIGNED) - 
                        CAST(SUBSTRING_INDEX(jam_ke, '-', 1) AS UNSIGNED) + 1
                    ELSE 1
                END
            ), 0) as total_jam FROM tbl_mengajar WHERE id_kelas = ? AND hari = ?");
            $check3->execute([$id_kelas, $hari]);
            $total_jam_kelas = (int)$check3->fetchColumn();
            
            if (($total_jam_kelas + $jumlah_jam) > 10) {
                $message = "Tidak bisa menambah jadwal! Total jam untuk kelas ini pada hari $hari akan melebihi 10 jam. " .
                           "(Saat ini: $total_jam_kelas jam, Akan ditambah: $jumlah_jam jam)";
                $messageType = "warning";
            } else {
                $stmt = $pdo->prepare("INSERT INTO tbl_mengajar (id_guru, id_mapel, id_kelas, hari, jam_ke, jumlah_jam_mingguan) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_guru, $id_mapel, $id_kelas, $hari, $jam_ke, $jumlah_jam]);
                header("Location: manage_mengajar.php?status=added");
                exit;
            }
        }
    }
}

// 2. LOGIKA EDIT JADWAL
if (isset($_POST['edit_mengajar'])) {
    $id = $_POST['edit_id'];
    $id_mapel = $_POST['edit_id_mapel'];
    $id_kelas = $_POST['edit_id_kelas'];
    $hari = $_POST['edit_hari'];
    $jam_ke = $_POST['edit_jam_ke'];
    $jumlah_jam = hitungJumlahJam($jam_ke);
    
    // Cek bentrok (kecuali diri sendiri)
    $check = $pdo->prepare("SELECT id FROM tbl_mengajar WHERE id_kelas = ? AND hari = ? AND jam_ke = ? AND id != ?");
    $check->execute([$id_kelas, $hari, $jam_ke, $id]);
    
    if ($check->fetch()) {
        $message = "Jadwal bentrok dengan jadwal lain!";
        $messageType = "danger";
    } else {
        $stmt = $pdo->prepare("UPDATE tbl_mengajar SET id_mapel = ?, id_kelas = ?, hari = ?, jam_ke = ?, jumlah_jam_mingguan = ? WHERE id = ?");
        $stmt->execute([$id_mapel, $id_kelas, $hari, $jam_ke, $jumlah_jam, $id]);
        header("Location: manage_mengajar.php?status=updated");
        exit;
    }
}

// 3. LOGIKA HAPUS
if (isset($_GET['delete'])) {
    $id_del = $_GET['delete'];
    
    // Cek apakah ada jurnal yang menggunakan jadwal ini
    $check_jurnal = $pdo->prepare("SELECT COUNT(*) FROM tbl_jurnal WHERE id_mengajar = ?");
    $check_jurnal->execute([$id_del]);
    
    if ($check_jurnal->fetchColumn() > 0) {
        $message = "Tidak dapat menghapus jadwal ini karena sudah ada jurnal yang terkait!";
        $messageType = "danger";
    } else {
        $stmt = $pdo->prepare("DELETE FROM tbl_mengajar WHERE id = ?");
        $stmt->execute([$id_del]);
        header("Location: manage_mengajar.php?status=deleted");
        exit;
    }
}

// 4. AMBIL DATA UNTUK FORM DROPDOWN
$list_guru  = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru ASC")->fetchAll();
$list_mapel = $pdo->query("SELECT id, nama_mapel FROM tbl_mapel ORDER BY nama_mapel ASC")->fetchAll();
$list_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC")->fetchAll();

// 5. FILTER
$filter_guru = $_GET['filter_guru'] ?? '';
$filter_mapel = $_GET['filter_mapel'] ?? '';
$filter_kelas = $_GET['filter_kelas'] ?? '';
$filter_hari = $_GET['filter_hari'] ?? '';
$view_mode = $_GET['view'] ?? 'jadwal'; 

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
if ($filter_hari) {
    $where[] = "m.hari = ?";
    $params[] = $filter_hari;
}
$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// 6. AMBIL DATA DENGAN FILTER
$stmt = $pdo->prepare("
    SELECT m.id, m.id_guru, m.id_mapel, m.id_kelas, m.hari, m.jam_ke,
           g.nama_guru, mp.nama_mapel, k.nama_kelas, m.jumlah_jam_mingguan
    FROM tbl_mengajar m
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    $whereClause
    ORDER BY FIELD(m.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), m.jam_ke, k.nama_kelas ASC
");
$stmt->execute($params);
$daftar_mengajar = $stmt->fetchAll();

// 7. KELOMPOKKAN DATA
$grouped_by_kelas_hari = [];
foreach ($daftar_mengajar as $d) {
    $kelas = $d['nama_kelas'];
    $hari = $d['hari'] ?? 'Belum Diset';
    if (!isset($grouped_by_kelas_hari[$kelas])) {
        $grouped_by_kelas_hari[$kelas] = [];
    }
    if (!isset($grouped_by_kelas_hari[$kelas][$hari])) {
        $grouped_by_kelas_hari[$kelas][$hari] = [];
    }
    $grouped_by_kelas_hari[$kelas][$hari][] = $d;
}
ksort($grouped_by_kelas_hari);

// 8. STATISTIK
$total_jadwal = count($daftar_mengajar);

require_once '../includes/header.php';
?>

<style>
.jadwal-card { margin-bottom: 1.5rem; }
.jadwal-card .card-header { font-weight: 600; }
.hari-badge { 
    display: inline-block; 
    padding: 0.35rem 0.75rem; 
    border-radius: 0.25rem; 
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.hari-senin { background: #e3f2fd; color: #1565c0; }
.hari-selasa { background: #f3e5f5; color: #7b1fa2; }
.hari-rabu { background: #e8f5e9; color: #2e7d32; }
.hari-kamis { background: #fff3e0; color: #e65100; }
.hari-jumat { background: #fce4ec; color: #c2185b; }
.hari-sabtu { background: #e0f7fa; color: #00838f; }
.jam-badge {
    background: #ff5722;
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.jadwal-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid #eee;
    background: #fafafa;
    border-radius: 0.25rem;
    margin-bottom: 0.5rem;
}
.jadwal-item:hover { background: #f0f0f0; }
.view-toggle .btn.active {
    background-color: #cc0000;
    color: white;
    border-color: #cc0000;
}
.stats-card {
    border-radius: 10px;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.table-jadwal th { background: #212121; color: white; }
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-calendar-alt me-2"></i>Kelola Jadwal Mengajar</h1>
        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus"></i> Tambah Jadwal
        </button>
    </div>

    <?php 
    if (isset($_GET['status'])) {
        $status = $_GET['status'];
        if ($status == 'added') echo "<div class='alert alert-success alert-dismissible fade show'>Jadwal berhasil ditambahkan! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        if ($status == 'updated') echo "<div class='alert alert-info alert-dismissible fade show'>Jadwal berhasil diupdate! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        if ($status == 'deleted') echo "<div class='alert alert-warning alert-dismissible fade show'>Jadwal berhasil dihapus! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    if (!empty($message)) echo "<div class='alert alert-{$messageType} alert-dismissible fade show'>{$message} <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    ?>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-dark text-white">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-filter me-2"></i>Filter & Tampilan</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-2 col-md-4">
                    <label class="form-label"><i class="fas fa-user"></i> Guru</label>
                    <select name="filter_guru" class="form-select form-select-sm">
                        <option value="">-- Semua Guru --</option>
                        <?php foreach($list_guru as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $filter_guru == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_guru']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label"><i class="fas fa-book"></i> Mapel</label>
                    <select name="filter_mapel" class="form-select form-select-sm">
                        <option value="">-- Semua Mapel --</option>
                        <?php foreach($list_mapel as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $filter_mapel == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label"><i class="fas fa-school"></i> Kelas</label>
                    <select name="filter_kelas" class="form-select form-select-sm">
                        <option value="">-- Semua Kelas --</option>
                        <?php foreach($list_kelas as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $filter_kelas == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label"><i class="fas fa-calendar-day"></i> Hari</label>
                    <select name="filter_hari" class="form-select form-select-sm">
                        <option value="">-- Semua Hari --</option>
                        <?php foreach($daftar_hari as $h): ?>
                            <option value="<?= $h ?>" <?= $filter_hari == $h ? 'selected' : '' ?>><?= $h ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <input type="hidden" name="view" value="<?= $view_mode ?>">
                    <button type="submit" class="btn btn-primary btn-sm me-1"><i class="fas fa-search"></i> Filter</button>
                    <a href="manage_mengajar.php?view=<?= $view_mode ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
                </div>
                <div class="col-lg-2 col-md-4">
                    <div class="btn-group view-toggle w-100">
                        <a href="?view=jadwal&filter_guru=<?= $filter_guru ?>&filter_mapel=<?= $filter_mapel ?>&filter_kelas=<?= $filter_kelas ?>&filter_hari=<?= $filter_hari ?>" 
                           class="btn btn-outline-dark btn-sm <?= $view_mode == 'jadwal' ? 'active' : '' ?>">
                            <i class="fas fa-th-large"></i> Jadwal
                        </a>
                        <a href="?view=table&filter_guru=<?= $filter_guru ?>&filter_mapel=<?= $filter_mapel ?>&filter_kelas=<?= $filter_kelas ?>&filter_hari=<?= $filter_hari ?>" 
                           class="btn btn-outline-dark btn-sm <?= $view_mode == 'table' ? 'active' : '' ?>">
                            <i class="fas fa-table"></i> Tabel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card bg-primary text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Jadwal</h6>
                            <h3 class="mb-0"><?= $total_jadwal ?></h3>
                        </div>
                        <i class="fas fa-calendar-check fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-success text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Kelas</h6>
                            <h3 class="mb-0"><?= count($grouped_by_kelas_hari) ?></h3>
                        </div>
                        <i class="fas fa-school fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-info text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Guru</h6>
                            <h3 class="mb-0"><?= count(array_unique(array_column($daftar_mengajar, 'id_guru'))) ?></h3>
                        </div>
                        <i class="fas fa-chalkboard-teacher fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card bg-warning text-dark">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Jam/Minggu</h6>
                            <h3 class="mb-0"><?= array_sum(array_column($daftar_mengajar, 'jumlah_jam_mingguan')) ?></h3>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($view_mode == 'jadwal'): ?>
    <!-- View Jadwal Per Kelas -->
    <?php foreach($grouped_by_kelas_hari as $kelas => $hari_data): ?>
    <div class="card jadwal-card shadow">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-school me-2"></i><?= htmlspecialchars($kelas) ?></span>
            <span class="badge bg-light text-dark"><?= array_sum(array_map('count', $hari_data)) ?> jadwal</span>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach($daftar_hari as $hari): ?>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="hari-badge hari-<?= strtolower($hari) ?> w-100 text-center"><?= $hari ?></div>
                    <?php if (isset($hari_data[$hari])): ?>
                        <?php 
                        // Sort by jam_ke
                        usort($hari_data[$hari], function($a, $b) {
                            return strcmp($a['jam_ke'], $b['jam_ke']);
                        });
                        foreach($hari_data[$hari] as $item): 
                        ?>
                        <div class="jadwal-item">
                            <div class="flex-grow-1">
                                <span class="jam-badge mb-1">Jam <?= htmlspecialchars($item['jam_ke']) ?></span>
                                <div class="small mt-1">
                                    <strong><?= htmlspecialchars($item['nama_mapel']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($item['nama_guru']) ?></small>
                                </div>
                            </div>
                            <div class="btn-group-vertical btn-group-sm">
                                <button class="btn btn-outline-warning btn-sm py-0" 
                                        onclick="editMengajar(<?= $item['id'] ?>, <?= $item['id_mapel'] ?>, <?= $item['id_kelas'] ?>, '<?= $item['hari'] ?>', '<?= $item['jam_ke'] ?>', '<?= addslashes($item['nama_guru']) ?>')"
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm py-0" 
                                        onclick="confirmDelete(<?= $item['id'] ?>, '<?= addslashes($item['nama_guru']) ?>', '<?= addslashes($item['nama_mapel']) ?>', '<?= addslashes($kelas) ?>', '<?= $item['hari'] ?>', '<?= $item['jam_ke'] ?>')"
                                        title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted small py-3">
                            <i class="fas fa-minus"></i><br>Kosong
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php else: ?>
    <!-- View Tabel -->
    <div class="card shadow">
        <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-table me-2"></i>Data Jadwal Mengajar</h6>
            <input type="text" id="searchInput" class="form-control form-control-sm bg-dark text-white border-secondary" style="max-width: 250px;" placeholder="Cari...">
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped" id="dataTable">
                    <thead class="table-jadwal">
                        <tr>
                            <th width="40" class="text-center">No</th>
                            <th>Hari</th>
                            <th>Jam Ke</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru</th>
                            <th width="80" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach($daftar_mengajar as $d): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><span class="hari-badge hari-<?= strtolower($d['hari'] ?? 'senin') ?>"><?= htmlspecialchars($d['hari'] ?? '-') ?></span></td>
                            <td><span class="jam-badge"><?= htmlspecialchars($d['jam_ke'] ?? '-') ?></span></td>
                            <td><i class="fas fa-school text-info me-1"></i> <?= htmlspecialchars($d['nama_kelas']) ?></td>
                            <td><i class="fas fa-book text-success me-1"></i> <?= htmlspecialchars($d['nama_mapel']) ?></td>
                            <td><i class="fas fa-user text-primary me-1"></i> <?= htmlspecialchars($d['nama_guru']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm py-0" 
                                        onclick="editMengajar(<?= $d['id'] ?>, <?= $d['id_mapel'] ?>, <?= $d['id_kelas'] ?>, '<?= $d['hari'] ?>', '<?= $d['jam_ke'] ?>', '<?= addslashes($d['nama_guru']) ?>')"
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm py-0" 
                                        onclick="confirmDelete(<?= $d['id'] ?>, '<?= addslashes($d['nama_guru']) ?>', '<?= addslashes($d['nama_mapel']) ?>', '<?= addslashes($d['nama_kelas']) ?>', '<?= $d['hari'] ?>', '<?= $d['jam_ke'] ?>')"
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
        <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
        <p class="text-muted">Tidak ada jadwal yang ditemukan.</p>
        <?php if ($filter_guru || $filter_mapel || $filter_kelas || $filter_hari): ?>
            <a href="manage_mengajar.php" class="btn btn-outline-primary">Reset Filter</a>
        <?php else: ?>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="fas fa-plus"></i> Tambah Jadwal Pertama
            </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Tambah Jadwal Mengajar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Hari <span class="text-danger">*</span></label>
                        <select name="hari" class="form-select" required>
                            <option value="">-- Pilih Hari --</option>
                            <?php foreach($daftar_hari as $h): ?>
                                <option value="<?= $h ?>"><?= $h ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jam Ke <span class="text-danger">*</span></label>
                        <input type="text" name="jam_ke" class="form-control" placeholder="Contoh: 1-2, 3-4, 5" required>
                        <small class="text-muted">Format: "1-2" untuk jam 1-2, atau "5" untuk jam ke-5 saja</small>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pilih Kelas <span class="text-danger">*</span></label>
                    <select name="id_kelas" class="form-select" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach($list_kelas as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pilih Guru <span class="text-danger">*</span></label>
                    <select name="id_guru" class="form-select" required>
                        <option value="">-- Pilih Guru --</option>
                        <?php foreach($list_guru as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_guru']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pilih Mata Pelajaran <span class="text-danger">*</span></label>
                    <select name="id_mapel" class="form-select" required>
                        <option value="">-- Pilih Mapel --</option>
                        <?php foreach($list_mapel as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>Catatan:</strong> Maksimal 10 jam pelajaran per kelas per hari. Sistem akan otomatis memvalidasi jadwal bentrok.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="add_mengajar" class="btn btn-danger">Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="edit_id" id="editId">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Jadwal Mengajar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Guru</label>
                    <input type="text" class="form-control" id="editGuru" readonly>
                    <small class="text-muted">Guru tidak dapat diubah. Hapus dan buat baru jika ingin ganti guru.</small>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Hari</label>
                        <select name="edit_hari" id="editHari" class="form-select" required>
                            <?php foreach($daftar_hari as $h): ?>
                                <option value="<?= $h ?>"><?= $h ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jam Ke</label>
                        <input type="text" name="edit_jam_ke" id="editJamKe" class="form-control" required>
                    </div>
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
                    <label class="form-label">Mata Pelajaran</label>
                    <select name="edit_id_mapel" id="editMapel" class="form-select" required>
                        <?php foreach($list_mapel as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="edit_mengajar" class="btn btn-warning">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus -->
<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Konfirmasi Hapus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus jadwal ini?</p>
                <table class="table table-sm table-bordered">
                    <tr><th width="100">Hari</th><td id="deleteHari"></td></tr>
                    <tr><th>Jam Ke</th><td id="deleteJam"></td></tr>
                    <tr><th>Kelas</th><td id="deleteKelas"></td></tr>
                    <tr><th>Mapel</th><td id="deleteMapel"></td></tr>
                    <tr><th>Guru</th><td id="deleteGuru"></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="btnConfirmDelete" class="btn btn-danger">Ya, Hapus!</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, guru, mapel, kelas, hari, jam) {
    document.getElementById('deleteGuru').textContent = guru;
    document.getElementById('deleteMapel').textContent = mapel;
    document.getElementById('deleteKelas').textContent = kelas;
    document.getElementById('deleteHari').textContent = hari;
    document.getElementById('deleteJam').textContent = jam;
    document.getElementById('btnConfirmDelete').href = 'manage_mengajar.php?delete=' + id;
    var modal = new bootstrap.Modal(document.getElementById('modalHapus'));
    modal.show();
}

function editMengajar(id, idMapel, idKelas, hari, jamKe, guru) {
    document.getElementById('editId').value = id;
    document.getElementById('editGuru').value = guru;
    document.getElementById('editMapel').value = idMapel;
    document.getElementById('editKelas').value = idKelas;
    document.getElementById('editHari').value = hari;
    document.getElementById('editJamKe').value = jamKe;
    var modal = new bootstrap.Modal(document.getElementById('modalEdit'));
    modal.show();
}

// Search
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
