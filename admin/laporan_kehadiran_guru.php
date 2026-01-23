<?php
/*
File: admin/laporan_kehadiran_guru.php
Deskripsi: Laporan Kehadiran Guru (Sakit, Izin, Cuti, Tidak Hadir)
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

// Filter
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_guru = $_GET['guru'] ?? '';
$filter_status = $_GET['status'] ?? '';

$nama_bulan = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];

$status_labels = [
    'tidak_hadir' => 'Tidak Hadir',
    'sakit' => 'Sakit',
    'izin' => 'Izin',
    'cuti' => 'Cuti'
];

// Query data guru
$daftar_guru = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru ASC")->fetchAll();

// Build query
$where = "WHERE MONTH(k.tanggal) = :bulan AND YEAR(k.tanggal) = :tahun";
$params = ['bulan' => $filter_bulan, 'tahun' => $filter_tahun];

if (!empty($filter_guru)) {
    $where .= " AND k.id_guru = :guru";
    $params['guru'] = $filter_guru;
}

if (!empty($filter_status)) {
    $where .= " AND k.status_kehadiran = :status";
    $params['status'] = $filter_status;
}

// Query laporan kehadiran guru
$sql = "
    SELECT k.id, k.tanggal, k.status_kehadiran, k.keterangan, k.created_at,
           g.nama_guru, g.nip,
           u.username as admin_username
    FROM tbl_kehadiran_guru k
    JOIN tbl_guru g ON k.id_guru = g.id
    LEFT JOIN tbl_users u ON k.created_by = u.id
    $where
    ORDER BY k.tanggal DESC, g.nama_guru ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$laporan = $stmt->fetchAll();

// Statistik
$sql_stat = "
    SELECT 
        COUNT(*) as total_ketidakhadiran,
        SUM(CASE WHEN status_kehadiran = 'tidak_hadir' THEN 1 ELSE 0 END) as total_tidak_hadir,
        SUM(CASE WHEN status_kehadiran = 'sakit' THEN 1 ELSE 0 END) as total_sakit,
        SUM(CASE WHEN status_kehadiran = 'izin' THEN 1 ELSE 0 END) as total_izin,
        SUM(CASE WHEN status_kehadiran = 'cuti' THEN 1 ELSE 0 END) as total_cuti,
        COUNT(DISTINCT id_guru) as total_guru_affected
    FROM tbl_kehadiran_guru
    $where
";

$stmt_stat = $pdo->prepare($sql_stat);
$stmt_stat->execute($params);
$statistik = $stmt_stat->fetch();

require_once '../includes/header.php';
?>

<style>
.stat-card {
    border-radius: 10px;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-user-clock me-2 text-danger"></i>Laporan Kehadiran Guru</h1>
            <p class="text-muted mb-0">Rekap guru yang sakit, izin, cuti, atau tidak hadir</p>
        </div>
        <div>
            <a href="export_rekap_kehadiran_guru_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <button onclick="window.print()" class="btn btn-secondary btn-sm">
                <i class="fas fa-print me-1"></i> Cetak
            </button>
        </div>
    </div>

    <!-- Statistik -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card stat-card bg-danger text-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-white text-danger me-3">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 opacity-75">Total</h6>
                        <h2 class="mb-0"><?= $statistik['total_ketidakhadiran'] ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card stat-card border-left-danger h-100">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Tidak Hadir</div>
                    <div class="h5 mb-0 font-weight-bold"><?= $statistik['total_tidak_hadir'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card stat-card border-left-warning h-100">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Sakit</div>
                    <div class="h5 mb-0 font-weight-bold"><?= $statistik['total_sakit'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card stat-card border-left-info h-100">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Izin</div>
                    <div class="h5 mb-0 font-weight-bold"><?= $statistik['total_izin'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card stat-card border-left-secondary h-100">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Cuti</div>
                    <div class="h5 mb-0 font-weight-bold"><?= $statistik['total_cuti'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card stat-card border-left-primary h-100">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Guru Terdampak</div>
                    <div class="h5 mb-0 font-weight-bold"><?= $statistik['total_guru_affected'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-filter me-2"></i>Filter Laporan</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Bulan</label>
                    <select name="bulan" class="form-select">
                        <?php foreach ($nama_bulan as $val => $nama): ?>
                        <option value="<?= $val ?>" <?= $filter_bulan == $val ? 'selected' : '' ?>><?= $nama ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tahun</label>
                    <select name="tahun" class="form-select">
                        <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                        <option value="<?= $y ?>" <?= $filter_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Guru</label>
                    <select name="guru" class="form-select">
                        <option value="">-- Semua Guru --</option>
                        <?php foreach ($daftar_guru as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $filter_guru == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_guru']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status Kehadiran</label>
                    <select name="status" class="form-select">
                        <option value="">-- Semua Status --</option>
                        <?php foreach ($status_labels as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $filter_status == $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Laporan -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Data Kehadiran Guru - <?= $nama_bulan[$filter_bulan] ?> <?= $filter_tahun ?>
            </h6>
        </div>
        <div class="card-body">
            <?php if (count($laporan) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="10%">Tanggal</th>
                            <th width="20%">Nama Guru</th>
                            <th width="12%">NIP</th>
                            <th width="12%">Status</th>
                            <th width="25%">Keterangan</th>
                            <th width="12%">Diinput Oleh</th>
                            <th width="12%">Waktu Input</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $status_colors = [
                            'tidak_hadir' => 'danger',
                            'sakit' => 'warning',
                            'izin' => 'info',
                            'cuti' => 'secondary'
                        ];
                        $status_icons = [
                            'tidak_hadir' => 'times-circle',
                            'sakit' => 'medkit',
                            'izin' => 'envelope',
                            'cuti' => 'plane'
                        ];
                        foreach ($laporan as $i => $row): 
                        ?>
                        <tr>
                            <td class="text-center"><?= $i + 1 ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                            <td><strong><?= htmlspecialchars($row['nama_guru']) ?></strong></td>
                            <td><small class="text-muted"><?= htmlspecialchars($row['nip'] ?? '-') ?></small></td>
                            <td>
                                <span class="badge bg-<?= $status_colors[$row['status_kehadiran']] ?>">
                                    <i class="fas fa-<?= $status_icons[$row['status_kehadiran']] ?> me-1"></i>
                                    <?= $status_labels[$row['status_kehadiran']] ?>
                                </span>
                            </td>
                            <td><small><?= htmlspecialchars($row['keterangan'] ?: '-') ?></small></td>
                            <td><small><?= htmlspecialchars($row['admin_username'] ?? 'System') ?></small></td>
                            <td><small><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-2"></i>Tidak ada data kehadiran guru untuk periode yang dipilih.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-info">
        <h6><i class="fas fa-info-circle me-2"></i>Informasi:</h6>
        <ul class="mb-0">
            <li>Data ini menampilkan guru yang tidak dapat mengisi jurnal karena sakit, izin, cuti, atau tidak hadir.</li>
            <li>Guru yang diblokir tidak akan bisa mengisi jurnal pada tanggal yang tercatat.</li>
            <li>Data diinput oleh Admin melalui menu Notifikasi Jurnal.</li>
        </ul>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
