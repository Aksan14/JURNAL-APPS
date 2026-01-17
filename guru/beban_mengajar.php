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

// Validasi: Pastikan guru ditemukan
if (!$guru) {
    $_SESSION['error_message'] = 'Akun Anda tidak terhubung dengan data guru. Silakan hubungi administrator.';
    header('Location: ' . BASE_URL . '/guru/index.php');
    exit;
}

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

// Hitung jumlah hari libur per hari (Senin, Selasa, dst) dalam bulan ini
// untuk setiap kelas yang diajar guru
$libur_per_hari_kelas = [];
$stmt_libur = $pdo->prepare("
    SELECT 
        DATE_FORMAT(h.tanggal, '%W') as nama_hari_en,
        h.id_kelas,
        COUNT(*) as jumlah_libur
    FROM tbl_hari_libur h
    WHERE h.tanggal BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(h.tanggal, '%W'), h.id_kelas
");
$stmt_libur->execute([$tanggal_mulai, $tanggal_selesai]);

// Mapping hari Inggris ke Indonesia
$hari_mapping = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa', 
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];

while ($row_libur = $stmt_libur->fetch()) {
    $hari_id = $hari_mapping[$row_libur['nama_hari_en']] ?? $row_libur['nama_hari_en'];
    $kelas_id = $row_libur['id_kelas'] ?? 'all'; // NULL = berlaku untuk semua
    
    if (!isset($libur_per_hari_kelas[$hari_id])) {
        $libur_per_hari_kelas[$hari_id] = [];
    }
    $libur_per_hari_kelas[$hari_id][$kelas_id] = $row_libur['jumlah_libur'];
}

// Fungsi untuk mendapatkan jumlah libur berdasarkan hari dan kelas
function getJumlahLibur($hari, $id_kelas, $libur_data) {
    $libur_semua = $libur_data[$hari]['all'] ?? 0; // Libur global (id_kelas = NULL)
    $libur_kelas = $libur_data[$hari][$id_kelas] ?? 0; // Libur khusus kelas
    return $libur_semua + $libur_kelas;
}

// Ambil detail libur per hari dan kelas untuk ditampilkan di kolom
$libur_detail_per_hari = [];
$stmt_libur_detail = $pdo->prepare("
    SELECT h.tanggal, h.nama_libur, h.id_kelas,
           DATE_FORMAT(h.tanggal, '%W') as hari_en,
           DATE_FORMAT(h.tanggal, '%d') as tgl
    FROM tbl_hari_libur h
    WHERE h.tanggal BETWEEN ? AND ?
    ORDER BY h.tanggal
");
$stmt_libur_detail->execute([$tanggal_mulai, $tanggal_selesai]);
while ($ld = $stmt_libur_detail->fetch()) {
    $hari_indo = $hari_mapping[$ld['hari_en']] ?? $ld['hari_en'];
    $kelas_key = $ld['id_kelas'] ?? 'all';
    if (!isset($libur_detail_per_hari[$hari_indo])) {
        $libur_detail_per_hari[$hari_indo] = [];
    }
    if (!isset($libur_detail_per_hari[$hari_indo][$kelas_key])) {
        $libur_detail_per_hari[$hari_indo][$kelas_key] = [];
    }
    $libur_detail_per_hari[$hari_indo][$kelas_key][] = [
        'tgl' => $ld['tgl'],
        'nama' => $ld['nama_libur']
    ];
}

// Ambil detail jam khusus per hari dan kelas
$jam_khusus_detail_per_hari = [];
$stmt_jk_detail = $pdo->prepare("
    SELECT jk.tanggal, jk.alasan, jk.max_jam, jk.id_kelas,
           DATE_FORMAT(jk.tanggal, '%W') as hari_en,
           DATE_FORMAT(jk.tanggal, '%d') as tgl
    FROM tbl_jam_khusus jk
    WHERE jk.tanggal BETWEEN ? AND ?
    ORDER BY jk.tanggal
");
$stmt_jk_detail->execute([$tanggal_mulai, $tanggal_selesai]);
while ($jk = $stmt_jk_detail->fetch()) {
    $hari_indo = $hari_mapping[$jk['hari_en']] ?? $jk['hari_en'];
    $kelas_key = $jk['id_kelas'] ?? 'all';
    if (!isset($jam_khusus_detail_per_hari[$hari_indo])) {
        $jam_khusus_detail_per_hari[$hari_indo] = [];
    }
    if (!isset($jam_khusus_detail_per_hari[$hari_indo][$kelas_key])) {
        $jam_khusus_detail_per_hari[$hari_indo][$kelas_key] = [];
    }
    $jam_khusus_detail_per_hari[$hari_indo][$kelas_key][] = [
        'tgl' => $jk['tgl'],
        'alasan' => $jk['alasan'],
        'max_jam' => $jk['max_jam']
    ];
}

// Fungsi untuk mendapatkan detail libur untuk hari dan kelas tertentu
function getLiburDetail($hari, $id_kelas, $libur_detail) {
    $result = [];
    // Libur global
    if (isset($libur_detail[$hari]['all'])) {
        $result = array_merge($result, $libur_detail[$hari]['all']);
    }
    // Libur khusus kelas
    if (isset($libur_detail[$hari][$id_kelas])) {
        $result = array_merge($result, $libur_detail[$hari][$id_kelas]);
    }
    return $result;
}

// Fungsi untuk mendapatkan detail jam khusus untuk hari dan kelas tertentu
function getJamKhususDetail($hari, $id_kelas, $jk_detail) {
    $result = [];
    // Jam khusus global
    if (isset($jk_detail[$hari]['all'])) {
        $result = array_merge($result, $jk_detail[$hari]['all']);
    }
    // Jam khusus kelas
    if (isset($jk_detail[$hari][$id_kelas])) {
        $result = array_merge($result, $jk_detail[$hari][$id_kelas]);
    }
    return $result;
}

// 4. Rekap per mapel-kelas
$stmt_rekap = $pdo->prepare("
    SELECT 
        m.id as id_mengajar,
        m.id_kelas,
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
    GROUP BY m.id, m.id_kelas, mp.nama_mapel, k.nama_kelas, m.jumlah_jam_mingguan, m.hari
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

// Ambil daftar ID kelas yang diajar guru ini
$kelas_guru_ids = array_unique(array_column($daftar_beban, 'id'));
$kelas_mengajar_map = [];
foreach ($daftar_beban as $beban) {
    if (!empty($beban['id'])) {
        // Ambil id_kelas dari rekap_data
        foreach ($rekap_data as $rk) {
            $kelas_mengajar_map[$rk['id_kelas']] = $rk['nama_kelas'];
        }
    }
}

// Ambil daftar hari libur dalam bulan ini (hanya yang relevan untuk guru ini)
// Global (id_kelas IS NULL) ATAU kelas yang diajar guru ini
$kelas_ids_str = !empty($kelas_mengajar_map) ? implode(',', array_keys($kelas_mengajar_map)) : '0';
$stmt_list_libur = $pdo->prepare("
    SELECT h.tanggal, h.nama_libur, h.jenis, h.id_kelas, 
           DAYNAME(h.tanggal) as hari_en,
           k.nama_kelas
    FROM tbl_hari_libur h
    LEFT JOIN tbl_kelas k ON h.id_kelas = k.id
    WHERE h.tanggal BETWEEN ? AND ?
      AND (h.id_kelas IS NULL OR h.id_kelas IN ($kelas_ids_str))
    ORDER BY h.tanggal ASC
");
$stmt_list_libur->execute([$tanggal_mulai, $tanggal_selesai]);
$daftar_libur_bulan = $stmt_list_libur->fetchAll();

// Ambil daftar jam khusus dalam bulan ini (hanya yang relevan untuk guru ini)
$stmt_list_jam_khusus = $pdo->prepare("
    SELECT jk.tanggal, jk.max_jam, jk.alasan, jk.id_kelas,
           DAYNAME(jk.tanggal) as hari_en,
           k.nama_kelas
    FROM tbl_jam_khusus jk
    LEFT JOIN tbl_kelas k ON jk.id_kelas = k.id
    WHERE jk.tanggal BETWEEN ? AND ?
      AND (jk.id_kelas IS NULL OR jk.id_kelas IN ($kelas_ids_str))
    ORDER BY jk.tanggal ASC
");
$stmt_list_jam_khusus->execute([$tanggal_mulai, $tanggal_selesai]);
$daftar_jam_khusus_bulan = $stmt_list_jam_khusus->fetchAll();

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

            <?php if (count($daftar_libur_bulan) > 0 || count($daftar_jam_khusus_bulan) > 0): ?>
            <div class="row mb-4">
                <?php if (count($daftar_libur_bulan) > 0): ?>
                <div class="col-md-6">
                    <div class="alert alert-warning border-left-warning mb-0">
                        <h6 class="fw-bold mb-2"><i class="fas fa-calendar-times me-2"></i>Hari Libur Bulan Ini (<?= count($daftar_libur_bulan) ?>)</h6>
                        <ul class="mb-0 small">
                            <?php foreach ($daftar_libur_bulan as $libur): 
                                $hari_indo = $hari_mapping[$libur['hari_en']] ?? $libur['hari_en'];
                                $badge_jenis = match($libur['jenis']) {
                                    'nasional' => 'bg-danger',
                                    'cuti_bersama' => 'bg-info',
                                    default => 'bg-secondary'
                                };
                            ?>
                                <li>
                                    <strong><?= date('d', strtotime($libur['tanggal'])) ?></strong> (<?= $hari_indo ?>) - 
                                    <?= htmlspecialchars($libur['nama_libur']) ?>
                                    <span class="badge <?= $badge_jenis ?>"><?= ucfirst($libur['jenis']) ?></span>
                                    <?php if ($libur['id_kelas']): ?>
                                        <span class="badge bg-dark"><?= htmlspecialchars($libur['nama_kelas']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Semua Kelas</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (count($daftar_jam_khusus_bulan) > 0): ?>
                <div class="col-md-6">
                    <div class="alert alert-info border-left-info mb-0">
                        <h6 class="fw-bold mb-2"><i class="fas fa-clock me-2"></i>Jam Khusus Bulan Ini (<?= count($daftar_jam_khusus_bulan) ?>)</h6>
                        <ul class="mb-0 small">
                            <?php foreach ($daftar_jam_khusus_bulan as $jk): 
                                $hari_indo = $hari_mapping[$jk['hari_en']] ?? $jk['hari_en'];
                            ?>
                                <li>
                                    <strong><?= date('d', strtotime($jk['tanggal'])) ?></strong> (<?= $hari_indo ?>) - 
                                    <?= htmlspecialchars($jk['alasan']) ?>
                                    <span class="badge bg-primary">Max <?= $jk['max_jam'] ?> jam</span>
                                    <?php if ($jk['id_kelas']): ?>
                                        <span class="badge bg-dark"><?= htmlspecialchars($jk['nama_kelas']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Semua Kelas</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <a href="export_beban_mengajar_csv.php?bulan=<?php echo $filter_bulan; ?>&tahun=<?php echo $filter_tahun; ?>" class="btn btn-success">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
                <a href="export_beban_mengajar_pdf.php?bulan=<?php echo $filter_bulan; ?>&tahun=<?php echo $filter_tahun; ?>" class="btn btn-danger ms-2">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Hari</th>
                            <th class="text-center">Roster/Mg</th>
                            <th class="text-center">Libur/Jam Khusus</th>
                            <th class="text-center">Target Bulan</th>
                            <th class="text-center">Terlaksana</th>
                            <th class="text-center">Selisih</th>
                            <th class="text-center">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rekap_data) > 0): ?>
                            <?php 
                            $no = 1;
                            $total_roster = 0;
                            $total_target = 0;
                            $total_target_awal = 0;
                            $total_pengurangan = 0;
                            $total_terlaksana = 0;
                            $total_pertemuan = 0;
                            ?>
                            <?php foreach ($rekap_data as $row): ?>
                                <?php 
                                // Hitung jumlah libur untuk hari dan kelas ini
                                $jumlah_libur = getJumlahLibur($row['hari'], $row['id_kelas'], $libur_per_hari_kelas);
                                
                                // Ambil detail libur dan jam khusus untuk hari dan kelas ini
                                $detail_libur = getLiburDetail($row['hari'], $row['id_kelas'], $libur_detail_per_hari);
                                $detail_jk = getJamKhususDetail($row['hari'], $row['id_kelas'], $jam_khusus_detail_per_hari);
                                
                                // Target = (roster × minggu) - (roster × jumlah_libur)
                                // karena setiap libur mengurangi 1 pertemuan
                                $target_awal = $row['roster_mingguan'] * $minggu;
                                $pengurangan = $row['roster_mingguan'] * $jumlah_libur;
                                $target = max(0, $target_awal - $pengurangan);
                                
                                $selisih = $row['jam_terlaksana'] - $target;
                                $persen = $target > 0 ? round(($row['jam_terlaksana'] / $target) * 100, 0) : 0;
                                
                                $total_roster += $row['roster_mingguan'];
                                $total_target += $target;
                                $total_target_awal += $target_awal;
                                $total_pengurangan += $pengurangan;
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
                                    <td class="text-center" style="font-size: 0.75rem;">
                                        <?php if (count($detail_libur) > 0 || count($detail_jk) > 0): ?>
                                            <?php foreach ($detail_libur as $lib): ?>
                                                <span class="badge bg-danger mb-1" title="<?= htmlspecialchars($lib['nama']) ?>">
                                                    <i class="fas fa-calendar-times"></i> Tgl <?= $lib['tgl'] ?>
                                                </span><br>
                                            <?php endforeach; ?>
                                            <?php foreach ($detail_jk as $jk): ?>
                                                <span class="badge bg-info mb-1" title="<?= htmlspecialchars($jk['alasan']) ?>">
                                                    <i class="fas fa-clock"></i> Tgl <?= $jk['tgl'] ?> (Max <?= $jk['max_jam'] ?>)
                                                </span><br>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold"><?= $target ?></span>
                                        <?php if ($jumlah_libur > 0): ?>
                                            <br>
                                            <small class="text-muted" style="font-size: 0.7rem;">
                                                <?= $target_awal ?> − <?= $pengurangan ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold"><?= $row['jam_terlaksana'] ?></td>
                                    <td class="text-center fw-bold <?= $selisih_class ?>"><?= ($selisih >= 0 ? '+' : '') . $selisih ?></td>
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
                                <td colspan="5" class="text-end">TOTAL:</td>
                                <td class="text-center">-</td>
                                <td class="text-center">
                                    <?= $total_target ?>
                                    <?php if ($total_pengurangan > 0): ?>
                                        <br>
                                        <small class="text-muted fw-normal" style="font-size: 0.7rem;">
                                            <?= $total_target_awal ?> − <?= $total_pengurangan ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= $total_terlaksana ?></td>
                                <td class="text-center <?= $total_selisih >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($total_selisih >= 0 ? '+' : '') . $total_selisih ?></td>
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
                    <span class="badge bg-danger"><i class="fas fa-calendar-times"></i></span> Hari Libur |
                    <span class="badge bg-info"><i class="fas fa-clock"></i></span> Jam Khusus
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