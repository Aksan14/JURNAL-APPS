<?php
/*
File: kepsek/index.php
Dashboard Kepala Sekolah - Monitoring Overview
*/
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['kepsek']);

$user_id = $_SESSION['user_id'];

// Statistik Umum
// Total Guru
$total_guru = $pdo->query("SELECT COUNT(*) FROM tbl_guru")->fetchColumn();

// Total Siswa
$total_siswa = $pdo->query("SELECT COUNT(*) FROM tbl_siswa")->fetchColumn();

// Total Kelas
$total_kelas = $pdo->query("SELECT COUNT(*) FROM tbl_kelas")->fetchColumn();

// Total Mapel
$total_mapel = $pdo->query("SELECT COUNT(*) FROM tbl_mapel")->fetchColumn();

// Total Jurnal Bulan Ini
$total_jurnal_bulan = $pdo->query("
    SELECT COUNT(*) FROM tbl_jurnal 
    WHERE MONTH(tanggal) = MONTH(CURRENT_DATE()) 
    AND YEAR(tanggal) = YEAR(CURRENT_DATE())
")->fetchColumn();

// Total Jurnal Hari Ini
$total_jurnal_hari = $pdo->query("
    SELECT COUNT(*) FROM tbl_jurnal WHERE tanggal = CURRENT_DATE()
")->fetchColumn();

// Guru yang sudah isi jurnal hari ini
$guru_sudah_isi = $pdo->query("
    SELECT COUNT(DISTINCT m.id_guru) 
    FROM tbl_jurnal j 
    JOIN tbl_mengajar m ON j.id_mengajar = m.id 
    WHERE j.tanggal = CURRENT_DATE()
")->fetchColumn();

// Guru yang punya jadwal hari ini tapi belum isi jurnal
$nama_hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$hari_ini = $nama_hari[date('w')];

$guru_belum_isi = $pdo->prepare("
    SELECT DISTINCT g.id, g.nama_guru, g.nip
    FROM tbl_guru g
    JOIN tbl_mengajar m ON g.id = m.id_guru
    WHERE m.hari = ?
    AND g.id NOT IN (
        SELECT DISTINCT m2.id_guru 
        FROM tbl_jurnal j 
        JOIN tbl_mengajar m2 ON j.id_mengajar = m2.id 
        WHERE j.tanggal = CURRENT_DATE()
    )
    ORDER BY g.nama_guru
");
$guru_belum_isi->execute([$hari_ini]);
$daftar_guru_belum = $guru_belum_isi->fetchAll();

// 5 Jurnal Terbaru
$jurnal_terbaru = $pdo->query("
    SELECT j.*, g.nama_guru, k.nama_kelas, mp.nama_mapel
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    ORDER BY j.tanggal DESC, j.id DESC
    LIMIT 10
")->fetchAll();

// Greeting
$hour = date('H');
$greeting = ($hour < 12) ? 'Selamat Pagi' : (($hour < 15) ? 'Selamat Siang' : (($hour < 18) ? 'Selamat Sore' : 'Selamat Malam'));

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><?= $greeting ?>, Kepala Sekolah!</h1>
            <p class="text-muted mb-0">Dashboard Monitoring Jurnal Pembelajaran</p>
        </div>
        <span class="badge bg-info text-white p-2">
            <i class="fas fa-calendar me-1"></i> <?= date('l, d F Y') ?>
        </span>
    </div>

    <!-- Statistik Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Guru</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_guru ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Siswa</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_siswa ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Kelas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_kelas ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-school fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Jurnal Hari Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_jurnal_hari ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-edit fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Jurnal Bulan Ini -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-dark shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Jurnal Bulan Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_jurnal_bulan ?> Entri</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Guru Sudah Isi -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Guru Sudah Isi (Hari Ini)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $guru_sudah_isi ?> Guru</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Guru Belum Isi -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Guru Belum Isi (Hari Ini)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($daftar_guru_belum) ?> Guru</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Daftar Guru Belum Isi Jurnal -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 bg-danger text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-exclamation-triangle me-2"></i>Guru Belum Isi Jurnal Hari Ini
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (count($daftar_guru_belum) > 0): ?>
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Guru</th>
                                    <th>NIP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daftar_guru_belum as $i => $gb): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($gb['nama_guru']) ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($gb['nip'] ?? '-') ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p class="text-muted">Semua guru sudah mengisi jurnal hari ini!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Jurnal Terbaru -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history me-2"></i>Jurnal Terbaru
                    </h6>
                    <a href="laporan_jurnal.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Guru</th>
                                    <th>Kelas</th>
                                    <th>Mapel</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($jurnal_terbaru) > 0): ?>
                                    <?php foreach ($jurnal_terbaru as $jt): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($jt['tanggal'])) ?></td>
                                        <td><?= htmlspecialchars($jt['nama_guru']) ?></td>
                                        <td><span class="badge bg-info"><?= htmlspecialchars($jt['nama_kelas']) ?></span></td>
                                        <td><small><?= htmlspecialchars($jt['nama_mapel']) ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada jurnal</td></tr>
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
