<?php
/*
File: admin/index.php (Dashboard dengan Grafik)
Lokasi: /jurnal_app/admin/index.php
*/

require_once '../includes/header.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

// Helper: Konversi nama hari dari bahasa Inggris ke Indonesia
$hari_map = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin', 
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$hari_ini = $hari_map[date('l')]; // Hari ini dalam bahasa Indonesia

// 1. Ambil Statistik Ringkas
$total_guru = $pdo->query("SELECT COUNT(*) FROM tbl_guru")->fetchColumn();
$total_jam = $pdo->query("SELECT SUM(jumlah_jam_mingguan) FROM tbl_mengajar")->fetchColumn() ?? 0;

// 2. Ambil jumlah jadwal mengajar HARI INI yang belum diisi jurnalnya
$stmt_belum = $pdo->prepare("
    SELECT COUNT(*) FROM tbl_mengajar m
    WHERE m.hari = :hari_ini
    AND m.id NOT IN (
        SELECT id_mengajar FROM tbl_jurnal WHERE tanggal = CURDATE()
    )
");
$stmt_belum->execute(['hari_ini' => $hari_ini]);
$jumlah_belum_isi = $stmt_belum->fetchColumn();

// 3. Ambil jumlah jurnal yang sudah diisi hari ini
$stmt_sudah = $pdo->prepare("SELECT COUNT(*) FROM tbl_jurnal WHERE tanggal = CURDATE()");
$stmt_sudah->execute();
$jumlah_sudah_isi = $stmt_sudah->fetchColumn();

// 4. Ambil daftar kelas yang belum lengkap jurnalnya hari ini (berdasarkan jadwal hari ini)
$stmt_kelas_belum = $pdo->prepare("
    SELECT k.nama_kelas, 
           COUNT(DISTINCT m.id) as total_mengajar,
           COUNT(DISTINCT j.id) as total_jurnal_isi
    FROM tbl_kelas k
    LEFT JOIN tbl_mengajar m ON k.id = m.id_kelas AND m.hari = :hari_ini
    LEFT JOIN tbl_jurnal j ON m.id = j.id_mengajar AND j.tanggal = CURDATE()
    WHERE m.id IS NOT NULL
    GROUP BY k.id, k.nama_kelas
    HAVING COUNT(DISTINCT m.id) > COUNT(DISTINCT j.id)
    ORDER BY (COUNT(DISTINCT m.id) - COUNT(DISTINCT j.id)) DESC
    LIMIT 5
");
$stmt_kelas_belum->execute(['hari_ini' => $hari_ini]);
$kelas_belum_lengkap = $stmt_kelas_belum->fetchAll();

// 5. Ambil Data untuk Grafik (Nama Guru & Total Jam)
$stmt_chart = $pdo->query("
    SELECT g.nama_guru, SUM(m.jumlah_jam_mingguan) as total_jam
    FROM tbl_guru g
    LEFT JOIN tbl_mengajar m ON g.id = m.id_guru
    GROUP BY g.id
    ORDER BY total_jam DESC
    LIMIT 10 
"); // Kita ambil top 10 guru saja agar grafik tidak terlalu padat
$chart_data = $stmt_chart->fetchAll();

$labels = [];
$data = [];
foreach ($chart_data as $row) {
    $labels[] = $row['nama_guru'];
    $data[] = $row['total_jam'] ?? 0;
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Dashboard Admin</h1>

    <?php if ($jumlah_belum_isi > 0): ?>
    <!-- Alert Notifikasi Jurnal -->
    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-bell fa-2x me-3"></i>
            <div>
                <strong>Perhatian!</strong> Ada <span class="badge bg-danger"><?= $jumlah_belum_isi ?></span> jadwal mengajar yang belum diisi jurnalnya hari ini.
                <a href="notifikasi_jurnal.php" class="btn btn-sm btn-warning ms-2">
                    <i class="fas fa-eye me-1"></i> Lihat Detail
                </a>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Guru</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_guru; ?> Orang</div>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Jam Mengajar</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_jam; ?> Jam/Minggu</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Belum Isi Jurnal (Hari Ini)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $jumlah_belum_isi; ?> Jadwal</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Sudah Isi Jurnal (Hari Ini)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $jumlah_sudah_isi; ?> Jurnal</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($kelas_belum_lengkap)): ?>
    <!-- Daftar Kelas yang Belum Lengkap Jurnalnya -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-danger text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-exclamation-circle me-2"></i>Kelas dengan Jurnal Belum Lengkap Hari Ini
                    </h6>
                    <a href="notifikasi_jurnal.php" class="btn btn-sm btn-light">
                        <i class="fas fa-list me-1"></i> Lihat Semua
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kelas</th>
                                    <th class="text-center">Total Jadwal</th>
                                    <th class="text-center">Sudah Diisi</th>
                                    <th class="text-center">Belum Diisi</th>
                                    <th class="text-center">Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kelas_belum_lengkap as $kls): 
                                    $belum = $kls['total_mengajar'] - $kls['total_jurnal_isi'];
                                    $persen = ($kls['total_mengajar'] > 0) ? round(($kls['total_jurnal_isi'] / $kls['total_mengajar']) * 100) : 0;
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($kls['nama_kelas']) ?></strong></td>
                                    <td class="text-center"><?= $kls['total_mengajar'] ?></td>
                                    <td class="text-center"><span class="badge bg-success"><?= $kls['total_jurnal_isi'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-danger"><?= $belum ?></span></td>
                                    <td class="text-center">
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?= $persen < 50 ? 'bg-danger' : ($persen < 80 ? 'bg-warning' : 'bg-success') ?>" 
                                                 role="progressbar" 
                                                 style="width: <?= $persen ?>%">
                                                <?= $persen ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Statistik Jam Mengajar (Top 10 Guru)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 300px;">
                        <canvas id="myTeacherChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const ctx = document.getElementById('myTeacherChart').getContext('2d');
    const myChart = new Chart(ctx, {
        type: 'bar', // Jenis grafik: batang
        data: {
            labels: <?php echo json_encode($labels); ?>, // Nama-nama guru
            datasets: [{
                label: 'Total Jam Per Minggu',
                data: <?php echo json_encode($data); ?>, // Angka jam
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah Jam'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false // Sembunyikan legenda dataset jika hanya satu
                }
            }
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>