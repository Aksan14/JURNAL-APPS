<?php
/*
File: admin/sidebar.php
Lokasi: /jurnal_app/admin/sidebar.php
Professional Blue Navy Sidebar - Admin
*/

global $current_page;
?>

<ul class="list-unstyled components">
    <li class="nav-item-header">Menu Utama</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/index.php" title="Dashboard">
            <i class="fas fa-home"></i><span>Dashboard</span>
        </a>
    </li>

    <li class="nav-item-header">Kelola Data Master</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'manage_guru.php' || $current_page == 'edit_guru.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/manage_guru.php" title="Guru">
            <i class="fas fa-chalkboard-teacher"></i><span>Guru</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'manage_siswa.php' || $current_page == 'edit_siswa.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/manage_siswa.php" title="Siswa">
            <i class="fas fa-user-graduate"></i><span>Siswa</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'manage_kelas.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/manage_kelas.php" title="Kelas">
            <i class="fas fa-school"></i><span>Kelas</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'manage_mapel.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/manage_mapel.php" title="Mata Pelajaran">
            <i class="fas fa-book"></i><span>Mata Pelajaran</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'manage_mengajar.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/manage_mengajar.php" title="Jadwal Mengajar">
            <i class="fas fa-calendar-alt"></i><span>Jadwal Mengajar</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'manage_libur.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/manage_libur.php" title="Hari Libur">
            <i class="fas fa-calendar-times"></i><span>Hari Libur</span>
        </a>
    </li>

    <li class="nav-item-header">Alat & Laporan</li>
    <?php
    // Hitung jumlah jurnal yang belum diisi hari ini (hanya jadwal untuk hari ini)
    $hari_map_sidebar = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
    ];
    $nama_hari_ini = $hari_map_sidebar[date('l')] ?? '';
    
    // Cek apakah hari ini adalah hari libur
    $stmt_cek_libur = $pdo->prepare("SELECT COUNT(*) FROM tbl_hari_libur WHERE tanggal = CURDATE()");
    $stmt_cek_libur->execute();
    $is_libur_hari_ini = $stmt_cek_libur->fetchColumn() > 0;
    
    $jumlah_belum_isi = 0;
    if (!$is_libur_hari_ini && $nama_hari_ini != 'Minggu') {
        $stmt_notif = $pdo->prepare("
            SELECT COUNT(*) FROM tbl_mengajar m
            WHERE m.hari = ?
            AND m.id NOT IN (
                SELECT id_mengajar FROM tbl_jurnal WHERE tanggal = CURDATE()
            )
        ");
        $stmt_notif->execute([$nama_hari_ini]);
        $jumlah_belum_isi = $stmt_notif->fetchColumn();
    }
    
    // Hitung permintaan jurnal mundur pending
    $stmt_request_pending = $pdo->query("SELECT COUNT(*) FROM tbl_request_jurnal_mundur WHERE status = 'pending'");
    $jumlah_request_pending = $stmt_request_pending->fetchColumn();
    ?>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'notifikasi_jurnal.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/notifikasi_jurnal.php" title="Notifikasi">
            <i class="fas fa-bell"></i><span>Notifikasi</span>
            <?php if ($jumlah_belum_isi > 0): ?>
                <span class="badge bg-danger"><?php echo $jumlah_belum_isi; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'request_jurnal_mundur.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/request_jurnal_mundur.php" title="Permintaan Jurnal">
            <i class="fas fa-envelope-open-text"></i><span>Permintaan Jurnal</span>
            <?php if ($jumlah_request_pending > 0): ?>
                <span class="badge bg-warning text-dark"><?php echo $jumlah_request_pending; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'import_data.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/import_data.php" title="Import Data">
            <i class="fas fa-file-import"></i><span>Import Data</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'laporan.php' || $current_page == 'edit_jurnal.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/laporan.php" title="Laporan Jurnal">
            <i class="fas fa-file-alt"></i><span>Laporan Jurnal</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'laporan_bulanan_guru.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/admin/laporan_bulanan_guru.php" title="Rekap Bulanan">
            <i class="fas fa-chart-bar"></i><span>Rekap Bulanan</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'rekap_jam_guru.php') ? 'active' : ''; ?>" 
           href="<?php echo BASE_URL; ?>/admin/rekap_jam_guru.php" title="Rekap Jam Guru">
            <i class="fas fa-clock"></i><span>Rekap Jam Guru</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'laporan_kehadiran_guru.php') ? 'active' : ''; ?>" 
           href="<?php echo BASE_URL; ?>/admin/laporan_kehadiran_guru.php" title="Kehadiran Guru">
            <i class="fas fa-user-clock"></i><span>Kehadiran Guru</span>
        </a>
    </li>
</ul>