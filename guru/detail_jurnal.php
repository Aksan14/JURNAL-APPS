<?php
/*
File: detail_jurnal.php (UPDATED with SQL Aggregate)
Lokasi: /jurnal_app/guru/detail_jurnal.php
*/

// 1. Panggil header
require_once '../includes/header.php';
// 2. Panggil auth check
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

// --- PHP LOGIC (START) ---

// 2. Validasi ID Jurnal dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['success_message'] = "<div class='alert alert-danger'>Error: ID Jurnal tidak valid.</div>";
    header('Location: riwayat_jurnal.php');
    exit;
}
$id_jurnal = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Ambil ID Guru
$stmt_guru = $pdo->prepare("SELECT id FROM tbl_guru WHERE user_id = ?");
$stmt_guru->execute([$user_id]);
$guru = $stmt_guru->fetch();

// Validasi: Pastikan guru ditemukan
if (!$guru) {
    $_SESSION['error_message'] = 'Akun Anda tidak terhubung dengan data guru. Silakan hubungi administrator.';
    header('Location: ' . BASE_URL . '/guru/index.php');
    exit;
}

$id_guru_login = $guru['id'];

// 3. LOGIKA READ (Ambil Data Jurnal Utama)
$stmt_jurnal = $pdo->prepare("
    SELECT 
        j.tanggal, j.jam_ke, j.topik_materi, j.catatan_guru,
        k.nama_kelas, mp.nama_mapel, g.nama_guru
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    JOIN tbl_guru g ON m.id_guru = g.id
    WHERE j.id = ? AND m.id_guru = ?
");
$stmt_jurnal->execute([$id_jurnal, $id_guru_login]);
$jurnal = $stmt_jurnal->fetch();

if (!$jurnal) {
    $_SESSION['success_message'] = "<div class='alert alert-warning'>Jurnal tidak ditemukan.</div>";
    header('Location: riwayat_jurnal.php');
    exit;
}

// 4. LOGIKA READ (Ambil Daftar Presensi Siswa)
$stmt_presensi = $pdo->prepare("
    SELECT s.nis, s.nama_siswa, p.status_kehadiran
    FROM tbl_presensi_siswa p
    JOIN tbl_siswa s ON p.id_siswa = s.id
    WHERE p.id_jurnal = ?
    ORDER BY s.nama_siswa ASC
");
$stmt_presensi->execute([$id_jurnal]);
$daftar_presensi = $stmt_presensi->fetchAll();

// ==========================================================
// 5. KODE BARU: AMBIL TOTAL ABSENSI (LANGSUNG DARI SQL)
// Ini menggantikan loop foreach di PHP
// ==========================================================
$stmt_totals = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_siswa,
        SUM(CASE WHEN status_kehadiran = 'H' THEN 1 ELSE 0 END) AS total_hadir,
        SUM(CASE WHEN status_kehadiran = 'S' THEN 1 ELSE 0 END) AS total_sakit,
        SUM(CASE WHEN status_kehadiran = 'I' THEN 1 ELSE 0 END) AS total_izin,
        SUM(CASE WHEN status_kehadiran = 'A' THEN 1 ELSE 0 END) AS total_alfa
    FROM tbl_presensi_siswa
    WHERE id_jurnal = ?
");
$stmt_totals->execute([$id_jurnal]);
$totals = $stmt_totals->fetch();
// ==========================================================
// AKHIR KODE BARU
// ==========================================================

// --- PHP LOGIC (END) ---
?>

<a href="riwayat_jurnal.php" class="btn btn-secondary mb-3">&laquo; Kembali ke Riwayat</a>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h4>Rekap Presensi Siswa</h4>
            </div>
            <div class="card-body">

                <p class="mb-2">
                    Total Siswa: <strong><?php echo $totals['total_siswa']; ?></strong>
                </p>
                <p>
                    <span class="badge bg-success fs-6 me-1">Hadir: <?php echo $totals['total_hadir']; ?></span>
                    <span class="badge bg-warning text-dark fs-6 me-1">Sakit: <?php echo $totals['total_sakit']; ?></span>
                    <span class="badge bg-info text-dark fs-6 me-1">Izin: <?php echo $totals['total_izin']; ?></span>
                    <span class="badge bg-danger fs-6 me-1">Alfa: <?php echo $totals['total_alfa']; ?></span>
                </p>
                <hr>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th style="width: 15%;" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($daftar_presensi as $presensi): ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($presensi['nis']); ?></td>
                                    <td><?php echo htmlspecialchars($presensi['nama_siswa']); ?></td>
                                    <td class="text-center">
                                        <?php 
                                            $status = htmlspecialchars($presensi['status_kehadiran']);
                                            $badge_class = 'bg-secondary';
                                            if ($status == 'H') $badge_class = 'bg-success';
                                            if ($status == 'S') $badge_class = 'bg-warning text-dark';
                                            if ($status == 'I') $badge_class = 'bg-info text-dark';
                                            if ($status == 'A') $badge_class = 'bg-danger';
                                            
                                            echo "<span class='badge $badge_class fs-6'>$status</span>";
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
// 3. Panggil footer
require_once '../includes/footer.php';
?>