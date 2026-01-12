<?php
/*
File: admin/notifikasi_jurnal.php
Lokasi: /jurnal_app/admin/notifikasi_jurnal.php
Deskripsi: Halaman notifikasi untuk guru yang belum mengisi jurnal berdasarkan jadwal mengajar
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

// Filter tanggal (default hari ini)
$tanggal_filter = $_GET['tanggal'] ?? date('Y-m-d');
$hari_ini = date('l', strtotime($tanggal_filter)); // Nama hari dalam bahasa Inggris

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

// Filter kelas
$filter_kelas = $_GET['kelas'] ?? '';

// Query untuk mendapatkan semua jadwal mengajar dan cek apakah sudah diisi jurnal
$where_kelas = "";
$params = [$tanggal_filter];

if (!empty($filter_kelas)) {
    $where_kelas = "AND m.id_kelas = ?";
    $params[] = $filter_kelas;
}

// Query gabungan: semua mengajar yang BELUM diisi jurnal pada tanggal tersebut
$sql_belum_isi = "
    SELECT 
        m.id as id_mengajar,
        g.id as id_guru,
        g.nama_guru,
        g.nip,
        k.id as id_kelas,
        k.nama_kelas,
        mp.nama_mapel,
        m.jumlah_jam_mingguan,
        COALESCE(
            (SELECT SUM(
                CASE 
                    WHEN j2.jam_ke LIKE '%-%' THEN 
                        CAST(SUBSTRING_INDEX(j2.jam_ke, '-', -1) AS UNSIGNED) - 
                        CAST(SUBSTRING_INDEX(j2.jam_ke, '-', 1) AS UNSIGNED) + 1
                    ELSE 1
                END
            ) FROM tbl_jurnal j2 
            JOIN tbl_mengajar m2 ON j2.id_mengajar = m2.id 
            WHERE m2.id_kelas = k.id AND j2.tanggal = ?), 0
        ) as total_jam_kelas_hari_ini
    FROM tbl_mengajar m
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE m.id NOT IN (
        SELECT id_mengajar FROM tbl_jurnal WHERE tanggal = ?
    )
    $where_kelas
    ORDER BY k.nama_kelas ASC, g.nama_guru ASC
";

$params_full = [$tanggal_filter, $tanggal_filter];
if (!empty($filter_kelas)) {
    $params_full[] = $filter_kelas;
}

$stmt_belum = $pdo->prepare($sql_belum_isi);
$stmt_belum->execute($params_full);
$belum_isi = $stmt_belum->fetchAll();

// Grup berdasarkan kelas untuk tampilan yang lebih rapi
$belum_isi_per_kelas = [];
foreach ($belum_isi as $row) {
    $kelas = $row['nama_kelas'];
    if (!isset($belum_isi_per_kelas[$kelas])) {
        $belum_isi_per_kelas[$kelas] = [
            'id_kelas' => $row['id_kelas'],
            'total_jam_hari_ini' => $row['total_jam_kelas_hari_ini'],
            'data' => []
        ];
    }
    $belum_isi_per_kelas[$kelas]['data'][] = $row;
}

// Query untuk mendapatkan jurnal yang SUDAH diisi hari ini (per kelas)
$sql_sudah_isi = "
    SELECT 
        j.id as id_jurnal,
        j.jam_ke,
        j.topik_materi,
        j.created_at,
        g.nama_guru,
        k.id as id_kelas,
        k.nama_kelas,
        mp.nama_mapel,
        CASE 
            WHEN j.jam_ke LIKE '%-%' THEN 
                CAST(SUBSTRING_INDEX(j.jam_ke, '-', -1) AS UNSIGNED) - 
                CAST(SUBSTRING_INDEX(j.jam_ke, '-', 1) AS UNSIGNED) + 1
            ELSE 1
        END as jumlah_jam
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE j.tanggal = ?
    " . (!empty($filter_kelas) ? "AND k.id = ?" : "") . "
    ORDER BY k.nama_kelas ASC, j.jam_ke ASC
";

$params_sudah = [$tanggal_filter];
if (!empty($filter_kelas)) {
    $params_sudah[] = $filter_kelas;
}

$stmt_sudah = $pdo->prepare($sql_sudah_isi);
$stmt_sudah->execute($params_sudah);
$sudah_isi = $stmt_sudah->fetchAll();

// Grup jurnal yang sudah diisi per kelas
$sudah_isi_per_kelas = [];
foreach ($sudah_isi as $row) {
    $kelas = $row['nama_kelas'];
    if (!isset($sudah_isi_per_kelas[$kelas])) {
        $sudah_isi_per_kelas[$kelas] = [
            'id_kelas' => $row['id_kelas'],
            'total_jam' => 0,
            'data' => []
        ];
    }
    $sudah_isi_per_kelas[$kelas]['total_jam'] += $row['jumlah_jam'];
    $sudah_isi_per_kelas[$kelas]['data'][] = $row;
}

// Ambil daftar kelas untuk dropdown filter
$list_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC")->fetchAll();

// Hitung statistik
$total_belum_isi = count($belum_isi);
$total_sudah_isi = count($sudah_isi);
$total_kelas_belum_lengkap = count($belum_isi_per_kelas);

// Konstanta batas jam per kelas per hari
define('MAX_JAM_PER_HARI', 10);

require_once '../includes/header.php';
?>

<style>
.notif-card {
    border-left: 4px solid;
    transition: all 0.3s ease;
}
.notif-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.notif-danger { border-left-color: #dc3545; }
.notif-warning { border-left-color: #ffc107; }
.notif-success { border-left-color: #28a745; }
.notif-info { border-left-color: #17a2b8; }

.kelas-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px 8px 0 0;
}

.jam-badge {
    font-size: 0.9rem;
    padding: 0.4rem 0.8rem;
}

.progress-jam {
    height: 8px;
    border-radius: 4px;
}

.table-notif th {
    font-weight: 600;
    background-color: #f8f9fc;
}

.alert-jam-penuh {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-bell me-2"></i>Notifikasi Jurnal Belum Diisi
        </h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
        </a>
    </div>

    <!-- Filter Section -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal_filter) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Filter Kelas</label>
                    <select name="kelas" class="form-select">
                        <option value="">-- Semua Kelas --</option>
                        <?php foreach ($list_kelas as $kls): ?>
                            <option value="<?= $kls['id'] ?>" <?= $filter_kelas == $kls['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kls['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="notifikasi_jurnal.php" class="btn btn-outline-secondary">
                        <i class="fas fa-sync me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Info Tanggal -->
    <div class="alert alert-info mb-4">
        <i class="fas fa-calendar-day me-2"></i>
        <strong>Tanggal:</strong> <?= date('d F Y', strtotime($tanggal_filter)) ?> (<?= $nama_hari ?>)
        <span class="float-end">
            <strong>Batas Maksimal:</strong> <?= MAX_JAM_PER_HARI ?> jam pelajaran per kelas per hari
        </span>
    </div>

    <!-- Statistik Ringkas -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card notif-card notif-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Jurnal Belum Diisi
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_belum_isi ?> Jadwal</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card notif-card notif-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Jurnal Sudah Diisi
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_sudah_isi ?> Jurnal</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card notif-card notif-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Kelas dengan Jurnal Belum Lengkap
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_kelas_belum_lengkap ?> Kelas</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-warning opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-4" id="notifTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="belum-tab" data-bs-toggle="tab" data-bs-target="#belum" type="button">
                <i class="fas fa-times-circle text-danger me-1"></i> 
                Belum Diisi <span class="badge bg-danger"><?= $total_belum_isi ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sudah-tab" data-bs-toggle="tab" data-bs-target="#sudah" type="button">
                <i class="fas fa-check-circle text-success me-1"></i> 
                Sudah Diisi <span class="badge bg-success"><?= $total_sudah_isi ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="rekap-tab" data-bs-toggle="tab" data-bs-target="#rekap" type="button">
                <i class="fas fa-chart-bar text-info me-1"></i> 
                Rekap Jam Per Kelas
            </button>
        </li>
    </ul>

    <div class="tab-content" id="notifTabContent">
        <!-- Tab Belum Diisi -->
        <div class="tab-pane fade show active" id="belum" role="tabpanel">
            <?php if (empty($belum_isi_per_kelas)): ?>
                <div class="alert alert-success text-center py-5">
                    <i class="fas fa-check-circle fa-4x mb-3 text-success"></i>
                    <h4>Semua Jurnal Sudah Terisi!</h4>
                    <p class="mb-0">Tidak ada guru yang belum mengisi jurnal pada tanggal <?= date('d/m/Y', strtotime($tanggal_filter)) ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($belum_isi_per_kelas as $kelas => $data): ?>
                    <?php 
                    $jam_terisi = $data['total_jam_hari_ini'];
                    $sisa_jam = MAX_JAM_PER_HARI - $jam_terisi;
                    $persen_jam = min(100, ($jam_terisi / MAX_JAM_PER_HARI) * 100);
                    ?>
                    <div class="card shadow mb-4">
                        <div class="kelas-header p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-school me-2"></i><?= htmlspecialchars($kelas) ?>
                                </h5>
                                <div>
                                    <span class="badge bg-light text-dark jam-badge">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= $jam_terisi ?> / <?= MAX_JAM_PER_HARI ?> Jam Terisi
                                    </span>
                                    <?php if ($sisa_jam <= 2 && $sisa_jam > 0): ?>
                                        <span class="badge bg-warning text-dark ms-2">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Hampir Penuh!
                                        </span>
                                    <?php elseif ($sisa_jam <= 0): ?>
                                        <span class="badge bg-danger ms-2 alert-jam-penuh">
                                            <i class="fas fa-ban me-1"></i>JAM PENUH!
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="progress progress-jam mt-2">
                                <div class="progress-bar <?= $persen_jam >= 100 ? 'bg-danger' : ($persen_jam >= 80 ? 'bg-warning' : 'bg-success') ?>" 
                                     role="progressbar" 
                                     style="width: <?= $persen_jam ?>%">
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-notif mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Nama Guru</th>
                                            <th>NIP</th>
                                            <th>Mata Pelajaran</th>
                                            <th class="text-center">Jam/Minggu</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['data'] as $i => $row): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['nama_guru']) ?></strong>
                                            </td>
                                            <td><small class="text-muted"><?= htmlspecialchars($row['nip'] ?? '-') ?></small></td>
                                            <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?= $row['jumlah_jam_mingguan'] ?> jam</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times me-1"></i>Belum Isi
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Tab Sudah Diisi -->
        <div class="tab-pane fade" id="sudah" role="tabpanel">
            <?php if (empty($sudah_isi_per_kelas)): ?>
                <div class="alert alert-warning text-center py-5">
                    <i class="fas fa-inbox fa-4x mb-3 text-warning"></i>
                    <h4>Belum Ada Jurnal Diisi</h4>
                    <p class="mb-0">Tidak ada jurnal yang diisi pada tanggal <?= date('d/m/Y', strtotime($tanggal_filter)) ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($sudah_isi_per_kelas as $kelas => $data): ?>
                    <?php 
                    $persen_jam = min(100, ($data['total_jam'] / MAX_JAM_PER_HARI) * 100);
                    ?>
                    <div class="card shadow mb-4">
                        <div class="kelas-header p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-school me-2"></i><?= htmlspecialchars($kelas) ?>
                                </h5>
                                <span class="badge bg-light text-dark jam-badge">
                                    <i class="fas fa-clock me-1"></i>
                                    Total: <?= $data['total_jam'] ?> / <?= MAX_JAM_PER_HARI ?> Jam
                                </span>
                            </div>
                            <div class="progress progress-jam mt-2">
                                <div class="progress-bar bg-success" 
                                     role="progressbar" 
                                     style="width: <?= $persen_jam ?>%">
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-notif mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Nama Guru</th>
                                            <th>Mata Pelajaran</th>
                                            <th class="text-center">Jam Ke</th>
                                            <th>Materi</th>
                                            <th class="text-center">Waktu Input</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['data'] as $i => $row): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><strong><?= htmlspecialchars($row['nama_guru']) ?></strong></td>
                                            <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?= htmlspecialchars($row['jam_ke']) ?></span>
                                                <small class="d-block text-muted">(<?= $row['jumlah_jam'] ?> jam)</small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars(substr($row['topik_materi'], 0, 50)) ?>
                                                <?= strlen($row['topik_materi']) > 50 ? '...' : '' ?>
                                            </td>
                                            <td class="text-center">
                                                <small class="text-muted">
                                                    <?= date('H:i', strtotime($row['created_at'])) ?>
                                                </small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Tab Rekap Jam Per Kelas -->
        <div class="tab-pane fade" id="rekap" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar me-2"></i>Rekap Penggunaan Jam Per Kelas
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Kelas</th>
                                    <th class="text-center">Jam Terisi</th>
                                    <th class="text-center">Sisa Jam</th>
                                    <th style="width: 40%;">Progress</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Gabungkan semua kelas
                                $all_kelas = array_merge(
                                    array_keys($sudah_isi_per_kelas), 
                                    array_keys($belum_isi_per_kelas)
                                );
                                $all_kelas = array_unique($all_kelas);
                                sort($all_kelas);
                                
                                foreach ($all_kelas as $kelas):
                                    $jam_terisi = $sudah_isi_per_kelas[$kelas]['total_jam'] ?? 0;
                                    $sisa = MAX_JAM_PER_HARI - $jam_terisi;
                                    $persen = min(100, ($jam_terisi / MAX_JAM_PER_HARI) * 100);
                                    
                                    $bar_class = 'bg-success';
                                    $status_badge = '<span class="badge bg-success">Normal</span>';
                                    
                                    if ($persen >= 100) {
                                        $bar_class = 'bg-danger';
                                        $status_badge = '<span class="badge bg-danger">PENUH</span>';
                                    } elseif ($persen >= 80) {
                                        $bar_class = 'bg-warning';
                                        $status_badge = '<span class="badge bg-warning text-dark">Hampir Penuh</span>';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($kelas) ?></strong></td>
                                    <td class="text-center"><?= $jam_terisi ?> jam</td>
                                    <td class="text-center"><?= max(0, $sisa) ?> jam</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?= $bar_class ?>" 
                                                 role="progressbar" 
                                                 style="width: <?= $persen ?>%"
                                                 aria-valuenow="<?= $jam_terisi ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="<?= MAX_JAM_PER_HARI ?>">
                                                <?= round($persen) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center"><?= $status_badge ?></td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($all_kelas)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Tidak ada data untuk tanggal ini.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
