<?php
/*
File: isi_jurnal.php (RIWAYAT + MODAL POPUP ISI JURNAL)
Lokasi: /jurnal_app/guru/isi_jurnal.php
*/

require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

$message = '';
$user_id = $_SESSION['user_id']; 

// Ambil ID Guru
$stmt_guru = $pdo->prepare("SELECT id FROM tbl_guru WHERE user_id = ?");
$stmt_guru->execute([$user_id]);
$guru = $stmt_guru->fetch();
$id_guru_login = $guru['id'];

// LOGIKA HAPUS JURNAL
if (isset($_GET['delete'])) {
    $id_jurnal = $_GET['delete'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM tbl_jurnal j JOIN tbl_mengajar m ON j.id_mengajar = m.id WHERE j.id = ? AND m.id_guru = ?");
    $check->execute([$id_jurnal, $id_guru_login]);

    if ($check->fetchColumn() > 0) {
        $pdo->prepare("DELETE FROM tbl_jurnal WHERE id = ?")->execute([$id_jurnal]);
        header("Location: isi_jurnal.php?status=deleted");
        exit;
    }
}

// Konstanta batas maksimal jam per kelas per hari
define('MAX_JAM_PER_HARI', 10);

// Fungsi untuk menghitung jumlah jam dari format jam_ke (misal: "1-2" = 2 jam)
function hitungJumlahJam($jam_ke) {
    if (preg_match('/^(\d+)-(\d+)$/', $jam_ke, $matches)) {
        return $matches[2] - $matches[1] + 1;
    }
    return 1;
}

// LOGIKA SIMPAN JURNAL
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_jurnal'])) {
    $id_mengajar = $_POST['id_mengajar'];
    $tanggal = $_POST['tanggal'];
    $jam_ke = $_POST['jam_ke'];
    $topik_materi = $_POST['topik_materi'];
    $catatan_guru = $_POST['catatan_guru'];
    $absensi = $_POST['absensi'] ?? [];

    // Mapping hari Indonesia ke nomor hari (0=Minggu, 1=Senin, dst)
    $hari_map_num = [
        'Minggu' => 0, 'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3,
        'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6
    ];

    try {
        // Ambil hari jadwal dari tbl_mengajar
        $stmt_hari = $pdo->prepare("SELECT hari FROM tbl_mengajar WHERE id = ?");
        $stmt_hari->execute([$id_mengajar]);
        $hari_jadwal = $stmt_hari->fetchColumn();
        
        // Cek apakah tanggal sesuai dengan hari jadwal
        $hari_tanggal = date('w', strtotime($tanggal)); // 0=Minggu, 1=Senin, dst
        $hari_jadwal_num = $hari_map_num[$hari_jadwal] ?? -1;
        
        if ($hari_tanggal != $hari_jadwal_num) {
            $nama_hari_tanggal = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$hari_tanggal];
            $message = "<div class='alert alert-danger alert-dismissible fade show'>
                <strong>Gagal!</strong> Tanggal yang dipilih adalah hari <strong>{$nama_hari_tanggal}</strong>, 
                tapi jadwal mengajar ini adalah hari <strong>{$hari_jadwal}</strong>. 
                Silakan pilih tanggal yang sesuai dengan hari jadwal.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } else {
            // Cek apakah jurnal sudah pernah diisi untuk kombinasi ini
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tbl_jurnal WHERE id_mengajar = ? AND tanggal = ?");
            $stmt_check->execute([$id_mengajar, $tanggal]);
        
        if ($stmt_check->fetchColumn() > 0) {
            $message = "<div class='alert alert-danger alert-dismissible fade show'>Gagal: Jurnal untuk kelas & mapel ini pada tanggal tersebut sudah pernah diisi. <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            // Ambil id_kelas dari id_mengajar
            $stmt_kelas = $pdo->prepare("SELECT id_kelas FROM tbl_mengajar WHERE id = ?");
            $stmt_kelas->execute([$id_mengajar]);
            $id_kelas = $stmt_kelas->fetchColumn();

            // Hitung total jam yang sudah terisi untuk kelas ini pada tanggal tersebut
            $stmt_jam = $pdo->prepare("
                SELECT COALESCE(SUM(
                    CASE 
                        WHEN j.jam_ke LIKE '%-%' THEN 
                            CAST(SUBSTRING_INDEX(j.jam_ke, '-', -1) AS UNSIGNED) - 
                            CAST(SUBSTRING_INDEX(j.jam_ke, '-', 1) AS UNSIGNED) + 1
                        ELSE 1
                    END
                ), 0) as total_jam
                FROM tbl_jurnal j
                JOIN tbl_mengajar m ON j.id_mengajar = m.id
                WHERE m.id_kelas = ? AND j.tanggal = ?
            ");
            $stmt_jam->execute([$id_kelas, $tanggal]);
            $total_jam_terisi = (int)$stmt_jam->fetchColumn();

            // Hitung jam yang akan diinput
            $jam_akan_diinput = hitungJumlahJam($jam_ke);

            // Validasi: total jam tidak boleh melebihi batas maksimal
            if (($total_jam_terisi + $jam_akan_diinput) > MAX_JAM_PER_HARI) {
                $sisa_jam = MAX_JAM_PER_HARI - $total_jam_terisi;
                $message = "<div class='alert alert-danger alert-dismissible fade show'>
                    <strong>Gagal!</strong> Total jam pelajaran untuk kelas ini pada tanggal tersebut akan melebihi batas maksimal " . MAX_JAM_PER_HARI . " jam per hari.
                    <br>Jam terisi saat ini: <strong>{$total_jam_terisi} jam</strong>, Sisa tersedia: <strong>{$sisa_jam} jam</strong>, Akan diinput: <strong>{$jam_akan_diinput} jam</strong>
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
            } else {
                $pdo->beginTransaction();
                
                $sql_jurnal = "INSERT INTO tbl_jurnal (id_mengajar, tanggal, jam_ke, topik_materi, catatan_guru) VALUES (?, ?, ?, ?, ?)";
                $stmt_jurnal = $pdo->prepare($sql_jurnal);
                $stmt_jurnal->execute([$id_mengajar, $tanggal, $jam_ke, $topik_materi, $catatan_guru]);
                $id_jurnal_baru = $pdo->lastInsertId();

                $sql_presensi = "INSERT INTO tbl_presensi_siswa (id_jurnal, id_siswa, status_kehadiran) VALUES (?, ?, ?)";
                $stmt_presensi = $pdo->prepare($sql_presensi);
                foreach ($absensi as $id_siswa => $status) {
                    $stmt_presensi->execute([$id_jurnal_baru, $id_siswa, $status]);
                }

                $pdo->commit();
                header("Location: isi_jurnal.php?status=success");
                exit;
            }
        }
        } // End validasi tanggal sesuai hari jadwal
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Gagal menyimpan: " . $e->getMessage() . "</div>";
    }
}

// Ambil data Kelas & Mapel yang diajar (termasuk hari jadwal)
$stmt_ajar = $pdo->prepare("
    SELECT m.id, m.hari, m.jam_ke as jam_jadwal, k.nama_kelas, mp.nama_mapel 
    FROM tbl_mengajar m
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE m.id_guru = ?
    ORDER BY FIELD(m.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), k.nama_kelas, mp.nama_mapel
");
$stmt_ajar->execute([$id_guru_login]);
$daftar_mengajar = $stmt_ajar->fetchAll();

// Buat mapping hari untuk validasi JS
$jadwal_hari_map = [];
foreach ($daftar_mengajar as $m) {
    $jadwal_hari_map[$m['id']] = $m['hari'];
}

// Ambil jurnal yang sudah diisi hari ini (untuk filter dropdown)
$stmt_sudah_isi = $pdo->prepare("
    SELECT id_mengajar FROM tbl_jurnal 
    WHERE id_mengajar IN (SELECT id FROM tbl_mengajar WHERE id_guru = ?) 
    AND tanggal = CURDATE()
");
$stmt_sudah_isi->execute([$id_guru_login]);
$sudah_isi_hari_ini = $stmt_sudah_isi->fetchAll(PDO::FETCH_COLUMN);

// Ambil riwayat jurnal
$stmt_riwayat = $pdo->prepare("
    SELECT j.*, k.nama_kelas, mp.nama_mapel,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'H') as hadir,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'S') as sakit,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'I') as izin,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'A') as alpa
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE m.id_guru = ?
    ORDER BY j.tanggal DESC, j.id DESC
");
$stmt_riwayat->execute([$id_guru_login]);
$riwayat = $stmt_riwayat->fetchAll();

require_once '../includes/header.php';
?>

<style>
    .kehadiran-radio {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        justify-content: center;
    }
    .kehadiran-radio .form-check {
        margin: 0;
        padding-left: 0;
    }
    .kehadiran-radio .form-check-input {
        display: none;
    }
    .kehadiran-radio .form-check-label {
        padding: 0.25rem 0.6rem;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.8rem;
        border: 2px solid #dee2e6;
        transition: all 0.2s;
    }
    .kehadiran-radio input[value="H"]:checked + label { background-color: #198754; color: white; border-color: #198754; }
    .kehadiran-radio input[value="S"]:checked + label { background-color: #ffc107; color: black; border-color: #ffc107; }
    .kehadiran-radio input[value="I"]:checked + label { background-color: #0dcaf0; color: black; border-color: #0dcaf0; }
    .kehadiran-radio input[value="A"]:checked + label { background-color: #dc3545; color: white; border-color: #dc3545; }
    
    @media (max-width: 768px) {
        .table-riwayat th, .table-riwayat td { font-size: 0.8rem; padding: 0.5rem; }
        .kehadiran-radio .form-check-label { padding: 0.2rem 0.5rem; font-size: 0.75rem; }
    }
    
    .modal-xl-custom { max-width: 900px; }
</style>

<div class="container-fluid">
    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between mb-4">
        <h1 class="h3 mb-2 mb-sm-0 text-gray-800">Jurnal Mengajar</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalIsiJurnal">
            <i class="fas fa-plus-circle me-2"></i>Isi Jurnal Baru
        </button>
    </div>

    <?php 
    echo $message;
    if (isset($_GET['status'])) {
        if ($_GET['status'] == 'deleted') echo "<div class='alert alert-warning alert-dismissible fade show'>Jurnal berhasil dihapus. <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        if ($_GET['status'] == 'updated') echo "<div class='alert alert-success alert-dismissible fade show'>Jurnal berhasil diperbarui. <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        if ($_GET['status'] == 'success') echo "<div class='alert alert-success alert-dismissible fade show'>Jurnal dan absensi berhasil disimpan! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    ?>

    <!-- Riwayat Jurnal -->
    <div class="card shadow">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-riwayat mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Kelas / Mapel</th>
                            <th>Materi</th>
                            <th class="text-center">Kehadiran</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($riwayat) > 0): ?>
                            <?php foreach ($riwayat as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td style="white-space: nowrap;"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nama_kelas']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['nama_mapel']) ?></small>
                                </td>
                                <td><?= htmlspecialchars(substr($row['topik_materi'] ?? '', 0, 40)) ?><?= strlen($row['topik_materi'] ?? '') > 40 ? '...' : '' ?></td>
                                <td class="text-center" style="white-space: nowrap;">
                                    <span class="badge bg-success" title="Hadir"><?= $row['hadir'] ?? 0 ?></span>
                                    <span class="badge bg-warning text-dark" title="Sakit"><?= $row['sakit'] ?? 0 ?></span>
                                    <span class="badge bg-info text-dark" title="Izin"><?= $row['izin'] ?? 0 ?></span>
                                    <span class="badge bg-danger" title="Alpa"><?= $row['alpa'] ?? 0 ?></span>
                                </td>
                                <td class="text-center" style="white-space: nowrap;">
                                    <a href="edit_jurnal.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info text-white" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="isi_jurnal.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus jurnal ini?')" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                                    <p class="mb-0">Belum ada riwayat jurnal. Klik tombol "Isi Jurnal Baru" untuk memulai.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Isi Jurnal -->
<div class="modal fade" id="modalIsiJurnal" tabindex="-1" aria-labelledby="modalIsiJurnalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl-custom modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalIsiJurnalLabel">
                    <i class="fas fa-edit me-2"></i>Formulir Jurnal Pembelajaran
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="isi_jurnal.php" method="POST" id="formIsiJurnal">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label for="id_mengajar" class="form-label fw-bold">Kelas & Mata Pelajaran</label>
                                <select class="form-select" id="id_mengajar" name="id_mengajar" required>
                                    <option value="">-- Pilih Kelas & Mapel --</option>
                                    <?php foreach ($daftar_mengajar as $ajar): 
                                        $disabled = in_array($ajar['id'], $sudah_isi_hari_ini) ? 'disabled' : '';
                                        $sudah_isi_text = in_array($ajar['id'], $sudah_isi_hari_ini) ? ' (Sudah diisi hari ini)' : '';
                                    ?>
                                        <option value="<?= $ajar['id'] ?>" 
                                                data-hari="<?= $ajar['hari'] ?>" 
                                                data-jam="<?= $ajar['jam_jadwal'] ?>"
                                                <?= $disabled ?>>
                                            [<?= $ajar['hari'] ?>] <?= htmlspecialchars($ajar['nama_kelas']) ?> - <?= htmlspecialchars($ajar['nama_mapel']) ?> (Jam <?= $ajar['jam_jadwal'] ?>)<?= $sudah_isi_text ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">* Jadwal diurutkan berdasarkan hari</small>
                            </div>

                            <!-- Info Hari Jadwal -->
                            <div id="info-hari-jadwal" class="alert alert-warning mb-3" style="display: none;">
                                <i class="fas fa-calendar-day me-1"></i>
                                <strong>Jadwal: Hari <span id="nama-hari-jadwal"></span></strong>
                                <br><small>Tanggal harus sesuai dengan hari jadwal mengajar.</small>
                            </div>

                            <!-- Info Jam Kelas (Realtime) -->
                            <div id="info-jam-kelas" class="alert alert-info mb-3" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong><i class="fas fa-clock me-1"></i> Info Jam Kelas:</strong>
                                    <span id="jam-status-badge" class="badge bg-success">Normal</span>
                                </div>
                                <div class="progress mb-2" style="height: 10px;">
                                    <div id="jam-progress-bar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small>
                                    <span id="jam-terisi">0</span> / <span id="jam-max"><?= MAX_JAM_PER_HARI ?></span> jam terisi
                                    (<span id="jam-sisa"><?= MAX_JAM_PER_HARI ?></span> jam tersedia)
                                </small>
                                <div id="jam-warning" class="text-danger mt-1 fw-bold" style="display: none;"></div>
                            </div>
                            
                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label for="tanggal" class="form-label fw-bold">Tanggal</label>
                                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                                    <small id="tanggal-warning" class="text-danger" style="display: none;">
                                        <i class="fas fa-exclamation-triangle"></i> Tanggal tidak sesuai dengan hari jadwal!
                                    </small>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label for="jam_ke" class="form-label fw-bold">Jam Ke-</label>
                                    <input type="text" class="form-control" id="jam_ke" name="jam_ke" placeholder="Misal: 1-2" required>
                                    <small class="text-muted">Format: 1-2 (untuk 2 jam), atau 3 (untuk 1 jam)</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="topik_materi" class="form-label fw-bold">Topik / Materi Pembelajaran</label>
                                <textarea class="form-control" id="topik_materi" name="topik_materi" rows="3" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="catatan_guru" class="form-label fw-bold">Catatan Guru (Opsional)</label>
                                <textarea class="form-control" id="catatan_guru" name="catatan_guru" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card bg-light h-100">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>Presensi Siswa</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div id="loading-spinner" class="text-center py-3" style="display: none;">
                                        <div class="spinner-border text-primary" role="status"></div>
                                        <p class="mt-2 mb-0">Memuat data siswa...</p>
                                    </div>
                                    <div id="daftar-presensi-siswa" style="max-height: 350px; overflow-y: auto;">
                                        <p class="text-muted text-center py-4">-- Pilih kelas & mapel terlebih dahulu --</p>
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <div class="d-flex flex-wrap gap-2 justify-content-center small">
                                        <span><span class="badge bg-success">H</span> Hadir</span>
                                        <span><span class="badge bg-warning text-dark">S</span> Sakit</span>
                                        <span><span class="badge bg-info text-dark">I</span> Izin</span>
                                        <span><span class="badge bg-danger">A</span> Alpa</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="submit" name="simpan_jurnal" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Simpan Jurnal & Absensi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update dropdown berdasarkan tanggal yang dipilih
document.getElementById('tanggal').addEventListener('change', function() {
    const tanggal = this.value;
    const selectMengajar = document.getElementById('id_mengajar');
    
    // Reset dan disable semua opsi dulu
    selectMengajar.querySelectorAll('option').forEach(opt => {
        opt.disabled = false;
        opt.textContent = opt.textContent.replace(' (Sudah diisi)', '');
    });
    
    // Ambil data jurnal yang sudah diisi pada tanggal tersebut
    fetch(`ajax_get_jurnal_by_date.php?tanggal=${tanggal}`)
        .then(response => response.json())
        .then(data => {
            if (data.sudah_isi) {
                data.sudah_isi.forEach(id => {
                    const opt = selectMengajar.querySelector(`option[value="${id}"]`);
                    if (opt) {
                        opt.disabled = true;
                        opt.textContent = opt.textContent + ' (Sudah diisi)';
                    }
                });
            }
        })
        .catch(err => console.error(err));
});

// Load siswa saat pilih kelas/mapel
document.getElementById('id_mengajar').addEventListener('change', function() {
    const idMengajar = this.value;
    const presensiContainer = document.getElementById('daftar-presensi-siswa');
    const spinner = document.getElementById('loading-spinner');

    if (!idMengajar) {
        presensiContainer.innerHTML = '<p class="text-muted text-center py-4">-- Pilih kelas & mapel terlebih dahulu --</p>';
        return;
    }

    spinner.style.display = 'block';
    presensiContainer.innerHTML = '';

    fetch(`ajax_get_siswa.php?id_mengajar=${idMengajar}`)
        .then(response => response.json())
        .then(data => {
            spinner.style.display = 'none';

            if (data.error) {
                presensiContainer.innerHTML = `<p class="text-danger text-center py-4">Error: ${data.error}</p>`;
                return;
            }

            if (data.length === 0) {
                presensiContainer.innerHTML = '<p class="text-muted text-center py-4">Tidak ada siswa di kelas ini.</p>';
                return;
            }

            let tableHTML = `
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width:40px;">No</th>
                            <th>Nama Siswa</th>
                            <th class="text-center" style="width:150px;">Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            let no = 1;
            data.forEach(siswa => {
                tableHTML += `
                    <tr>
                        <td class="text-center">${no++}</td>
                        <td>
                            <div class="fw-semibold">${siswa.nama_siswa}</div>
                            <small class="text-muted">${siswa.nis}</small>
                        </td>
                        <td>
                            <div class="kehadiran-radio">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="absensi[${siswa.id}]" id="H-${siswa.id}" value="H" checked>
                                    <label class="form-check-label" for="H-${siswa.id}">H</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="absensi[${siswa.id}]" id="S-${siswa.id}" value="S">
                                    <label class="form-check-label" for="S-${siswa.id}">S</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="absensi[${siswa.id}]" id="I-${siswa.id}" value="I">
                                    <label class="form-check-label" for="I-${siswa.id}">I</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="absensi[${siswa.id}]" id="A-${siswa.id}" value="A">
                                    <label class="form-check-label" for="A-${siswa.id}">A</label>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tableHTML += `</tbody></table>`;
            presensiContainer.innerHTML = tableHTML;
        })
        .catch(error => {
            spinner.style.display = 'none';
            presensiContainer.innerHTML = `<p class="text-danger text-center py-4">Gagal memuat data: ${error.message}</p>`;
        });
});

// Fungsi untuk mengecek jam kelas yang tersedia
function cekJamKelas() {
    const idMengajar = document.getElementById('id_mengajar').value;
    const tanggal = document.getElementById('tanggal').value;
    const jamKe = document.getElementById('jam_ke').value;
    const infoContainer = document.getElementById('info-jam-kelas');
    const submitBtn = document.querySelector('button[name="simpan_jurnal"]');
    
    if (!idMengajar || !tanggal) {
        infoContainer.style.display = 'none';
        return;
    }
    
    fetch(`ajax_cek_jam_kelas.php?id_mengajar=${idMengajar}&tanggal=${tanggal}&jam_ke=${encodeURIComponent(jamKe)}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }
            
            infoContainer.style.display = 'block';
            
            const persen = Math.min(100, (data.total_jam_terisi / data.max_jam) * 100);
            const progressBar = document.getElementById('jam-progress-bar');
            const statusBadge = document.getElementById('jam-status-badge');
            const warningDiv = document.getElementById('jam-warning');
            
            document.getElementById('jam-terisi').textContent = data.total_jam_terisi;
            document.getElementById('jam-max').textContent = data.max_jam;
            document.getElementById('jam-sisa').textContent = data.sisa_jam;
            
            progressBar.style.width = persen + '%';
            
            // Update warna dan status
            if (data.sisa_jam <= 0) {
                progressBar.className = 'progress-bar bg-danger';
                statusBadge.className = 'badge bg-danger';
                statusBadge.textContent = 'JAM PENUH!';
                infoContainer.className = 'alert alert-danger mb-3';
            } else if (data.sisa_jam <= 2) {
                progressBar.className = 'progress-bar bg-warning';
                statusBadge.className = 'badge bg-warning text-dark';
                statusBadge.textContent = 'Hampir Penuh';
                infoContainer.className = 'alert alert-warning mb-3';
            } else {
                progressBar.className = 'progress-bar bg-success';
                statusBadge.className = 'badge bg-success';
                statusBadge.textContent = 'Normal';
                infoContainer.className = 'alert alert-info mb-3';
            }
            
            // Validasi jam yang akan diinput
            if (!data.is_valid && jamKe) {
                warningDiv.style.display = 'block';
                warningDiv.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> Peringatan: Jam yang akan diinput (${data.jam_akan_diinput} jam) melebihi sisa jam tersedia (${data.sisa_jam} jam)!`;
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-secondary');
                submitBtn.classList.remove('btn-primary');
            } else {
                warningDiv.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-secondary');
                submitBtn.classList.add('btn-primary');
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Mapping hari Indonesia ke hari JS (0=Minggu, 1=Senin, dst)
const hariToDay = {
    'Minggu': 0,
    'Senin': 1,
    'Selasa': 2,
    'Rabu': 3,
    'Kamis': 4,
    'Jumat': 5,
    'Sabtu': 6
};

// Validasi tanggal sesuai hari jadwal
function validasiTanggalHari() {
    const selectMengajar = document.getElementById('id_mengajar');
    const inputTanggal = document.getElementById('tanggal');
    const infoHari = document.getElementById('info-hari-jadwal');
    const namaHariSpan = document.getElementById('nama-hari-jadwal');
    const tanggalWarning = document.getElementById('tanggal-warning');
    const submitBtn = document.querySelector('button[name="simpan_jurnal"]');
    
    const selectedOption = selectMengajar.options[selectMengajar.selectedIndex];
    
    if (!selectMengajar.value) {
        infoHari.style.display = 'none';
        tanggalWarning.style.display = 'none';
        return true;
    }
    
    const hariJadwal = selectedOption.getAttribute('data-hari');
    const jamJadwal = selectedOption.getAttribute('data-jam');
    
    // Tampilkan info hari jadwal
    infoHari.style.display = 'block';
    namaHariSpan.textContent = hariJadwal + ' (Jam ' + jamJadwal + ')';
    
    // Auto-fill jam ke dari jadwal
    if (document.getElementById('jam_ke').value === '') {
        document.getElementById('jam_ke').value = jamJadwal;
    }
    
    if (!inputTanggal.value) {
        tanggalWarning.style.display = 'none';
        return true;
    }
    
    // Cek apakah tanggal yang dipilih sesuai dengan hari jadwal
    const tanggalDipilih = new Date(inputTanggal.value);
    const hariDipilih = tanggalDipilih.getDay(); // 0=Minggu, 1=Senin, dst
    const hariJadwalIndex = hariToDay[hariJadwal];
    
    if (hariDipilih !== hariJadwalIndex) {
        tanggalWarning.style.display = 'block';
        inputTanggal.classList.add('is-invalid');
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-secondary');
        submitBtn.classList.remove('btn-primary');
        return false;
    } else {
        tanggalWarning.style.display = 'none';
        inputTanggal.classList.remove('is-invalid');
        return true;
    }
}

// Event listeners untuk cek jam kelas
document.getElementById('id_mengajar').addEventListener('change', function() {
    validasiTanggalHari();
    cekJamKelas();
});
document.getElementById('tanggal').addEventListener('change', function() {
    validasiTanggalHari();
    cekJamKelas();
});
document.getElementById('jam_ke').addEventListener('input', cekJamKelas);

// Cek jam saat modal dibuka
document.getElementById('modalIsiJurnal').addEventListener('shown.bs.modal', function() {
    validasiTanggalHari();
    cekJamKelas();
});

// Auto open modal jika ada parameter open_modal
<?php if (isset($_GET['open_modal']) && $_GET['open_modal'] == '1'): ?>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('modalIsiJurnal'));
    modal.show();
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>