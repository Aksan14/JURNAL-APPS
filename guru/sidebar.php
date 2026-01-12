<?php
/*
File: guru/sidebar.php
YouTube Style Dark Sidebar
*/
global $current_page, $user_role;
?>

<ul class="list-unstyled components">
    <li class="nav-item-header">Menu Utama</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/guru/index.php" title="Dashboard">
           <i class="fas fa-home"></i><span>Dashboard</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'isi_jurnal.php' || $current_page == 'riwayat_jurnal.php' || $current_page == 'edit_jurnal.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/guru/isi_jurnal.php" title="Jurnal">
           <i class="fas fa-edit"></i><span>Jurnal</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'beban_mengajar.php') ? 'active' : ''; ?>" 
           href="<?php echo BASE_URL; ?>/guru/beban_mengajar.php" title="Jadwal & Rekap">
            <i class="fas fa-calendar-alt"></i><span>Jadwal & Rekap</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'laporan_bulanan.php') ? 'active' : ''; ?>" 
           href="<?php echo BASE_URL; ?>/guru/laporan_bulanan.php" title="Riwayat Jurnal">
            <i class="fas fa-history"></i><span>Riwayat Jurnal</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'ganti_password.php') ? 'active' : ''; ?>" 
           href="<?php echo BASE_URL; ?>/guru/ganti_password.php" title="Ganti Password">
            <i class="fas fa-lock"></i><span>Ganti Password</span>
        </a>
    </li>

    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'walikelas'): ?>
    <li class="nav-item-header">Wali Kelas</li>
    <li class="nav-item">
        <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/walikelas/rekap_absensi.php') !== false) ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/walikelas/rekap_absensi.php" title="Rekap Absensi">
           <i class="fas fa-calendar-check"></i><span>Rekap Absensi</span>
        </a>
    </li>
    <?php endif; ?>

    <li class="nav-item-header">Akun</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'profil.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/guru/profil.php" title="Edit Profil">
           <i class="fas fa-user"></i><span>Edit Profil</span>
        </a>
    </li>
</ul>