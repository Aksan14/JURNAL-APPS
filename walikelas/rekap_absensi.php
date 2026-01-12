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
// ... (Inisialisasi variabel) ...
$id_kelas_target = null;

// HAPUS Inisialisasi total harian (karena akan diambil dari SQL)
// $total_harian_h = 0; ... (dll)

// ... (Logika 2 & 3: Ambil ID Guru & Tentukan Kelas Target) ...

// 4. Tentukan Tanggal Filter
$tanggal_filter = $_GET['tanggal'] ?? date('Y-m-d');


// 5. LOGIKA UTAMA: Ambil data hanya jika kelas target sudah ditentukan
$totals_harian = ['total_harian_h' => 0, 'total_harian_s' => 0, 'total_harian_i' => 0, 'total_harian_a' => 0]; // Default

if ($id_kelas_target) {
    try {
        // A. Ambil daftar siswa di kelasnya
        $stmt_siswa = $pdo->prepare("SELECT id, nis, nama_siswa FROM tbl_siswa WHERE id_kelas = ? ORDER BY nama_siswa ASC");
        $stmt_siswa->execute([$id_kelas_target]);
        $daftar_siswa = $stmt_siswa->fetchAll();

        // B. Ambil daftar jurnal/mapel yang ada di kelas itu pada tanggal yg difilter
        $stmt_jurnal = $pdo->prepare("SELECT j.id, j.jam_ke, mp.nama_mapel FROM tbl_jurnal j JOIN tbl_mengajar m ON j.id_mengajar = m.id JOIN tbl_mapel mp ON m.id_mapel = mp.id WHERE m.id_kelas = ? AND j.tanggal = ? ORDER BY j.jam_ke ASC");
        $stmt_jurnal->execute([$id_kelas_target, $tanggal_filter]);
        $daftar_jurnal_hari_ini = $stmt_jurnal->fetchAll();

        // C. Ambil SEMUA data absensi mentah (untuk tabel pivot)
        $stmt_absensi = $pdo->prepare("SELECT p.id_siswa, p.id_jurnal, p.status_kehadiran FROM tbl_presensi_siswa p JOIN tbl_jurnal j ON p.id_jurnal = j.id JOIN tbl_mengajar m ON j.id_mengajar = m.id WHERE m.id_kelas = ? AND j.tanggal = ?");
        $stmt_absensi->execute([$id_kelas_target, $tanggal_filter]);
        $semua_absensi = $stmt_absensi->fetchAll();
        
        // ==========================================================
        // D. KODE BARU: AMBIL TOTAL HARIAN (LANGSUNG DARI SQL)
        // Ini menggantikan loop foreach di PHP
        // ==========================================================
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
        // ==========================================================
        // AKHIR KODE BARU
        // ==========================================================

        // E. Ubah format array $semua_absensi agar mudah diakses di tabel
        foreach ($semua_absensi as $absen) {
            $data_absensi[$absen['id_siswa']][$absen['id_jurnal']] = $absen['status_kehadiran'];
        }

    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Gagal mengambil data: " . $e->getMessage() . "</div>";
    }
} else {
    // ... (sisa logika pesan error) ...
}
// --- PHP LOGIC (END) ---
?>

<div class="container-fluid">
    <h3>Rekap Absensi Kelas
        <?php if ($nama_kelas_target): ?>
            : <?php echo htmlspecialchars($nama_kelas_target); ?>
        <?php endif; ?>
    </h3>
    <hr>

    <div class="card mb-3 bg-light border-secondary">
        </div>
    
    <?php echo $message; ?>

    <?php if ($id_kelas_target && !empty($daftar_jurnal_hari_ini)): ?>
        <div class="card mb-3 bg-light">
            <div class="card-body p-3">
                <h5 class="card-title mb-2">Total Absensi Kelas (Semua Mapel) - <?php echo htmlspecialchars(date('d F Y', strtotime($tanggal_filter))); ?></h5>
                <p class="mb-0">
                    <span class="badge bg-success fs-6 me-2">Total Hadir: <?php echo $totals_harian['total_harian_h'] ?? 0; ?></span>
                    <span class="badge bg-warning text-dark fs-6 me-2">Total Sakit: <?php echo $totals_harian['total_harian_s'] ?? 0; ?></span>
                    <span class="badge bg-info text-dark fs-6 me-2">Total Izin: <?php echo $totals_harian['total_harian_i'] ?? 0; ?></span>
                    <span class="badge bg-danger fs-6 me-2">Total Alfa: <?php echo $totals_harian['total_harian_a'] ?? 0; ?></span>
                </p>
            </div>
        </div>
    <?php endif; ?>
    </div>
<?php
// 6. Panggil footer
require_once '../includes/footer.php';
?>