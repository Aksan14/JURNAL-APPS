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

// Hitung jumlah minggu dalam bulan
$start_date = new DateTime($tanggal_mulai);
$end_date = new DateTime($tanggal_selesai);
$diff = $start_date->diff($end_date);
$total_hari_filter = $diff->days + 1;
$total_minggu_penuh = floor($total_hari_filter / 7);

// 3. Ambil SEMUA jadwal mengajar (dari tbl_mengajar) beserta info guru, mapel, kelas
$sql_roster = "
    SELECT 
        g.id AS id_guru,
        g.nama_guru,
        m.id AS id_mengajar,
        mp.nama_mapel,
        k.nama_kelas,
        m.jumlah_jam_mingguan AS jam_roster_mingguan
    FROM tbl_mengajar m
    JOIN tbl_guru g ON m.id_guru = g.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    ORDER BY g.nama_guru ASC, mp.nama_mapel ASC, k.nama_kelas ASC
";
$semua_roster = $pdo->query($sql_roster)->fetchAll();

// 4. Ambil data jurnal yang sudah diisi di bulan ini
$sql_jurnal = "
    SELECT 
        j.id_mengajar,
        j.jam_ke
    FROM tbl_jurnal j
    WHERE j.tanggal BETWEEN ? AND ?
";
$stmt_jurnal = $pdo->prepare($sql_jurnal);
$stmt_jurnal->execute([$tanggal_mulai, $tanggal_selesai]);
$data_jurnal = $stmt_jurnal->fetchAll();

// Hitung total jam terlaksana per id_mengajar
$jam_terlaksana_per_mengajar = [];
foreach ($data_jurnal as $jurnal) {
    $id_mengajar = $jurnal['id_mengajar'];
    $jam = calculateHours($jurnal['jam_ke']);
    if (!isset($jam_terlaksana_per_mengajar[$id_mengajar])) {
        $jam_terlaksana_per_mengajar[$id_mengajar] = 0;
    }
    $jam_terlaksana_per_mengajar[$id_mengajar] += $jam;
}

// 5. Proses dan kelompokkan data per guru
$hasil_rekap_detail = [];

foreach ($semua_roster as $roster) {
    $id_guru = $roster['id_guru'];
    $nama_guru = $roster['nama_guru'];
    $id_mengajar = $roster['id_mengajar'];
    $mapel_kelas = $roster['nama_mapel'] . ' - ' . $roster['nama_kelas'];
    $jam_roster = (int)$roster['jam_roster_mingguan'];
    $jam_terlaksana = $jam_terlaksana_per_mengajar[$id_mengajar] ?? 0;

    // Inisialisasi data guru jika belum ada
    if (!isset($hasil_rekap_detail[$id_guru])) {
        $hasil_rekap_detail[$id_guru] = [
            'nama_guru' => $nama_guru,
            'assignments' => [],
            'total_jam_terlaksana_guru' => 0,
            'total_jam_roster_mingguan_guru' => 0,
            'total_jam_seharusnya_guru' => 0
        ];
    }

    // Tambah assignment
    $hasil_rekap_detail[$id_guru]['assignments'][$mapel_kelas] = [
        'nama_mapel_kelas' => $mapel_kelas,
        'jam_roster_mingguan' => $jam_roster,
        'jam_terlaksana_bulanan' => $jam_terlaksana
    ];

    // Akumulasi total per guru
    $hasil_rekap_detail[$id_guru]['total_jam_roster_mingguan_guru'] += $jam_roster;
    $hasil_rekap_detail[$id_guru]['total_jam_terlaksana_guru'] += $jam_terlaksana;
}

// Hitung jam seharusnya per guru
foreach ($hasil_rekap_detail as $id_guru => &$data_guru) {
    $data_guru['total_jam_seharusnya_guru'] = $data_guru['total_jam_roster_mingguan_guru'] * $total_minggu_penuh;
}
unset($data_guru);

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
        </h4>

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
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark text-center">
                    <tr>
                        <th rowspan="2" class="align-middle" style="width: 5%;">No</th>
                        <th rowspan="2" class="align-middle" style="width: 20%;">Nama Guru</th>
                        <th rowspan="2" class="align-middle" style="width: 25%;">Mapel - Kelas</th>
                        <th colspan="2" class="text-center">Jam Roster</th>
                        <th colspan="2" class="text-center">Jam Bulan Ini</th>
                        <th rowspan="2" class="align-middle" style="width: 15%;">Total Rekap Guru</th>
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
                        <tr><td colspan="8" class="text-center">Tidak ada data guru.</td></tr>
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
                                    <td colspan="5" class="text-center text-muted"><em>Tidak ada jadwal/jurnal</em></td>
                                    <td class="text-center align-middle">Roster/Mg: 0</td>
                                </tr>
                            <?php else: ?>
                                <!-- Guru dengan jadwal -->
                                <?php $row_idx = 0; foreach ($assignments as $mapel_kelas => $assignment): ?>
                                    <?php 
                                        $jam_seharusnya_assignment = $assignment['jam_roster_mingguan'] * $total_minggu_penuh;
                                        $selisih = $assignment['jam_terlaksana_bulanan'] - $jam_seharusnya_assignment;
                                        $selisih_text = ($selisih > 0 ? '+' : '') . $selisih;
                                        $selisih_color = $selisih >= 0 ? 'text-success' : 'text-danger';
                                    ?>
                                    <tr>
                                        <?php if ($row_idx == 0): ?>
                                            <td rowspan="<?php echo $rowspan; ?>" class="text-center align-middle"><?php echo $no++; ?></td>
                                            <td rowspan="<?php echo $rowspan; ?>" class="align-middle"><?php echo htmlspecialchars($data_guru['nama_guru']); ?></td>
                                        <?php endif; ?>
                                        
                                        <td><?php echo htmlspecialchars($assignment['nama_mapel_kelas']); ?></td>
                                        <td class="text-center"><?php echo $assignment['jam_roster_mingguan']; ?></td>
                                        <td class="text-center"><?php echo $jam_seharusnya_assignment; ?></td>
                                        <td class="text-center"><?php echo $assignment['jam_terlaksana_bulanan']; ?></td>
                                        <td class="text-center fw-bold <?php echo $selisih_color; ?>"><?php echo $selisih_text; ?></td>
                                        
                                        <?php if ($row_idx == 0): ?>
                                            <td rowspan="<?php echo $rowspan; ?>" class="align-middle small">
                                                <strong>Roster/Mg:</strong> <?php echo $data_guru['total_jam_roster_mingguan_guru']; ?><br>
                                                <strong>Seharusnya:</strong> <?php echo $data_guru['total_jam_seharusnya_guru']; ?><br>
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