<?php
/* File: guru/index.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

$user_id = $_SESSION['user_id'];

// 1. Ambil Data Guru & Profil
$stmt_guru = $pdo->prepare("SELECT * FROM tbl_guru WHERE user_id = ?");
$stmt_guru->execute([$user_id]);
$guru = $stmt_guru->fetch();
$id_guru = $guru['id'];

// 2. Statistik Ringkas
// Total Mapel/Kelas yang diampu
$stmt_mapel = $pdo->prepare("SELECT COUNT(*) FROM tbl_mengajar WHERE id_guru = ?");
$stmt_mapel->execute([$id_guru]);
$total_plotting = $stmt_mapel->fetchColumn();

// Total Jurnal yang sudah diisi bulan ini
$stmt_jurnal = $pdo->prepare("
    SELECT COUNT(*) FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    WHERE m.id_guru = ? AND MONTH(j.tanggal) = MONTH(CURRENT_DATE())
");
$stmt_jurnal->execute([$id_guru]);
$total_jurnal_bulan_ini = $stmt_jurnal->fetchColumn();

// Total Jam Mengajar per Minggu
$stmt_jam = $pdo->prepare("SELECT SUM(jumlah_jam_mingguan) FROM tbl_mengajar WHERE id_guru = ?");
$stmt_jam->execute([$id_guru]);
$total_jam = $stmt_jam->fetchColumn() ?? 0;

// 3. Ambil 5 Jurnal Terakhir
$stmt_recent = $pdo->prepare("
    SELECT j.*, k.nama_kelas, mp.nama_mapel 
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE m.id_guru = ?
    ORDER BY j.tanggal DESC, j.id DESC LIMIT 5
");
$stmt_recent->execute([$id_guru]);
$recent_jurnal = $stmt_recent->fetchAll();

// Greeting berdasarkan waktu
$hour = date('H');
$greeting = ($hour < 12) ? 'Selamat Pagi' : (($hour < 15) ? 'Selamat Siang' : (($hour < 18) ? 'Selamat Sore' : 'Selamat Malam'));

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><?= $greeting ?>, <strong><?= htmlspecialchars($guru['nama_guru']) ?></strong></h1>
            <p class="text-muted">Berikut adalah ringkasan aktivitas mengajar Anda.</p>
        </div>
        <a href="isi_jurnal.php?open_modal=1" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Isi Jurnal Hari Ini
        </a>
    </div>

    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Plotting Kelas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_plotting ?> Kelas/Mapel</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Jurnal (Bulan Ini)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_jurnal_bulan_ini ?> Entri</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Beban Kerja</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_jam ?> Jam/Minggu</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Riwayat Jurnal Terakhir</h6>
                    <a href="riwayat_jurnal.php" class="small">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Kelas</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Materi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($recent_jurnal) > 0): ?>
                                    <?php foreach($recent_jurnal as $j): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($j['tanggal'])) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($j['nama_kelas']) ?></span></td>
                                        <td><?= htmlspecialchars($j['nama_mapel']) ?></td>
                                        <td><?= htmlspecialchars(substr($j['topik_materi'] ?? $j['materi'] ?? '-', 0, 50)) ?><?= strlen($j['topik_materi'] ?? $j['materi'] ?? '') > 50 ? '...' : '' ?></td>
                                        <td>
                                            <a href="detail_jurnal.php?id=<?= $j['id'] ?>" class="btn btn-sm btn-light border"><i class="fas fa-eye"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center">Belum ada jurnal yang diisi.</td></tr>
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