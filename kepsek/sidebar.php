<?php
/*
File: kepsek/sidebar.php
Sidebar untuk Kepala Sekolah
*/
global $current_page;
?>

<ul class="list-unstyled components">
    <li class="nav-item-header">Menu Utama</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/kepsek/index.php" title="Dashboard">
           <i class="fas fa-home"></i><span>Dashboard</span>
        </a>
    </li>
    
    <li class="nav-item-header">Monitoring</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'lihat_guru.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/kepsek/lihat_guru.php" title="Data Guru">
           <i class="fas fa-chalkboard-teacher"></i><span>Data Guru</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'lihat_kelas.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/kepsek/lihat_kelas.php" title="Data Kelas">
           <i class="fas fa-school"></i><span>Data Kelas</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'lihat_siswa.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/kepsek/lihat_siswa.php" title="Data Siswa">
           <i class="fas fa-user-graduate"></i><span>Data Siswa</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'lihat_mapel.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/kepsek/lihat_mapel.php" title="Data Mapel">
           <i class="fas fa-book"></i><span>Data Mapel</span>
        </a>
    </li>
    
    <li class="nav-item-header">Laporan Jurnal</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'laporan_jurnal.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/kepsek/laporan_jurnal.php" title="Laporan Jurnal">
           <i class="fas fa-file-alt"></i><span>Laporan Jurnal</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'rekap_kehadiran.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/kepsek/rekap_kehadiran.php" title="Rekap Kehadiran">
           <i class="fas fa-clipboard-check"></i><span>Rekap Kehadiran</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'rekap_guru.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/kepsek/rekap_guru.php" title="Rekap Per Guru">
           <i class="fas fa-chart-bar"></i><span>Rekap Per Guru</span>
        </a>
    </li>
    
    <li class="nav-item-header">Akun</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'ganti_password.php') ? 'active' : ''; ?>" 
           href="<?php echo BASE_URL; ?>/kepsek/ganti_password.php" title="Ganti Password">
            <i class="fas fa-lock"></i><span>Ganti Password</span>
        </a>
    </li>
</ul>
