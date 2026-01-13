<?php
/*
File: guru/beban_mengajar.php
Beban Mengajar & Jadwal + Rekap Bulanan Guru
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

$user_id = $_SESSION['user_id'];

// 1. Ambil Data Guru
$stmt_guru = $pdo->prepare("SELECT id, nama_guru FROM tbl_guru WHERE user_id = ?");
$stmt_guru->execute([$user_id]);
$guru = $stmt_guru->fetch();
$id_guru = $guru['id'];

// 2. Ambil data jadwal mengajar dengan hari dan jam
$stmt_beban = $pdo->prepare("
    SELECT m.id, mp.nama_mapel, k.nama_kelas, m.jumlah_jam_mingguan, m.hari, m.jam_ke
    FROM tbl_mengajar m
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    WHERE m.id_guru = ?
    ORDER BY FIELD(m.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), m.jam_ke ASC
");
$stmt_beban->execute([$id_guru]);
$daftar_beban = $stmt_beban->fetchAll();

// 3. Filter bulan untuk rekap
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$tanggal_mulai = "$tahun-$bulan-01";
$tanggal_selesai = date('Y-m-t', strtotime($tanggal_mulai));

// Hitung jumlah minggu
$start = new DateTime($tanggal_mulai);
$end = new DateTime($tanggal_selesai);
$minggu = max(1, floor($start->diff($end)->days / 7));

// 4. Rekap per mapel-kelas
$stmt_rekap = $pdo->prepare("
    SELECT 
        m.id as id_mengajar,
        mp.nama_mapel,
        k.nama_kelas,
        m.jumlah_jam_mingguan as roster_mingguan,
        m.hari,
        COALESCE(SUM(
            CASE 
                WHEN j.jam_ke LIKE '%-%' THEN 
                    CAST(SUBSTRING_INDEX(j.jam_ke, '-', -1) AS UNSIGNED) - 
                    CAST(SUBSTRING_INDEX(j.jam_ke, '-', 1) AS UNSIGNED) + 1
                ELSE 
                    CASE WHEN j.jam_ke IS NOT NULL AND j.jam_ke != '' THEN 1 ELSE 0 END
            END
        ), 0) as jam_terlaksana,
        COUNT(j.id) as total_pertemuan
    FROM tbl_mengajar m
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    LEFT JOIN tbl_jurnal j ON j.id_mengajar = m.id AND j.tanggal BETWEEN ? AND ?
    WHERE m.id_guru = ?
    GROUP BY m.id, mp.nama_mapel, k.nama_kelas, m.jumlah_jam_mingguan, m.hari
    ORDER BY mp.nama_mapel, k.nama_kelas
");
$stmt_rekap->execute([$tanggal_mulai, $tanggal_selesai, $id_guru]);
$rekap_data = $stmt_rekap->fetchAll();

// Nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Kelompokkan jadwal per hari
$jadwal_per_hari = [];
foreach ($daftar_beban as $row) {
    $hari = $row['hari'] ?? 'Belum Diatur';
    if (!isset($jadwal_per_hari[$hari])) {
        $jadwal_per_hari[$hari] = [];
    }
    $jadwal_per_hari[$hari][] = $row;
}

// Urutan hari
$urutan_hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Beban Mengajar & Jadwal</h1>
    </div>

    <!-- Jadwal Mengajar Per Hari -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-primary text-white">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-calendar-alt me-2"></i>Jadwal Mengajar Mingguan</h6>
        </div>
        <div class="card-body">
            <?php if (count($daftar_beban) > 0): ?>
                <div class="row">
                    <?php foreach ($urutan_hari as $hari): ?>
                        <?php if (isset($jadwal_per_hari[$hari])): ?>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card border-left-primary h-100">
                                    <div class="card-header py-2 bg-light">
                                        <strong><i class="fas fa-calendar-day me-1"></i><?= $hari ?></strong>
                                    </div>
                                    <div class="card-body py-2">
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($jadwal_per_hari[$hari] as $jadwal): ?>
                                                <li class="py-2 border-bottom">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <strong class="text-dark"><?= htmlspecialchars($jadwal['nama_mapel']) ?></strong><br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-users me-1"></i><?= htmlspecialchars($jadwal['nama_kelas']) ?>
                                                            </small>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="badge bg-danger">Jam <?= $jadwal['jam_ke'] ?? '-' ?></span><br>
                                                            <small class="text-muted"><?= $jadwal['jumlah_jam_mingguan'] ?? 0 ?> JP</small>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php if (isset($jadwal_per_hari['Belum Diatur'])): ?>
                        <div class="col-lg-4 col-md-6 mb-3">
                            <div class="card border-left-warning h-100">
                                <div class="card-header py-2 bg-warning text-dark">
                                    <strong><i class="fas fa-exclamation-triangle me-1"></i>Belum Diatur</strong>
                                </div>
                                <div class="card-body py-2">
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($jadwal_per_hari['Belum Diatur'] as $jadwal): ?>
                                            <li class="py-2 border-bottom">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong class="text-dark"><?= htmlspecialchars($jadwal['nama_mapel']) ?></strong><br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-users me-1"></i><?= htmlspecialchars($jadwal['nama_kelas']) ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-secondary">Jam ?</span><br>
                                                        <small class="text-muted"><?= $jadwal['jumlah_jam_mingguan'] ?? 0 ?> JP</small>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Total Summary -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <strong>Total Kelas/Mapel:</strong> <?= count($daftar_beban) ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Total Jam/Minggu:</strong> <?= array_sum(array_column($daftar_beban, 'jumlah_jam_mingguan')) ?> JP
                                </div>
                                <div class="col-md-4">
                                    <strong>Hari Aktif:</strong> <?= count($jadwal_per_hari) - (isset($jadwal_per_hari['Belum Diatur']) ? 1 : 0) ?> Hari
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-calendar-times fa-3x mb-3"></i>
                    <p>Belum ada jadwal mengajar. Hubungi Admin untuk plotting kelas.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rekap Bulanan -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-success text-white">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-chart-bar me-2"></i>Rekap Bulanan</h6>
        </div>
        <div class="card-body">
            <!-- Filter Bulan -->
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Bulan</label>
                    <select name="bulan" class="form-select">
                        <?php foreach ($nama_bulan as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $bulan == $num ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tahun</label>
                    <input type="number" name="tahun" class="form-control" value="<?= $tahun ?>" min="2020" max="2030">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
                </div>
            </form>

            <h5 class="mb-3">Rekap: <?= $nama_bulan[$bulan] ?> <?= $tahun ?> <small class="text-muted">(<?= $minggu ?> minggu)</small></h5>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Hari</th>
                            <th class="text-center">Roster/Mg</th>
                            <th class="text-center">Target Bulan</th>
                            <th class="text-center">Terlaksana</th>
                            <th class="text-center">Selisih</th>
                            <th class="text-center">Pertemuan</th>
                            <th class="text-center">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rekap_data) > 0): ?>
                            <?php 
                            $no = 1;
                            $total_roster = 0;
                            $total_target = 0;
                            $total_terlaksana = 0;
                            $total_pertemuan = 0;
                            ?>
                            <?php foreach ($rekap_data as $row): ?>
                                <?php 
                                $target = $row['roster_mingguan'] * $minggu;
                                $selisih = $row['jam_terlaksana'] - $target;
                                $persen = $target > 0 ? round(($row['jam_terlaksana'] / $target) * 100, 0) : 0;
                                
                                $total_roster += $row['roster_mingguan'];
                                $total_target += $target;
                                $total_terlaksana += $row['jam_terlaksana'];
                                $total_pertemuan += $row['total_pertemuan'];
                                
                                $badge_class = $persen >= 100 ? 'bg-success' : ($persen >= 80 ? 'bg-warning' : 'bg-danger');
                                $selisih_class = $selisih >= 0 ? 'text-success' : 'text-danger';
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($row['nama_kelas']) ?></span></td>
                                    <td><?= $row['hari'] ?? '-' ?></td>
                                    <td class="text-center"><?= $row['roster_mingguan'] ?></td>
                                    <td class="text-center"><?= $target ?></td>
                                    <td class="text-center fw-bold"><?= $row['jam_terlaksana'] ?></td>
                                    <td class="text-center fw-bold <?= $selisih_class ?>"><?= ($selisih >= 0 ? '+' : '') . $selisih ?></td>
                                    <td class="text-center"><?= $row['total_pertemuan'] ?>x</td>
                                    <td class="text-center"><span class="badge <?= $badge_class ?>"><?= $persen ?>%</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted">Tidak ada data</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if (count($rekap_data) > 0): ?>
                        <?php 
                        $total_persen = $total_target > 0 ? round(($total_terlaksana / $total_target) * 100, 0) : 0;
                        $total_selisih = $total_terlaksana - $total_target;
                        ?>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="4" class="text-end">TOTAL:</td>
                                <td class="text-center"><?= $total_roster ?></td>
                                <td class="text-center"><?= $total_target ?></td>
                                <td class="text-center"><?= $total_terlaksana ?></td>
                                <td class="text-center <?= $total_selisih >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($total_selisih >= 0 ? '+' : '') . $total_selisih ?></td>
                                <td class="text-center"><?= $total_pertemuan ?>x</td>
                                <td class="text-center"><span class="badge <?= $total_persen >= 100 ? 'bg-success' : ($total_persen >= 80 ? 'bg-warning' : 'bg-danger') ?>"><?= $total_persen ?>%</span></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Legend -->
            <div class="mt-3">
                <small class="text-muted">
                    <strong>Keterangan:</strong>
                    <span class="badge bg-success">≥100%</span> Tercapai |
                    <span class="badge bg-warning">80-99%</span> Hampir Tercapai |
                    <span class="badge bg-danger">&lt;80%</span> Belum Tercapai |
                    <strong>JP</strong> = Jam Pelajaran
                </small>
            </div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="alert alert-info border-left-info shadow" role="alert">
        <i class="fas fa-info-circle"></i> <strong>Informasi:</strong> 
        Jadwal mengajar ditentukan oleh Admin. Jika ada perubahan jadwal, silakan hubungi <strong>Admin Kurikulum</strong>.
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>