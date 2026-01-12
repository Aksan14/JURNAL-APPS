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
    
    <!-- Google Fonts - Roboto & Audiowide -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Audiowide&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

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
        html.sidebar-collapsed-mode #sidebar .sidebar-header h3 {
            opacity: 0 !important;
            width: 0 !important;
        }
        html.sidebar-collapsed-mode #sidebar ul li a.nav-link span,
        html.sidebar-collapsed-mode #sidebar .nav-item-header {
            display: none !important;
        }
        
        * { font-family: 'Roboto', sans-serif; }
        html, body { height: 100%; margin: 0; }
        body { display: flex; min-height: 100vh; flex-direction: column; background-color: #f5f5f5; }
        .wrapper { display: flex; width: 100%; align-items: stretch; flex-grow: 1; min-height: 100vh; }
        
        /* YouTube Style Sidebar */
        #sidebar { 
            width: 240px; 
            min-width: 240px;
            max-width: 240px; 
            background: #212121; 
            color: #fff; 
            transition: all 0.3s ease; 
            min-height: 100vh;
            padding: 0;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        /* Collapsed Sidebar - Only Icons */
        #sidebar.collapsed { 
            width: 72px; 
            min-width: 72px;
            max-width: 72px; 
        }
        
        #sidebar .sidebar-header { 
            padding: 14px 20px; 
            display: flex;
            align-items: center;
            justify-content: center;
            height: 56px;
            box-sizing: border-box;
            border-bottom: 1px solid #333;
        }
        #sidebar .sidebar-header h3 { 
            margin: 0; 
            font-family: 'Audiowide', cursive;
            font-size: 14px; 
            font-weight: 400;
            white-space: nowrap;
            transition: opacity 0.2s;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        #sidebar .sidebar-header h3 .highlight {
            color: #ff4444;
            text-shadow: 0 0 8px rgba(255, 68, 68, 0.4);
        }
        #sidebar .sidebar-header h3 .dark {
            color: #666;
        }
        #sidebar.collapsed .sidebar-header h3 { 
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        #sidebar.collapsed .sidebar-header {
            padding: 12px;
        }
        
        /* Navigation Items */
        #sidebar ul.components { 
            padding: 8px; 
            margin: 0;
            list-style: none;
        }
        #sidebar ul li { margin: 2px 0; }
        
        #sidebar ul li a.nav-link { 
            padding: 10px 12px; 
            font-size: 14px; 
            display: flex; 
            align-items: center;
            color: #f1f1f1; 
            text-decoration: none; 
            transition: all 0.2s ease;
            border-radius: 10px;
            font-weight: 400;
            white-space: nowrap;
            overflow: hidden;
        }
        #sidebar ul li a.nav-link i { 
            width: 24px; 
            min-width: 24px;
            font-size: 18px; 
            margin-right: 16px;
            text-align: center;
        }
        #sidebar ul li a.nav-link:hover { 
            background: #3d3d3d; 
            color: #fff;
        }
        #sidebar ul li a.nav-link.active { 
            background: #3d3d3d; 
            color: #fff;
            font-weight: 500;
        }
        
        /* Collapsed nav links */
        #sidebar.collapsed ul.components {
            padding: 8px 12px;
        }
        #sidebar.collapsed ul li a.nav-link {
            padding: 12px;
            justify-content: center;
            border-radius: 50%;
            width: 48px;
            height: 48px;
        }
        #sidebar.collapsed ul li a.nav-link i {
            margin-right: 0;
            font-size: 20px;
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
            padding: 16px 12px 8px 12px; 
            font-size: 11px; 
            color: #aaa; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            font-weight: 500;
            border-top: 1px solid #3d3d3d;
            margin-top: 8px;
            white-space: nowrap;
            overflow: hidden;
        }
        #sidebar .nav-item-header:first-child {
            border-top: none;
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
        }
        
        /* Badge */
        #sidebar .badge {
            font-size: 10px;
            padding: 3px 6px;
            border-radius: 10px;
            margin-left: auto;
        }
        
        /* Content Area */
        #content { 
            width: 100%; 
            padding: 0; 
            min-height: 100vh; 
            transition: all 0.3s ease; 
            background-color: #f5f5f5;
            margin-left: 240px;
            display: flex;
            flex-direction: column;
        }
        #content.expanded {
            margin-left: 72px;
        }
        
        /* Navbar - Dark Theme matching Sidebar */
        .navbar { 
            padding: 0 16px; 
            background: #212121; 
            border: none; 
            box-shadow: none;
            position: sticky;
            top: 0;
            z-index: 1001;
            height: 56px;
            display: flex;
            align-items: center;
        }
        
        #sidebarCollapse {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 20px;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        #sidebarCollapse:hover {
            background: #3d3d3d;
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
            width: 32px;
            height: 32px;
            background: #cc0000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 14px;
        }
        
        .navbar .nav-link {
            color: #fff;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 20px;
            transition: background 0.2s;
        }
        
        .navbar .nav-link:hover {
            background: #3d3d3d;
            color: #fff;
        }
        
        .navbar .dropdown-toggle::after {
            margin-left: 8px;
            color: #fff;
        }
        
        .navbar .badge {
            background: #3d3d3d !important;
            color: #fff;
        }
        
        .dropdown-header {
            padding: 12px 16px;
        }
        
        /* Main Content */
        main {
            padding: 20px;
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
            background: #3d3d3d;
            border-radius: 4px;
        }
        
        @media (max-width: 768px) {
            #sidebar { 
                margin-left: -240px; 
            }
            #sidebar.active { 
                margin-left: 0; 
                width: 240px;
                min-width: 240px;
                max-width: 240px;
            }
            #sidebar.active .sidebar-header h3 {
                opacity: 1;
                width: auto;
            }
            #content { margin-left: 0 !important; }
            .navbar { padding: 0 10px; }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- Overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <nav id="sidebar">
        <div class="sidebar-header">
            <h3><span class="highlight">MANAJEMEN</span><span class="dark">JURNAL</span></h3>
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