<?php
/*
File: kepsek/rekap_kehadiran.php
Rekap Kehadiran Siswa (Read Only)
*/
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['kepsek']);

// Filter
$filter_kelas = $_GET['kelas'] ?? '';
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');

// Daftar kelas
$daftar_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas")->fetchAll();

$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
               '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];

// Query rekap kehadiran per kelas
$rekap_kelas = [];
if (!empty($filter_kelas)) {
    $sql = "
        SELECT s.id, s.nis, s.nama_siswa,
               SUM(CASE WHEN p.status_kehadiran = 'H' THEN 1 ELSE 0 END) as total_hadir,
               SUM(CASE WHEN p.status_kehadiran = 'S' THEN 1 ELSE 0 END) as total_sakit,
               SUM(CASE WHEN p.status_kehadiran = 'I' THEN 1 ELSE 0 END) as total_izin,
               SUM(CASE WHEN p.status_kehadiran = 'A' THEN 1 ELSE 0 END) as total_alpa,
               COUNT(p.id) as total_pertemuan
        FROM tbl_siswa s
        LEFT JOIN tbl_presensi_siswa p ON s.id = p.id_siswa
        LEFT JOIN tbl_jurnal j ON p.id_jurnal = j.id 
            AND MONTH(j.tanggal) = :bulan AND YEAR(j.tanggal) = :tahun
        WHERE s.id_kelas = :kelas
        GROUP BY s.id, s.nis, s.nama_siswa
        ORDER BY s.nama_siswa
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['kelas' => $filter_kelas, 'bulan' => $filter_bulan, 'tahun' => $filter_tahun]);
    $rekap_kelas = $stmt->fetchAll();
}

// Rekap summary per kelas
$summary_kelas = $pdo->query("
    SELECT k.id, k.nama_kelas,
           (SELECT COUNT(*) FROM tbl_siswa WHERE id_kelas = k.id) as jml_siswa,
           (SELECT COUNT(*) FROM tbl_jurnal j 
            JOIN tbl_mengajar m ON j.id_mengajar = m.id 
            WHERE m.id_kelas = k.id 
            AND MONTH(j.tanggal) = " . intval($filter_bulan) . " 
            AND YEAR(j.tanggal) = " . intval($filter_tahun) . ") as jml_jurnal
    FROM tbl_kelas k
    ORDER BY k.nama_kelas
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-clipboard-check me-2"></i>Rekap Kehadiran Siswa</h1>
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
                <div class="col-md-4">
                    <label class="form-label">Kelas</label>
                    <select name="kelas" class="form-select">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $filter_kelas == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Tampilkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($filter_kelas) && count($rekap_kelas) > 0): ?>
    <!-- Rekap Per Siswa -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Rekap Kehadiran - <?= $nama_bulan[$filter_bulan] ?> <?= $filter_tahun ?>
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th class="text-center bg-success text-white">Hadir</th>
                            <th class="text-center bg-warning">Sakit</th>
                            <th class="text-center bg-info text-white">Izin</th>
                            <th class="text-center bg-danger text-white">Alpa</th>
                            <th class="text-center">% Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rekap_kelas as $i => $r): 
                            $persen = $r['total_pertemuan'] > 0 ? round(($r['total_hadir'] / $r['total_pertemuan']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td class="text-center"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($r['nis']) ?></td>
                            <td><?= htmlspecialchars($r['nama_siswa']) ?></td>
                            <td class="text-center"><?= $r['total_hadir'] ?></td>
                            <td class="text-center"><?= $r['total_sakit'] ?></td>
                            <td class="text-center"><?= $r['total_izin'] ?></td>
                            <td class="text-center"><?= $r['total_alpa'] ?></td>
                            <td class="text-center">
                                <span class="badge <?= $persen >= 80 ? 'bg-success' : ($persen >= 60 ? 'bg-warning' : 'bg-danger') ?>">
                                    <?= $persen ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php elseif (!empty($filter_kelas)): ?>
    <div class="alert alert-info">Tidak ada data kehadiran untuk kelas ini pada bulan yang dipilih.</div>
    <?php endif; ?>

    <!-- Summary Semua Kelas -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Ringkasan Per Kelas - <?= $nama_bulan[$filter_bulan] ?> <?= $filter_tahun ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Kelas</th>
                            <th class="text-center">Jumlah Siswa</th>
                            <th class="text-center">Jumlah Jurnal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary_kelas as $i => $sk): ?>
                        <tr>
                            <td class="text-center"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($sk['nama_kelas']) ?></td>
                            <td class="text-center"><span class="badge bg-info"><?= $sk['jml_siswa'] ?></span></td>
                            <td class="text-center"><span class="badge bg-success"><?= $sk['jml_jurnal'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
