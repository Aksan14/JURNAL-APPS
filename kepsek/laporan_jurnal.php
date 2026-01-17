<?php
/*
File: kepsek/laporan_jurnal.php
Laporan Jurnal Semua Guru (Read Only)
*/
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['kepsek']);

// Filter
$filter_guru = $_GET['guru'] ?? '';
$filter_kelas = $_GET['kelas'] ?? '';
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');

// Daftar untuk filter
$daftar_guru = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru")->fetchAll();
$daftar_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas")->fetchAll();

// Query jurnal
$sql = "
    SELECT j.*, g.nama_guru, g.nip, k.nama_kelas, mp.nama_mapel,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'H') as hadir,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'S') as sakit,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'I') as izin,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'A') as alpa
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE MONTH(j.tanggal) = :bulan AND YEAR(j.tanggal) = :tahun
";

$params = ['bulan' => $filter_bulan, 'tahun' => $filter_tahun];

if (!empty($filter_guru)) {
    $sql .= " AND m.id_guru = :guru";
    $params['guru'] = $filter_guru;
}
if (!empty($filter_kelas)) {
    $sql .= " AND m.id_kelas = :kelas";
    $params['kelas'] = $filter_kelas;
}

$sql .= " ORDER BY j.tanggal DESC, g.nama_guru";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftar_jurnal = $stmt->fetchAll();

$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
               '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-alt me-2"></i>Laporan Jurnal</h1>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
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
                <div class="col-md-2">
                    <label class="form-label">Kelas</label>
                    <select name="kelas" class="form-select">
                        <option value="">-- Semua --</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $filter_kelas == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                Data Jurnal - <?= $nama_bulan[$filter_bulan] ?> <?= $filter_tahun ?>
            </h6>
            <span class="badge bg-info"><?= count($daftar_jurnal) ?> Entri</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Guru</th>
                            <th>Kelas</th>
                            <th>Mapel</th>
                            <th>Jam</th>
                            <th>Materi</th>
                            <th>H</th>
                            <th>S</th>
                            <th>I</th>
                            <th>A</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($daftar_jurnal) > 0): ?>
                            <?php foreach ($daftar_jurnal as $i => $j): ?>
                            <tr>
                                <td class="text-center"><?= $i + 1 ?></td>
                                <td><?= date('d/m/Y', strtotime($j['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($j['nama_guru']) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($j['nama_kelas']) ?></span></td>
                                <td><small><?= htmlspecialchars($j['nama_mapel']) ?></small></td>
                                <td class="text-center"><?= $j['jam_ke'] ?></td>
                                <td><small><?= htmlspecialchars(substr($j['topik_materi'], 0, 50)) ?>...</small></td>
                                <td class="text-center text-success"><?= $j['hadir'] ?></td>
                                <td class="text-center text-warning"><?= $j['sakit'] ?></td>
                                <td class="text-center text-info"><?= $j['izin'] ?></td>
                                <td class="text-center text-danger"><?= $j['alpa'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="11" class="text-center text-muted">Tidak ada data jurnal</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
