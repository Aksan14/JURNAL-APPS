<?php
/*
File: kepsek/rekap_guru.php
Rekap Jurnal Per Guru (Read Only)
*/
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['kepsek']);

// Filter
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');

$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
               '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];

// Query rekap per guru
$sql = "
    SELECT g.id, g.nama_guru, g.nip,
           (SELECT COUNT(DISTINCT m2.id_kelas) FROM tbl_mengajar m2 WHERE m2.id_guru = g.id) as jml_kelas,
           (SELECT SUM(m3.jumlah_jam_mingguan) FROM tbl_mengajar m3 WHERE m3.id_guru = g.id) as total_jam_minggu,
           COUNT(j.id) as jml_jurnal,
           SUM(CASE 
               WHEN j.jam_ke LIKE '%-%' THEN 
                   CAST(SUBSTRING_INDEX(j.jam_ke, '-', -1) AS UNSIGNED) - 
                   CAST(SUBSTRING_INDEX(j.jam_ke, '-', 1) AS UNSIGNED) + 1
               WHEN j.id IS NOT NULL THEN 1
               ELSE 0
           END) as total_jam_mengajar
    FROM tbl_guru g
    LEFT JOIN tbl_mengajar m ON g.id = m.id_guru
    LEFT JOIN tbl_jurnal j ON m.id = j.id_mengajar 
        AND MONTH(j.tanggal) = :bulan AND YEAR(j.tanggal) = :tahun
    GROUP BY g.id, g.nama_guru, g.nip
    ORDER BY g.nama_guru
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['bulan' => $filter_bulan, 'tahun' => $filter_tahun]);
$rekap_guru = $stmt->fetchAll();

// Hitung total
$total_jurnal = array_sum(array_column($rekap_guru, 'jml_jurnal'));
$total_jam = array_sum(array_column($rekap_guru, 'total_jam_mengajar'));

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-bar me-2"></i>Rekap Per Guru</h1>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Bulan</label>
                    <select name="bulan" class="form-select">
                        <?php foreach ($nama_bulan as $val => $nama): ?>
                        <option value="<?= $val ?>" <?= $filter_bulan == $val ? 'selected' : '' ?>><?= $nama ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tahun</label>
                    <select name="tahun" class="form-select">
                        <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                        <option value="<?= $y ?>" <?= $filter_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Guru</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($rekap_guru) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Jurnal</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_jurnal ?> Entri</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Jam Mengajar</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_jam ?> Jam</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Rekap Jurnal Per Guru - <?= $nama_bulan[$filter_bulan] ?> <?= $filter_tahun ?>
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Nama Guru</th>
                            <th>NIP</th>
                            <th class="text-center">Jml Kelas</th>
                            <th class="text-center">Jam/Minggu</th>
                            <th class="text-center">Jurnal Diisi</th>
                            <th class="text-center">Jam Mengajar</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rekap_guru as $i => $r): ?>
                        <tr>
                            <td class="text-center"><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($r['nama_guru']) ?></strong></td>
                            <td><small class="text-muted"><?= htmlspecialchars($r['nip'] ?? '-') ?></small></td>
                            <td class="text-center"><span class="badge bg-info"><?= $r['jml_kelas'] ?? 0 ?></span></td>
                            <td class="text-center"><?= $r['total_jam_minggu'] ?? 0 ?></td>
                            <td class="text-center">
                                <span class="badge <?= $r['jml_jurnal'] > 0 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $r['jml_jurnal'] ?>
                                </span>
                            </td>
                            <td class="text-center"><?= $r['total_jam_mengajar'] ?? 0 ?> Jam</td>
                            <td class="text-center">
                                <?php if ($r['jml_jurnal'] > 0): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-minus"></i> Belum ada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
