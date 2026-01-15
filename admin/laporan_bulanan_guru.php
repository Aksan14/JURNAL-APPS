<?php
/*
File: laporan_bulanan_guru.php (UPDATED with Detailed Rekap)
Lokasi: /jurnal_app/admin/laporan_bulanan_guru.php
*/

// 1. Panggil
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
checkRole(['admin']); // Hanya admin

// ==========================================================
// FUNGSI HELPER (Sama)
// ==========================================================
function calculateHours($jam_ke_str) {
    $jam_ke_str = trim($jam_ke_str);
    if (strpos($jam_ke_str, '-') !== false) {
        $parts = explode('-', $jam_ke_str);
        if (count($parts) == 2) { $start = (int)trim($parts[0]); $end = (int)trim($parts[1]); if ($end >= $start) { return ($end - $start) + 1; } }
    }
    if (is_numeric($jam_ke_str) && (int)$jam_ke_str > 0) return 1;
    if (strpos($jam_ke_str, ',') !== false) return count(explode(',', $jam_ke_str));
    return 0;
}
// ==========================================================

// 2. Logika Filter Bulan & Tahun
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$tanggal_mulai = date('Y-m-01', strtotime("$filter_tahun-$filter_bulan-01"));
$tanggal_selesai = date('Y-m-t', strtotime("$filter_tahun-$filter_bulan-01"));

// Hitung jumlah minggu dalam bulan (samakan dengan walikelas)
$start_date = new DateTime($tanggal_mulai);
$end_date = new DateTime($tanggal_selesai);
$total_minggu_penuh = max(1, floor($start_date->diff($end_date)->days / 7));

// Hitung jumlah hari libur per hari (Senin, Selasa, dst) dalam bulan ini
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
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
];

$libur_per_hari_kelas = [];
while ($row_libur = $stmt_libur->fetch()) {
    $hari_id = $hari_mapping[$row_libur['nama_hari_en']] ?? $row_libur['nama_hari_en'];
    $kelas_id = $row_libur['id_kelas'] ?? 'all';
    if (!isset($libur_per_hari_kelas[$hari_id])) {
        $libur_per_hari_kelas[$hari_id] = [];
    }
    $libur_per_hari_kelas[$hari_id][$kelas_id] = $row_libur['jumlah_libur'];
}

// Fungsi untuk mendapatkan jumlah libur berdasarkan hari dan kelas
function getJumlahLiburAdmin($hari, $id_kelas, $libur_data) {
    $libur_semua = $libur_data[$hari]['all'] ?? 0;
    $libur_kelas = $libur_data[$hari][$id_kelas] ?? 0;
    return $libur_semua + $libur_kelas;
}

// Ambil detail libur per hari dan kelas untuk ditampilkan di kolom
$libur_detail_per_hari_admin = [];
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
    if (!isset($libur_detail_per_hari_admin[$hari_indo])) {
        $libur_detail_per_hari_admin[$hari_indo] = [];
    }
    if (!isset($libur_detail_per_hari_admin[$hari_indo][$kelas_key])) {
        $libur_detail_per_hari_admin[$hari_indo][$kelas_key] = [];
    }
    $libur_detail_per_hari_admin[$hari_indo][$kelas_key][] = [
        'tgl' => $ld['tgl'],
        'nama' => $ld['nama_libur']
    ];
}

// Ambil detail jam khusus per hari dan kelas
$jam_khusus_detail_per_hari_admin = [];
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
    if (!isset($jam_khusus_detail_per_hari_admin[$hari_indo])) {
        $jam_khusus_detail_per_hari_admin[$hari_indo] = [];
    }
    if (!isset($jam_khusus_detail_per_hari_admin[$hari_indo][$kelas_key])) {
        $jam_khusus_detail_per_hari_admin[$hari_indo][$kelas_key] = [];
    }
    $jam_khusus_detail_per_hari_admin[$hari_indo][$kelas_key][] = [
        'tgl' => $jk['tgl'],
        'alasan' => $jk['alasan'],
        'max_jam' => $jk['max_jam']
    ];
}

// Fungsi untuk mendapatkan detail libur untuk hari dan kelas tertentu
function getLiburDetailAdmin($hari, $id_kelas, $libur_detail) {
    $result = [];
    if (isset($libur_detail[$hari]['all'])) {
        $result = array_merge($result, $libur_detail[$hari]['all']);
    }
    if (isset($libur_detail[$hari][$id_kelas])) {
        $result = array_merge($result, $libur_detail[$hari][$id_kelas]);
    }
    return $result;
}

// Fungsi untuk mendapatkan detail jam khusus untuk hari dan kelas tertentu
function getJamKhususDetailAdmin($hari, $id_kelas, $jk_detail) {
    $result = [];
    if (isset($jk_detail[$hari]['all'])) {
        $result = array_merge($result, $jk_detail[$hari]['all']);
    }
    if (isset($jk_detail[$hari][$id_kelas])) {
        $result = array_merge($result, $jk_detail[$hari][$id_kelas]);
    }
    return $result;
}

// 3. Ambil SEMUA jadwal mengajar beserta jam terlaksana (menggunakan query SQL langsung seperti di walikelas)
$sql_roster = "
    SELECT 
        g.id AS id_guru,
        g.nama_guru,
        m.id AS id_mengajar,
        m.id_kelas,
        m.hari,
        mp.nama_mapel,
        k.nama_kelas,
        m.jumlah_jam_mingguan AS jam_roster_mingguan,
        COALESCE(SUM(
            CASE 
                WHEN j.jam_ke LIKE '%-%' THEN 
                    CAST(SUBSTRING_INDEX(j.jam_ke, '-', -1) AS UNSIGNED) - 
                    CAST(SUBSTRING_INDEX(j.jam_ke, '-', 1) AS UNSIGNED) + 1
                ELSE 
                    CASE WHEN j.jam_ke IS NOT NULL AND j.jam_ke != '' THEN 1 ELSE 0 END
            END
        ), 0) as jam_terlaksana
    FROM tbl_mengajar m
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    LEFT JOIN tbl_jurnal j ON j.id_mengajar = m.id AND j.tanggal BETWEEN ? AND ?
    GROUP BY g.id, g.nama_guru, m.id, m.id_kelas, m.hari, mp.nama_mapel, k.nama_kelas, m.jumlah_jam_mingguan
    ORDER BY g.nama_guru ASC, mp.nama_mapel ASC, k.nama_kelas ASC
";
$stmt_roster = $pdo->prepare($sql_roster);
$stmt_roster->execute([$tanggal_mulai, $tanggal_selesai]);
$semua_roster = $stmt_roster->fetchAll();

// 5. Proses dan kelompokkan data per guru
$hasil_rekap_detail = [];

foreach ($semua_roster as $roster) {
    $id_guru = $roster['id_guru'];
    $nama_guru = $roster['nama_guru'];
    $id_mengajar = $roster['id_mengajar'];
    $mapel_kelas = $roster['nama_mapel'] . ' - ' . $roster['nama_kelas'];
    // Key unik dengan menyertakan hari untuk menghindari data tertimpa
    $assignment_key = $mapel_kelas . ' (' . $roster['hari'] . ')';
    $jam_roster = (int)$roster['jam_roster_mingguan'];
    $jam_terlaksana = (int)$roster['jam_terlaksana'];
    
    // Hitung pengurangan libur
    $jumlah_libur = getJumlahLiburAdmin($roster['hari'], $roster['id_kelas'], $libur_per_hari_kelas);
    $target_awal = $jam_roster * $total_minggu_penuh;
    $pengurangan = $jam_roster * $jumlah_libur;
    $jam_seharusnya = max(0, $target_awal - $pengurangan);
    
    // Ambil detail libur dan jam khusus
    $detail_libur = getLiburDetailAdmin($roster['hari'], $roster['id_kelas'], $libur_detail_per_hari_admin);
    $detail_jk = getJamKhususDetailAdmin($roster['hari'], $roster['id_kelas'], $jam_khusus_detail_per_hari_admin);

    // Inisialisasi data guru jika belum ada
    if (!isset($hasil_rekap_detail[$id_guru])) {
        $hasil_rekap_detail[$id_guru] = [
            'nama_guru' => $nama_guru,
            'assignments' => [],
            'total_jam_terlaksana_guru' => 0,
            'total_jam_roster_mingguan_guru' => 0,
            'total_jam_seharusnya_guru' => 0,
            'total_target_awal_guru' => 0,
            'total_pengurangan_guru' => 0
        ];
    }

    // Tambah assignment dengan key unik
    $hasil_rekap_detail[$id_guru]['assignments'][$assignment_key] = [
        'nama_mapel_kelas' => $mapel_kelas,
        'jam_roster_mingguan' => $jam_roster,
        'jam_terlaksana_bulanan' => $jam_terlaksana,
        'jam_seharusnya' => $jam_seharusnya,
        'target_awal' => $target_awal,
        'pengurangan' => $pengurangan,
        'jumlah_libur' => $jumlah_libur,
        'hari' => $roster['hari'],
        'id_kelas' => $roster['id_kelas'],
        'detail_libur' => $detail_libur,
        'detail_jk' => $detail_jk
    ];

    // Akumulasi total per guru
    $hasil_rekap_detail[$id_guru]['total_jam_roster_mingguan_guru'] += $jam_roster;
    $hasil_rekap_detail[$id_guru]['total_jam_terlaksana_guru'] += $jam_terlaksana;
    $hasil_rekap_detail[$id_guru]['total_jam_seharusnya_guru'] += $jam_seharusnya;
    $hasil_rekap_detail[$id_guru]['total_target_awal_guru'] += $target_awal;
    $hasil_rekap_detail[$id_guru]['total_pengurangan_guru'] += $pengurangan;
}

// 6. Ambil guru yang tidak punya jadwal mengajar (opsional)
$semua_guru = $pdo->query("SELECT id, nama_guru FROM tbl_guru ORDER BY nama_guru ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($semua_guru as $guru) {
    if (!isset($hasil_rekap_detail[$guru['id']])) {
        $hasil_rekap_detail[$guru['id']] = [
            'nama_guru' => $guru['nama_guru'],
            'assignments' => [],
            'total_jam_terlaksana_guru' => 0,
            'total_jam_roster_mingguan_guru' => 0,
            'total_jam_seharusnya_guru' => 0
        ];
    }
}

// Urutkan berdasarkan nama guru
uasort($hasil_rekap_detail, function($a, $b) {
    return strcmp($a['nama_guru'], $b['nama_guru']);
});

// Ambil daftar hari libur dalam bulan ini (untuk ditampilkan sebagai keterangan) - Admin melihat semua
$stmt_list_libur = $pdo->prepare("
    SELECT h.tanggal, h.nama_libur, h.jenis, h.id_kelas, 
           DAYNAME(h.tanggal) as hari_en,
           k.nama_kelas
    FROM tbl_hari_libur h
    LEFT JOIN tbl_kelas k ON h.id_kelas = k.id
    WHERE h.tanggal BETWEEN ? AND ?
    ORDER BY h.tanggal ASC
");
$stmt_list_libur->execute([$tanggal_mulai, $tanggal_selesai]);
$daftar_libur_bulan = $stmt_list_libur->fetchAll();

// Ambil daftar jam khusus dalam bulan ini - Admin melihat semua
$stmt_list_jam_khusus = $pdo->prepare("
    SELECT jk.tanggal, jk.max_jam, jk.alasan, jk.id_kelas,
           DAYNAME(jk.tanggal) as hari_en,
           k.nama_kelas
    FROM tbl_jam_khusus jk
    LEFT JOIN tbl_kelas k ON jk.id_kelas = k.id
    WHERE jk.tanggal BETWEEN ? AND ?
    ORDER BY jk.tanggal ASC
");
$stmt_list_jam_khusus->execute([$tanggal_mulai, $tanggal_selesai]);
$daftar_jam_khusus_bulan = $stmt_list_jam_khusus->fetchAll();

// Daftar bulan untuk dropdown
$daftar_bulan = [ '01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun','07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des' ];

?>

<div class="card">
    <div class="card-header">
        <h3>Rekap Jurnal Bulanan Per Guru (Detail)</h3>
    </div>
    <div class="card-body">

        <div class="card mb-4" style="background-color: #f8f9fa;">
            <div class="card-header">Filter Laporan</div>
            <div class="card-body">
                <form action="laporan_bulanan_guru.php" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="bulan" class="form-label">Bulan</label>
                        <select name="bulan" id="bulan" class="form-select">
                            <?php foreach ($daftar_bulan as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($filter_bulan == $num) ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="tahun" class="form-label">Tahun</label>
                        <input type="number" class="form-control" name="tahun" id="tahun"
                               value="<?php echo htmlspecialchars($filter_tahun); ?>"
                               min="2020" max="<?php echo date('Y') + 1; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Tampilkan Rekap</button>
                    </div>
                </form>
            </div>
        </div>

        <h4 class="mb-3">
            Hasil Rekap Bulan: <?php echo $daftar_bulan[$filter_bulan] . ' ' . $filter_tahun; ?>
            <small class="text-muted">(<?= $total_minggu_penuh ?> minggu)</small>
        </h4>

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
            <a href="export_rekap_bulanan_csv.php?bulan=<?php echo $filter_bulan; ?>&tahun=<?php echo $filter_tahun; ?>" class="btn btn-success">
                <i class="fas fa-file-csv"></i> Export CSV (Ringkas)
            </a>
            <a href="export_rekap_bulanan_pdf.php?bulan=<?php echo $filter_bulan; ?>&tahun=<?php echo $filter_tahun; ?>" class="btn btn-danger ms-2">
                <i class="fas fa-file-pdf"></i> Export PDF (Ringkas)
            </a>
            <a href="export_pdf_bulanan_guru_detail.php?bulan=<?php echo $filter_bulan; ?>&tahun=<?php echo $filter_tahun; ?>" class="btn btn-outline-danger ms-2">
                <i class="fas fa-file-pdf"></i> Export PDF (Detail)
            </a>
            <a href="export_excel_bulanan_guru_detail.php?bulan=<?php echo $filter_bulan; ?>&tahun=<?php echo $filter_tahun; ?>" class="btn btn-outline-success ms-2">
                <i class="fas fa-file-excel"></i> Export CSV (Detail)
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark text-center">
                    <tr>
                        <th rowspan="2" class="align-middle" style="width: 5%;">No</th>
                        <th rowspan="2" class="align-middle" style="width: 15%;">Nama Guru</th>
                        <th rowspan="2" class="align-middle" style="width: 20%;">Mapel - Kelas</th>
                        <th colspan="2" class="text-center">Jam Roster</th>
                        <th rowspan="2" class="align-middle">Libur/Jam Khusus</th>
                        <th colspan="2" class="text-center">Jam Bulan Ini</th>
                        <th rowspan="2" class="align-middle" style="width: 12%;">Total Rekap Guru</th>
                    </tr>
                    <tr>
                        <th class="text-center">Per Minggu</th>
                        <th class="text-center">Seharusnya</th>
                        <th class="text-center">Terlaksana</th>
                        <th class="text-center">Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($hasil_rekap_detail)): ?>
                        <tr><td colspan="9" class="text-center">Tidak ada data guru.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($hasil_rekap_detail as $id_guru => $data_guru): ?>
                            <?php 
                                $assignments = $data_guru['assignments'];
                                $rowspan = count($assignments) > 0 ? count($assignments) : 1;
                                $total_selisih = $data_guru['total_jam_terlaksana_guru'] - $data_guru['total_jam_seharusnya_guru'];
                                $total_selisih_text = ($total_selisih > 0 ? '+' : '') . $total_selisih;
                                $total_selisih_color = $total_selisih >= 0 ? 'text-success' : 'text-danger';
                            ?>
                            
                            <?php if (empty($assignments)): ?>
                                <!-- Guru tanpa jadwal -->
                                <tr>
                                    <td class="text-center align-middle"><?php echo $no++; ?></td>
                                    <td class="align-middle"><?php echo htmlspecialchars($data_guru['nama_guru']); ?></td>
                                    <td colspan="6" class="text-center text-muted"><em>Tidak ada jadwal/jurnal</em></td>
                                    <td class="text-center align-middle">Roster/Mg: 0</td>
                                </tr>
                            <?php else: ?>
                                <!-- Guru dengan jadwal -->
                                <?php $row_idx = 0; foreach ($assignments as $mapel_kelas => $assignment): ?>
                                    <?php 
                                        $selisih = $assignment['jam_terlaksana_bulanan'] - $assignment['jam_seharusnya'];
                                        $selisih_text = ($selisih > 0 ? '+' : '') . $selisih;
                                        $selisih_color = $selisih >= 0 ? 'text-success' : 'text-danger';
                                        $detail_libur = $assignment['detail_libur'] ?? [];
                                        $detail_jk = $assignment['detail_jk'] ?? [];
                                    ?>
                                    <tr>
                                        <?php if ($row_idx == 0): ?>
                                            <td rowspan="<?php echo $rowspan; ?>" class="text-center align-middle"><?php echo $no++; ?></td>
                                            <td rowspan="<?php echo $rowspan; ?>" class="align-middle"><?php echo htmlspecialchars($data_guru['nama_guru']); ?></td>
                                        <?php endif; ?>
                                        
                                        <td>
                                            <?php echo htmlspecialchars($assignment['nama_mapel_kelas']); ?>
                                            <br><small class="text-muted">Hari: <?= $assignment['hari'] ?? '-' ?></small>
                                        </td>
                                        <td class="text-center"><?php echo $assignment['jam_roster_mingguan']; ?></td>
                                        <td class="text-center">
                                            <span class="fw-bold"><?php echo $assignment['jam_seharusnya']; ?></span>
                                            <?php if ($assignment['jumlah_libur'] > 0): ?>
                                                <br>
                                                <small class="text-muted" style="font-size: 0.7rem;">
                                                    <?= $assignment['target_awal'] ?> − <?= $assignment['pengurangan'] ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
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
                                        <td class="text-center"><?php echo $assignment['jam_terlaksana_bulanan']; ?></td>
                                        <td class="text-center fw-bold <?php echo $selisih_color; ?>"><?php echo $selisih_text; ?></td>
                                        
                                        <?php if ($row_idx == 0): ?>
                                            <td rowspan="<?php echo $rowspan; ?>" class="align-middle small">
                                                <strong>Roster/Mg:</strong> <?php echo $data_guru['total_jam_roster_mingguan_guru']; ?><br>
                                                <strong>Seharusnya:</strong> 
                                                <?php if ($data_guru['total_pengurangan_guru'] > 0): ?>
                                                    <?= $data_guru['total_jam_seharusnya_guru'] ?>
                                                    <span class="text-muted">(<?= $data_guru['total_target_awal_guru'] ?> − <?= $data_guru['total_pengurangan_guru'] ?>)</span>
                                                <?php else: ?>
                                                    <?php echo $data_guru['total_jam_seharusnya_guru']; ?>
                                                <?php endif; ?>
                                                <br>
                                                <strong>Terlaksana:</strong> <?php echo $data_guru['total_jam_terlaksana_guru']; ?><br>
                                                <strong class="<?php echo $total_selisih_color; ?>">Selisih: <?php echo $total_selisih_text; ?></strong>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php $row_idx++; endforeach; ?>
                            <?php endif; ?>
                            
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div> </div> <?php
// 5. Panggil footer
require_once '../includes/footer.php';
?>