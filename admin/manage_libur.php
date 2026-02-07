<?php
/*
File: admin/manage_libur.php
Deskripsi: Halaman kelola hari libur dan jam khusus
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['admin']);

$message = '';
$messageType = '';

// --- LOGIKA TAMBAH HARI LIBUR ---
if (isset($_POST['add_libur'])) {
    $tanggal = $_POST['tanggal'];
    $nama_libur = trim($_POST['nama_libur']);
    $jenis = $_POST['jenis'];
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (!empty($tanggal) && !empty($nama_libur)) {
        try {
            $id_kelas_libur = !empty($_POST['id_kelas']) ? $_POST['id_kelas'] : null;
            $stmt = $pdo->prepare("INSERT INTO tbl_hari_libur (tanggal, nama_libur, jenis, id_kelas, keterangan) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$tanggal, $nama_libur, $jenis, $id_kelas_libur, $keterangan ?: null]);
            $message = "Hari libur berhasil ditambahkan!";
            $messageType = 'success';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "Tanggal tersebut sudah ada di daftar hari libur untuk kelas yang sama!";
            } else {
                $message = "Error: " . $e->getMessage();
            }
            $messageType = 'danger';
        }
    }
}

// --- LOGIKA TAMBAH JAM KHUSUS ---
if (isset($_POST['add_jam_khusus'])) {
    $tanggal = $_POST['tanggal'];
    $max_jam = (int)$_POST['max_jam'];
    $alasan = trim($_POST['alasan']);
    $id_kelas = !empty($_POST['id_kelas']) ? $_POST['id_kelas'] : null;
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (!empty($tanggal) && !empty($alasan) && $max_jam > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tbl_jam_khusus (tanggal, max_jam, alasan, id_kelas, keterangan) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$tanggal, $max_jam, $alasan, $id_kelas, $keterangan ?: null]);
            $message = "Jam khusus berhasil ditambahkan!";
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// --- LOGIKA HAPUS HARI LIBUR ---
if (isset($_GET['delete_libur'])) {
    $id = $_GET['delete_libur'];
    $stmt = $pdo->prepare("DELETE FROM tbl_hari_libur WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_libur.php?status=deleted");
    exit;
}

// --- LOGIKA HAPUS JAM KHUSUS ---
if (isset($_GET['delete_jam'])) {
    $id = $_GET['delete_jam'];
    $stmt = $pdo->prepare("DELETE FROM tbl_jam_khusus WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_libur.php?tab=jam&status=deleted_jam");
    exit;
}

// --- TANGKAP STATUS DARI URL ---
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deleted') {
        $message = "Hari libur berhasil dihapus!";
        $messageType = 'warning';
    } elseif ($_GET['status'] == 'deleted_jam') {
        $message = "Jam khusus berhasil dihapus!";
        $messageType = 'warning';
    }
}

// --- AMBIL DATA ---
$bulan_filter = $_GET['bulan'] ?? date('Y-m');
$tahun = substr($bulan_filter, 0, 4);
$bulan = substr($bulan_filter, 5, 2);

// Daftar hari libur
$stmt = $pdo->prepare("
    SELECT hl.*, k.nama_kelas 
    FROM tbl_hari_libur hl 
    LEFT JOIN tbl_kelas k ON hl.id_kelas = k.id 
    WHERE YEAR(hl.tanggal) = ? AND MONTH(hl.tanggal) = ? 
    ORDER BY hl.tanggal ASC, hl.id_kelas IS NULL DESC
");
$stmt->execute([$tahun, $bulan]);
$daftar_libur = $stmt->fetchAll();

// Daftar jam khusus
$stmt = $pdo->prepare("
    SELECT jk.*, k.nama_kelas 
    FROM tbl_jam_khusus jk 
    LEFT JOIN tbl_kelas k ON jk.id_kelas = k.id 
    WHERE YEAR(jk.tanggal) = ? AND MONTH(jk.tanggal) = ? 
    ORDER BY jk.tanggal ASC
");
$stmt->execute([$tahun, $bulan]);
$daftar_jam_khusus = $stmt->fetchAll();

// Daftar kelas untuk dropdown
$daftar_kelas = $pdo->query("SELECT id, nama_kelas FROM tbl_kelas ORDER BY nama_kelas")->fetchAll();

// Nama bulan Indonesia
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$active_tab = $_GET['tab'] ?? 'libur';

// Buat data untuk kalender
$libur_map = [];
foreach ($daftar_libur as $l) {
    $day = (int)date('j', strtotime($l['tanggal']));
    if (!isset($libur_map[$day])) {
        $libur_map[$day] = [];
    }
    $libur_map[$day][] = $l;
}

$jam_khusus_map = [];
foreach ($daftar_jam_khusus as $jk) {
    $day = (int)date('j', strtotime($jk['tanggal']));
    if (!isset($jam_khusus_map[$day])) {
        $jam_khusus_map[$day] = [];
    }
    $jam_khusus_map[$day][] = $jk;
}

// Hitung data kalender
$first_day = mktime(0, 0, 0, $bulan, 1, $tahun);
$days_in_month = date('t', $first_day);
$start_day = date('N', $first_day); // 1=Senin, 7=Minggu

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-calendar-alt me-2"></i>Kelola Hari Libur & Jam Khusus</h1>
        <div>
            <button class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#modalTambahLibur">
                <i class="fas fa-plus"></i> Tambah Hari Libur
            </button>
            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahJam">
                <i class="fas fa-plus"></i> Tambah Jam Khusus
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Bulan -->
    <div class="card shadow mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row align-items-center">
                <input type="hidden" name="tab" value="<?= $active_tab ?>">
                <div class="col-auto">
                    <label class="form-label mb-0">Pilih Bulan:</label>
                </div>
                <div class="col-auto">
                    <input type="month" name="bulan" class="form-control form-control-sm" value="<?= $bulan_filter ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
                <div class="col-auto ms-auto">
                    <span class="badge bg-danger me-2"><?= count($daftar_libur) ?> Hari Libur</span>
                    <span class="badge bg-warning text-dark"><?= count($daftar_jam_khusus) ?> Jam Khusus</span>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- KALENDER -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary text-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-calendar me-2"></i><?= $nama_bulan[$bulan] ?> <?= $tahun ?>
                    </h6>
                    <div>
                        <?php 
                        $prev_month = date('Y-m', strtotime($bulan_filter . '-01 -1 month'));
                        $next_month = date('Y-m', strtotime($bulan_filter . '-01 +1 month'));
                        ?>
                        <a href="?bulan=<?= $prev_month ?>&tab=<?= $active_tab ?>" class="btn btn-sm btn-light">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <a href="?bulan=<?= date('Y-m') ?>&tab=<?= $active_tab ?>" class="btn btn-sm btn-light mx-1">Hari Ini</a>
                        <a href="?bulan=<?= $next_month ?>&tab=<?= $active_tab ?>" class="btn btn-sm btn-light">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0 calendar-table">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:14.28%">Sen</th>
                                <th class="text-center" style="width:14.28%">Sel</th>
                                <th class="text-center" style="width:14.28%">Rab</th>
                                <th class="text-center" style="width:14.28%">Kam</th>
                                <th class="text-center" style="width:14.28%">Jum</th>
                                <th class="text-center" style="width:14.28%">Sab</th>
                                <th class="text-center text-danger" style="width:14.28%">Ming</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $day_count = 1;
                            $today_day = (date('Y-m') == $bulan_filter) ? (int)date('j') : 0;
                            
                            // Mulai baris pertama
                            echo '<tr>';
                            
                            // Kosongkan sel sebelum tanggal 1
                            for ($i = 1; $i < $start_day; $i++) {
                                echo '<td class="bg-light"></td>';
                            }
                            
                            // Isi tanggal
                            for ($day = 1; $day <= $days_in_month; $day++) {
                                $current_date = sprintf('%s-%s-%02d', $tahun, $bulan, $day);
                                $day_of_week = date('N', strtotime($current_date)); // 1=Senin, 7=Minggu
                                
                                // Mulai baris baru setelah Minggu
                                if ($day_of_week == 1 && $day > 1) {
                                    echo '</tr><tr>';
                                }
                                
                                $is_today = ($day == $today_day);
                                $is_libur = isset($libur_map[$day]);
                                $is_jam_khusus = isset($jam_khusus_map[$day]);
                                $is_sunday = ($day_of_week == 7);
                                
                                $cell_class = '';
                                $cell_style = '';
                                if ($is_libur) {
                                    $cell_class = 'bg-danger text-white';
                                } elseif ($is_jam_khusus) {
                                    $cell_class = 'bg-warning';
                                } elseif ($is_sunday) {
                                    $cell_class = 'text-danger';
                                }
                                
                                echo '<td class="calendar-cell ' . $cell_class . '" style="height:80px; vertical-align:top; ' . $cell_style . '">';
                                echo '<div class="d-flex justify-content-between align-items-start">';
                                echo '<span class="' . ($is_today ? 'badge bg-primary rounded-circle' : '') . '">' . $day . '</span>';
                                
                                // Tombol hapus jika ada libur/jam khusus
                                if ($is_libur || $is_jam_khusus) {
                                    echo '<div class="dropdown">';
                                    echo '<button class="btn btn-sm btn-link p-0 ' . ($is_libur ? 'text-white' : 'text-dark') . '" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>';
                                    echo '<ul class="dropdown-menu dropdown-menu-end">';
                                    if ($is_libur) {
                                        foreach ($libur_map[$day] as $l) {
                                            echo '<li><a class="dropdown-item text-danger" href="manage_libur.php?delete_libur=' . $l['id'] . '&bulan=' . $bulan_filter . '" onclick="return confirm(\'Hapus hari libur ini?\')"><i class="fas fa-trash me-2"></i>Hapus: ' . htmlspecialchars($l['nama_libur']) . '</a></li>';
                                        }
                                    }
                                    if ($is_jam_khusus) {
                                        foreach ($jam_khusus_map[$day] as $jk) {
                                            echo '<li><a class="dropdown-item text-warning" href="manage_libur.php?delete_jam=' . $jk['id'] . '&tab=jam&bulan=' . $bulan_filter . '" onclick="return confirm(\'Hapus jam khusus ini?\')"><i class="fas fa-trash me-2"></i>Hapus: ' . htmlspecialchars($jk['alasan']) . '</a></li>';
                                        }
                                    }
                                    echo '</ul></div>';
                                }
                                echo '</div>';
                                
                                // Info libur
                                if ($is_libur) {
                                    foreach ($libur_map[$day] as $l) {
                                        echo '<div class="small mt-1"><strong>' . htmlspecialchars($l['nama_libur']) . '</strong>';
                                        if ($l['id_kelas']) {
                                            echo '<br><span class="badge bg-light text-dark" style="font-size:0.65rem">' . htmlspecialchars($l['nama_kelas']) . '</span>';
                                        }
                                        echo '</div>';
                                    }
                                }
                                
                                // Info jam khusus
                                if ($is_jam_khusus) {
                                    foreach ($jam_khusus_map[$day] as $jk) {
                                        echo '<div class="small mt-1"><i class="fas fa-clock"></i> ' . htmlspecialchars($jk['alasan']);
                                        echo '<br><span class="badge bg-secondary" style="font-size:0.65rem">Maks ' . $jk['max_jam'] . ' jam</span>';
                                        if ($jk['id_kelas']) {
                                            echo ' <span class="badge bg-info" style="font-size:0.65rem">' . htmlspecialchars($jk['nama_kelas']) . '</span>';
                                        }
                                        echo '</div>';
                                    }
                                }
                                
                                echo '</td>';
                            }
                            
                            // Kosongkan sel setelah tanggal terakhir
                            $last_day_of_week = date('N', strtotime($current_date));
                            for ($i = $last_day_of_week + 1; $i <= 7; $i++) {
                                echo '<td class="bg-light"></td>';
                            }
                            echo '</tr>';
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- DAFTAR LIBUR & JAM KHUSUS -->
        <div class="col-lg-4">
            <!-- Daftar Hari Libur Bulan Ini -->
            <div class="card shadow mb-4">
                <div class="card-header py-2 bg-danger text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-calendar-times me-2"></i>Hari Libur</h6>
                </div>
                <div class="card-body p-0" style="max-height: 250px; overflow-y: auto;">
                    <?php if (count($daftar_libur) > 0): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($daftar_libur as $row): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <strong><?= date('d', strtotime($row['tanggal'])) ?></strong> - <?= htmlspecialchars($row['nama_libur']) ?>
                                <?php if ($row['id_kelas']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($row['nama_kelas']) ?></small>
                                <?php endif; ?>
                            </div>
                            <a href="manage_libur.php?delete_libur=<?= $row['id'] ?>&bulan=<?= $bulan_filter ?>" 
                               class="btn btn-sm btn-outline-danger" 
                               onclick="return confirm('Hapus?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                        <p class="mb-0 small">Tidak ada hari libur</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Daftar Jam Khusus -->
            <div class="card shadow mb-4">
                <div class="card-header py-2 bg-warning">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-clock me-2"></i>Jam Khusus</h6>
                </div>
                <div class="card-body p-0" style="max-height: 250px; overflow-y: auto;">
                    <?php if (count($daftar_jam_khusus) > 0): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($daftar_jam_khusus as $row): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <strong><?= date('d', strtotime($row['tanggal'])) ?></strong> - <?= htmlspecialchars($row['alasan']) ?>
                                <br><small class="text-muted">Maks <?= $row['max_jam'] ?> jam <?= $row['id_kelas'] ? '(' . $row['nama_kelas'] . ')' : '' ?></small>
                            </div>
                            <a href="manage_libur.php?delete_jam=<?= $row['id'] ?>&tab=jam&bulan=<?= $bulan_filter ?>" 
                               class="btn btn-sm btn-outline-warning" 
                               onclick="return confirm('Hapus?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-clock fa-2x mb-2 text-success"></i>
                        <p class="mb-0 small">Semua hari normal</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Legenda -->
            <div class="card shadow">
                <div class="card-body py-2">
                    <h6 class="mb-2"><i class="fas fa-info-circle me-1"></i>Legenda</h6>
                    <div class="d-flex flex-wrap gap-2 small">
                        <span><span class="badge bg-danger">&nbsp;&nbsp;</span> Libur</span>
                        <span><span class="badge bg-warning">&nbsp;&nbsp;</span> Jam Khusus</span>
                        <span><span class="badge bg-primary">&nbsp;&nbsp;</span> Hari Ini</span>
                        <span class="text-danger">🔴 Minggu</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.calendar-table td {
    font-size: 0.85rem;
}
.calendar-cell:hover {
    background-color: #f8f9fa !important;
    cursor: pointer;
}
.calendar-cell.bg-danger:hover {
    background-color: #bb2d3b !important;
}
.calendar-cell.bg-warning:hover {
    background-color: #e5a800 !important;
}
</style>

<!-- Modal Tambah Hari Libur -->
<div class="modal fade" id="modalTambahLibur" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-times me-2"></i>Tambah Hari Libur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Hari Libur <span class="text-danger">*</span></label>
                    <input type="text" name="nama_libur" class="form-control" placeholder="Contoh: Hari Kemerdekaan RI" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Jenis Libur <span class="text-danger">*</span></label>
                    <select name="jenis" class="form-select" required>
                        <option value="nasional">Libur Nasional</option>
                        <option value="sekolah">Libur Sekolah</option>
                        <option value="cuti_bersama">Cuti Bersama</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Berlaku Untuk Kelas</label>
                    <select name="id_kelas" class="form-select">
                        <option value="">-- Semua Kelas --</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Kosongkan jika berlaku untuk semua kelas</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Keterangan (opsional)</label>
                    <textarea name="keterangan" class="form-control" rows="2" placeholder="Keterangan tambahan..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="add_libur" class="btn btn-danger">
                    <i class="fas fa-save me-1"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tambah Jam Khusus -->
<div class="modal fade" id="modalTambahJam" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-clock me-2"></i>Tambah Jam Khusus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Maksimal Jam Pelajaran <span class="text-danger">*</span></label>
                    <input type="number" name="max_jam" class="form-control" min="1" max="10" value="6" required>
                    <small class="text-muted">Jam di atas angka ini tidak perlu diisi jurnal</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Alasan <span class="text-danger">*</span></label>
                    <input type="text" name="alasan" class="form-control" placeholder="Contoh: Pulang Cepat, Ujian Semester" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Berlaku Untuk Kelas</label>
                    <select name="id_kelas" class="form-select">
                        <option value="">-- Semua Kelas --</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Kosongkan untuk berlaku semua kelas</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Keterangan (opsional)</label>
                    <textarea name="keterangan" class="form-control" rows="2" placeholder="Keterangan tambahan..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="add_jam_khusus" class="btn btn-warning">
                    <i class="fas fa-save me-1"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
