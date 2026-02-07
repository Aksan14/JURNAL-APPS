<?php
/*
File: includes/header.php
Deskripsi: Bagian header template, termasuk navbar dan pemanggilan sidebar dinamis.
*/

// Start output buffering untuk mencegah "headers already sent" error
ob_start();

// Panggil config.php SEBELUM output HTML apapun untuk memulai session & BASE_URL
// Pastikan path ini benar relatif terhadap file yang memanggil header.php
// Menggunakan __DIR__ memastikan path absolut dari lokasi header.php
require_once __DIR__ . '/../config.php';

// Ambil nama file saat ini untuk menandai link sidebar aktif
$current_page = basename($_SERVER['PHP_SELF']);
$current_folder = ''; // Untuk menentukan folder sidebar

// Cek apakah user sudah login (untuk menampilkan/menyembunyikan item navbar)
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? ''; // Ambil role jika login
// Ambil nama display jika ada (diset saat login, contoh: dari tbl_guru atau tbl_siswa)
// Jika belum diset saat login, perlu ditambahkan query di sini atau saat login
$display_name = $_SESSION['display_name'] ?? 'Pengguna';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Jurnal Dan data kependidikan</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    
    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Prevent sidebar flash on page load -->
    <script>
        (function() {
            if (window.innerWidth > 768 && localStorage.getItem('sidebarCollapsed') === 'true') {
                document.documentElement.classList.add('sidebar-collapsed-mode');
            }
        })();
    </script>
    <style>
        /* Instant collapse on page load - prevent flash */
        html.sidebar-collapsed-mode #sidebar {
            width: 72px !important;
            min-width: 72px !important;
            max-width: 72px !important;
        }
        html.sidebar-collapsed-mode #content {
            margin-left: 72px !important;
        }
        html.sidebar-collapsed-mode #sidebar .sidebar-header .logo-text {
            opacity: 0 !important;
            width: 0 !important;
        }
        html.sidebar-collapsed-mode #sidebar ul li a.nav-link span,
        html.sidebar-collapsed-mode #sidebar .nav-item-header {
            display: none !important;
        }
        
        * { font-family: 'Poppins', sans-serif; }
        html, body { height: 100%; margin: 0; }
        body { display: flex; min-height: 100vh; flex-direction: column; background-color: #f5f7fa; }
        .wrapper { display: flex; width: 100%; align-items: stretch; flex-grow: 1; min-height: 100vh; }
        
        /* Professional Soft Blue Sidebar */
        #sidebar { 
            width: 260px; 
            min-width: 260px;
            max-width: 260px; 
            background: #5C9CE5; 
            color: #fff; 
            transition: all 0.3s ease; 
            height: 100vh;
            padding: 0;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }
        
        /* Scrollbar styling for sidebar */
        #sidebar::-webkit-scrollbar {
            width: 6px;
        }
        #sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        #sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        #sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
        
        /* Collapsed Sidebar - Only Icons */
        #sidebar.collapsed { 
            width: 72px; 
            min-width: 72px;
            max-width: 72px; 
        }
        
        #sidebar .sidebar-header { 
            padding: 20px; 
            display: flex;
            align-items: center;
            height: auto;
            box-sizing: border-box;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            background: #4A8AD4;
        }
        #sidebar .sidebar-header .logo-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
        #sidebar .sidebar-header .logo-icon i {
            font-size: 20px;
            color: #fff;
        }
        #sidebar .sidebar-header .logo-text { 
            transition: opacity 0.2s;
        }
        #sidebar .sidebar-header .logo-text h4 {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            line-height: 1.3;
        }
        #sidebar .sidebar-header .logo-text span {
            font-size: 11px;
            color: rgba(255,255,255,0.7);
        }
        #sidebar.collapsed .sidebar-header .logo-text { 
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        #sidebar.collapsed .sidebar-header {
            padding: 16px;
            justify-content: center;
        }
        #sidebar.collapsed .sidebar-header .logo-icon {
            margin-right: 0;
        }
        
        /* Navigation Items */
        #sidebar ul.components { 
            padding: 12px; 
            margin: 0;
            list-style: none;
            flex: 1;
            overflow-y: auto;
            padding-bottom: 20px;
        }
        #sidebar ul li { margin: 2px 0; }
        
        #sidebar ul li a.nav-link { 
            padding: 12px 16px; 
            font-size: 14px; 
            display: flex; 
            align-items: center;
            color: rgba(255,255,255,0.85); 
            text-decoration: none; 
            transition: all 0.2s ease;
            border-radius: 8px;
            font-weight: 400;
            white-space: nowrap;
            overflow: hidden;
        }
        #sidebar ul li a.nav-link i { 
            width: 22px; 
            min-width: 22px;
            font-size: 16px; 
            margin-right: 14px;
            text-align: center;
            color: rgba(255,255,255,0.7);
        }
        #sidebar ul li a.nav-link:hover { 
            background: rgba(255,255,255,0.1); 
            color: #fff;
        }
        #sidebar ul li a.nav-link:hover i {
            color: #fff;
        }
        #sidebar ul li a.nav-link.active { 
            background: rgba(255,255,255,0.15); 
            color: #fff;
            font-weight: 500;
        }
        #sidebar ul li a.nav-link.active i {
            color: #fff;
        }
        
        /* Collapsed nav links */
        #sidebar.collapsed ul.components {
            padding: 8px 12px;
        }
        #sidebar.collapsed ul li a.nav-link {
            padding: 12px;
            justify-content: center;
            border-radius: 10px;
            width: 48px;
            height: 48px;
        }
        #sidebar.collapsed ul li a.nav-link i {
            margin-right: 0;
            font-size: 18px;
        }
        #sidebar.collapsed ul li a.nav-link span {
            display: none;
        }
        #sidebar.collapsed ul li a.nav-link .badge {
            position: absolute;
            top: 4px;
            right: 4px;
            font-size: 8px;
            padding: 2px 4px;
            min-width: 14px;
        }
        #sidebar.collapsed ul li {
            display: flex;
            justify-content: center;
            position: relative;
        }
        
        /* Section Headers */
        #sidebar .nav-item-header { 
            padding: 18px 16px 10px 16px; 
            font-size: 11px; 
            color: rgba(255,255,255,0.5); 
            text-transform: uppercase; 
            letter-spacing: 0.8px;
            font-weight: 500;
            margin-top: 8px;
            white-space: nowrap;
            overflow: hidden;
        }
        #sidebar .nav-item-header:first-child {
            margin-top: 0;
        }
        #sidebar.collapsed .nav-item-header {
            font-size: 0;
            padding: 8px 0;
            text-align: center;
        }
        #sidebar.collapsed .nav-item-header::after {
            content: '•••';
            font-size: 10px;
            letter-spacing: 2px;
            color: rgba(255,255,255,0.3);
        }
        
        /* Badge */
        #sidebar .badge {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 6px;
            margin-left: auto;
            background: rgba(255,255,255,0.2) !important;
        }
        
        /* Content Area */
        #content { 
            width: 100%; 
            padding: 0; 
            min-height: 100vh; 
            transition: all 0.3s ease; 
            background-color: #f5f7fa;
            margin-left: 260px;
            display: flex;
            flex-direction: column;
        }
        #content.expanded {
            margin-left: 72px;
        }
        
        /* Navbar - White Clean Style */
        .navbar { 
            padding: 0 20px; 
            background: #fff; 
            border-bottom: 1px solid #e3e8ef; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1001;
            height: 60px;
            display: flex;
            align-items: center;
        }
        
        #sidebarCollapse {
            background: transparent;
            border: none;
            color: #5C9CE5;
            font-size: 18px;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.2s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        #sidebarCollapse:hover {
            background: rgba(92, 156, 229, 0.12);
        }
        #sidebarCollapse i {
            transition: transform 0.3s ease;
        }
        /* Rotate icon when sidebar is collapsed */
        #sidebarCollapse.toggled i {
            transform: rotate(90deg);
        }
        
        /* User Avatar */
        .user-avatar {
            width: 36px;
            height: 36px;
            background: #5C9CE5;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 14px;
        }
        
        .navbar .nav-link {
            color: #37474F;
            font-size: 14px;
            padding: 8px 14px;
            border-radius: 8px;
            transition: background 0.2s;
            font-weight: 500;
        }
        
        .navbar .nav-link:hover {
            background: rgba(92, 156, 229, 0.1);
            color: #5C9CE5;
        }
        
        .navbar .dropdown-toggle::after {
            margin-left: 8px;
            color: #607D8B;
        }
        
        .navbar .badge {
            background: #5C9CE5 !important;
            color: #fff;
            font-weight: 500;
        }
        
        .dropdown-header {
            padding: 14px 18px;
        }
        
        /* Main Content */
        main {
            padding: 24px;
            flex: 1 0 auto;
        }
        
        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .sidebar-overlay.active { display: block; }
        
        /* Scrollbar for sidebar */
        #sidebar::-webkit-scrollbar {
            width: 4px;
        }
        #sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
        }
        
        @media (max-width: 768px) {
            #sidebar { 
                margin-left: -260px; 
            }
            #sidebar.active { 
                margin-left: 0; 
                width: 260px;
                min-width: 260px;
                max-width: 260px;
            }
            #sidebar.active .sidebar-header .logo-text {
                opacity: 1;
                width: auto;
            }
            #content { margin-left: 0 !important; }
            .navbar { padding: 0 16px; }
            main { padding: 16px; }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- Overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <nav id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon">
                <i class="fas fa-book-open"></i>
            </div>
            <div class="logo-text">
                <h4>Sistem Jurnal</h4>
                <span>Manajemen Pembelajaran</span>
            </div>
        </div>

        <?php
        // Logika untuk memanggil sidebar yang sesuai
        // Menggunakan __DIR__ memastikan path relatif terhadap file header.php ini
        if ($is_logged_in) {
            if ($user_role == 'admin') {
                $current_folder = 'admin';
                // Cek Eksistensi File (Optional tapi bagus untuk debug)
                if (file_exists(__DIR__ . '/../admin/sidebar.php')) {
                    require_once __DIR__ . '/../admin/sidebar.php';
                } else {
                    echo '<p class="text-danger p-3">Error: admin/sidebar.php not found!</p>';
                }
            } elseif ($user_role == 'guru' || $user_role == 'walikelas') {
                $current_folder = 'guru';
                 // Cek Eksistensi File
                if (file_exists(__DIR__ . '/../guru/sidebar.php')) {
                    require_once __DIR__ . '/../guru/sidebar.php'; // Memanggil sidebar guru
                } else {
                     echo '<p class="text-danger p-3">Error: guru/sidebar.php not found!</p>';
                }
            } elseif ($user_role == 'siswa') {
                $current_folder = 'siswa';
                 // Cek Eksistensi File
                if (file_exists(__DIR__ . '/../siswa/sidebar.php')) {
                    require_once __DIR__ . '/../siswa/sidebar.php';
                } else {
                     echo '<p class="text-danger p-3">Error: siswa/sidebar.php not found!</p>';
                }
            } elseif ($user_role == 'kepsek') {
                $current_folder = 'kepsek';
                // Cek Eksistensi File
                if (file_exists(__DIR__ . '/../kepsek/sidebar.php')) {
                    require_once __DIR__ . '/../kepsek/sidebar.php';
                } else {
                    echo '<p class="text-danger p-3">Error: kepsek/sidebar.php not found!</p>';
                }
            } else {
                // Sidebar default atau kosong jika role tidak dikenal
                echo '<ul class="list-unstyled components"><li class="nav-item"><a href="'.BASE_URL.'/logout.php">Logout (Unknown Role)</a></li></ul>';
            }
        } else {
             // Jika belum login, tampilkan link login di area sidebar
             echo '<ul class="list-unstyled components"><li class="nav-item"><a href="'.BASE_URL.'/login.php">Login</a></li></ul>';
        }
        ?>
    </nav>

    <div id="content">

        <nav class="navbar navbar-expand-lg sticky-top">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="d-flex align-items-center ms-auto">
                    <?php if ($is_logged_in): ?>
                        <!-- User Dropdown -->
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="user-avatar me-2">
                                    <i class="fas fa-user"></i>
                                </div>
                                <span class="d-none d-md-inline"><?php echo htmlspecialchars($display_name); ?></span>
                                <span class="badge bg-secondary ms-2 d-none d-md-inline"><?php echo htmlspecialchars(ucfirst($user_role)); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li class="dropdown-header">
                                    <strong><?php echo htmlspecialchars($display_name); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars(ucfirst($user_role)); ?></small>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($user_role == 'guru' || $user_role == 'walikelas'): ?>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/guru/profil.php"><i class="fas fa-user-edit me-2"></i>Edit Profil</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/guru/ganti_password.php"><i class="fas fa-key me-2"></i>Ganti Password</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a class="btn btn-outline-dark btn-sm" href="<?php echo BASE_URL; ?>/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <main class="mt-3 px-3">