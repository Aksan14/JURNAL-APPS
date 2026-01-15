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

    // Ambil id_kelas dari id_mengajar untuk validasi libur per kelas
    $stmt_kelas_awal = $pdo->prepare("SELECT id_kelas FROM tbl_mengajar WHERE id = ?");
    $stmt_kelas_awal->execute([$id_mengajar]);
    $id_kelas_jurnal = $stmt_kelas_awal->fetchColumn();

    // ============================================
    // VALIDASI HARI LIBUR (Umum atau Khusus Kelas)
    // ============================================
    $stmt_libur = $pdo->prepare("
        SELECT nama_libur, jenis, id_kelas FROM tbl_hari_libur 
        WHERE tanggal = ? AND (id_kelas IS NULL OR id_kelas = ?)
        ORDER BY id_kelas DESC LIMIT 1
    ");
    $stmt_libur->execute([$tanggal, $id_kelas_jurnal]);
    $hari_libur = $stmt_libur->fetch();
    
    if ($hari_libur) {
        $jenis_libur = ucfirst(str_replace('_', ' ', $hari_libur['jenis']));
        $keterangan_kelas = $hari_libur['id_kelas'] ? ' (Khusus kelas ini)' : '';
        $message = "<div class='alert alert-warning alert-dismissible fade show'>
            <strong><i class='fas fa-calendar-times me-1'></i> Tanggal Libur!</strong> 
            Tanggal <strong>" . date('d/m/Y', strtotime($tanggal)) . "</strong> adalah <strong>{$hari_libur['nama_libur']}</strong> ({$jenis_libur}){$keterangan_kelas}.
            <br>Anda tidak perlu mengisi jurnal pada hari libur.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {

    // Validasi: Cek apakah tanggal tidak melebihi batas mundur
    $tanggal_input = new DateTime($tanggal);
    $tanggal_hari_ini = new DateTime(date('Y-m-d'));
    $selisih_hari = $tanggal_hari_ini->diff($tanggal_input)->days;
    $is_past = $tanggal_input < $tanggal_hari_ini;
    
    // Cek apakah ada izin approved untuk tanggal ini
    $has_approval = false;
    if ($is_past && $selisih_hari > MAX_HARI_MUNDUR_JURNAL) {
        $stmt_approval = $pdo->prepare("
            SELECT id FROM tbl_request_jurnal_mundur 
            WHERE id_guru = ? AND id_mengajar = ? AND tanggal_jurnal = ? AND status = 'approved'
        ");
        $stmt_approval->execute([$id_guru_login, $id_mengajar, $tanggal]);
        $has_approval = $stmt_approval->fetch() ? true : false;
    }
    
    if ($is_past && $selisih_hari > MAX_HARI_MUNDUR_JURNAL && !$has_approval) {
        $batas_tanggal = date('d/m/Y', strtotime('-' . MAX_HARI_MUNDUR_JURNAL . ' days'));
        $message = "<div class='alert alert-danger alert-dismissible fade show'>
            <strong>Gagal!</strong> Anda tidak dapat mengisi jurnal untuk tanggal <strong>" . date('d/m/Y', strtotime($tanggal)) . "</strong>.
            <br>Batas maksimal mundur adalah <strong>" . MAX_HARI_MUNDUR_JURNAL . " hari</strong> dari hari ini (minimal tanggal <strong>{$batas_tanggal}</strong>).
            <br><small class='text-muted'>Silakan ajukan permintaan izin ke admin melalui menu 'Minta Izin Tanggal Lain' di form jurnal.</small>
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {

    try {
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

            $stmt_jam_khusus = $pdo->prepare("
                SELECT max_jam, alasan FROM tbl_jam_khusus 
                WHERE tanggal = ? AND (id_kelas IS NULL OR id_kelas = ?)
                ORDER BY id_kelas DESC LIMIT 1
            ");
            $stmt_jam_khusus->execute([$tanggal, $id_kelas]);
            $jam_khusus = $stmt_jam_khusus->fetch();
            $max_jam_hari_ini = $jam_khusus ? (int)$jam_khusus['max_jam'] : MAX_JAM_PER_HARI;
            $alasan_jam_khusus = $jam_khusus ? $jam_khusus['alasan'] : null;

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

            // Validasi: total jam tidak boleh melebihi batas maksimal (dengan mempertimbangkan jam khusus)
            if (($total_jam_terisi + $jam_akan_diinput) > $max_jam_hari_ini) {
                $sisa_jam = $max_jam_hari_ini - $total_jam_terisi;
                $info_khusus = $alasan_jam_khusus ? "<br><small class='text-info'><i class='fas fa-info-circle'></i> {$alasan_jam_khusus}: Maksimal {$max_jam_hari_ini} jam untuk tanggal ini</small>" : "";
                $message = "<div class='alert alert-danger alert-dismissible fade show'>
                    <strong>Gagal!</strong> Total jam pelajaran untuk kelas ini pada tanggal tersebut akan melebihi batas maksimal {$max_jam_hari_ini} jam.{$info_khusus}
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
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Gagal menyimpan: " . $e->getMessage() . "</div>";
    }
    } // End validasi batas mundur tanggal
    } // End validasi hari libur
}

// Ambil data Kelas & Mapel yang diajar (termasuk hari jadwal)
$stmt_ajar = $pdo->prepare("
    SELECT m.id, m.id_kelas, m.hari, m.jam_ke as jam_jadwal, k.nama_kelas, mp.nama_mapel 
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

// Ambil tanggal yang sudah di-approve untuk guru ini
$stmt_approved_dates = $pdo->prepare("
    SELECT r.id_mengajar, r.tanggal_jurnal 
    FROM tbl_request_jurnal_mundur r
    WHERE r.id_guru = ? AND r.status = 'approved'
    AND r.tanggal_jurnal NOT IN (
        SELECT tanggal FROM tbl_jurnal WHERE id_mengajar = r.id_mengajar
    )
");
$stmt_approved_dates->execute([$id_guru_login]);
$approved_dates = $stmt_approved_dates->fetchAll();

// Format approved dates untuk JavaScript
$approved_dates_map = [];
foreach ($approved_dates as $ad) {
    $approved_dates_map[$ad['id_mengajar']][] = $ad['tanggal_jurnal'];
}

// ============================================
// AMBIL DATA HARI LIBUR & JAM KHUSUS UNTUK JS
// ============================================
// Ambil semua kelas yang diajar guru ini
$kelas_guru_ids = array_unique(array_filter(array_column($daftar_mengajar, 'id_kelas')));
$libur_data = [];
$jam_khusus_data = [];

// Hari libur (30 hari ke belakang s/d 7 hari ke depan) - termasuk libur umum (id_kelas IS NULL)
if (!empty($kelas_guru_ids)) {
    $placeholders = implode(',', array_fill(0, count($kelas_guru_ids), '?'));
    $stmt_libur_js = $pdo->prepare("
        SELECT tanggal, nama_libur, jenis, id_kelas FROM tbl_hari_libur 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND tanggal <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND (id_kelas IS NULL OR id_kelas IN ($placeholders))
    ");
    $stmt_libur_js->execute($kelas_guru_ids);
} else {
    // Jika tidak ada kelas, ambil hanya libur umum
    $stmt_libur_js = $pdo->prepare("
        SELECT tanggal, nama_libur, jenis, id_kelas FROM tbl_hari_libur 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND tanggal <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND id_kelas IS NULL
    ");
    $stmt_libur_js->execute();
}
while ($row = $stmt_libur_js->fetch()) {
    $libur_data[] = [
        'tanggal' => $row['tanggal'],
        'nama' => $row['nama_libur'],
        'jenis' => $row['jenis'],
        'id_kelas' => $row['id_kelas']
    ];
}

// Jam khusus
if (!empty($kelas_guru_ids)) {
    $placeholders = implode(',', array_fill(0, count($kelas_guru_ids), '?'));
    $stmt_jam_js = $pdo->prepare("
        SELECT tanggal, max_jam, alasan, id_kelas FROM tbl_jam_khusus 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND tanggal <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND (id_kelas IS NULL OR id_kelas IN ($placeholders))
    ");
    $stmt_jam_js->execute($kelas_guru_ids);
} else {
    $stmt_jam_js = $pdo->prepare("
        SELECT tanggal, max_jam, alasan, id_kelas FROM tbl_jam_khusus 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND tanggal <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND id_kelas IS NULL
    ");
    $stmt_jam_js->execute();
}
while ($row = $stmt_jam_js->fetch()) {
    $jam_khusus_data[] = [
        'tanggal' => $row['tanggal'],
        'max_jam' => (int)$row['max_jam'],
        'alasan' => $row['alasan'],
        'id_kelas' => $row['id_kelas']
    ];
}

// Generate tanggal mundur untuk setiap hari jadwal (untuk modal request)
// Tanggal dimulai dari 8 hari lalu sampai 90 hari lalu
$tanggal_mundur_per_hari = [];
$hari_map = ['Minggu' => 0, 'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];
$nama_hari_indo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$nama_bulan_indo = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

foreach ($hari_map as $nama_hari => $index_hari) {
    $tanggal_mundur_per_hari[$nama_hari] = [];
    
    // Mulai dari 8 hari lalu
    $start = new DateTime();
    $start->modify('-' . (MAX_HARI_MUNDUR_JURNAL + 1) . ' days');
    
    // Sampai 90 hari lalu
    $end = new DateTime();
    $end->modify('-90 days');
    
    $current = clone $start;
    $count = 0;
    
    while ($current >= $end && $count < 12) {
        if ((int)$current->format('w') === $index_hari) {
            $tanggal_mundur_per_hari[$nama_hari][] = [
                'value' => $current->format('Y-m-d'),
                'label' => $nama_hari_indo[(int)$current->format('w')] . ', ' . 
                          (int)$current->format('d') . ' ' . 
                          $nama_bulan_indo[(int)$current->format('n')] . ' ' . 
                          $current->format('Y')
            ];
            $count++;
        }
        $current->modify('-1 day');
    }
}

// Cek apakah ada permintaan yang baru di-approve atau di-reject (belum dilihat)
$stmt_notif_request = $pdo->prepare("
    SELECT r.*, k.nama_kelas, mp.nama_mapel 
    FROM tbl_request_jurnal_mundur r
    JOIN tbl_mengajar m ON r.id_mengajar = m.id
    JOIN tbl_kelas k ON m.id_kelas = k.id
    JOIN tbl_mapel mp ON m.id_mapel = mp.id
    WHERE r.id_guru = ? AND r.status IN ('approved', 'rejected') 
    AND (r.notified_guru IS NULL OR r.notified_guru = 0)
    ORDER BY r.updated_at DESC
");
$stmt_notif_request->execute([$id_guru_login]);
$notif_requests = $stmt_notif_request->fetchAll();

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
    
    /* Fix modal scroll */
    #modalIsiJurnal .modal-content {
        display: flex;
        flex-direction: column;
    }
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
    
    // Tampilkan notifikasi permintaan yang sudah diproses
    if (count($notif_requests) > 0): ?>
        <?php foreach ($notif_requests as $notif): ?>
            <?php if ($notif['status'] == 'approved'): ?>
                <div class="alert alert-success alert-dismissible fade show" data-notif-id="<?= $notif['id'] ?>">
                    <strong><i class="fas fa-check-circle"></i> Permintaan Disetujui!</strong><br>
                    Permintaan Anda untuk mengisi jurnal tanggal <strong><?= date('d/m/Y', strtotime($notif['tanggal_jurnal'])) ?></strong> 
                    (<?= htmlspecialchars($notif['nama_kelas']) ?> - <?= htmlspecialchars($notif['nama_mapel']) ?>) telah <span class="text-success fw-bold">DISETUJUI</span> oleh admin.
                    <?php if (!empty($notif['catatan_admin'])): ?>
                        <br><small class="text-muted"><i class="fas fa-comment"></i> Catatan admin: <?= htmlspecialchars($notif['catatan_admin']) ?></small>
                    <?php endif; ?>
                    <br><small>Silakan pilih tanggal tersebut di dropdown "Tanggal Disetujui Admin" saat mengisi jurnal.</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" onclick="markNotifRead(<?= $notif['id'] ?>)"></button>
                </div>
            <?php else: ?>
                <div class="alert alert-danger alert-dismissible fade show" data-notif-id="<?= $notif['id'] ?>">
                    <strong><i class="fas fa-times-circle"></i> Permintaan Ditolak</strong><br>
                    Permintaan Anda untuk mengisi jurnal tanggal <strong><?= date('d/m/Y', strtotime($notif['tanggal_jurnal'])) ?></strong> 
                    (<?= htmlspecialchars($notif['nama_kelas']) ?> - <?= htmlspecialchars($notif['nama_mapel']) ?>) telah <span class="text-danger fw-bold">DITOLAK</span> oleh admin.
                    <?php if (!empty($notif['catatan_admin'])): ?>
                        <br><small><i class="fas fa-comment"></i> Alasan: <?= htmlspecialchars($notif['catatan_admin']) ?></small>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" onclick="markNotifRead(<?= $notif['id'] ?>)"></button>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif;
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
    <div class="modal-dialog modal-xl-custom">
        <div class="modal-content" style="max-height: 90vh;">
            <div class="modal-header bg-primary text-white" style="flex-shrink: 0;">
                <h5 class="modal-title" id="modalIsiJurnalLabel">
                    <i class="fas fa-edit me-2"></i>Formulir Jurnal Pembelajaran
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="isi_jurnal.php" method="POST" id="formIsiJurnal" style="display: flex; flex-direction: column; overflow: hidden; flex: 1;">
                <div class="modal-body" style="overflow-y: auto; flex: 1;">
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
                            <div id="info-hari-jadwal" class="alert alert-primary mb-3" style="display: none;">
                                <i class="fas fa-calendar-day me-1"></i>
                                <strong>Jadwal: Hari <span id="nama-hari-jadwal"></span></strong>
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
                                    <select class="form-select" id="tanggal" name="tanggal" required disabled>
                                        <option value="">-- Pilih Kelas & Mapel dulu --</option>
                                    </select>
                                    <small id="tanggal-info" class="text-muted">
                                        <i class="fas fa-info-circle"></i> Pilih kelas & mapel untuk melihat tanggal yang tersedia
                                    </small>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label for="jam_ke" class="form-label fw-bold">Jam Ke-</label>
                                    <input type="text" class="form-control bg-light" id="jam_ke" name="jam_ke" readonly required>
                                    <small class="text-muted"><i class="fas fa-info-circle"></i> Otomatis sesuai jadwal mengajar</small>
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
                <div class="modal-footer" style="flex-shrink: 0; border-top: 1px solid #dee2e6;">
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
// Konstanta batas mundur dari PHP
const MAX_HARI_MUNDUR = <?= MAX_HARI_MUNDUR_JURNAL ?>;

// Tanggal yang sudah di-approve dari database
const approvedDates = <?= json_encode($approved_dates_map) ?>;

// Data tanggal mundur per hari dari PHP (untuk modal request)
const tanggalMundurPerHari = <?= json_encode($tanggal_mundur_per_hari) ?>;

// Data hari libur dari PHP
const dataHariLibur = <?= json_encode($libur_data) ?>;

// Data jam khusus dari PHP
const dataJamKhusus = <?= json_encode($jam_khusus_data) ?>;

// Mapping id_mengajar ke id_kelas
const mengajarToKelas = <?= json_encode(array_column($daftar_mengajar, 'id_kelas', 'id')) ?>;

// Mapping hari ke index (0=Minggu, 1=Senin, dst)
const hariToIndex = {
    'Minggu': 0, 'Senin': 1, 'Selasa': 2, 'Rabu': 3,
    'Kamis': 4, 'Jumat': 5, 'Sabtu': 6
};

const namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const namaBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Variable global untuk menyimpan hari jadwal saat ini
let currentHariJadwal = '';
let currentIdMengajar = '';

// Fungsi untuk cek apakah tanggal adalah hari libur
function isHariLibur(dateStr, idKelas) {
    for (const libur of dataHariLibur) {
        if (libur.tanggal === dateStr) {
            // Libur umum (id_kelas = null/undefined) atau libur khusus kelas ini
            if (libur.id_kelas === null || libur.id_kelas === undefined || String(libur.id_kelas) === String(idKelas)) {
                return libur;
            }
        }
    }
    return null;
}

// Fungsi untuk cek jam khusus
function getJamKhusus(dateStr, idKelas) {
    for (const jk of dataJamKhusus) {
        if (jk.tanggal === dateStr) {
            if (jk.id_kelas === null || jk.id_kelas == idKelas) {
                return jk;
            }
        }
    }
    return null;
}

// Fungsi untuk populate dropdown tanggal request mundur (HARUS DIDEFINISIKAN LEBIH AWAL)
function populateTanggalRequestDropdown(hariJadwal) {
    const select = document.getElementById('tanggal_request');
    select.innerHTML = '<option value="">-- Pilih Tanggal --</option>';
    
    console.log('Populating tanggal request - Hari:', hariJadwal);
    console.log('Available data:', tanggalMundurPerHari);
    
    if (!hariJadwal || !tanggalMundurPerHari[hariJadwal]) {
        console.log('No data available for', hariJadwal);
        return;
    }
    
    const tanggalList = tanggalMundurPerHari[hariJadwal];
    console.log('Tanggal list:', tanggalList);
    
    if (tanggalList.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Tidak ada tanggal tersedia';
        option.disabled = true;
        select.appendChild(option);
        return;
    }
    
    tanggalList.forEach(function(item) {
        const option = document.createElement('option');
        option.value = item.value;
        option.textContent = item.label;
        select.appendChild(option);
    });
    
    console.log('Dropdown populated with', tanggalList.length, 'options');
}

// Helper function untuk format date ke YYYY-MM-DD (local timezone)
function formatDateToYMD(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Fungsi untuk generate tanggal berdasarkan hari jadwal
function generateTanggalOptions(hariJadwal, idMengajar) {
    const selectTanggal = document.getElementById('tanggal');
    const tanggalInfo = document.getElementById('tanggal-info');
    const hariIndex = hariToIndex[hariJadwal];
    currentHariJadwal = hariJadwal;
    currentIdMengajar = idMengajar;
    
    // Get id_kelas dari idMengajar
    const idKelas = mengajarToKelas[idMengajar];
    
    // Clear existing options
    selectTanggal.innerHTML = '';
    
    // Get date range (7 hari ke belakang sampai hari ini)
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const minDate = new Date(today);
    minDate.setDate(minDate.getDate() - MAX_HARI_MUNDUR);
    
    // Collect valid dates (hanya tanggal yang sesuai hari jadwal)
    const validDates = [];
    const liburDates = [];
    let checkDate = new Date(today);
    
    // Loop dari hari ini ke belakang sampai batas mundur
    while (checkDate >= minDate) {
        const dateStr = formatDateToYMD(checkDate);
        
        // Filter: hanya tampilkan tanggal yang harinya sesuai jadwal
        if (checkDate.getDay() === hariIndex) {
            const libur = isHariLibur(dateStr, idKelas);
            
            if (libur) {
                // Simpan info libur untuk ditampilkan
                liburDates.push({date: new Date(checkDate), info: libur});
            } else {
                validDates.push(new Date(checkDate));
            }
        }
        checkDate.setDate(checkDate.getDate() - 1);
    }
    
    // Add options (sudah terurut dari terbaru)
    selectTanggal.innerHTML = '<option value="">-- Pilih Tanggal --</option>';
    
    // Jika tidak ada tanggal valid sama sekali (semua libur)
    if (validDates.length === 0 && liburDates.length > 0) {
        const warnOption = document.createElement('option');
        warnOption.value = '';
        warnOption.textContent = '⚠️ Semua tanggal dalam range adalah hari libur';
        warnOption.disabled = true;
        warnOption.style.color = '#dc3545';
        selectTanggal.appendChild(warnOption);
    }
    
    let firstValidSelected = false;
    if (validDates.length > 0) {
        validDates.forEach((date, index) => {
            const dateStr = formatDateToYMD(date);
            const dayName = namaHari[date.getDay()];
            const displayDate = date.getDate() + ' ' + namaBulan[date.getMonth()] + ' ' + date.getFullYear();
            
            // Cek jam khusus
            const jamKhusus = getJamKhusus(dateStr, idKelas);
            
            const option = document.createElement('option');
            option.value = dateStr;
            option.textContent = dayName + ', ' + displayDate;
            if (jamKhusus) {
                option.textContent += ' ⚠️ ' + jamKhusus.alasan + ' (Maks ' + jamKhusus.max_jam + ' Jam)';
                option.style.color = '#856404';
            }
            
            // Set tanggal pertama yang valid sebagai default
            if (!firstValidSelected) {
                option.selected = true;
                firstValidSelected = true;
            }
            
            selectTanggal.appendChild(option);
        });
    }
    
    // Tambahkan info hari libur (disabled)
    if (liburDates.length > 0) {
        const liburGroup = document.createElement('optgroup');
        liburGroup.label = '🔴 Hari Libur (Tidak Tersedia)';
        
        liburDates.forEach(item => {
            const date = item.date;
            const dayName = namaHari[date.getDay()];
            const displayDate = date.getDate() + ' ' + namaBulan[date.getMonth()] + ' ' + date.getFullYear();
            
            const option = document.createElement('option');
            option.value = '';
            option.textContent = '🔴 ' + dayName + ', ' + displayDate + ' - ' + item.info.nama;
            option.disabled = true;
            option.style.color = '#dc3545';
            liburGroup.appendChild(option);
        });
        
        selectTanggal.appendChild(liburGroup);
    }
    
    // Cek apakah ada tanggal yang sudah di-approve untuk id_mengajar ini
    if (approvedDates[idMengajar] && approvedDates[idMengajar].length > 0) {
        const approvedGroup = document.createElement('optgroup');
        approvedGroup.label = '✅ Tanggal Disetujui Admin';
        
        approvedDates[idMengajar].forEach(dateStr => {
            const date = new Date(dateStr);
            const dayName = namaHari[date.getDay()];
            const displayDate = date.getDate() + ' ' + namaBulan[date.getMonth()] + ' ' + date.getFullYear();
            
            const option = document.createElement('option');
            option.value = dateStr;
            option.textContent = '✅ ' + dayName + ', ' + displayDate;
            option.style.color = 'green';
            approvedGroup.appendChild(option);
        });
        
        selectTanggal.appendChild(approvedGroup);
    }
    
    // Tambahkan opsi untuk request tanggal lebih lama
    const optGroup = document.createElement('optgroup');
    optGroup.label = '── Tanggal Lebih Lama ──';
    const requestOption = document.createElement('option');
    requestOption.value = 'request_older';
    requestOption.textContent = '📝 Minta Izin Tanggal Lain...';
    optGroup.appendChild(requestOption);
    selectTanggal.appendChild(optGroup);
    
    selectTanggal.disabled = false;
    
    let infoText = '<i class="fas fa-check-circle text-success"></i> ' + validDates.length + ' tanggal tersedia';
    if (liburDates.length > 0) {
        infoText += ' <span class="text-danger">(' + liburDates.length + ' hari libur)</span>';
    }
    if (approvedDates[idMengajar] && approvedDates[idMengajar].length > 0) {
        infoText += ' + <span class="text-success">' + approvedDates[idMengajar].length + ' tanggal disetujui</span>';
    }
    tanggalInfo.innerHTML = infoText;
    
    // Trigger validasi dan cek jam kelas setelah tanggal di-generate
    setTimeout(() => {
        validasiTanggalHari();
        cekJamKelas();
    }, 100);
}

// Update dropdown tanggal berdasarkan pilihan kelas/mapel
document.getElementById('id_mengajar').addEventListener('change', function() {
    const idMengajar = this.value;
    const selectTanggal = document.getElementById('tanggal');
    const tanggalInfo = document.getElementById('tanggal-info');
    
    if (!idMengajar) {
        selectTanggal.innerHTML = '<option value="">-- Pilih Kelas & Mapel dulu --</option>';
        selectTanggal.disabled = true;
        tanggalInfo.innerHTML = '<i class="fas fa-info-circle"></i> Pilih kelas & mapel untuk melihat tanggal yang tersedia';
        return;
    }
    
    // Get hari jadwal dari selected option
    const selectedOption = this.options[this.selectedIndex];
    const hariJadwal = selectedOption.getAttribute('data-hari');
    
    // Generate tanggal options
    generateTanggalOptions(hariJadwal, idMengajar);
});

// Event listener untuk tanggal - cek jurnal yang sudah diisi atau request older
document.getElementById('tanggal').addEventListener('change', function() {
    const tanggal = this.value;
    const selectMengajar = document.getElementById('id_mengajar');
    
    if (!selectMengajar.value) return;
    
    // Jika pilih "Minta Izin Tanggal Lain"
    if (tanggal === 'request_older') {
        // Simpan info kelas/mapel SEBELUM reset pilihan
        const selectedOpt = selectMengajar.options[selectMengajar.selectedIndex];
        const savedHari = selectedOpt.getAttribute('data-hari');
        const savedIdMengajar = selectMengajar.value;
        const savedMapelText = selectedOpt.textContent;
        
        // Reset pilihan tanggal ke kosong
        this.value = '';
        
        // Panggil fungsi untuk buka modal (fungsi didefinisikan di bawah)
        bukaModalRequestMundur(savedIdMengajar, savedHari, savedMapelText);
        return;
    }
    
    if (!tanggal) return;
    
    // Panggil cekJamKelas SETELAH tanggal dipilih
    cekJamKelas();
    
    // Ambil data jurnal yang sudah diisi pada tanggal tersebut
    fetch(`ajax_get_jurnal_by_date.php?tanggal=${tanggal}`)
        .then(response => response.json())
        .then(data => {
            if (data.sudah_isi && data.sudah_isi.includes(parseInt(selectMengajar.value))) {
                // Tandai tanggal ini sudah diisi - tampilkan toast
                this.classList.add('is-invalid');
                showToast('warning', 'Perhatian', 'Jurnal untuk tanggal ini sudah diisi!');
            } else {
                this.classList.remove('is-invalid');
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
    const formInputs = document.querySelectorAll('#formIsiJurnal input:not([type="hidden"]), #formIsiJurnal textarea, #formIsiJurnal select');
    
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
            
            const progressBar = document.getElementById('jam-progress-bar');
            const statusBadge = document.getElementById('jam-status-badge');
            const warningDiv = document.getElementById('jam-warning');
            
            // ============================================
            // CEK JIKA HARI LIBUR - DISABLE SEMUA FORM
            // ============================================
            if (data.is_libur) {
                document.getElementById('jam-terisi').textContent = '-';
                document.getElementById('jam-max').textContent = '-';
                document.getElementById('jam-sisa').textContent = '-';
                
                progressBar.style.width = '100%';
                progressBar.className = 'progress-bar bg-danger';
                statusBadge.className = 'badge bg-danger';
                statusBadge.innerHTML = '<i class="fas fa-calendar-times me-1"></i> HARI LIBUR';
                infoContainer.className = 'alert alert-danger mb-3';
                
                warningDiv.style.display = 'block';
                warningDiv.innerHTML = `<i class="fas fa-ban me-1"></i> <strong>TIDAK DAPAT MENGISI JURNAL!</strong><br>
                    Tanggal <strong>${formatTanggalIndo(data.tanggal)}</strong> adalah <strong>${data.nama_libur}</strong> (${data.jenis_libur})${data.keterangan_kelas}.<br>
                    <small class="text-muted">Silakan pilih tanggal lain yang bukan hari libur.</small>`;
                
                // Disable submit button
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-secondary');
                submitBtn.classList.remove('btn-primary');
                submitBtn.innerHTML = '<i class="fas fa-ban me-1"></i> Tidak Dapat Menyimpan (Hari Libur)';
                
                // Disable form inputs kecuali dropdown kelas dan tanggal
                document.getElementById('topik_materi').disabled = true;
                document.getElementById('catatan_guru').disabled = true;
                document.querySelectorAll('input[name^="absensi"]').forEach(el => el.disabled = true);
                
                return;
            }
            
            // ============================================
            // CEK JIKA JAM SUDAH PENUH (Pulang Cepat)
            // ============================================
            if (data.is_jam_penuh) {
                document.getElementById('jam-terisi').textContent = data.total_jam_terisi;
                document.getElementById('jam-max').textContent = data.max_jam;
                document.getElementById('jam-sisa').textContent = 0;
                
                progressBar.style.width = '100%';
                progressBar.className = 'progress-bar bg-danger';
                statusBadge.className = 'badge bg-danger';
                statusBadge.innerHTML = '<i class="fas fa-clock me-1"></i> JAM PENUH';
                infoContainer.className = 'alert alert-danger mb-3';
                
                const infoJamKhusus = data.jam_khusus ? `<br><small class="text-warning"><i class="fas fa-info-circle"></i> ${data.jam_khusus}: Maksimal ${data.max_jam} jam pada tanggal ini</small>` : '';
                warningDiv.style.display = 'block';
                warningDiv.innerHTML = `<i class="fas fa-ban me-1"></i> <strong>TIDAK DAPAT MENGISI JURNAL!</strong><br>
                    Jam pelajaran untuk kelas ini pada tanggal tersebut sudah penuh (${data.total_jam_terisi}/${data.max_jam} jam).${infoJamKhusus}<br>
                    <small class="text-muted">Silakan pilih tanggal lain.</small>`;
                
                // Disable submit button
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-secondary');
                submitBtn.classList.remove('btn-primary');
                submitBtn.innerHTML = '<i class="fas fa-ban me-1"></i> Tidak Dapat Menyimpan (Jam Penuh)';
                
                // Disable form inputs kecuali dropdown kelas dan tanggal
                document.getElementById('topik_materi').disabled = true;
                document.getElementById('catatan_guru').disabled = true;
                document.querySelectorAll('input[name^="absensi"]').forEach(el => el.disabled = true);
                
                return;
            }
            
            // ============================================
            // NORMAL - Enable form
            // ============================================
            // Enable form inputs
            document.getElementById('topik_materi').disabled = false;
            document.getElementById('catatan_guru').disabled = false;
            document.querySelectorAll('input[name^="absensi"]').forEach(el => el.disabled = false);
            
            const persen = Math.min(100, (data.total_jam_terisi / data.max_jam) * 100);
            
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
            } else if (data.jam_khusus) {
                progressBar.className = 'progress-bar bg-warning';
                statusBadge.className = 'badge bg-warning text-dark';
                statusBadge.textContent = data.jam_khusus;
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
                submitBtn.innerHTML = '<i class="fas fa-save me-1"></i> Simpan Jurnal & Absensi';
            } else {
                warningDiv.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-secondary');
                submitBtn.classList.add('btn-primary');
                submitBtn.innerHTML = '<i class="fas fa-save me-1"></i> Simpan Jurnal & Absensi';
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Helper function untuk format tanggal
function formatTanggalIndo(dateStr) {
    const date = new Date(dateStr);
    const hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return hari[date.getDay()] + ', ' + date.getDate() + ' ' + bulan[date.getMonth()] + ' ' + date.getFullYear();
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
    const submitBtn = document.querySelector('button[name="simpan_jurnal"]');
    
    // Reset form inputs ke enabled state terlebih dahulu
    document.getElementById('topik_materi').disabled = false;
    document.getElementById('catatan_guru').disabled = false;
    document.querySelectorAll('input[name^="absensi"]').forEach(el => el.disabled = false);
    submitBtn.innerHTML = '<i class="fas fa-save me-1"></i> Simpan Jurnal & Absensi';
    
    const selectedOption = selectMengajar.options[selectMengajar.selectedIndex];
    
    // Jika belum pilih kelas, sembunyikan info
    if (!selectMengajar.value) {
        infoHari.style.display = 'none';
        inputTanggal.classList.remove('is-invalid');
        submitBtn.disabled = false;
        submitBtn.classList.remove('btn-secondary');
        submitBtn.classList.add('btn-primary');
        return true;
    }
    
    const hariJadwal = selectedOption.getAttribute('data-hari');
    const jamJadwal = selectedOption.getAttribute('data-jam');
    
    // Tampilkan info hari jadwal
    infoHari.style.display = 'block';
    namaHariSpan.textContent = hariJadwal + ' (Jam ' + jamJadwal + ')';
    
    // Set jam ke otomatis dari jadwal (selalu update)
    document.getElementById('jam_ke').value = jamJadwal;
    
    // Tanggal sudah pasti sesuai karena di-generate berdasarkan hari jadwal
    inputTanggal.classList.remove('is-invalid');
    submitBtn.disabled = false;
    submitBtn.classList.remove('btn-secondary');
    submitBtn.classList.add('btn-primary');
    return true;
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

// Fungsi untuk menandai notifikasi sudah dibaca
function markNotifRead(notifId) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax_request_jurnal_mundur.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('action=mark_read&request_id=' + notifId);
}

// Auto open modal jika ada parameter open_modal
<?php if (isset($_GET['open_modal']) && $_GET['open_modal'] == '1'): ?>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('modalIsiJurnal'));
    modal.show();
});
<?php endif; ?>
</script>

<!-- Toast Container untuk Notifikasi -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
    <div id="toastNotification" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header" id="toastHeader">
            <i class="fas fa-bell me-2" id="toastIcon"></i>
            <strong class="me-auto" id="toastTitle">Notifikasi</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<!-- Modal Request Jurnal Mundur -->
<div class="modal fade" id="modalRequestMundur" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-alt me-2"></i>Minta Izin Jurnal Mundur
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle me-1"></i>
                    Anda akan mengirim permintaan ke admin untuk mengisi jurnal lebih dari <?= MAX_HARI_MUNDUR_JURNAL ?> hari yang lalu.
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Kelas & Mata Pelajaran</label>
                    <div class="form-control bg-light" id="request-info-mapel">-</div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Hari Jadwal</label>
                    <div class="form-control bg-light" id="request-info-hari">-</div>
                </div>
                
                <div class="mb-3">
                    <label for="tanggal_request" class="form-label fw-bold">Tanggal yang Diminta <span class="text-danger">*</span></label>
                    <select class="form-select" id="tanggal_request" required>
                        <option value="">-- Pilih Tanggal --</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="alasan_request" class="form-label fw-bold">Alasan <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="alasan_request" rows="3" required 
                              placeholder="Jelaskan mengapa Anda perlu mengisi jurnal untuk tanggal tersebut..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-warning" id="btn-submit-request" onclick="kirimPermintaanMundur()">
                    <i class="fas fa-paper-plane me-1"></i>Kirim Permintaan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Variable untuk menyimpan ID Mengajar -->
<script>
var requestIdMengajar = '';

// Fungsi untuk menampilkan toast notification
function showToast(type, title, message, autoHide = true) {
    var toast = document.getElementById('toastNotification');
    var header = document.getElementById('toastHeader');
    var icon = document.getElementById('toastIcon');
    var titleEl = document.getElementById('toastTitle');
    var body = document.getElementById('toastBody');
    
    // Reset classes
    header.className = 'toast-header';
    icon.className = 'fas me-2';
    
    // Set style berdasarkan type
    if (type === 'success') {
        header.classList.add('bg-success', 'text-white');
        icon.classList.add('fa-check-circle');
    } else if (type === 'error') {
        header.classList.add('bg-danger', 'text-white');
        icon.classList.add('fa-times-circle');
    } else if (type === 'warning') {
        header.classList.add('bg-warning', 'text-dark');
        icon.classList.add('fa-exclamation-triangle');
    } else {
        header.classList.add('bg-info', 'text-white');
        icon.classList.add('fa-info-circle');
    }
    
    titleEl.textContent = title;
    body.innerHTML = message;
    
    var bsToast = new bootstrap.Toast(toast, {
        autohide: autoHide,
        delay: autoHide ? 5000 : 999999
    });
    bsToast.show();
}

function kirimPermintaanMundur() {
    var idMengajar = requestIdMengajar;
    var tanggal = document.getElementById('tanggal_request').value;
    var alasan = document.getElementById('alasan_request').value.trim();
    
    // Validasi
    if (!idMengajar) {
        showToast('error', 'Error', 'ID Mengajar tidak ditemukan. Silakan tutup modal dan coba lagi.');
        return;
    }
    if (!tanggal) {
        showToast('warning', 'Peringatan', 'Pilih tanggal terlebih dahulu!');
        return;
    }
    if (!alasan) {
        showToast('warning', 'Peringatan', 'Isi alasan terlebih dahulu!');
        return;
    }
    
    // Disable button
    var btn = document.getElementById('btn-submit-request');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
    
    // Buat request
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax_request_jurnal_mundur.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Kirim Permintaan';
            
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Tutup modal dulu
                        var modal = bootstrap.Modal.getInstance(document.getElementById('modalRequestMundur'));
                        if (modal) modal.hide();
                        
                        // Tampilkan toast sukses
                        showToast('success', 'Berhasil!', '<i class="fas fa-check me-1"></i>' + response.message + '<br><small class="text-muted">Halaman akan dimuat ulang...</small>');
                        
                        // Reload setelah 2 detik
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showToast('error', 'Gagal', response.message);
                    }
                } catch (e) {
                    showToast('error', 'Error', 'Gagal memproses response dari server.<br><small>' + xhr.responseText.substring(0, 100) + '</small>');
                }
            } else {
                showToast('error', 'Error', 'Terjadi kesalahan jaringan (HTTP ' + xhr.status + ')');
            }
        }
    };
    
    var data = 'action=submit_request&id_mengajar=' + encodeURIComponent(idMengajar) + 
               '&tanggal_jurnal=' + encodeURIComponent(tanggal) + 
               '&alasan=' + encodeURIComponent(alasan);
    xhr.send(data);
}

function bukaModalRequestMundur(idMengajar, hariJadwal, mapelText) {
    // Simpan ID mengajar ke variabel global
    requestIdMengajar = idMengajar;
    
    // Set info di modal
    document.getElementById('request-info-mapel').textContent = mapelText;
    document.getElementById('request-info-hari').textContent = hariJadwal;
    
    // Reset form
    document.getElementById('tanggal_request').value = '';
    document.getElementById('alasan_request').value = '';
    
    // Populate tanggal
    var select = document.getElementById('tanggal_request');
    select.innerHTML = '<option value="">-- Pilih Tanggal --</option>';
    
    if (tanggalMundurPerHari[hariJadwal]) {
        tanggalMundurPerHari[hariJadwal].forEach(function(item) {
            var opt = document.createElement('option');
            opt.value = item.value;
            opt.textContent = item.label;
            select.appendChild(opt);
        });
    }
    
    // Buka modal
    var modal = new bootstrap.Modal(document.getElementById('modalRequestMundur'));
    modal.show();
}
</script>

<?php require_once '../includes/footer.php'; ?>