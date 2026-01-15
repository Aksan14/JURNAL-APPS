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

// ============================================
// CEK HARI LIBUR UNTUK GURU
// ============================================
// Ambil kelas yang diajar guru ini
$stmt_kelas_guru = $pdo->prepare("SELECT DISTINCT id_kelas FROM tbl_mengajar WHERE id_guru = ?");
$stmt_kelas_guru->execute([$id_guru]);
$kelas_guru = $stmt_kelas_guru->fetchAll(PDO::FETCH_COLUMN);

// Cek libur hari ini (umum atau khusus kelas yang diajar)
$libur_hari_ini = [];
if (!empty($kelas_guru)) {
    $placeholders = implode(',', array_fill(0, count($kelas_guru), '?'));
    $stmt_libur = $pdo->prepare("
        SELECT hl.*, k.nama_kelas 
        FROM tbl_hari_libur hl
        LEFT JOIN tbl_kelas k ON hl.id_kelas = k.id
        WHERE hl.tanggal = CURDATE() 
        AND (hl.id_kelas IS NULL OR hl.id_kelas IN ($placeholders))
        ORDER BY hl.id_kelas IS NULL DESC, k.nama_kelas
    ");
    $stmt_libur->execute($kelas_guru);
    $libur_hari_ini = $stmt_libur->fetchAll();
}

// Cek libur mendatang (7 hari ke depan)
$libur_mendatang = [];
if (!empty($kelas_guru)) {
    $placeholders = implode(',', array_fill(0, count($kelas_guru), '?'));
    $stmt_libur_next = $pdo->prepare("
        SELECT hl.*, k.nama_kelas 
        FROM tbl_hari_libur hl
        LEFT JOIN tbl_kelas k ON hl.id_kelas = k.id
        WHERE hl.tanggal > CURDATE() AND hl.tanggal <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND (hl.id_kelas IS NULL OR hl.id_kelas IN ($placeholders))
        ORDER BY hl.tanggal, hl.id_kelas IS NULL DESC
    ");
    $stmt_libur_next->execute($kelas_guru);
    $libur_mendatang = $stmt_libur_next->fetchAll();
}

// Cek jam khusus hari ini
$jam_khusus_hari_ini = [];
if (!empty($kelas_guru)) {
    $placeholders = implode(',', array_fill(0, count($kelas_guru), '?'));
    $stmt_jam = $pdo->prepare("
        SELECT jk.*, k.nama_kelas 
        FROM tbl_jam_khusus jk
        LEFT JOIN tbl_kelas k ON jk.id_kelas = k.id
        WHERE jk.tanggal = CURDATE()
        AND (jk.id_kelas IS NULL OR jk.id_kelas IN ($placeholders))
        ORDER BY jk.id_kelas IS NULL DESC, k.nama_kelas
    ");
    $stmt_jam->execute($kelas_guru);
    $jam_khusus_hari_ini = $stmt_jam->fetchAll();
}

// ============================================
// DATA KALENDER BULAN INI
// ============================================
$bulan_kalender = date('Y-m');
$tahun_kal = date('Y');
$bulan_kal = date('m');

// Ambil semua libur bulan ini untuk kelas yang diajar
$libur_bulan_ini = [];
if (!empty($kelas_guru)) {
    $placeholders = implode(',', array_fill(0, count($kelas_guru), '?'));
    $stmt_libur_bulan = $pdo->prepare("
        SELECT hl.*, k.nama_kelas 
        FROM tbl_hari_libur hl
        LEFT JOIN tbl_kelas k ON hl.id_kelas = k.id
        WHERE YEAR(hl.tanggal) = ? AND MONTH(hl.tanggal) = ?
        AND (hl.id_kelas IS NULL OR hl.id_kelas IN ($placeholders))
        ORDER BY hl.tanggal
    ");
    $params = array_merge([$tahun_kal, $bulan_kal], $kelas_guru);
    $stmt_libur_bulan->execute($params);
    while ($row = $stmt_libur_bulan->fetch()) {
        $day = (int)date('j', strtotime($row['tanggal']));
        if (!isset($libur_bulan_ini[$day])) $libur_bulan_ini[$day] = [];
        $libur_bulan_ini[$day][] = $row;
    }
}

// Ambil jam khusus bulan ini
$jam_khusus_bulan_ini = [];
if (!empty($kelas_guru)) {
    $placeholders = implode(',', array_fill(0, count($kelas_guru), '?'));
    $stmt_jk_bulan = $pdo->prepare("
        SELECT jk.*, k.nama_kelas 
        FROM tbl_jam_khusus jk
        LEFT JOIN tbl_kelas k ON jk.id_kelas = k.id
        WHERE YEAR(jk.tanggal) = ? AND MONTH(jk.tanggal) = ?
        AND (jk.id_kelas IS NULL OR jk.id_kelas IN ($placeholders))
        ORDER BY jk.tanggal
    ");
    $params = array_merge([$tahun_kal, $bulan_kal], $kelas_guru);
    $stmt_jk_bulan->execute($params);
    while ($row = $stmt_jk_bulan->fetch()) {
        $day = (int)date('j', strtotime($row['tanggal']));
        if (!isset($jam_khusus_bulan_ini[$day])) $jam_khusus_bulan_ini[$day] = [];
        $jam_khusus_bulan_ini[$day][] = $row;
    }
}

// Data kalender
$first_day_kal = mktime(0, 0, 0, $bulan_kal, 1, $tahun_kal);
$days_in_month_kal = date('t', $first_day_kal);
$start_day_kal = date('N', $first_day_kal);
$nama_bulan_kal = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <?php if (!empty($libur_hari_ini)): ?>
    <!-- NOTIFIKASI HARI LIBUR HARI INI -->
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <h5 class="alert-heading"><i class="fas fa-calendar-times me-2"></i> Hari Ini Libur!</h5>
        <?php foreach ($libur_hari_ini as $libur): ?>
            <div class="mb-1">
                <strong><?= htmlspecialchars($libur['nama_libur']) ?></strong>
                <?php if ($libur['id_kelas']): ?>
                    <span class="badge bg-warning text-dark ms-2">Khusus Kelas <?= htmlspecialchars($libur['nama_kelas']) ?></span>
                <?php else: ?>
                    <span class="badge bg-danger ms-2"><?= ucfirst(str_replace('_', ' ', $libur['jenis'])) ?></span>
                <?php endif; ?>
                <?php if ($libur['keterangan']): ?>
                    <small class="text-muted d-block"><?= htmlspecialchars($libur['keterangan']) ?></small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <hr>
        <p class="mb-0 small">Tidak perlu mengisi jurnal untuk hari libur.</p>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($jam_khusus_hari_ini)): ?>
    <!-- NOTIFIKASI JAM KHUSUS HARI INI -->
    <div class="alert alert-warning alert-dismissible fade show shadow-sm mb-4" role="alert">
        <h5 class="alert-heading"><i class="fas fa-clock me-2"></i> Jam Khusus Hari Ini</h5>
        <?php foreach ($jam_khusus_hari_ini as $jk): ?>
            <div class="mb-1">
                <strong><?= htmlspecialchars($jk['alasan']) ?></strong> - Maksimal <strong><?= $jk['max_jam'] ?> Jam</strong>
                <?php if ($jk['id_kelas']): ?>
                    <span class="badge bg-info ms-2">Kelas <?= htmlspecialchars($jk['nama_kelas']) ?></span>
                <?php else: ?>
                    <span class="badge bg-secondary ms-2">Semua Kelas</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($libur_mendatang)): ?>
    <!-- NOTIFIKASI LIBUR MENDATANG -->
    <div class="alert alert-info alert-dismissible fade show shadow-sm mb-4" role="alert">
        <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Jadwal Libur 7 Hari ke Depan</h6>
        <ul class="mb-0 small">
        <?php foreach ($libur_mendatang as $libur): ?>
            <li>
                <strong><?= date('d/m/Y (l)', strtotime($libur['tanggal'])) ?></strong>: <?= htmlspecialchars($libur['nama_libur']) ?>
                <?php if ($libur['id_kelas']): ?>
                    <span class="badge bg-warning text-dark">Kelas <?= htmlspecialchars($libur['nama_kelas']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

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
        <div class="col-lg-8">
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

        <!-- KALENDER LIBUR BULAN INI -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-2 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-calendar-alt me-2"></i><?= $nama_bulan_kal[(int)$bulan_kal] ?> <?= $tahun_kal ?>
                    </h6>
                </div>
                <div class="card-body p-2">
                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
                        <thead>
                            <tr class="text-center">
                                <th>S</th><th>S</th><th>R</th><th>K</th><th>J</th><th>S</th><th class="text-danger">M</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $today_day = (int)date('j');
                            echo '<tr>';
                            for ($i = 1; $i < $start_day_kal; $i++) {
                                echo '<td class="bg-light"></td>';
                            }
                            for ($day = 1; $day <= $days_in_month_kal; $day++) {
                                $dow = date('N', mktime(0,0,0,$bulan_kal,$day,$tahun_kal));
                                if ($dow == 1 && $day > 1) echo '</tr><tr>';
                                
                                $is_today = ($day == $today_day);
                                $is_libur = isset($libur_bulan_ini[$day]);
                                $is_jk = isset($jam_khusus_bulan_ini[$day]);
                                $is_sunday = ($dow == 7);
                                
                                $class = 'text-center';
                                $style = '';
                                $title = '';
                                
                                if ($is_libur) {
                                    $class .= ' bg-danger text-white';
                                    $title = $libur_bulan_ini[$day][0]['nama_libur'];
                                } elseif ($is_jk) {
                                    $class .= ' bg-warning';
                                    $title = $jam_khusus_bulan_ini[$day][0]['alasan'] . ' (Maks ' . $jam_khusus_bulan_ini[$day][0]['max_jam'] . ' jam)';
                                } elseif ($is_sunday) {
                                    $class .= ' text-danger';
                                }
                                
                                echo '<td class="' . $class . '" title="' . htmlspecialchars($title) . '" style="cursor:default;' . $style . '">';
                                if ($is_today) {
                                    echo '<span class="badge bg-primary rounded-circle" style="width:22px;height:22px;line-height:16px;">' . $day . '</span>';
                                } else {
                                    echo $day;
                                }
                                echo '</td>';
                            }
                            $last_dow = date('N', mktime(0,0,0,$bulan_kal,$days_in_month_kal,$tahun_kal));
                            for ($i = $last_dow + 1; $i <= 7; $i++) {
                                echo '<td class="bg-light"></td>';
                            }
                            echo '</tr>';
                            ?>
                        </tbody>
                    </table>
                    
                    <!-- Legenda -->
                    <div class="d-flex flex-wrap gap-2 mt-2 small justify-content-center">
                        <span><span class="badge bg-danger">&nbsp;</span> Libur</span>
                        <span><span class="badge bg-warning">&nbsp;</span> Jam Khusus</span>
                        <span><span class="badge bg-primary">&nbsp;</span> Hari Ini</span>
                    </div>
                </div>
                
                <!-- Daftar Libur Bulan Ini -->
                <?php 
                $all_libur = [];
                foreach ($libur_bulan_ini as $day => $items) {
                    foreach ($items as $item) {
                        $all_libur[] = $item;
                    }
                }
                foreach ($jam_khusus_bulan_ini as $day => $items) {
                    foreach ($items as $item) {
                        $item['is_jam_khusus'] = true;
                        $all_libur[] = $item;
                    }
                }
                usort($all_libur, fn($a, $b) => strtotime($a['tanggal']) - strtotime($b['tanggal']));
                ?>
                <?php if (!empty($all_libur)): ?>
                <div class="card-footer p-2" style="max-height: 150px; overflow-y: auto;">
                    <small class="text-muted d-block mb-1"><strong>Jadwal Bulan Ini:</strong></small>
                    <?php foreach ($all_libur as $item): ?>
                    <div class="small mb-1">
                        <?php if (isset($item['is_jam_khusus'])): ?>
                            <span class="badge bg-warning text-dark"><?= date('d', strtotime($item['tanggal'])) ?></span>
                            <i class="fas fa-clock text-warning"></i> <?= htmlspecialchars($item['alasan']) ?>
                            <small class="text-muted">(<?= $item['max_jam'] ?> jam)</small>
                        <?php else: ?>
                            <span class="badge bg-danger"><?= date('d', strtotime($item['tanggal'])) ?></span>
                            <i class="fas fa-calendar-times text-danger"></i> <?= htmlspecialchars($item['nama_libur']) ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>