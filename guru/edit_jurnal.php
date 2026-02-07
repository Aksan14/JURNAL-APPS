<?php
/* File: guru/edit_jurnal.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

$user_id = $_SESSION['user_id'];
$message = '';

// 1. Cari ID Guru berdasarkan session
$stmt_g = $pdo->prepare("SELECT id FROM tbl_guru WHERE user_id = ?");
$stmt_g->execute([$user_id]);
$id_guru = $stmt_g->fetchColumn();

// Validasi: Pastikan guru ditemukan
if (!$id_guru) {
    $_SESSION['error_message'] = 'Akun Anda tidak terhubung dengan data guru. Silakan hubungi administrator.';
    header('Location: ' . BASE_URL . '/guru/index.php');
    exit;
}

// 2. Ambil ID Jurnal dari URL
$id_jurnal = $_GET['id'] ?? 0;

// 3. Validasi: Pastikan jurnal ini milik guru yang login
$stmt_check = $pdo->prepare("
    SELECT j.*, m.id_guru, m.id_kelas
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    WHERE j.id = ? AND m.id_guru = ?
");
$stmt_check->execute([$id_jurnal, $id_guru]);
$jurnal = $stmt_check->fetch();

if (!$jurnal) {
    header("Location: isi_jurnal.php?error=notfound");
    exit;
}

// 4. LOGIKA UPDATE JURNAL DAN KEHADIRAN
if (isset($_POST['update_jurnal'])) {
    $id_mengajar = $_POST['id_mengajar'];
    $tanggal     = $_POST['tanggal'];
    $jam_ke      = $_POST['jam_ke'];
    $topik_materi = trim($_POST['topik_materi']);
    $catatan_guru = trim($_POST['catatan_guru'] ?? '');
    $kehadiran_siswa = $_POST['absensi'] ?? [];

    if (!empty($id_mengajar) && !empty($topik_materi)) {
        try {
            $pdo->beginTransaction();
            
            // Update jurnal
            $sql = "UPDATE tbl_jurnal SET 
                        id_mengajar = ?, 
                        tanggal = ?, 
                        jam_ke = ?,
                        topik_materi = ?, 
                        catatan_guru = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_mengajar, $tanggal, $jam_ke, $topik_materi, $catatan_guru, $id_jurnal]);
            
            // Update kehadiran siswa
            foreach ($kehadiran_siswa as $id_siswa => $status) {
                $check = $pdo->prepare("SELECT id FROM tbl_presensi_siswa WHERE id_jurnal = ? AND id_siswa = ?");
                $check->execute([$id_jurnal, $id_siswa]);
                
                if ($check->fetch()) {
                    $pdo->prepare("UPDATE tbl_presensi_siswa SET status_kehadiran = ? WHERE id_jurnal = ? AND id_siswa = ?")
                        ->execute([$status, $id_jurnal, $id_siswa]);
                } else {
                    $pdo->prepare("INSERT INTO tbl_presensi_siswa (id_jurnal, id_siswa, status_kehadiran) VALUES (?, ?, ?)")
                        ->execute([$id_jurnal, $id_siswa, $status]);
                }
            }
            
            $pdo->commit();
            header("Location: isi_jurnal.php?status=updated");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Harap isi semua kolom yang wajib!</div>";
    }
}

// 5. AMBIL NAMA KELAS DAN MAPEL UNTUK JURNAL INI
$stmt_kelas_mapel = $pdo->prepare("
    SELECT k.nama_kelas, mp.nama_mapel 
    FROM tbl_mengajar m
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE m.id = ?
");
$stmt_kelas_mapel->execute([$jurnal['id_mengajar']]);
$kelas_mapel = $stmt_kelas_mapel->fetch();

// 6. AMBIL DAFTAR SISWA DI KELAS INI BESERTA STATUS KEHADIRANNYA
$stmt_siswa = $pdo->prepare("
    SELECT s.id, s.nis, s.nama_siswa,
           COALESCE(p.status_kehadiran, 'H') as status_kehadiran
    FROM tbl_siswa s
    LEFT JOIN tbl_presensi_siswa p ON s.id = p.id_siswa AND p.id_jurnal = ?
    WHERE s.id_kelas = ?
    ORDER BY s.nama_siswa ASC
");
$stmt_siswa->execute([$id_jurnal, $jurnal['id_kelas']]);
$daftar_siswa = $stmt_siswa->fetchAll();

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
    .kehadiran-radio input[value="H"]:checked + label { background-color: #66BB6A; color: white; border-color: #66BB6A; }
    .kehadiran-radio input[value="S"]:checked + label { background-color: #FFB74D; color: white; border-color: #FFB74D; }
    .kehadiran-radio input[value="I"]:checked + label { background-color: #4DD0E1; color: white; border-color: #4DD0E1; }
    .kehadiran-radio input[value="A"]:checked + label { background-color: #EF5350; color: white; border-color: #EF5350; }
    
    @media (max-width: 768px) {
        .kehadiran-radio .form-check-label { padding: 0.2rem 0.5rem; font-size: 0.75rem; }
    }
</style>

<div class="container-fluid">
    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between mb-4">
        <h1 class="h3 mb-2 mb-sm-0 text-gray-800">Edit Jurnal Mengajar</h1>
        <a href="isi_jurnal.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <?php echo $message; ?>

    <form method="POST">
        <input type="hidden" name="id_mengajar" value="<?= $jurnal['id_mengajar'] ?>">
        
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Form Edit Jurnal</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Kelas & Mata Pelajaran</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($kelas_mapel['nama_kelas']) ?> - <?= htmlspecialchars($kelas_mapel['nama_mapel']) ?>" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label class="form-label fw-bold">Tanggal</label>
                                <input type="date" name="tanggal" class="form-control" value="<?= $jurnal['tanggal'] ?>" required>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="form-label fw-bold">Jam Ke</label>
                                <input type="text" name="jam_ke" class="form-control" value="<?= htmlspecialchars($jurnal['jam_ke']) ?>" placeholder="Contoh: 1-2" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Topik Materi / Kompetensi Dasar</label>
                            <textarea name="topik_materi" class="form-control" rows="3" required><?= htmlspecialchars($jurnal['topik_materi']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Catatan Guru (Opsional)</label>
                            <textarea name="catatan_guru" class="form-control" rows="2"><?= htmlspecialchars($jurnal['catatan_guru'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card bg-light h-100">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-users me-2"></i>Presensi Siswa
                                    <span class="badge bg-light text-dark ms-2"><?= count($daftar_siswa) ?> siswa</span>
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <?php if (count($daftar_siswa) > 0): ?>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th style="width:40px;">No</th>
                                                <th>Nama Siswa</th>
                                                <th class="text-center" style="width:150px;">Kehadiran</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($daftar_siswa as $i => $siswa): ?>
                                            <tr>
                                                <td class="text-center"><?= $i + 1 ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($siswa['nama_siswa']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($siswa['nis']) ?></small>
                                                </td>
                                                <td>
                                                    <div class="kehadiran-radio">
                                                        <?php 
                                                        $status = $siswa['status_kehadiran'];
                                                        $statuses = ['H', 'S', 'I', 'A'];
                                                        foreach ($statuses as $key): 
                                                        ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" 
                                                                   name="absensi[<?= $siswa['id'] ?>]" 
                                                                   id="<?= $key ?>-<?= $siswa['id'] ?>" 
                                                                   value="<?= $key ?>"
                                                                   <?= ($status == $key) ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="<?= $key ?>-<?= $siswa['id'] ?>"><?= $key ?></label>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <p class="mb-0">Belum ada data siswa</p>
                                </div>
                                <?php endif; ?>
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
                
                <hr>
                <div class="text-center">
                    <button type="submit" name="update_jurnal" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                    <a href="isi_jurnal.php" class="btn btn-secondary btn-lg px-4">
                        <i class="fas fa-times me-2"></i>Batal
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>