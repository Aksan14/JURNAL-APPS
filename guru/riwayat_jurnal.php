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

// Ambil data presensi siswa untuk setiap jurnal
$presensi_per_jurnal = [];
foreach ($riwayat as $row) {
    $stmt_presensi = $pdo->prepare("
        SELECT s.nis, s.nama_siswa, p.status_kehadiran
        FROM tbl_presensi_siswa p
        JOIN tbl_siswa s ON p.id_siswa = s.id
        WHERE p.id_jurnal = ?
        ORDER BY s.nama_siswa ASC
    ");
    $stmt_presensi->execute([$row['id']]);
    $presensi_per_jurnal[$row['id']] = $stmt_presensi->fetchAll();
}

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

<style>
    .jurnal-row { cursor: pointer; }
    .jurnal-row:hover { background-color: #f8f9fa; }
    .toggle-icon { transition: transform 0.2s ease; color: #6c757d; }
    .toggle-icon.rotated { transform: rotate(90deg); }
    .detail-siswa-row { background-color: #f8f9fa; }
    .detail-siswa-row table { font-size: 0.85rem; }
</style>
    

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-light">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Aktivitas Mengajar</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 30px;"></th>
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
                            <tr class="jurnal-row" data-jurnal-id="<?= $row['id'] ?>">
                                <td class="text-center">
                                    <i class="fas fa-chevron-right toggle-icon" id="icon-<?= $row['id'] ?>"></i>
                                </td>
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
                                <td style="white-space: nowrap;" onclick="event.stopPropagation();">
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
                            <!-- Detail Siswa (Expandable) -->
                            <tr class="detail-siswa-row" id="detail-<?= $row['id'] ?>" style="display: none;">
                                <td colspan="7" class="bg-light p-0">
                                    <div class="p-3">
                                        <h6 class="mb-2"><i class="fas fa-users me-1"></i> Daftar Presensi Siswa</h6>
                                        <?php if (!empty($presensi_per_jurnal[$row['id']])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead class="table-secondary">
                                                    <tr>
                                                        <th style="width: 40px;">No</th>
                                                        <th style="width: 100px;">NIS</th>
                                                        <th>Nama Siswa</th>
                                                        <th style="width: 80px;" class="text-center">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($presensi_per_jurnal[$row['id']] as $idx => $siswa): ?>
                                                    <tr>
                                                        <td class="text-center"><?= $idx + 1 ?></td>
                                                        <td><?= htmlspecialchars($siswa['nis']) ?></td>
                                                        <td><?= htmlspecialchars($siswa['nama_siswa']) ?></td>
                                                        <td class="text-center">
                                                            <?php 
                                                                $status = $siswa['status_kehadiran'];
                                                                $badge = 'bg-secondary';
                                                                $label = $status;
                                                                if ($status == 'H') { $badge = 'bg-success'; $label = 'Hadir'; }
                                                                if ($status == 'S') { $badge = 'bg-warning text-dark'; $label = 'Sakit'; }
                                                                if ($status == 'I') { $badge = 'bg-info text-dark'; $label = 'Izin'; }
                                                                if ($status == 'A') { $badge = 'bg-danger'; $label = 'Alpa'; }
                                                            ?>
                                                            <span class="badge <?= $badge ?>"><?= $label ?></span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <p class="text-muted mb-0"><i class="fas fa-info-circle"></i> Tidak ada data presensi siswa</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada riwayat mengajar.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle expand/collapse daftar siswa di riwayat jurnal
document.querySelectorAll('.jurnal-row').forEach(function(row) {
    row.addEventListener('click', function() {
        var jurnalId = this.getAttribute('data-jurnal-id');
        var detailRow = document.getElementById('detail-' + jurnalId);
        var icon = document.getElementById('icon-' + jurnalId);
        
        if (detailRow.style.display === 'none') {
            detailRow.style.display = 'table-row';
            icon.classList.add('rotated');
        } else {
            detailRow.style.display = 'none';
            icon.classList.remove('rotated');
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>