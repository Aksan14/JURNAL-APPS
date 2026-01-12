<?php
/*
File: sidebar.php
Lokasi: /jurnal_app/includes/sidebar.php
*/

// Pastikan sesi sudah dimulai (dipanggil dari header.php)
if (!isset($_SESSION['role'])) {
    return; // Jangan tampilkan apapun jika tidak ada role
}

$role = $_SESSION['role'];
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>

<div class="sidebar">
    <h4 class="sidebar-title">Menu Navigasi</h4>
    <ul class="nav flex-column">
        
        <?php // ======= MENU ADMIN ======= ?>
        <?php if ($role == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/admin/index.php">Dasbor</a>
            </li>
            <li class="nav-item-header">Master Data</li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage_mapel.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/admin/manage_mapel.php">Data Mapel</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage_guru.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/admin/manage_guru.php">Data Guru</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage_kelas.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/admin/manage_kelas.php">Data Kelas</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage_siswa.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/admin/manage_siswa.php">Data Siswa</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'manage_mengajar.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/admin/manage_mengajar.php">Relasi Mengajar</a>
            </li>
            <li class="nav-item-header">Alat</li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'import_data.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/admin/import_data.php">Import Data Massal</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'laporan.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/admin/laporan.php">Laporan Jurnal</a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'laporan_bulanan_guru.php') ? 'active' : ''; ?>" 
                    href="<?php echo BASE_URL; ?>/admin/laporan_bulanan_guru.php">Rekap Bulanan Guru</a> 
            </li>
        
        <?php // ======= MENU GURU ======= ?>
        <?php elseif ($role == 'guru'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/guru/index.php">Dasbor Guru</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'isi_jurnal.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/guru/isi_jurnal.php">Isi Jurnal Baru</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'riwayat_jurnal.php' || $current_page == 'detail_jurnal.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/guru/riwayat_jurnal.php">Riwayat Jurnal</a>
            </li>

        <?php // ======= MENU WALI KELAS ======= ?>
        <?php elseif ($role == 'walikelas'): ?>
            <li class="nav-item-header">Menu Guru</li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'isi_jurnal.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/guru/isi_jurnal.php">Isi Jurnal Baru</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'riwayat_jurnal.php' || $current_page == 'detail_jurnal.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/guru/riwayat_jurnal.php">Riwayat Jurnal</a>
            </li>
            <li class="nav-item-header">Menu Wali Kelas</li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'rekap_absensi.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/walikelas/rekap_absensi.php">Rekap Absensi Kelas</a>
            </li>

            <ul class="nav flex-column">
    <li class="nav-item-header">Menu Utama</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" 
           href="<?php echo BASE_URL; ?>/guru/index.php">Dashboard</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'isi_jurnal.php') ? 'active' : ''; ?>" 
           href="<?php echo BASE_URL; ?>/guru/isi_jurnal.php">Isi Jurnal</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'riwayat_jurnal.php') ? 'active' : ''; ?>" 
           href="<?php echo BASE_URL; ?>/guru/riwayat_jurnal.php">Riwayat Jurnal</a>
    </li>

    <?php 
    if ($_SESSION['role'] == 'walikelas') : 
        // Anda bisa tambahkan pengecekan apakah dia benar wali kelas suatu kelas jika perlu
    ?>
        <li class="nav-item-header">Wali Kelas</li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'rekap_absensi.php') ? 'active' : ''; ?>" 
               href="<?php echo BASE_URL; ?>/walikelas/rekap_absensi.php">Rekap Absensi</a>
        </li>
    <?php endif; ?>
    
    <li class="nav-item-header">Akun</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'profil.php') ? 'active' : ''; ?>" 
           href="<?php echo BASE_URL; ?>/guru/profil.php">Edit Profil</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-danger" href="<?php echo BASE_URL; ?>/logout.php">Logout</a>
    </li>
</ul>

        <?php // ======= MENU SISWA ======= ?>
        <?php elseif ($role == 'siswa'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'lihat_jurnal.php') ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/siswa/lihat_jurnal.php">Riwayat Jurnal & Absensi</a>
            </li>
        <?php endif; ?>

        <li class="nav-item-header">Akun</li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo BASE_URL; ?>/logout.php">Logout</a>
        </li>
    </ul>
</div>