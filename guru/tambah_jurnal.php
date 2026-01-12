<?php
/* File: guru/tambah_jurnal.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

$user_id = $_SESSION['user_id'];
$message = '';

// 1. Cari ID Guru berdasarkan session
$stmt_g = $pdo->prepare("SELECT id FROM tbl_guru WHERE user_id = ?");
$stmt_g->execute([$user_id]);
$id_guru = $stmt_g->fetchColumn();

// 2. LOGIKA SIMPAN JURNAL
if (isset($_POST['simpan_jurnal'])) {
    $id_mengajar = $_POST['id_mengajar'];
    $tanggal     = $_POST['tanggal'];
    $materi      = trim($_POST['materi']);
    $sakit       = (int)$_POST['sakit'];
    $izin        = (int)$_POST['izin'];
    $alpa        = (int)$_POST['alpa'];

    if (!empty($id_mengajar) && !empty($materi)) {
        try {
            $sql = "INSERT INTO tbl_jurnal (id_mengajar, tanggal, materi, sakit, izin, alpa) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_mengajar, $tanggal, $materi, $sakit, $izin, $alpa]);
            
            header("Location: index.php?status=success");
            exit;
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Harap isi semua kolom yang wajib!</div>";
    }
}

// 3. AMBIL DAFTAR PLOTTING (KELAS & MAPEL) UNTUK GURU INI
$query_mapel = "
    SELECT m.id, k.nama_kelas, mp.nama_mapel 
    FROM tbl_mengajar m
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE m.id_guru = ?
";
$stmt_m = $pdo->prepare($query_mapel);
$stmt_m->execute([$id_guru]);
$daftar_mengajar = $stmt_m->fetchAll();

require_once '../includes/header.php';''
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Isi Jurnal Mengajar</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">Batal</a>
    </div>

    <?php echo $message; ?>
    

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary">
                    <h6 class="m-0 font-weight-bold text-white">Form Jurnal Harian</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <label class="form-label font-weight-bold">Kelas & Mata Pelajaran</label>
                                <select name="id_mengajar" class="form-select" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($daftar_mengajar as $dm): ?>
                                        <option value="<?= $dm['id'] ?>">
                                            <?= htmlspecialchars($dm['nama_kelas']) ?> - <?= htmlspecialchars($dm['nama_mapel']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label class="form-label font-weight-bold">Tanggal</label>
                                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Materi Pelajaran / Kompetensi Dasar</label>
                            <textarea name="materi" class="form-control" rows="4" placeholder="Tuliskan materi yang diajarkan hari ini..." required></textarea>
                        </div>

                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <label class="font-weight-bold mb-2"><i class="fas fa-users me-1"></i> Absensi Siswa (Jumlah yang tidak hadir)</label>
                                <div class="row">
                                    <div class="col-4">
                                        <label class="small">Sakit</label>
                                        <input type="number" name="sakit" class="form-control" value="0" min="0">
                                    </div>
                                    <div class="col-4">
                                        <label class="small">Izin</label>
                                        <input type="number" name="izin" class="form-control" value="0" min="0">
                                    </div>
                                    <div class="col-4">
                                        <label class="small">Alpa</label>
                                        <input type="number" name="alpa" class="form-control" value="0" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <div class="d-grid">
                            <button type="submit" name="simpan_jurnal" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i> Simpan Jurnal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>