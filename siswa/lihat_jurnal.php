<?php
/*
File: lihat_jurnal.php (UPDATED for Sidebar Layout)
Lokasi: /jurnal_app/siswa/lihat_jurnal.php
*/

// 1. Panggil
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
checkRole(['siswa']); // Hanya siswa

// --- PHP LOGIC (START) ---
$user_id = $_SESSION['user_id'];
$message = '';
$riwayat_jurnal_kelas = [];
$data_absensi_siswa = [];

// 2. Ambil Data Siswa (ID Siswa, ID Kelas, Nama)
$stmt_siswa = $pdo->prepare("SELECT id, id_kelas, nama_siswa FROM tbl_siswa WHERE user_id = ?");
$stmt_siswa->execute([$user_id]);
$siswa = $stmt_siswa->fetch();

if (!$siswa) {
    // Jika data siswa tidak ada, tampilkan error
    $message = "<div class='alert alert-danger'>Error: Data siswa tidak ditemukan. Silakan hubungi Admin.</div>";
    $id_kelas = 0; // Set ID Kelas ke 0 agar query di bawah tidak error
    $nama_siswa = "Siswa"; // Nama default
} else {
    $id_siswa = $siswa['id'];
    $id_kelas = $siswa['id_kelas'];
    $nama_siswa = $siswa['nama_siswa'];
}

if ($id_kelas != 0) {
    try {
        // 3. Ambil SEMUA Riwayat Absensi PRIBADI Siswa
        $stmt_absensi = $pdo->prepare("SELECT id_jurnal, status_kehadiran FROM tbl_presensi_siswa WHERE id_siswa = ?");
        $stmt_absensi->execute([$id_siswa]);
        $absensi_siswa = $stmt_absensi->fetchAll();
        
        // Ubah jadi array lookup [id_jurnal] => 'status' agar cepat
        foreach ($absensi_siswa as $absen) {
            $data_absensi_siswa[$absen['id_jurnal']] = $absen['status_kehadiran'];
        }

        // 4. Ambil SEMUA Riwayat Jurnal di KELAS Siswa
        $stmt_jurnal = $pdo->prepare("
            SELECT 
                j.id, 
                j.tanggal, 
                j.jam_ke, 
                j.topik_materi, 
                mp.nama_mapel, 
                g.nama_guru
            FROM tbl_jurnal j
            JOIN tbl_mengajar m ON j.id_mengajar = m.id
            JOIN tbl_mapel mp ON m.id_mapel = mp.id
            JOIN tbl_guru g ON m.id_guru = g.id
            WHERE m.id_kelas = ?
            ORDER BY j.tanggal DESC, j.jam_ke DESC
        ");
        $stmt_jurnal->execute([$id_kelas]);
        $riwayat_jurnal_kelas = $stmt_jurnal->fetchAll();

    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Gagal mengambil data: " . $e->getMessage() . "</div>";
    }
}
// --- PHP LOGIC (END) ---
?>

<div class="card">
    <div class="card-header">
        <h4>Selamat Datang, <?php echo htmlspecialchars($nama_siswa); ?>!</h4>
    </div>
    <div class="card-body">
        <p>Ini adalah riwayat jurnal pembelajaran dan status absensi Anda.</p>
        <hr>

        <?php echo $message; ?>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 10%;">Tanggal</th>
                        <th style="width: 15%;">Mapel</th>
                        <th style="width: 20%;">Guru</th>
                        <th>Topik Materi</th>
                        <th style="width: 15%;" class="text-center">Status Absensi Anda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($riwayat_jurnal_kelas)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Belum ada riwayat jurnal di kelas Anda.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($riwayat_jurnal_kelas as $jurnal): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($jurnal['tanggal']))); ?></td>
                                <td><?php echo htmlspecialchars($jurnal['nama_mapel']); ?></td>
                                <td><?php echo htmlspecialchars($jurnal['nama_guru']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($jurnal['topik_materi'])); ?></td>
                                <td class="text-center">
                                    <?php
                                        // Gunakan array lookup yang tadi kita buat
                                        $status = $data_absensi_siswa[$jurnal['id']] ?? '-';
                                        
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
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
// 3. Panggil footer
require_once '../includes/footer.php';
?>