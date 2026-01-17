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
    <title>Login - Manajemen Jurnal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Audiowide&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * { 
            font-family: 'Roboto', sans-serif; 
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body { 
            min-height: 100vh; 
            background: #121212;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        
        .login-card { 
            background: #212121;
            border-radius: 12px;
            padding: 45px 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            border: 1px solid #2a2a2a;
        }
        
        /* Logo Header */
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .login-header h1 {
            font-family: 'Audiowide', cursive;
            font-size: 1.5rem;
            font-weight: 400;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .login-header h1 .highlight {
            color: #ff4444;
            text-shadow: 0 0 12px rgba(255, 68, 68, 0.5);
        }
        .login-header h1 .dark {
            color: #666;
        }
        
        .login-header p {
            color: #888;
            font-size: 0.9rem;
            font-weight: 400;
        }
        
        /* Form Styling */
        .form-group {
            margin-bottom: 22px;
        }
        
        .form-label {
            color: #bbb;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 10px;
            display: block;
        }
        
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-group .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1rem;
            transition: color 0.3s ease;
            z-index: 2;
            pointer-events: none;
        }
        
        .form-control {
            width: 100%;
            background: #181818;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 15px 50px 15px 48px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:hover {
            border-color: #555;
        }
        
        .form-control:focus {
            background: #1a1a1a;
            border-color: #cc0000;
            box-shadow: 0 0 0 3px rgba(204, 0, 0, 0.15);
            color: #fff;
            outline: none;
        }
        
        .input-group.focused .input-icon,
        .form-control:focus ~ .input-icon {
            color: #cc0000;
        }
        
        .form-control::placeholder {
            color: #666;
        }
        
        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 8px;
            transition: color 0.3s ease;
            z-index: 2;
        }
        
        .password-toggle:hover,
        .password-toggle:focus {
            color: #aaa;
            outline: none;
        }
        
        /* Submit Button */
        .btn-login {
            width: 100%;
            background: #cc0000;
            border: none;
            border-radius: 8px;
            padding: 15px;
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover {
            background: #b00000;
        }
        
        .btn-login:active {
            background: #990000;
            transform: scale(0.99);
        }
        
        /* Alert */
        .alert-danger {
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff6b6b;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-danger i {
            font-size: 1.1rem;
        }
        
        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #333;
        }
        
        .login-footer p {
            color: #555;
            font-size: 0.85rem;
        }
        
        /* Remember Me */
        .form-check {
            margin-top: 5px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            background-color: #282828;
            border: 1px solid #555;
            border-radius: 4px;
            cursor: pointer;
            margin: 0;
        }
        
        .form-check-input:checked {
            background-color: #cc0000;
            border-color: #cc0000;
        }
        
        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(204, 0, 0, 0.15);
            border-color: #cc0000;
        }
        
        .form-check-input:hover {
            border-color: #777;
        }
        
        .form-check-label {
            color: #999;
            font-size: 0.9rem;
            cursor: pointer;
            user-select: none;
        }
        
        /* Loading State */
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.8;
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
                padding: 30px 25px;
            }
            .login-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><span class="highlight">MANAJEMEN</span><span class="dark">JURNAL</span></h1>
                <p>Sistem Jurnal Pembelajaran Digital</p>
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
                    <div class="input-group">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" required>
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                        <i class="fas fa-lock input-icon"></i>
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
                <p>&copy; <?php echo date('Y'); ?> Manajemen Jurnal</p>
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
        
        // Focus effect
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    </script>
</body>
</html>