<?php
/*
File: login.php
Lokasi: /jurnal_app/login.php
*/

// Panggil file konfigurasi
require_once 'config.php';

// Jika sudah login, tendang ke halaman masing-masing
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] == 'admin') {
        header('Location: admin/index.php');
    } elseif ($_SESSION['role'] == 'guru') {
        header('Location: guru/index.php');
    } elseif ($_SESSION['role'] == 'walikelas') {
        header('Location: walikelas/index.php');
    } elseif ($_SESSION['role'] == 'kepsek') {
        header('Location: kepsek/index.php');
    } else {
        header('Location: siswa/index.php');
    }
    exit;
}

$error_message = '';

// Cek apakah ada pesan error dari session (misal dari guru yang tidak terhubung)
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Logika saat form disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        // 1. Cari user berdasarkan username
        $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // 2. Verifikasi user dan password
        if ($user && password_verify($password, $user['password_hash'])) {
            
            // 3. Jika berhasil, simpan data ke Sesi
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            // 4. Redirect berdasarkan role (gunakan BASE_URL)
            if ($user['role'] == 'admin') {
                header('Location: ' . BASE_URL . '/admin/index.php');
            } elseif ($user['role'] == 'guru') {
                header('Location: ' . BASE_URL . '/guru/index.php');
            } elseif ($user['role'] == 'walikelas') {
                header('Location: ' . BASE_URL . '/walikelas/index.php');
            } elseif ($user['role'] == 'kepsek') {
                header('Location: ' . BASE_URL . '/kepsek/index.php');
            } else {
                header('Location: ' . BASE_URL . '/siswa/index.php');
            }
            exit;

        } else {
            // Jika username atau password salah
            $error_message = "Username atau password salah!";
        }

    } catch (PDOException $e) {
        $error_message = "Terjadi error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Jurnal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { 
            font-family: 'Poppins', sans-serif; 
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body { 
            min-height: 100vh; 
            background: linear-gradient(135deg, #5C9CE5 0%, #4A8AD4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Background Pattern */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            z-index: 0;
        }
        
        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 24px;
            position: relative;
            z-index: 1;
        }
        
        .login-card { 
            background: #fff;
            border-radius: 16px;
            padding: 48px 44px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        /* Logo Header */
        .login-header {
            text-align: center;
            margin-bottom: 36px;
        }
        
        .logo-icon {
            width: 72px;
            height: 72px;
            background: #5C9CE5;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .logo-icon i {
            font-size: 32px;
            color: #fff;
        }
        
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #4A8AD4;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #5a6a85;
            font-size: 0.9rem;
            font-weight: 400;
        }
        
        /* Form Styling */
        .form-group {
            margin-bottom: 22px;
        }
        
        .form-label {
            color: #212529;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 10px;
            display: block;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #8898aa;
            font-size: 1rem;
            z-index: 3;
            pointer-events: none;
            transition: color 0.3s ease;
        }
        
        .input-wrapper.focused .input-icon {
            color: #5C9CE5;
        }
        
        .form-control {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #e3e8ef;
            border-radius: 10px;
            padding: 14px 50px 14px 48px;
            color: #212529;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:hover {
            border-color: #c8d0dc;
        }
        
        .form-control:focus {
            background: #fff;
            border-color: #5C9CE5;
            box-shadow: 0 0 0 3px rgba(92, 156, 229, 0.2);
            color: #212529;
            outline: none;
        }
        
        .form-control::placeholder {
            color: #8898aa;
        }
        
        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #8898aa;
            cursor: pointer;
            padding: 8px;
            transition: color 0.3s ease;
            z-index: 3;
        }
        
        .password-toggle:hover,
        .password-toggle:focus {
            color: #5C9CE5;
            outline: none;
        }
        
        /* Submit Button */
        .btn-login {
            width: 100%;
            background: #5C9CE5;
            border: none;
            border-radius: 10px;
            padding: 14px;
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover {
            background: #4A8AD4;
            box-shadow: 0 6px 20px rgba(92, 156, 229, 0.35);
        }
        
        .btn-login:active {
            background: #3D7AC3;
            transform: scale(0.99);
        }
        
        /* Alert */
        .alert-danger {
            background: rgba(239, 83, 80, 0.08);
            border: 1px solid rgba(239, 83, 80, 0.2);
            border-left: 4px solid #EF5350;
            color: #E53935;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-danger i {
            font-size: 1.1rem;
        }
        
        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #e3e8ef;
        }
        
        .login-footer p {
            color: #8898aa;
            font-size: 0.85rem;
        }
        
        /* Remember Me */
        .form-check {
            margin-top: 8px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            background-color: #f8fafc;
            border: 1px solid #e3e8ef;
            border-radius: 5px;
            cursor: pointer;
            margin: 0;
        }
        
        .form-check-input:checked {
            background-color: #5C9CE5;
            border-color: #5C9CE5;
        }
        
        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(92, 156, 229, 0.2);
            border-color: #5C9CE5;
        }
        
        .form-check-input:hover {
            border-color: #c8d0dc;
        }
        
        .form-check-label {
            color: #5a6a85;
            font-size: 0.9rem;
            cursor: pointer;
            user-select: none;
        }
        
        /* Loading State */
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.85;
        }
        
        .btn-login.loading .btn-text {
            display: none;
        }
        
        .btn-login .spinner {
            display: none;
        }
        
        .btn-login.loading .spinner {
            display: inline-block;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 36px 28px;
            }
            .login-header h1 {
                font-size: 1.35rem;
            }
            .logo-icon {
                width: 64px;
                height: 64px;
            }
            .logo-icon i {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h1>Sistem Jurnal</h1>
                <p>Manajemen Pembelajaran Digital</p>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" required autocomplete="username">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember">
                    <label class="form-check-label" for="remember">
                        Ingat saya
                    </label>
                </div>
                
                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Masuk</span>
                    <span class="spinner"><i class="fas fa-spinner fa-spin"></i> Memproses...</span>
                </button>
            </form>
            
            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> Sistem Jurnal - Manajemen Pembelajaran</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Loading state on submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('btnLogin').classList.add('loading');
        });
        
        // Focus effect for icon color
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.closest('.input-wrapper').classList.add('focused');
            });
            input.addEventListener('blur', function() {
                this.closest('.input-wrapper').classList.remove('focused');
            });
        });
    </script>
</body>
</html>