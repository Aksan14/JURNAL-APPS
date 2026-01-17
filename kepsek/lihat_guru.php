<?php
/*
File: kepsek/lihat_guru.php
Lihat Data Guru (Read Only) - Detail
*/
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['kepsek']);

// Filter bulan untuk statistik
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');

$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
               '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];

// Ambil Data Guru dengan statistik lengkap
$query = "
    SELECT g.*, u.username, 
           (SELECT COUNT(*) FROM tbl_mengajar WHERE id_guru = g.id) as total_mengajar,
           (SELECT COUNT(DISTINCT id_kelas) FROM tbl_mengajar WHERE id_guru = g.id) as total_kelas,
           (SELECT COUNT(DISTINCT id_mapel) FROM tbl_mengajar WHERE id_guru = g.id) as total_mapel,
           (SELECT SUM(jumlah_jam_mingguan) FROM tbl_mengajar WHERE id_guru = g.id) as total_jam_minggu,
           (SELECT COUNT(*) FROM tbl_jurnal j JOIN tbl_mengajar m ON j.id_mengajar = m.id 
            WHERE m.id_guru = g.id AND MONTH(j.tanggal) = ? AND YEAR(j.tanggal) = ?) as jurnal_bulan_ini,
           (SELECT COUNT(*) FROM tbl_jurnal j JOIN tbl_mengajar m ON j.id_mengajar = m.id 
            WHERE m.id_guru = g.id AND j.tanggal = CURDATE()) as jurnal_hari_ini,
           (SELECT MAX(j.tanggal) FROM tbl_jurnal j JOIN tbl_mengajar m ON j.id_mengajar = m.id 
            WHERE m.id_guru = g.id) as terakhir_isi,
           (SELECT k.nama_kelas FROM tbl_kelas k WHERE k.id_wali_kelas = g.id LIMIT 1) as wali_kelas
    FROM tbl_guru g 
    LEFT JOIN tbl_users u ON g.user_id = u.id 
    ORDER BY g.nama_guru ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute([$filter_bulan, $filter_tahun]);
$daftar_guru = $stmt->fetchAll();

// Hitung statistik summary
$total_jurnal_bulan = array_sum(array_column($daftar_guru, 'jurnal_bulan_ini'));
$guru_aktif = count(array_filter($daftar_guru, fn($g) => $g['jurnal_bulan_ini'] > 0));

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chalkboard-teacher me-2"></i>Data Guru</h1>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Guru</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($daftar_guru) ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Guru Aktif (Bulan Ini)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $guru_aktif ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-user-check fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Jurnal Bulan Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_jurnal_bulan ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-book fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Belum Isi Bulan Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($daftar_guru) - $guru_aktif ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0 me-2">Statistik Bulan:</label>
                </div>
                <div class="col-auto">
                    <select name="bulan" class="form-select form-select-sm">
                        <?php foreach ($nama_bulan as $val => $nama): ?>
                        <option value="<?= $val ?>" <?= $filter_bulan == $val ? 'selected' : '' ?>><?= $nama ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="tahun" class="form-select form-select-sm">
                        <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                        <option value="<?= $y ?>" <?= $filter_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                Daftar Guru - <?= $nama_bulan[$filter_bulan] ?> <?= $filter_tahun ?>
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable">
                    <thead class="table-dark">
                        <tr>
                            <th width="40" class="text-center">No</th>
                            <th>Nama Guru</th>
                            <th>NIP</th>
                            <th class="text-center">Jurnal Bulan Ini</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar_guru as $i => $g): ?>
                        <tr class="<?= $g['jurnal_bulan_ini'] == 0 && $g['total_mengajar'] > 0 ? 'table-warning' : '' ?>">
                            <td class="text-center"><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($g['nama_guru']) ?></strong></td>
                            <td><code><?= htmlspecialchars($g['nip'] ?? '-') ?></code></td>
                            <td class="text-center">
                                <?php if ($g['jurnal_bulan_ini'] > 0): ?>
                                    <span class="badge bg-primary"><?= $g['jurnal_bulan_ini'] ?> Entri</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($g['username']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Belum Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalDetailGuru<?= $g['id'] ?>">
                                    <i class="fas fa-eye me-1"></i>Detail
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Guru (Di luar tabel) -->
<?php foreach ($daftar_guru as $g): ?>
<div class="modal fade" id="modalDetailGuru<?= $g['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i>Detail Guru - <?= htmlspecialchars($g['nama_guru']) ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-primary border-bottom pb-2"><i class="fas fa-id-card me-2"></i>Informasi Pribadi</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr><th width="100">Nama</th><td>: <strong><?= htmlspecialchars($g['nama_guru']) ?></strong></td></tr>
                            <tr><th>NIP</th><td>: <?= htmlspecialchars($g['nip'] ?? '-') ?></td></tr>
                            <tr><th>Email</th><td>: <?= htmlspecialchars($g['email'] ?? '-') ?></td></tr>
                            <tr><th>Username</th><td>: <?= htmlspecialchars($g['username'] ?? '-') ?></td></tr>
                            <tr><th>Wali Kelas</th><td>: <?= $g['wali_kelas'] ? '<span class="badge bg-success">' . htmlspecialchars($g['wali_kelas']) . '</span>' : '-' ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary border-bottom pb-2"><i class="fas fa-chart-bar me-2"></i>Statistik</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr><th width="120">Total Kelas</th><td>: <?= $g['total_kelas'] ?? 0 ?> Kelas</td></tr>
                            <tr><th>Total Mapel</th><td>: <?= $g['total_mapel'] ?? 0 ?> Mapel</td></tr>
                            <tr><th>Jam/Minggu</th><td>: <?= $g['total_jam_minggu'] ?? 0 ?> Jam</td></tr>
                            <tr><th>Jurnal Hari Ini</th><td>: <?= $g['jurnal_hari_ini'] ?> Entri</td></tr>
                            <tr><th>Jurnal Bulan Ini</th><td>: <?= $g['jurnal_bulan_ini'] ?> Entri</td></tr>
                            <tr><th>Terakhir Isi</th><td>: <?= $g['terakhir_isi'] ? date('d/m/Y', strtotime($g['terakhir_isi'])) : '-' ?></td></tr>
                        </table>
                    </div>
                </div>
                
                <?php 
                // Ambil jadwal mengajar guru ini
                $jadwal = $pdo->prepare("
                    SELECT m.*, k.nama_kelas, mp.nama_mapel
                    FROM tbl_mengajar m
                    JOIN tbl_kelas k ON m.id_kelas = k.id
                    JOIN tbl_mapel mp ON m.id_mapel = mp.id
                    WHERE m.id_guru = ?
                    ORDER BY FIELD(m.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), m.jam_ke
                ");
                $jadwal->execute([$g['id']]);
                $daftar_jadwal = $jadwal->fetchAll();
                ?>
                
                <?php if (count($daftar_jadwal) > 0): ?>
                <h6 class="text-primary border-bottom pb-2"><i class="fas fa-calendar-alt me-2"></i>Jadwal Mengajar</h6>
                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-striped mb-0">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Hari</th>
                                <th>Jam</th>
                                <th>Kelas</th>
                                <th>Mata Pelajaran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftar_jadwal as $jd): ?>
                            <tr>
                                <td><?= $jd['hari'] ?></td>
                                <td><span class="badge bg-secondary">Jam <?= $jd['jam_ke'] ?></span></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($jd['nama_kelas']) ?></span></td>
                                <td><?= htmlspecialchars($jd['nama_mapel']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-info-circle me-2"></i>Guru ini belum memiliki jadwal mengajar.
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <a href="laporan_jurnal.php?guru=<?= $g['id'] ?>&bulan=<?= $filter_bulan ?>&tahun=<?= $filter_tahun ?>" class="btn btn-info">
                    <i class="fas fa-file-alt me-1"></i>Lihat Jurnal
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once '../includes/footer.php'; ?>
