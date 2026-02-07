<?php
/*
File: laporan.php (Laporan Jurnal dengan Statistik Kehadiran)
Lokasi: /jurnal_app/admin/laporan.php
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';

// ==========================================================
// LOGIKA DELETE JURNAL
// ==========================================================
if (isset($_GET['hapus']) && !empty($_GET['hapus'])) {
    $id_jurnal_hapus = $_GET['hapus'];

    $pdo->beginTransaction();
    try {
        $stmt_del_presensi = $pdo->prepare("DELETE FROM tbl_presensi_siswa WHERE id_jurnal = ?");
        $stmt_del_presensi->execute([$id_jurnal_hapus]);

        $stmt_del_jurnal = $pdo->prepare("DELETE FROM tbl_jurnal WHERE id = ?");
        $stmt_del_jurnal->execute([$id_jurnal_hapus]);

        $pdo->commit();
        $message = "<div class='alert alert-success'>Jurnal berhasil dihapus.</div>";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Gagal menghapus jurnal: " . $e->getMessage() . "</div>";
    }
}

// ==========================================================
// FILTER
// ==========================================================
$daftar_guru = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru ASC")->fetchAll();
$daftar_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC")->fetchAll();

$tanggal_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tanggal_selesai = $_GET['tanggal_selesai'] ?? date('Y-m-t');
$filter_id_guru = $_GET['id_guru'] ?? '';
$filter_id_kelas = $_GET['id_kelas'] ?? '';

// Build query conditions
$filter_sql = " WHERE j.tanggal BETWEEN ? AND ?";
$params = [$tanggal_mulai, $tanggal_selesai];

if (!empty($filter_id_guru)) {
    $filter_sql .= " AND m.id_guru = ?";
    $params[] = $filter_id_guru;
}
if (!empty($filter_id_kelas)) {
    $filter_sql .= " AND m.id_kelas = ?";
    $params[] = $filter_id_kelas;
}

// ==========================================================
// QUERY LAPORAN JURNAL
// ==========================================================
$sql_laporan = "
    SELECT j.id, j.tanggal, j.jam_ke, j.topik_materi, 
           g.nama_guru, mp.nama_mapel, k.nama_kelas,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'H') as hadir,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'S') as sakit,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'I') as izin,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'A') as alpa
    FROM tbl_jurnal j 
    JOIN tbl_mengajar m ON j.id_mengajar = m.id 
    JOIN tbl_guru g ON m.id_guru = g.id 
    JOIN tbl_mapel mp ON m.id_mapel = mp.id 
    JOIN tbl_kelas k ON m.id_kelas = k.id 
    $filter_sql 
    ORDER BY j.tanggal DESC, g.nama_guru ASC, j.jam_ke ASC
";

$stmt_laporan = $pdo->prepare($sql_laporan);
$stmt_laporan->execute($params);
$laporan = $stmt_laporan->fetchAll();

// ==========================================================
// STATISTIK KEHADIRAN KESELURUHAN
// ==========================================================
$stat_params = $params;

$sql_statistik = "
    SELECT 
        COUNT(DISTINCT j.id) as total_pertemuan,
        COALESCE(SUM(CASE WHEN p.status_kehadiran = 'H' THEN 1 ELSE 0 END), 0) as total_hadir,
        COALESCE(SUM(CASE WHEN p.status_kehadiran = 'S' THEN 1 ELSE 0 END), 0) as total_sakit,
        COALESCE(SUM(CASE WHEN p.status_kehadiran = 'I' THEN 1 ELSE 0 END), 0) as total_izin,
        COALESCE(SUM(CASE WHEN p.status_kehadiran = 'A' THEN 1 ELSE 0 END), 0) as total_alpa,
        COUNT(p.id) as total_presensi
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    LEFT JOIN tbl_presensi_siswa p ON j.id = p.id_jurnal
    $filter_sql
";

$stmt_stat = $pdo->prepare($sql_statistik);
$stmt_stat->execute($stat_params);
$statistik = $stmt_stat->fetch();

// Hitung persentase
$total_presensi = $statistik['total_presensi'] ?: 1;
$persen_hadir = round(($statistik['total_hadir'] / $total_presensi) * 100, 1);
$persen_sakit = round(($statistik['total_sakit'] / $total_presensi) * 100, 1);
$persen_izin = round(($statistik['total_izin'] / $total_presensi) * 100, 1);
$persen_alpa = round(($statistik['total_alpa'] / $total_presensi) * 100, 1);

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Laporan Jurnal Pembelajaran</h1>
        <div>
            <a href="export_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <a href="export_pdf.php?<?= http_build_query($_GET) ?>" class="btn btn-danger btn-sm">
                <i class="fas fa-file-pdf me-1"></i> Export PDF
            </a>
        </div>
    </div>

    <?= $message ?>

    <!-- Filter Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-filter me-2"></i>Filter Laporan</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" class="form-control" value="<?= $tanggal_mulai ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Selesai</label>
                    <input type="date" name="tanggal_selesai" class="form-control" value="<?= $tanggal_selesai ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Guru</label>
                    <select name="id_guru" class="form-select">
                        <option value="">-- Semua Guru --</option>
                        <?php foreach ($daftar_guru as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $filter_id_guru == $g['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['nama_guru']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kelas</label>
                    <select name="id_kelas" class="form-select">
                        <option value="">-- Semua Kelas --</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $filter_id_kelas == $k['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Tampilkan
                    </button>
                    <a href="laporan.php" class="btn btn-secondary">
                        <i class="fas fa-sync me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistik Kehadiran -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-chart-pie me-2"></i>Statistik Kehadiran Siswa</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Total Pertemuan -->
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <div class="card bg-secondary text-white h-100">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                    <h3 class="mb-0"><?= $statistik['total_pertemuan'] ?></h3>
                                    <small>Total Pertemuan</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hadir -->
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-user-check fa-2x mb-2"></i>
                                    <h3 class="mb-0"><?= $statistik['total_hadir'] ?></h3>
                                    <small>Hadir (<?= $persen_hadir ?>%)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sakit -->
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <div class="card bg-warning text-dark h-100">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-briefcase-medical fa-2x mb-2"></i>
                                    <h3 class="mb-0"><?= $statistik['total_sakit'] ?></h3>
                                    <small>Sakit (<?= $persen_sakit ?>%)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Izin -->
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <div class="card bg-info text-white h-100">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-envelope fa-2x mb-2"></i>
                                    <h3 class="mb-0"><?= $statistik['total_izin'] ?></h3>
                                    <small>Izin (<?= $persen_izin ?>%)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Alpa -->
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <div class="card bg-danger text-white h-100">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-user-times fa-2x mb-2"></i>
                                    <h3 class="mb-0"><?= $statistik['total_alpa'] ?></h3>
                                    <small>Alpa (<?= $persen_alpa ?>%)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Total Siswa -->
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <div class="card bg-primary text-white h-100">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <h3 class="mb-0"><?= $statistik['total_presensi'] ?></h3>
                                    <small>Total Data Presensi</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar Visual -->
                    <?php if ($statistik['total_presensi'] > 0): ?>
                    <div class="mt-3">
                        <label class="form-label mb-2">Distribusi Kehadiran</label>
                        <div class="progress" style="height: 30px;">
                            <div class="progress-bar bg-success" style="width: <?= $persen_hadir ?>%;" 
                                 data-bs-toggle="tooltip" title="Hadir: <?= $statistik['total_hadir'] ?> (<?= $persen_hadir ?>%)">
                                <?= $persen_hadir > 10 ? 'H: '.$persen_hadir.'%' : '' ?>
                            </div>
                            <div class="progress-bar bg-warning" style="width: <?= $persen_sakit ?>%;" 
                                 data-bs-toggle="tooltip" title="Sakit: <?= $statistik['total_sakit'] ?> (<?= $persen_sakit ?>%)">
                                <?= $persen_sakit > 10 ? 'S: '.$persen_sakit.'%' : '' ?>
                            </div>
                            <div class="progress-bar bg-info" style="width: <?= $persen_izin ?>%;" 
                                 data-bs-toggle="tooltip" title="Izin: <?= $statistik['total_izin'] ?> (<?= $persen_izin ?>%)">
                                <?= $persen_izin > 10 ? 'I: '.$persen_izin.'%' : '' ?>
                            </div>
                            <div class="progress-bar bg-danger" style="width: <?= $persen_alpa ?>%;" 
                                 data-bs-toggle="tooltip" title="Alpa: <?= $statistik['total_alpa'] ?> (<?= $persen_alpa ?>%)">
                                <?= $persen_alpa > 10 ? 'A: '.$persen_alpa.'%' : '' ?>
                            </div>
                        </div>
                        <div class="d-flex justify-content-center gap-4 mt-2">
                            <small><span class="badge bg-success">●</span> Hadir</small>
                            <small><span class="badge bg-warning text-dark">●</span> Sakit</small>
                            <small><span class="badge bg-info">●</span> Izin</small>
                            <small><span class="badge bg-danger">●</span> Alpa</small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Laporan Jurnal -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2"></i>Daftar Jurnal (<?= count($laporan) ?> data)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable">
                    <thead class="table-dark">
                        <tr>
                            <th width="50">No</th>
                            <th>Tanggal</th>
                            <th>Guru</th>
                            <th>Kelas</th>
                            <th>Mapel</th>
                            <th>Jam Ke-</th>
                            <th>Topik Materi</th>
                            <th width="180">Kehadiran</th>
                            <th width="100">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($laporan)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    Tidak ada data laporan untuk filter ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($laporan as $j): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= date('d-m-Y', strtotime($j['tanggal'])) ?></td>
                                    <td><?= htmlspecialchars($j['nama_guru']) ?></td>
                                    <td><?= htmlspecialchars($j['nama_kelas']) ?></td>
                                    <td><?= htmlspecialchars($j['nama_mapel']) ?></td>
                                    <td><?= htmlspecialchars($j['jam_ke']) ?></td>
                                    <td><?= htmlspecialchars($j['topik_materi']) ?></td>
                                    <td>
                                        <span class="badge bg-success rounded-pill" title="Hadir"><?= $j['hadir'] ?></span>
                                        <span class="badge bg-warning text-dark rounded-pill" title="Sakit"><?= $j['sakit'] ?></span>
                                        <span class="badge bg-info rounded-pill" title="Izin"><?= $j['izin'] ?></span>
                                        <span class="badge bg-danger rounded-pill" title="Alpa"><?= $j['alpa'] ?></span>
                                    </td>
                                    <td>
                                        <a href="edit_jurnal.php?id=<?= $j['id'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="laporan.php?hapus=<?= $j['id'] ?>&<?= http_build_query($_GET) ?>" 
                                           class="btn btn-danger btn-sm" 
                                           title="Hapus"
                                           onclick="return confirm('Yakin ingin menghapus jurnal ini beserta data absensinya?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>