<?php
/*
File: admin/notifikasi_jurnal.php
Deskripsi: Notifikasi guru yang belum mengisi jurnal berdasarkan jadwal (hari & jam)
          + Fitur blokir jurnal guru yang tidak hadir/sakit/izin/cuti
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';

// ============================================
// LOGIKA SIMPAN BLOKIR GURU (Sakit/Izin/Cuti/Tidak Hadir)
// ============================================
if (isset($_POST['simpan_blokir'])) {
    $id_guru = $_POST['id_guru'];
    $tanggal = $_POST['tanggal_blokir'];
    $status_kehadiran = $_POST['status_kehadiran'];
    $keterangan = trim($_POST['keterangan']);
    $admin_id = $_SESSION['user_id'];

    try {
        // Cek apakah sudah ada data untuk guru ini di tanggal tersebut
        $stmt_check = $pdo->prepare("SELECT id FROM tbl_kehadiran_guru WHERE id_guru = ? AND tanggal = ?");
        $stmt_check->execute([$id_guru, $tanggal]);
        $existing = $stmt_check->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE tbl_kehadiran_guru SET status_kehadiran = ?, keterangan = ?, created_by = ? WHERE id = ?");
            $stmt->execute([$status_kehadiran, $keterangan, $admin_id, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO tbl_kehadiran_guru (id_guru, tanggal, status_kehadiran, keterangan, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_guru, $tanggal, $status_kehadiran, $keterangan, $admin_id]);
        }
        $message = "<div class='alert alert-success alert-dismissible fade show'>
            <i class='fas fa-check-circle me-1'></i> Status kehadiran guru berhasil disimpan! Guru tidak dapat mengisi jurnal pada tanggal tersebut.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// ============================================
// LOGIKA HAPUS BLOKIR (Buka Blokir)
// ============================================
if (isset($_GET['buka_blokir'])) {
    $id_blokir = $_GET['buka_blokir'];
    try {
        $stmt = $pdo->prepare("DELETE FROM tbl_kehadiran_guru WHERE id = ?");
        $stmt->execute([$id_blokir]);
        $redirect_tanggal = $_GET['tanggal'] ?? date('Y-m-d');
        $redirect_view = $_GET['view'] ?? 'kelas';
        header("Location: notifikasi_jurnal.php?tanggal={$redirect_tanggal}&view={$redirect_view}&status=unblocked");
        exit;
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Filter tanggal (default hari ini - pastikan selalu menggunakan server time)
$hari_ini_server = date('Y-m-d'); // Tanggal hari ini di server
$tanggal_filter = isset($_GET['tanggal']) && !empty($_GET['tanggal']) ? $_GET['tanggal'] : $hari_ini_server;
$hari_ini = date('l', strtotime($tanggal_filter));

// Mapping hari ke nama Indonesia
$hari_map = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa', 
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$nama_hari = $hari_map[$hari_ini] ?? $hari_ini;

// ============================================
// CEK HARI LIBUR (Umum untuk semua kelas)
// ============================================
$stmt_libur = $pdo->prepare("SELECT nama_libur, jenis FROM tbl_hari_libur WHERE tanggal = ? AND id_kelas IS NULL");
$stmt_libur->execute([$tanggal_filter]);
$hari_libur = $stmt_libur->fetch();

// ============================================
// CEK JAM KHUSUS (Pulang Cepat)
// ============================================
$stmt_jam_khusus_global = $pdo->prepare("
    SELECT max_jam, alasan FROM tbl_jam_khusus 
    WHERE tanggal = ? AND id_kelas IS NULL
    LIMIT 1
");
$stmt_jam_khusus_global->execute([$tanggal_filter]);
$jam_khusus_global = $stmt_jam_khusus_global->fetch();

// Filter
$filter_kelas = $_GET['kelas'] ?? '';
$filter_guru = $_GET['guru'] ?? '';

// Build WHERE clause
$where_conditions = ["m.hari = ?"];
$params = [$nama_hari];

if (!empty($filter_kelas)) {
    $where_conditions[] = "m.id_kelas = ?";
    $params[] = $filter_kelas;
}
if (!empty($filter_guru)) {
    $where_conditions[] = "m.id_guru = ?";
    $params[] = $filter_guru;
}

$where_clause = implode(" AND ", $where_conditions);

// Query: Jadwal yang BELUM diisi jurnal berdasarkan hari ini
$sql_belum_isi = "
    SELECT 
        m.id as id_mengajar,
        m.hari,
        m.jam_ke as jam_jadwal,
        g.id as id_guru,
        g.nama_guru,
        g.nip,
        k.id as id_kelas,
        k.nama_kelas,
        mp.id as id_mapel,
        mp.nama_mapel,
        m.jumlah_jam_mingguan
    FROM tbl_mengajar m
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE $where_clause
    AND m.id NOT IN (
        SELECT id_mengajar FROM tbl_jurnal WHERE tanggal = ?
    )
    ORDER BY k.nama_kelas ASC, m.jam_ke ASC, g.nama_guru ASC
";

$params[] = $tanggal_filter;
$stmt_belum = $pdo->prepare($sql_belum_isi);
$stmt_belum->execute($params);
$belum_isi = $stmt_belum->fetchAll();

// Grup berdasarkan kelas
$belum_isi_per_kelas = [];
foreach ($belum_isi as $row) {
    $kelas = $row['nama_kelas'];
    if (!isset($belum_isi_per_kelas[$kelas])) {
        $belum_isi_per_kelas[$kelas] = [
            'id_kelas' => $row['id_kelas'],
            'data' => []
        ];
    }
    $belum_isi_per_kelas[$kelas]['data'][] = $row;
}

// Grup berdasarkan guru
$belum_isi_per_guru = [];
foreach ($belum_isi as $row) {
    $guru = $row['nama_guru'];
    if (!isset($belum_isi_per_guru[$guru])) {
        $belum_isi_per_guru[$guru] = [
            'id_guru' => $row['id_guru'],
            'nip' => $row['nip'],
            'data' => []
        ];
    }
    $belum_isi_per_guru[$guru]['data'][] = $row;
}

// Query: Jadwal yang SUDAH diisi jurnal hari ini
$sql_sudah_isi = "
    SELECT 
        j.id as id_jurnal,
        j.jam_ke as jam_jurnal,
        j.topik_materi,
        j.created_at,
        m.hari,
        m.jam_ke as jam_jadwal,
        g.id as id_guru,
        g.nama_guru,
        k.id as id_kelas,
        k.nama_kelas,
        mp.nama_mapel
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE j.tanggal = ? AND m.hari = ?
    " . (!empty($filter_kelas) ? "AND k.id = ?" : "") . "
    " . (!empty($filter_guru) ? "AND g.id = ?" : "") . "
    ORDER BY k.nama_kelas ASC, j.jam_ke ASC
";

$params_sudah = [$tanggal_filter, $nama_hari];
if (!empty($filter_kelas)) $params_sudah[] = $filter_kelas;
if (!empty($filter_guru)) $params_sudah[] = $filter_guru;

$stmt_sudah = $pdo->prepare($sql_sudah_isi);
$stmt_sudah->execute($params_sudah);
$sudah_isi = $stmt_sudah->fetchAll();

// Dropdown data
$list_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC")->fetchAll();
$list_guru = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru ASC")->fetchAll();

// ============================================
// AMBIL DATA GURU YANG DIBLOKIR PADA TANGGAL INI
// ============================================
$stmt_blokir = $pdo->prepare("
    SELECT k.*, g.nama_guru, g.nip, u.username as admin_username
    FROM tbl_kehadiran_guru k
    JOIN tbl_guru g ON k.id_guru = g.id
    LEFT JOIN tbl_users u ON k.created_by = u.id
    WHERE k.tanggal = ?
    ORDER BY g.nama_guru ASC
");
$stmt_blokir->execute([$tanggal_filter]);
$guru_diblokir = $stmt_blokir->fetchAll();

// Array id_guru yang diblokir untuk filter
$id_guru_diblokir = array_column($guru_diblokir, 'id_guru');

// Status label dan warna
$status_blokir_labels = [
    'tidak_hadir' => ['label' => 'Tidak Hadir', 'color' => 'danger', 'icon' => 'times-circle'],
    'sakit' => ['label' => 'Sakit', 'color' => 'warning', 'icon' => 'medkit'],
    'izin' => ['label' => 'Izin', 'color' => 'info', 'icon' => 'envelope'],
    'cuti' => ['label' => 'Cuti', 'color' => 'secondary', 'icon' => 'plane']
];

// Statistik
$total_jadwal_hari_ini = count($belum_isi) + count($sudah_isi);
$total_belum_isi = count($belum_isi);
$total_sudah_isi = count($sudah_isi);
$persentase = $total_jadwal_hari_ini > 0 ? round(($total_sudah_isi / $total_jadwal_hari_ini) * 100) : 0;

// View mode
$view_mode = $_GET['view'] ?? 'kelas';

// Hitung statistik blokir
$total_guru_diblokir = count($guru_diblokir);

require_once '../includes/header.php';
?>

<style>
.stat-card {
    border-radius: 15px;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}
.stat-card:hover { transform: translateY(-5px); }
.stat-card .icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.hari-badge {
    font-size: 1.2rem;
    padding: 0.5rem 1.5rem;
    border-radius: 25px;
}
.hari-senin { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
.hari-selasa { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
.hari-rabu { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
.hari-kamis { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
.hari-jumat { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }
.hari-sabtu { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }
.kelas-card {
    border-left: 4px solid #dc3545;
    border-radius: 10px;
}
.kelas-card.complete {
    border-left-color: #28a745;
}
.guru-card {
    border-left: 4px solid #ffc107;
    border-radius: 10px;
}
.jam-badge {
    background: #ff5722;
    color: white;
    padding: 0.3rem 0.6rem;
    border-radius: 5px;
    font-size: 0.8rem;
    font-weight: bold;
}
.jam-badge.filled {
    background: #28a745;
}
.progress-ring {
    width: 120px;
    height: 120px;
}
.table-notif th { background: #212121; color: white; }
.alert-libur {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 15px;
}
/* ============================================
   MODAL BLOKIR JURNAL - CLEAN VERSION
   ============================================ */

/* Modal Container */
.modal-blokir-custom .modal-dialog {
    max-width: 600px;
    margin: 1.75rem auto;
}

.modal-blokir-custom .modal-content {
    border: none;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

/* Modal Header */
.modal-blokir-custom .modal-header {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    padding: 1.25rem 1.75rem;
    border-bottom: none;
    border-radius: 10px 10px 0 0;
}

.modal-blokir-custom .modal-title {
    font-size: 1.1rem;
    font-weight: 600;
}

/* Modal Body */
.modal-blokir-custom .modal-body {
    padding: 1.75rem;
    background-color: #fff;
}

/* Alert Warning Box */
.blokir-alert {
    background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);
    border-left: 4px solid #ffc107;
    border-radius: 6px;
    padding: 0.9rem 1rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    color: #856404;
}

.blokir-alert i {
    color: #ffc107;
    font-size: 1.1rem;
    vertical-align: middle;
}

/* Info Card - Guru & Tanggal */
.blokir-info-card {
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}

.blokir-info-item {
    margin-bottom: 1rem;
}

.blokir-info-item:last-child {
    margin-bottom: 0;
}

.blokir-info-label {
    display: block;
    font-size: 0.65rem;
    font-weight: 700;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 0.35rem;
}

.blokir-info-value {
    display: block;
    font-size: 1.3rem;
    font-weight: 700;
    color: #212529;
    line-height: 1.3;
}

/* Form Groups */
.blokir-form-group {
    margin-bottom: 1.25rem;
}

.blokir-form-label {
    display: block;
    font-weight: 600;
    font-size: 0.95rem;
    color: #495057;
    margin-bottom: 0.5rem;
}

.blokir-form-select,
.blokir-form-textarea {
    width: 100%;
    padding: 0.7rem 0.9rem;
    font-size: 0.95rem;
    border: 2px solid #ced4da;
    border-radius: 6px;
    transition: all 0.2s;
}

.blokir-form-select:focus,
.blokir-form-textarea:focus {
    border-color: #dc3545;
    outline: none;
    box-shadow: 0 0 0 0.15rem rgba(220, 53, 69, 0.2);
}

.blokir-form-textarea {
    resize: vertical;
    font-family: inherit;
}

/* Modal Footer */
.modal-blokir-custom .modal-footer {
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    padding: 1rem 1.75rem;
    border-radius: 0 0 10px 10px;
}

.modal-blokir-custom .modal-footer .btn {
    padding: 0.55rem 1.25rem;
    font-weight: 500;
    border-radius: 5px;
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-bell me-2 text-danger"></i>Notifikasi Jurnal</h1>
            <p class="text-muted mb-0">Tracking guru yang belum mengisi jurnal</p>
        </div>
        <span class="hari-badge hari-<?= strtolower($nama_hari) ?>">
            <i class="fas fa-calendar-day me-2"></i><?= $nama_hari ?>, <?= date('d M Y', strtotime($tanggal_filter)) ?>
        </span>
    </div>

    <?php if ($nama_hari == 'Minggu'): ?>
    <div class="alert alert-libur text-center py-5">
        <i class="fas fa-coffee fa-4x mb-3"></i>
        <h3>Hari Minggu - Libur</h3>
        <p class="mb-0">Tidak ada jadwal pelajaran pada hari Minggu.</p>
        <a href="?tanggal=<?= date('Y-m-d', strtotime($tanggal_filter . ' -1 day')) ?>" class="btn btn-light mt-3">
            <i class="fas fa-arrow-left me-1"></i> Lihat Hari Sabtu
        </a>
    </div>
    <?php elseif ($hari_libur): ?>
    <div class="alert alert-libur text-center py-5">
        <i class="fas fa-calendar-times fa-4x mb-3"></i>
        <h3><?= htmlspecialchars($hari_libur['nama_libur']) ?></h3>
        <p class="mb-0">
            <span class="badge bg-light text-dark"><?= ucfirst(str_replace('_', ' ', $hari_libur['jenis'])) ?></span>
        </p>
        <p class="mt-2 mb-0">Tidak ada jadwal pelajaran pada tanggal ini karena hari libur.</p>
        <div class="mt-3">
            <a href="?tanggal=<?= date('Y-m-d', strtotime($tanggal_filter . ' -1 day')) ?>" class="btn btn-light">
                <i class="fas fa-arrow-left me-1"></i> Hari Sebelumnya
            </a>
            <a href="?tanggal=<?= date('Y-m-d', strtotime($tanggal_filter . ' +1 day')) ?>" class="btn btn-light">
                Hari Berikutnya <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
    <?php else: ?>

    <?php if ($jam_khusus_global): ?>
    <div class="alert alert-warning d-flex align-items-center mb-4">
        <i class="fas fa-clock fa-2x me-3"></i>
        <div>
            <strong>Jam Khusus:</strong> <?= htmlspecialchars($jam_khusus_global['alasan']) ?>
            <br><small>Maksimal jam pelajaran: <strong><?= $jam_khusus_global['max_jam'] ?> jam</strong> untuk tanggal ini</small>
        </div>
    </div>
    <?php endif; ?>

    <?php 
    // Tampilkan pesan sukses/error
    if (isset($_GET['status']) && $_GET['status'] == 'unblocked') {
        echo "<div class='alert alert-success alert-dismissible fade show'>
            <i class='fas fa-check-circle me-1'></i> Blokir jurnal berhasil dihapus! Guru dapat kembali mengisi jurnal.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
    echo $message; 
    ?>

    <!-- Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label"><i class="fas fa-calendar me-1"></i> Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= $tanggal_filter ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label"><i class="fas fa-school me-1"></i> Kelas</label>
                    <select name="kelas" class="form-select">
                        <option value="">Semua Kelas</option>
                        <?php foreach($list_kelas as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $filter_kelas == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="form-label"><i class="fas fa-user me-1"></i> Guru</label>
                    <select name="guru" class="form-select">
                        <option value="">Semua Guru</option>
                        <?php foreach($list_guru as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $filter_guru == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_guru']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <input type="hidden" name="view" value="<?= $view_mode ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Filter</button>
                    <a href="notifikasi_jurnal.php" class="btn btn-outline-secondary" title="Hari Ini"><i class="fas fa-calendar-day"></i></a>
                </div>
                <div class="col-lg-2 col-md-6">
                    <div class="btn-group w-100">
                        <a href="?tanggal=<?= $tanggal_filter ?>&kelas=<?= $filter_kelas ?>&guru=<?= $filter_guru ?>&view=kelas" 
                           class="btn btn-sm <?= $view_mode == 'kelas' ? 'btn-dark' : 'btn-outline-dark' ?>">
                            <i class="fas fa-school"></i> Kelas
                        </a>
                        <a href="?tanggal=<?= $tanggal_filter ?>&kelas=<?= $filter_kelas ?>&guru=<?= $filter_guru ?>&view=guru" 
                           class="btn btn-sm <?= $view_mode == 'guru' ? 'btn-dark' : 'btn-outline-dark' ?>">
                            <i class="fas fa-user"></i> Guru
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card bg-primary text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon bg-white text-primary me-3"><i class="fas fa-calendar-check"></i></div>
                    <div>
                        <h6 class="mb-0 opacity-75">Total Jadwal</h6>
                        <h2 class="mb-0"><?= $total_jadwal_hari_ini ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card bg-success text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon bg-white text-success me-3"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <h6 class="mb-0 opacity-75">Sudah Diisi</h6>
                        <h2 class="mb-0"><?= $total_sudah_isi ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card bg-danger text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon bg-white text-danger me-3"><i class="fas fa-exclamation-circle"></i></div>
                    <div>
                        <h6 class="mb-0 opacity-75">Belum Diisi</h6>
                        <h2 class="mb-0"><?= $total_belum_isi ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="text-center">
                        <div class="position-relative d-inline-block">
                            <svg class="progress-ring" viewBox="0 0 36 36">
                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                      fill="none" stroke="#eee" stroke-width="3"/>
                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                      fill="none" stroke="<?= $persentase >= 80 ? '#28a745' : ($persentase >= 50 ? '#ffc107' : '#dc3545') ?>" 
                                      stroke-width="3" stroke-dasharray="<?= $persentase ?>, 100"/>
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle">
                                <h3 class="mb-0"><?= $persentase ?>%</h3>
                                <small class="text-muted">Terisi</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Guru Diblokir -->
    <?php if (!empty($guru_diblokir)): ?>
    <div class="card shadow-sm mb-4 border-left-warning" style="border-left: 4px solid #ffc107;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-user-slash text-warning me-2"></i>Guru Tidak Masuk / Diblokir (<?= $total_guru_diblokir ?> orang)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Guru</th>
                            <th>NIP</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                            <th width="80">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($guru_diblokir as $gb): 
                            $status_info = $status_blokir_labels[$gb['status_kehadiran']] ?? ['label' => $gb['status_kehadiran'], 'color' => 'secondary', 'icon' => 'question'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($gb['nama_guru']) ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($gb['nip'] ?? '-') ?></small></td>
                            <td>
                                <span class="badge bg-<?= $status_info['color'] ?>">
                                    <i class="fas fa-<?= $status_info['icon'] ?> me-1"></i>
                                    <?= $status_info['label'] ?>
                                </span>
                            </td>
                            <td><small><?= htmlspecialchars($gb['keterangan'] ?? '-') ?></small></td>
                            <td>
                                <a href="?buka_blokir=<?= $gb['id'] ?>&tanggal=<?= $tanggal_filter ?>&view=<?= $view_mode ?>" 
                                   class="btn btn-sm btn-success" 
                                   title="Buka Blokir - Guru bisa isi jurnal lagi"
                                   onclick="return confirm('Yakin buka blokir? Guru akan dapat mengisi jurnal kembali.')">
                                    <i class="fas fa-unlock"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($total_belum_isi == 0): ?>
    <div class="alert alert-success text-center py-4">
        <i class="fas fa-check-circle fa-3x mb-3"></i>
        <h4>Semua Jurnal Sudah Terisi!</h4>
        <p class="mb-0">Tidak ada jadwal yang belum diisi jurnal pada hari <?= $nama_hari ?>, <?= date('d M Y', strtotime($tanggal_filter)) ?></p>
    </div>
    <?php else: ?>

    <?php if ($view_mode == 'kelas'): ?>
    <!-- View Per Kelas -->
    <h5 class="mb-3"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Daftar Jadwal Belum Diisi (Per Kelas)</h5>
    <div class="row">
        <?php foreach($belum_isi_per_kelas as $kelas => $data): ?>
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="card kelas-card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-school text-danger me-2"></i><?= htmlspecialchars($kelas) ?></h6>
                    <span class="badge bg-danger"><?= count($data['data']) ?> belum diisi</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Jam</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th width="60">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data['data'] as $item): 
                                $is_guru_blocked = in_array($item['id_guru'], $id_guru_diblokir);
                            ?>
                            <tr class="<?= $is_guru_blocked ? 'table-secondary' : '' ?>">
                                <td><span class="jam-badge"><?= htmlspecialchars($item['jam_jadwal']) ?></span></td>
                                <td><?= htmlspecialchars($item['nama_mapel']) ?></td>
                                <td>
                                    <small><?= htmlspecialchars($item['nama_guru']) ?></small>
                                    <?php if ($is_guru_blocked): ?>
                                        <span class="badge bg-secondary ms-1"><i class="fas fa-ban"></i></span>
                                    <?php endif; ?>
                                    <?php if ($item['nip']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($item['nip']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$is_guru_blocked): ?>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalBlokirKelas<?= $item['id_guru'] ?>" 
                                            title="Blokir guru ini">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Modal Blokir untuk View Kelas -->
                            <?php if (!$is_guru_blocked): ?>
                            <div class="modal fade" id="modalBlokirKelas<?= $item['id_guru'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered" style="max-width: 550px;">
                                    <form method="POST" style="margin: 0;">
                                        <div class="modal-content" style="border: none; border-radius: 12px; overflow: hidden;">
                                            <!-- HEADER -->
                                            <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 1.25rem 1.5rem; border-bottom: none;">
                                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                                    <h5 style="margin: 0; font-size: 1.1rem; font-weight: 600;">
                                                        <i class="fas fa-user-slash" style="margin-right: 0.5rem;"></i>Blokir Jurnal Guru
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="margin: 0;"></button>
                                                </div>
                                            </div>
                                            
                                            <!-- BODY -->
                                            <div style="padding: 1.5rem; background: white;">
                                                <!-- Alert -->
                                                <div style="background: #fff9e6; border-left: 4px solid #ffc107; border-radius: 6px; padding: 0.85rem; margin-bottom: 1.25rem; color: #856404; font-size: 0.9rem;">
                                                    <i class="fas fa-exclamation-triangle" style="color: #ffc107; margin-right: 0.5rem;"></i>
                                                    <strong>Perhatian:</strong> Guru yang diblokir tidak akan bisa mengisi jurnal pada tanggal tersebut.
                                                </div>
                                                
                                                <input type="hidden" name="id_guru" value="<?= $item['id_guru'] ?>">
                                                <input type="hidden" name="tanggal_blokir" value="<?= $tanggal_filter ?>">
                                                
                                                <!-- Info Card -->
                                                <div style="background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                                                    <div style="margin-bottom: 1rem;">
                                                        <div style="font-size: 0.65rem; font-weight: 700; color: #6c757d; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 0.35rem;">NAMA GURU</div>
                                                        <div style="font-size: 1.25rem; font-weight: 700; color: #212529; line-height: 1.3;"><?= htmlspecialchars($item['nama_guru']) ?></div>
                                                    </div>
                                                    <div>
                                                        <div style="font-size: 0.65rem; font-weight: 700; color: #6c757d; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 0.35rem;">TANGGAL</div>
                                                        <div style="font-size: 1.25rem; font-weight: 700; color: #212529; line-height: 1.3;"><?= date('d F Y', strtotime($tanggal_filter)) ?></div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Status Kehadiran -->
                                                <div style="margin-bottom: 1.15rem;">
                                                    <label style="display: block; font-weight: 600; font-size: 0.95rem; color: #495057; margin-bottom: 0.5rem;">
                                                        Status Kehadiran <span style="color: #dc3545;">*</span>
                                                    </label>
                                                    <select name="status_kehadiran" required class="form-select" style="width: 100%; padding: 0.65rem 0.85rem; font-size: 0.95rem; border: 2px solid #ced4da; border-radius: 6px;">
                                                        <option value="">-- Pilih Status --</option>
                                                        <option value="tidak_hadir">Tidak Hadir</option>
                                                        <option value="sakit">Sakit</option>
                                                        <option value="izin">Izin</option>
                                                        <option value="cuti">Cuti</option>
                                                    </select>
                                                </div>
                                                
                                                <!-- Keterangan -->
                                                <div>
                                                    <label style="display: block; font-weight: 600; font-size: 0.95rem; color: #495057; margin-bottom: 0.5rem;">Keterangan</label>
                                                    <textarea name="keterangan" rows="3" class="form-control" placeholder="Contoh: Sakit demam, izin keluarga, dll" style="width: 100%; padding: 0.65rem 0.85rem; font-size: 0.95rem; border: 2px solid #ced4da; border-radius: 6px; resize: vertical;"></textarea>
                                                </div>
                                            </div>
                                            
                                            <!-- FOOTER -->
                                            <div style="background: #f8f9fa; border-top: 1px solid #dee2e6; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="padding: 0.5rem 1.15rem; font-weight: 500;">
                                                    <i class="fas fa-times" style="margin-right: 0.4rem;"></i>Batal
                                                </button>
                                                <button type="submit" name="simpan_blokir" class="btn btn-danger" style="padding: 0.5rem 1.15rem; font-weight: 500;">
                                                    <i class="fas fa-ban" style="margin-right: 0.4rem;"></i>Blokir Jurnal
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- View Per Guru -->
    <h5 class="mb-3"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Daftar Guru Belum Mengisi Jurnal</h5>
    <div class="row">
        <?php foreach($belum_isi_per_guru as $guru => $data): 
            $is_blocked = in_array($data['id_guru'], $id_guru_diblokir);
        ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card guru-card shadow-sm <?= $is_blocked ? 'opacity-50' : '' ?>">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="fas fa-user text-warning me-2"></i><?= htmlspecialchars($guru) ?></h6>
                        <?php if ($data['nip']): ?>
                            <small class="text-muted">NIP: <?= htmlspecialchars($data['nip']) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_blocked): ?>
                    <button class="btn btn-sm btn-outline-danger" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalBlokir<?= $data['id_guru'] ?>" 
                            title="Blokir - Guru tidak hadir">
                        <i class="fas fa-user-slash"></i>
                    </button>
                    <?php else: ?>
                    <span class="badge bg-secondary"><i class="fas fa-ban"></i> Diblokir</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong><?= count($data['data']) ?> jadwal belum diisi:</strong></p>
                    <?php foreach($data['data'] as $item): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <span class="jam-badge me-2"><?= htmlspecialchars($item['jam_jadwal']) ?></span>
                            <?= htmlspecialchars($item['nama_mapel']) ?>
                        </div>
                        <small class="text-muted"><?= htmlspecialchars($item['nama_kelas']) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Modal Blokir Guru -->
        <?php if (!$is_blocked): ?>
        <div class="modal fade" id="modalBlokir<?= $data['id_guru'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 550px;">
                <form method="POST" style="margin: 0;">
                    <div class="modal-content" style="border: none; border-radius: 12px; overflow: hidden;">
                        <!-- HEADER -->
                        <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 1.25rem 1.5rem; border-bottom: none;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h5 style="margin: 0; font-size: 1.1rem; font-weight: 600;">
                                    <i class="fas fa-user-slash" style="margin-right: 0.5rem;"></i>Blokir Jurnal Guru
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="margin: 0;"></button>
                            </div>
                        </div>
                        
                        <!-- BODY -->
                        <div style="padding: 1.5rem; background: white;">
                            <!-- Alert -->
                            <div style="background: #fff9e6; border-left: 4px solid #ffc107; border-radius: 6px; padding: 0.85rem; margin-bottom: 1.25rem; color: #856404; font-size: 0.9rem;">
                                <i class="fas fa-exclamation-triangle" style="color: #ffc107; margin-right: 0.5rem;"></i>
                                <strong>Perhatian:</strong> Guru yang diblokir tidak akan bisa mengisi jurnal pada tanggal tersebut.
                            </div>
                            
                            <input type="hidden" name="id_guru" value="<?= $data['id_guru'] ?>">
                            <input type="hidden" name="tanggal_blokir" value="<?= $tanggal_filter ?>">
                            
                            <!-- Info Card -->
                            <div style="background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                                <div style="margin-bottom: 1rem;">
                                    <div style="font-size: 0.65rem; font-weight: 700; color: #6c757d; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 0.35rem;">NAMA GURU</div>
                                    <div style="font-size: 1.25rem; font-weight: 700; color: #212529; line-height: 1.3;"><?= htmlspecialchars($guru) ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.65rem; font-weight: 700; color: #6c757d; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 0.35rem;">TANGGAL</div>
                                    <div style="font-size: 1.25rem; font-weight: 700; color: #212529; line-height: 1.3;"><?= date('d F Y', strtotime($tanggal_filter)) ?></div>
                                </div>
                            </div>
                            
                            <!-- Status Kehadiran -->
                            <div style="margin-bottom: 1.15rem;">
                                <label style="display: block; font-weight: 600; font-size: 0.95rem; color: #495057; margin-bottom: 0.5rem;">
                                    Status Kehadiran <span style="color: #dc3545;">*</span>
                                </label>
                                <select name="status_kehadiran" required class="form-select" style="width: 100%; padding: 0.65rem 0.85rem; font-size: 0.95rem; border: 2px solid #ced4da; border-radius: 6px;">
                                    <option value="">-- Pilih Status --</option>
                                    <option value="tidak_hadir">🚫 Tidak Hadir</option>
                                    <option value="sakit">🏥 Sakit</option>
                                    <option value="izin">✉️ Izin</option>
                                    <option value="cuti">✈️ Cuti</option>
                                </select>
                            </div>
                            
                            <!-- Keterangan -->
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 0.95rem; color: #495057; margin-bottom: 0.5rem;">Keterangan</label>
                                <textarea name="keterangan" rows="3" class="form-control" placeholder="Contoh: Sakit demam, izin keperluan keluarga, dll" style="width: 100%; padding: 0.65rem 0.85rem; font-size: 0.95rem; border: 2px solid #ced4da; border-radius: 6px; resize: vertical;"></textarea>
                            </div>
                        </div>
                        
                        <!-- FOOTER -->
                        <div style="background: #f8f9fa; border-top: 1px solid #dee2e6; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="padding: 0.5rem 1.15rem; font-weight: 500;">
                                <i class="fas fa-times" style="margin-right: 0.4rem;"></i>Batal
                            </button>
                            <button type="submit" name="simpan_blokir" class="btn btn-danger" style="padding: 0.5rem 1.15rem; font-weight: 500;">
                                <i class="fas fa-ban" style="margin-right: 0.4rem;"></i>Blokir Jurnal
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Jurnal yang sudah diisi -->
    <?php if (!empty($sudah_isi)): ?>
    <h5 class="mb-3 mt-4"><i class="fas fa-check-circle text-success me-2"></i>Jurnal Sudah Diisi Hari Ini</h5>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-notif">
                        <tr>
                            <th>Jam</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru</th>
                            <th>Topik Materi</th>
                            <th>Waktu Input</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sudah_isi as $item): ?>
                        <tr>
                            <td><span class="jam-badge filled"><?= htmlspecialchars($item['jam_jurnal']) ?></span></td>
                            <td><?= htmlspecialchars($item['nama_kelas']) ?></td>
                            <td><?= htmlspecialchars($item['nama_mapel']) ?></td>
                            <td><?= htmlspecialchars($item['nama_guru']) ?></td>
                            <td><small><?= htmlspecialchars(substr($item['topik_materi'], 0, 50)) ?>...</small></td>
                            <td><small><?= date('H:i', strtotime($item['created_at'])) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // end if bukan minggu dan bukan libur ?>
</div>

<!-- Quick Navigation -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1000;">
    <div class="btn-group-vertical shadow">
        <a href="?tanggal=<?= date('Y-m-d', strtotime($tanggal_filter . ' -1 day')) ?>&view=<?= $view_mode ?>" 
           class="btn btn-dark" title="Hari Sebelumnya">
            <i class="fas fa-chevron-left"></i>
        </a>
        <a href="?tanggal=<?= date('Y-m-d') ?>&view=<?= $view_mode ?>" 
           class="btn btn-danger" title="Hari Ini">
            <i class="fas fa-calendar-day"></i>
        </a>
        <a href="?tanggal=<?= date('Y-m-d', strtotime($tanggal_filter . ' +1 day')) ?>&view=<?= $view_mode ?>" 
           class="btn btn-dark" title="Hari Berikutnya">
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>
</div>

<script>
// Ensure modal classes are applied on show
document.addEventListener('DOMContentLoaded', function() {
    const modals = document.querySelectorAll('[id^="modalBlokir"]');
    
    modals.forEach(function(modal) {
        // Reset validation on close
        modal.addEventListener('hidden.bs.modal', function() {
            const form = this.querySelector('form');
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
