<?php
/*
File: rekap_absensi.php (UPDATED with SQL Aggregate)
Lokasi: /jurnal_app/walikelas/rekap_absensi.php
*/

// 1. Panggil
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
checkRole(['walikelas', 'guru', 'admin']); 
 
// --- PHP LOGIC (START) ---
$id_kelas_target = null;
$nama_kelas_target = 'Semua Kelas';
$message = '';
$daftar_siswa = [];
$daftar_jurnal_hari_ini = [];
$data_absensi = [];
$daftar_kelas_mengajar = [];
$data_per_kelas = []; // Untuk menampung data semua kelas

// Logika: Ambil ID Guru berdasarkan user_id
$user_id = $_SESSION['user_id'] ?? null;
$id_guru = null;

if ($user_id) {
    // Ambil id guru dari tbl_guru berdasarkan user_id
    $stmt_guru = $pdo->prepare("SELECT id FROM tbl_guru WHERE user_id = ?");
    $stmt_guru->execute([$user_id]);
    $guru_data = $stmt_guru->fetch();
    if ($guru_data) {
        $id_guru = $guru_data['id'];
    }
}

// Ambil semua kelas yang diajar oleh guru ini (dari tbl_mengajar)
if ($id_guru) {
    $stmt_kelas_mengajar = $pdo->prepare("
        SELECT DISTINCT k.id, k.nama_kelas 
        FROM tbl_kelas k 
        JOIN tbl_mengajar m ON k.id = m.id_kelas 
        WHERE m.id_guru = ? 
        ORDER BY k.nama_kelas ASC
    ");
    $stmt_kelas_mengajar->execute([$id_guru]);
    $daftar_kelas_mengajar = $stmt_kelas_mengajar->fetchAll();
}

// Tentukan kelas yang dipilih (dari GET, default = semua/all)
$id_kelas_filter = $_GET['id_kelas'] ?? 'all';

if ($id_kelas_filter !== 'all') {
    // Cari nama kelas berdasarkan id yang dipilih
    foreach ($daftar_kelas_mengajar as $kls) {
        if ($kls['id'] == $id_kelas_filter) {
            $id_kelas_target = $kls['id'];
            $nama_kelas_target = $kls['nama_kelas'];
            break;
        }
    }
}

// Tentukan Tanggal Filter
$tanggal_filter = $_GET['tanggal'] ?? date('Y-m-d');

// LOGIKA UTAMA
$totals_harian = ['total_harian_h' => 0, 'total_harian_s' => 0, 'total_harian_i' => 0, 'total_harian_a' => 0];

if (!empty($daftar_kelas_mengajar)) {
    try {
        // Jika filter = semua kelas
        if ($id_kelas_filter === 'all') {
            foreach ($daftar_kelas_mengajar as $kelas) {
                $kelas_id = $kelas['id'];
                $kelas_nama = $kelas['nama_kelas'];
                
                // Ambil daftar siswa
                $stmt_siswa = $pdo->prepare("SELECT id, nis, nama_siswa FROM tbl_siswa WHERE id_kelas = ? ORDER BY nama_siswa ASC");
                $stmt_siswa->execute([$kelas_id]);
                $siswa_kelas = $stmt_siswa->fetchAll();
                
                // Ambil daftar jurnal
                $stmt_jurnal = $pdo->prepare("SELECT j.id, j.jam_ke, mp.nama_mapel FROM tbl_jurnal j JOIN tbl_mengajar m ON j.id_mengajar = m.id JOIN tbl_mapel mp ON m.id_mapel = mp.id WHERE m.id_kelas = ? AND j.tanggal = ? ORDER BY j.jam_ke ASC");
                $stmt_jurnal->execute([$kelas_id, $tanggal_filter]);
                $jurnal_kelas = $stmt_jurnal->fetchAll();
                
                // Ambil data absensi
                $stmt_absensi = $pdo->prepare("SELECT p.id_siswa, p.id_jurnal, p.status_kehadiran FROM tbl_presensi_siswa p JOIN tbl_jurnal j ON p.id_jurnal = j.id JOIN tbl_mengajar m ON j.id_mengajar = m.id WHERE m.id_kelas = ? AND j.tanggal = ?");
                $stmt_absensi->execute([$kelas_id, $tanggal_filter]);
                $absensi_kelas = $stmt_absensi->fetchAll();
                
                // Ambil total
                $stmt_totals = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN p.status_kehadiran = 'H' THEN 1 ELSE 0 END) AS total_h,
                        SUM(CASE WHEN p.status_kehadiran = 'S' THEN 1 ELSE 0 END) AS total_s,
                        SUM(CASE WHEN p.status_kehadiran = 'I' THEN 1 ELSE 0 END) AS total_i,
                        SUM(CASE WHEN p.status_kehadiran = 'A' THEN 1 ELSE 0 END) AS total_a
                    FROM tbl_presensi_siswa p
                    JOIN tbl_jurnal j ON p.id_jurnal = j.id
                    JOIN tbl_mengajar m ON j.id_mengajar = m.id
                    WHERE m.id_kelas = ? AND j.tanggal = ?
                ");
                $stmt_totals->execute([$kelas_id, $tanggal_filter]);
                $totals_kelas = $stmt_totals->fetch();
                
                // Format data absensi
                $absensi_formatted = [];
                foreach ($absensi_kelas as $absen) {
                    $absensi_formatted[$absen['id_siswa']][$absen['id_jurnal']] = $absen['status_kehadiran'];
                }
                
                // Hitung total keseluruhan
                $totals_harian['total_harian_h'] += $totals_kelas['total_h'] ?? 0;
                $totals_harian['total_harian_s'] += $totals_kelas['total_s'] ?? 0;
                $totals_harian['total_harian_i'] += $totals_kelas['total_i'] ?? 0;
                $totals_harian['total_harian_a'] += $totals_kelas['total_a'] ?? 0;
                
                // Simpan ke array (tampilkan semua kelas meskipun tidak ada jurnal)
                $data_per_kelas[] = [
                    'id' => $kelas_id,
                    'nama_kelas' => $kelas_nama,
                    'siswa' => $siswa_kelas,
                    'jurnal' => $jurnal_kelas,
                    'absensi' => $absensi_formatted,
                    'totals' => $totals_kelas
                ];
            }
        } else {
            // Filter kelas tertentu
            $stmt_siswa = $pdo->prepare("SELECT id, nis, nama_siswa FROM tbl_siswa WHERE id_kelas = ? ORDER BY nama_siswa ASC");
            $stmt_siswa->execute([$id_kelas_target]);
            $daftar_siswa = $stmt_siswa->fetchAll();

            $stmt_jurnal = $pdo->prepare("SELECT j.id, j.jam_ke, mp.nama_mapel FROM tbl_jurnal j JOIN tbl_mengajar m ON j.id_mengajar = m.id JOIN tbl_mapel mp ON m.id_mapel = mp.id WHERE m.id_kelas = ? AND j.tanggal = ? ORDER BY j.jam_ke ASC");
            $stmt_jurnal->execute([$id_kelas_target, $tanggal_filter]);
            $daftar_jurnal_hari_ini = $stmt_jurnal->fetchAll();

            $stmt_absensi = $pdo->prepare("SELECT p.id_siswa, p.id_jurnal, p.status_kehadiran FROM tbl_presensi_siswa p JOIN tbl_jurnal j ON p.id_jurnal = j.id JOIN tbl_mengajar m ON j.id_mengajar = m.id WHERE m.id_kelas = ? AND j.tanggal = ?");
            $stmt_absensi->execute([$id_kelas_target, $tanggal_filter]);
            $semua_absensi = $stmt_absensi->fetchAll();
            
            $stmt_totals_harian = $pdo->prepare("
                SELECT 
                    SUM(CASE WHEN p.status_kehadiran = 'H' THEN 1 ELSE 0 END) AS total_harian_h,
                    SUM(CASE WHEN p.status_kehadiran = 'S' THEN 1 ELSE 0 END) AS total_harian_s,
                    SUM(CASE WHEN p.status_kehadiran = 'I' THEN 1 ELSE 0 END) AS total_harian_i,
                    SUM(CASE WHEN p.status_kehadiran = 'A' THEN 1 ELSE 0 END) AS total_harian_a
                FROM tbl_presensi_siswa p
                JOIN tbl_jurnal j ON p.id_jurnal = j.id
                JOIN tbl_mengajar m ON j.id_mengajar = m.id
                WHERE m.id_kelas = ? AND j.tanggal = ?
            ");
            $stmt_totals_harian->execute([$id_kelas_target, $tanggal_filter]);
            $totals_harian = $stmt_totals_harian->fetch();

            foreach ($semua_absensi as $absen) {
                $data_absensi[$absen['id_siswa']][$absen['id_jurnal']] = $absen['status_kehadiran'];
            }
        }

    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Gagal mengambil data: " . $e->getMessage() . "</div>";
    }
} else {
    $message = "<div class='alert alert-warning'>Anda tidak memiliki jadwal mengajar di kelas manapun.</div>";
}
// --- PHP LOGIC (END) ---
?>

<div class="container-fluid py-3">
    <!-- Header dengan Judul & Tanggal -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0 fw-bold text-dark">
            <i class="bi bi-clipboard-check me-2"></i>Rekap Absensi
        </h4>
        <span class="badge bg-secondary fs-6"><?php echo htmlspecialchars(date('d M Y', strtotime($tanggal_filter))); ?></span>
    </div>

    <!-- Filter Compact -->
    <div class="bg-light rounded-3 p-3 mb-4">
        <form method="GET">
            <div class="row g-2 align-items-center">
                <div class="col-md-4 col-6">
                    <select class="form-select form-select-sm" id="id_kelas" name="id_kelas">
                        <option value="all" <?php echo ($id_kelas_filter === 'all') ? 'selected' : ''; ?>>Semua Kelas</option>
                        <?php foreach ($daftar_kelas_mengajar as $kls): ?>
                            <option value="<?php echo $kls['id']; ?>" <?php echo ($id_kelas_filter == $kls['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kls['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 col-6">
                    <input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($tanggal_filter); ?>">
                </div>
                <div class="col-md-4 col-12">
                    <button type="submit" class="btn btn-dark btn-sm w-100">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <?php echo $message; ?>

    <!-- Ringkasan Kehadiran - Compact -->
    <?php if (!empty($daftar_kelas_mengajar)): ?>
        <div class="d-flex flex-wrap gap-2 mb-4 justify-content-center">
            <div class="d-flex align-items-center bg-success text-white rounded-pill px-3 py-2">
                <i class="bi bi-check-circle me-2"></i>
                <span class="fw-bold me-1"><?php echo $totals_harian['total_harian_h'] ?? 0; ?></span>
                <small>Hadir</small>
            </div>
            <div class="d-flex align-items-center bg-warning text-dark rounded-pill px-3 py-2">
                <i class="bi bi-bandaid me-2"></i>
                <span class="fw-bold me-1"><?php echo $totals_harian['total_harian_s'] ?? 0; ?></span>
                <small>Sakit</small>
            </div>
            <div class="d-flex align-items-center bg-info text-dark rounded-pill px-3 py-2">
                <i class="bi bi-envelope me-2"></i>
                <span class="fw-bold me-1"><?php echo $totals_harian['total_harian_i'] ?? 0; ?></span>
                <small>Izin</small>
            </div>
            <div class="d-flex align-items-center bg-danger text-white rounded-pill px-3 py-2">
                <i class="bi bi-x-circle me-2"></i>
                <span class="fw-bold me-1"><?php echo $totals_harian['total_harian_a'] ?? 0; ?></span>
                <small>Alfa</small>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tampilan untuk SEMUA KELAS -->
    <?php if ($id_kelas_filter === 'all'): ?>
        <?php if (!empty($data_per_kelas)): ?>
            <div class="row g-3">
            <?php foreach ($data_per_kelas as $kelas_data): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">
                                <i class="bi bi-mortarboard text-primary me-2"></i><?php echo htmlspecialchars($kelas_data['nama_kelas']); ?>
                            </h6>
                            <?php if (!empty($kelas_data['jurnal'])): ?>
                            <div class="d-flex gap-1">
                                <span class="badge bg-success"><?php echo $kelas_data['totals']['total_h'] ?? 0; ?> H</span>
                                <span class="badge bg-warning text-dark"><?php echo $kelas_data['totals']['total_s'] ?? 0; ?> S</span>
                                <span class="badge bg-info text-dark"><?php echo $kelas_data['totals']['total_i'] ?? 0; ?> I</span>
                                <span class="badge bg-danger"><?php echo $kelas_data['totals']['total_a'] ?? 0; ?> A</span>
                            </div>
                            <?php else: ?>
                            <span class="badge bg-secondary">Belum ada jurnal</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($kelas_data['jurnal'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width: 40px;">#</th>
                                            <th>Nama Siswa</th>
                                            <?php foreach ($kelas_data['jurnal'] as $jurnal): ?>
                                                <th class="text-center" style="width: 70px;">
                                                    <small class="d-block text-muted">Jam <?php echo htmlspecialchars($jurnal['jam_ke']); ?></small>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($kelas_data['siswa'])): ?>
                                            <?php $no = 1; foreach ($kelas_data['siswa'] as $siswa): ?>
                                                <tr>
                                                    <td class="text-center text-muted small"><?php echo $no++; ?></td>
                                                    <td class="fw-medium"><?php echo htmlspecialchars($siswa['nama_siswa']); ?></td>
                                                    <?php foreach ($kelas_data['jurnal'] as $jurnal): ?>
                                                        <?php 
                                                            $status = $kelas_data['absensi'][$siswa['id']][$jurnal['id']] ?? '-';
                                                            $badge_class = 'bg-secondary';
                                                            if ($status == 'H') $badge_class = 'bg-success';
                                                            elseif ($status == 'S') $badge_class = 'bg-warning text-dark';
                                                            elseif ($status == 'I') $badge_class = 'bg-info text-dark';
                                                            elseif ($status == 'A') $badge_class = 'bg-danger';
                                                        ?>
                                                        <td class="text-center">
                                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?php echo 2 + count($kelas_data['jurnal']); ?>" class="text-center text-muted py-3">
                                                    <small>Tidak ada siswa</small>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="p-3 text-center text-muted">
                                <small><i class="bi bi-journal-x me-1"></i>Belum ada jurnal hari ini</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-light border text-center">
                <i class="bi bi-info-circle me-1"></i> Tidak ada kelas yang Anda ajar.
            </div>
        <?php endif; ?>
        
    <!-- Tampilan untuk KELAS TERTENTU -->
    <?php elseif ($id_kelas_target && !empty($daftar_jurnal_hari_ini)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <i class="bi bi-mortarboard text-primary me-2"></i><?php echo htmlspecialchars($nama_kelas_target); ?>
                </h6>
                <div class="d-flex gap-1">
                    <span class="badge bg-success"><?php echo $totals_harian['total_harian_h'] ?? 0; ?> H</span>
                    <span class="badge bg-warning text-dark"><?php echo $totals_harian['total_harian_s'] ?? 0; ?> S</span>
                    <span class="badge bg-info text-dark"><?php echo $totals_harian['total_harian_i'] ?? 0; ?> I</span>
                    <span class="badge bg-danger"><?php echo $totals_harian['total_harian_a'] ?? 0; ?> A</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Nama Siswa</th>
                                <?php foreach ($daftar_jurnal_hari_ini as $jurnal): ?>
                                    <th class="text-center" style="width: 70px;">
                                        <small class="d-block text-muted">Jam <?php echo htmlspecialchars($jurnal['jam_ke']); ?></small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($daftar_siswa)): ?>
                                <?php $no = 1; foreach ($daftar_siswa as $siswa): ?>
                                    <tr>
                                        <td class="text-center text-muted small"><?php echo $no++; ?></td>
                                        <td class="fw-medium"><?php echo htmlspecialchars($siswa['nama_siswa']); ?></td>
                                        <?php foreach ($daftar_jurnal_hari_ini as $jurnal): ?>
                                            <?php 
                                                $status = $data_absensi[$siswa['id']][$jurnal['id']] ?? '-';
                                                $badge_class = 'bg-secondary';
                                                if ($status == 'H') $badge_class = 'bg-success';
                                                elseif ($status == 'S') $badge_class = 'bg-warning text-dark';
                                                elseif ($status == 'I') $badge_class = 'bg-info text-dark';
                                                elseif ($status == 'A') $badge_class = 'bg-danger';
                                            ?>
                                            <td class="text-center">
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo 2 + count($daftar_jurnal_hari_ini); ?>" class="text-center text-muted py-3">
                                        <small>Tidak ada siswa di kelas ini</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($id_kelas_target && empty($daftar_jurnal_hari_ini)): ?>
        <div class="alert alert-light border text-center">
            <i class="bi bi-journal-x me-2"></i>Tidak ada jurnal pada <strong><?php echo htmlspecialchars(date('d M Y', strtotime($tanggal_filter))); ?></strong> untuk kelas <strong><?php echo htmlspecialchars($nama_kelas_target); ?></strong>
        </div>
    <?php endif; ?>

    <!-- Legend -->
    <div class="mt-4 text-center">
        <small class="text-muted">
            <i class="bi bi-check-lg text-success me-1"></i>Hadir
            <span class="mx-2">|</span>
            <i class="bi bi-bandaid text-warning me-1"></i>Sakit
            <span class="mx-2">|</span>
            <i class="bi bi-envelope text-info me-1"></i>Izin
            <span class="mx-2">|</span>
            <i class="bi bi-x-lg text-danger me-1"></i>Alfa
        </small>
    </div>
</div>
<?php
// 6. Panggil footer
require_once '../includes/footer.php';
?>