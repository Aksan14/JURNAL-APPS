<?php
/* File: guru/riwayat_jurnal.php */
require_once '../config.php';
require_once '../includes/auth_check.php';
checkRole(['guru', 'walikelas']);

$user_id = $_SESSION['user_id'];
$message = '';

// 1. Ambil ID Guru
$stmt_g = $pdo->prepare("SELECT id FROM tbl_guru WHERE user_id = ?");
$stmt_g->execute([$user_id]);
$id_guru = $stmt_g->fetchColumn();

// 2. LOGIKA HAPUS JURNAL (Pastikan jurnal tersebut milik guru yang login)
if (isset($_GET['delete'])) {
    $id_jurnal = $_GET['delete'];
    
    // Validasi kepemilikan data sebelum hapus
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM tbl_jurnal j
        JOIN tbl_mengajar m ON j.id_mengajar = m.id
        WHERE j.id = ? AND m.id_guru = ?
    ");
    $check->execute([$id_jurnal, $id_guru]);

    if ($check->fetchColumn() > 0) {
        $stmt_del = $pdo->prepare("DELETE FROM tbl_jurnal WHERE id = ?");
        $stmt_del->execute([$id_jurnal]);
        header("Location: riwayat_jurnal.php?status=deleted");
        exit;
    } else {
        $message = "<div class='alert alert-danger'>Aksi ditolak! Anda tidak memiliki akses ke data ini.</div>";
    }
}

// 3. AMBIL SEMUA DATA JURNAL GURU INI BESERTA STATISTIK ABSENSI
$query = "
    SELECT j.*, k.nama_kelas, mp.nama_mapel,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'H') as hadir,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'S') as sakit,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'I') as izin,
           (SELECT COUNT(*) FROM tbl_presensi_siswa WHERE id_jurnal = j.id AND status_kehadiran = 'A') as alpa
    FROM tbl_jurnal j
    JOIN tbl_mengajar m ON j.id_mengajar = m.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE m.id_guru = :id_guru
    ORDER BY j.tanggal DESC, j.id DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute(['id_guru' => $id_guru]);
$riwayat = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Riwayat Jurnal Mengajar</h1>
        <a href="tambah_jurnal.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus me-1"></i> Tambah Jurnal
        </a>
    </div>

    <?php 
    if (isset($_GET['status'])) {
        if ($_GET['status'] == 'deleted') echo "<div class='alert alert-warning alert-dismissible fade show'>Jurnal berhasil dihapus. <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        if ($_GET['status'] == 'updated') echo "<div class='alert alert-success alert-dismissible fade show'>Jurnal berhasil diperbarui. <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    echo $message; 
    ?>

    

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-light">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Aktivitas Mengajar</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Kelas / Mapel</th>
                            <th>Materi</th>
                            <th>Absensi (H/S/I/A)</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($riwayat) > 0): ?>
                            <?php foreach ($riwayat as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td style="white-space: nowrap;"><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nama_kelas']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['nama_mapel']) ?></small>
                                </td>
                                <td><?= nl2br(htmlspecialchars($row['topik_materi'] ?? '')) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success" title="Hadir"><?= $row['hadir'] ?? 0 ?></span>
                                    <span class="badge bg-warning text-dark" title="Sakit"><?= $row['sakit'] ?? 0 ?></span>
                                    <span class="badge bg-info" title="Izin"><?= $row['izin'] ?? 0 ?></span>
                                    <span class="badge bg-danger" title="Alpa"><?= $row['alpa'] ?? 0 ?></span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a href="edit_jurnal.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info text-white" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="riwayat_jurnal.php?delete=<?= $row['id'] ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus jurnal ini?')"
                                       title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada riwayat mengajar.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>