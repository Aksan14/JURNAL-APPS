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
    $libur_hari_ini = [];
    $libur_mendatang = [];
    $jam_khusus_hari_ini = [];
    $libur_bulan_ini = [];
    $jam_khusus_bulan_ini = [];
    $bulan_kal = date('m');
    $tahun_kal = date('Y');
    $first_day_kal = mktime(0, 0, 0, $bulan_kal, 1, $tahun_kal);
    $days_in_month_kal = date('t', $first_day_kal);
    $start_day_kal = date('N', $first_day_kal);
    $nama_bulan_kal = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
} else {
    $id_siswa = $siswa['id'];
    $id_kelas = $siswa['id_kelas'];
    $nama_siswa = $siswa['nama_siswa'];
    
    // ============================================
    // CEK HARI LIBUR UNTUK SISWA
    // ============================================
    // Cek libur hari ini (umum atau khusus kelas siswa)
    $stmt_libur = $pdo->prepare("
        SELECT hl.*, k.nama_kelas 
        FROM tbl_hari_libur hl
        LEFT JOIN tbl_kelas k ON hl.id_kelas = k.id
        WHERE hl.tanggal = CURDATE() 
        AND (hl.id_kelas IS NULL OR hl.id_kelas = ?)
        ORDER BY hl.id_kelas IS NULL DESC
    ");
    $stmt_libur->execute([$id_kelas]);
    $libur_hari_ini = $stmt_libur->fetchAll();
    
    // Cek libur mendatang (7 hari ke depan)
    $stmt_libur_next = $pdo->prepare("
        SELECT hl.*, k.nama_kelas 
        FROM tbl_hari_libur hl
        LEFT JOIN tbl_kelas k ON hl.id_kelas = k.id
        WHERE hl.tanggal > CURDATE() AND hl.tanggal <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND (hl.id_kelas IS NULL OR hl.id_kelas = ?)
        ORDER BY hl.tanggal
    ");
    $stmt_libur_next->execute([$id_kelas]);
    $libur_mendatang = $stmt_libur_next->fetchAll();
    
    // Cek jam khusus hari ini
    $stmt_jam = $pdo->prepare("
        SELECT jk.*, k.nama_kelas 
        FROM tbl_jam_khusus jk
        LEFT JOIN tbl_kelas k ON jk.id_kelas = k.id
        WHERE jk.tanggal = CURDATE()
        AND (jk.id_kelas IS NULL OR jk.id_kelas = ?)
        ORDER BY jk.id_kelas IS NULL DESC
    ");
    $stmt_jam->execute([$id_kelas]);
    $jam_khusus_hari_ini = $stmt_jam->fetchAll();
    
    // ============================================
    // DATA KALENDER BULAN INI
    // ============================================
    $bulan_kal = date('m');
    $tahun_kal = date('Y');
    
    // Ambil semua libur bulan ini (umum atau khusus kelas siswa)
    $stmt_libur_kal = $pdo->prepare("
        SELECT hl.*, k.nama_kelas, DAY(hl.tanggal) as hari
        FROM tbl_hari_libur hl
        LEFT JOIN tbl_kelas k ON hl.id_kelas = k.id
        WHERE MONTH(hl.tanggal) = ? AND YEAR(hl.tanggal) = ?
        AND (hl.id_kelas IS NULL OR hl.id_kelas = ?)
        ORDER BY hl.tanggal
    ");
    $stmt_libur_kal->execute([$bulan_kal, $tahun_kal, $id_kelas]);
    $libur_bulan_ini = [];
    while ($row = $stmt_libur_kal->fetch()) {
        $libur_bulan_ini[(int)$row['hari']][] = $row;
    }
    
    // Ambil jam khusus bulan ini (umum atau khusus kelas siswa)
    $stmt_jk_kal = $pdo->prepare("
        SELECT jk.*, k.nama_kelas, DAY(jk.tanggal) as hari
        FROM tbl_jam_khusus jk
        LEFT JOIN tbl_kelas k ON jk.id_kelas = k.id
        WHERE MONTH(jk.tanggal) = ? AND YEAR(jk.tanggal) = ?
        AND (jk.id_kelas IS NULL OR jk.id_kelas = ?)
        ORDER BY jk.tanggal
    ");
    $stmt_jk_kal->execute([$bulan_kal, $tahun_kal, $id_kelas]);
    $jam_khusus_bulan_ini = [];
    while ($row = $stmt_jk_kal->fetch()) {
        $jam_khusus_bulan_ini[(int)$row['hari']][] = $row;
    }
    
    // Kalkulasi kalender
    $first_day_kal = mktime(0, 0, 0, $bulan_kal, 1, $tahun_kal);
    $days_in_month_kal = date('t', $first_day_kal);
    $start_day_kal = date('N', $first_day_kal); // 1=Senin, 7=Minggu
    $nama_bulan_kal = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
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
        <?php if (!empty($libur_hari_ini)): ?>
        <!-- NOTIFIKASI HARI LIBUR HARI INI -->
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5 class="alert-heading"><i class="fas fa-calendar-times me-2"></i> Hari Ini Libur!</h5>
            <?php foreach ($libur_hari_ini as $libur): ?>
                <div class="mb-1">
                    <strong><?= htmlspecialchars($libur['nama_libur']) ?></strong>
                    <?php if ($libur['id_kelas']): ?>
                        <span class="badge bg-warning text-dark ms-2">Khusus Kelasmu</span>
                    <?php else: ?>
                        <span class="badge bg-danger ms-2"><?= ucfirst(str_replace('_', ' ', $libur['jenis'])) ?></span>
                    <?php endif; ?>
                    <?php if ($libur['keterangan']): ?>
                        <small class="text-muted d-block"><?= htmlspecialchars($libur['keterangan']) ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($jam_khusus_hari_ini)): ?>
        <!-- NOTIFIKASI JAM KHUSUS HARI INI -->
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <h6 class="alert-heading"><i class="fas fa-clock me-2"></i> Jam Khusus Hari Ini</h6>
            <?php foreach ($jam_khusus_hari_ini as $jk): ?>
                <div class="mb-1">
                    <strong><?= htmlspecialchars($jk['alasan']) ?></strong> - Maksimal <strong><?= $jk['max_jam'] ?> Jam</strong> pelajaran
                    <?php if ($jk['id_kelas']): ?>
                        <span class="badge bg-info ms-2">Khusus Kelasmu</span>
                    <?php else: ?>
                        <span class="badge bg-secondary ms-2">Semua Kelas</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($libur_mendatang)): ?>
        <!-- NOTIFIKASI LIBUR MENDATANG -->
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Jadwal Libur 7 Hari ke Depan</h6>
            <ul class="mb-0 small">
            <?php foreach ($libur_mendatang as $libur): ?>
                <li>
                    <strong><?= date('d/m/Y (l)', strtotime($libur['tanggal'])) ?></strong>: <?= htmlspecialchars($libur['nama_libur']) ?>
                    <?php if ($libur['id_kelas']): ?>
                        <span class="badge bg-warning text-dark">Khusus Kelasmu</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- KALENDER LIBUR BULAN INI -->
        <?php if ($id_kelas != 0): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2 bg-primary text-white">
                        <h6 class="m-0 font-weight-bold">
                            <i class="fas fa-calendar-alt me-2"></i>Kalender <?= $nama_bulan_kal[(int)$bulan_kal] ?> <?= $tahun_kal ?>
                        </h6>
                    </div>
                    <div class="card-body p-2">
                        <table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem;">
                            <thead>
                                <tr class="text-center table-light">
                                    <th>S</th><th>S</th><th>R</th><th>K</th><th>J</th><th>S</th><th class="text-danger">M</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $today_day = (int)date('j');
                                echo '<tr>';
                                for ($i = 1; $i < $start_day_kal; $i++) {
                                    echo '<td class="bg-light"></td>';
                                }
                                for ($day = 1; $day <= $days_in_month_kal; $day++) {
                                    $dow = date('N', mktime(0,0,0,$bulan_kal,$day,$tahun_kal));
                                    if ($dow == 1 && $day > 1) echo '</tr><tr>';
                                    
                                    $is_today = ($day == $today_day);
                                    $is_libur = isset($libur_bulan_ini[$day]);
                                    $is_jk = isset($jam_khusus_bulan_ini[$day]);
                                    $is_sunday = ($dow == 7);
                                    
                                    $class = 'text-center';
                                    $title = '';
                                    
                                    if ($is_libur) {
                                        $class .= ' bg-danger text-white';
                                        $title = $libur_bulan_ini[$day][0]['nama_libur'];
                                    } elseif ($is_jk) {
                                        $class .= ' bg-warning';
                                        $title = $jam_khusus_bulan_ini[$day][0]['alasan'] . ' (Maks ' . $jam_khusus_bulan_ini[$day][0]['max_jam'] . ' jam)';
                                    } elseif ($is_sunday) {
                                        $class .= ' text-danger';
                                    }
                                    
                                    echo '<td class="' . $class . '" title="' . htmlspecialchars($title) . '" style="cursor:default;">';
                                    if ($is_today) {
                                        echo '<span class="badge bg-primary rounded-circle" style="width:24px;height:24px;line-height:18px;">' . $day . '</span>';
                                    } else {
                                        echo $day;
                                    }
                                    echo '</td>';
                                }
                                $last_dow = date('N', mktime(0,0,0,$bulan_kal,$days_in_month_kal,$tahun_kal));
                                for ($i = $last_dow + 1; $i <= 7; $i++) {
                                    echo '<td class="bg-light"></td>';
                                }
                                echo '</tr>';
                                ?>
                            </tbody>
                        </table>
                        
                        <!-- Legenda -->
                        <div class="d-flex flex-wrap gap-2 mt-2 small justify-content-center">
                            <span><span class="badge bg-danger">&nbsp;</span> Libur</span>
                            <span><span class="badge bg-warning">&nbsp;</span> Jam Khusus</span>
                            <span><span class="badge bg-primary">&nbsp;</span> Hari Ini</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Daftar Jadwal Bulan Ini -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2 bg-info text-white">
                        <h6 class="m-0 font-weight-bold">
                            <i class="fas fa-list me-2"></i>Jadwal Libur & Jam Khusus
                        </h6>
                    </div>
                    <div class="card-body p-2" style="max-height: 280px; overflow-y: auto;">
                        <?php 
                        $all_jadwal = [];
                        foreach ($libur_bulan_ini as $day => $items) {
                            foreach ($items as $item) {
                                $item['type'] = 'libur';
                                $all_jadwal[] = $item;
                            }
                        }
                        foreach ($jam_khusus_bulan_ini as $day => $items) {
                            foreach ($items as $item) {
                                $item['type'] = 'jam_khusus';
                                $all_jadwal[] = $item;
                            }
                        }
                        usort($all_jadwal, fn($a, $b) => strtotime($a['tanggal']) - strtotime($b['tanggal']));
                        ?>
                        <?php if (!empty($all_jadwal)): ?>
                            <?php foreach ($all_jadwal as $item): ?>
                            <div class="d-flex align-items-center mb-2 small border-bottom pb-1">
                                <?php if ($item['type'] == 'libur'): ?>
                                    <span class="badge bg-danger me-2" style="width: 30px;"><?= date('d', strtotime($item['tanggal'])) ?></span>
                                    <div>
                                        <strong><?= htmlspecialchars($item['nama_libur']) ?></strong>
                                        <?php if ($item['id_kelas']): ?>
                                            <span class="badge bg-warning text-dark ms-1">Kelasmu</span>
                                        <?php endif; ?>
                                        <small class="text-muted d-block"><?= date('l, d F Y', strtotime($item['tanggal'])) ?></small>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark me-2" style="width: 30px;"><?= date('d', strtotime($item['tanggal'])) ?></span>
                                    <div>
                                        <strong><?= htmlspecialchars($item['alasan']) ?></strong>
                                        <span class="badge bg-secondary ms-1"><?= $item['max_jam'] ?> jam</span>
                                        <?php if ($item['id_kelas']): ?>
                                            <span class="badge bg-info ms-1">Kelasmu</span>
                                        <?php endif; ?>
                                        <small class="text-muted d-block"><?= date('l, d F Y', strtotime($item['tanggal'])) ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                <p class="mb-0">Tidak ada jadwal libur atau jam khusus bulan ini.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

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