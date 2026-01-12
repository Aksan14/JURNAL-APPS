<?php
/*
File: siswa/sidebar.php
YouTube Style Dark Sidebar
*/
global $current_page, $user_role;
?>

<ul class="list-unstyled components">
    <li class="nav-item-header">Menu Utama</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'lihat_jurnal.php' || $current_page == 'index.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/siswa/lihat_jurnal.php" title="Lihat Jurnal">
           <i class="fas fa-book"></i><span>Lihat Jurnal</span>
        </a>
    </li>

    <li class="nav-item-header">Akun</li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'profil.php') ? 'active' : ''; ?>"
           href="<?php echo BASE_URL; ?>/siswa/profil.php" title="Edit Profil">
           <i class="fas fa-user"></i><span>Edit Profil</span>
        </a>
    </li>
</ul>
