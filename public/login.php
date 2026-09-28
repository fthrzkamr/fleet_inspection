<?php
/**
 * Halaman Login Petugas & Administrator - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/public/index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        if (attempt_login($username, $password)) {
            $user = current_user();
            flash_set('success', 'Selamat datang, ' . $user['nama'] . '.');
            
            $target = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);

            if ($target) {
                header('Location: ' . $target);
            } else {
                if ($user['role'] === 'petugas') {
                    header('Location: ' . BASE_URL . '/public/scan.php');
                } else {
                    header('Location: ' . BASE_URL . '/public/dashboard.php');
                }
            }
            exit;
        } else {
            $error = 'Username atau password yang Anda masukkan tidak sesuai.';
        }
    }
}

$pageTitle = 'Login Sistem - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/public/assets/img/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
</head>
<body class="bg-slate-100 text-slate-800 h-screen w-screen overflow-hidden flex items-center justify-center p-3 sm:p-4">
    
    <div class="max-w-[420px] w-full my-auto flex flex-col justify-center">
        <!-- Logo & Company Branding Header -->
        <div class="text-center mb-3.5">
            <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-700 text-white text-lg shadow-xs mb-1.5">
                <i class="fa-solid fa-truck-ramp-box"></i>
            </div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight leading-tight"><?= APP_NAME ?></h1>
            <p class="text-slate-500 text-[11px]">Sistem Informasi Pengecekan Kendaraan Operasional</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 sm:p-6">
            <div class="mb-3.5 border-b border-slate-100 pb-2.5">
                <h2 class="text-sm font-bold text-slate-900">Masuk ke Akun</h2>
                <p class="text-[11px] text-slate-500 mt-0.5">Silakan masukkan username dan kata sandi Anda.</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-3 p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-xs flex-shrink-0"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?= render_flash_messages() ?>

            <form method="POST" action="" class="space-y-3">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none text-slate-400">
                            <i class="fa-regular fa-user text-xs"></i>
                        </span>
                        <input type="text" name="username" id="input-username" required placeholder="Masukkan username" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 rounded-lg text-xs text-slate-900 placeholder-slate-400 outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </span>
                        <input type="password" name="password" id="input-password" required placeholder="Masukkan kata sandi"
                               class="w-full pl-8 pr-8 py-1.5 bg-white border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 rounded-lg text-xs text-slate-900 placeholder-slate-400 outline-none transition-all">
                        <button type="button" onclick="togglePassVisibility()" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600">
                            <i class="fa-solid fa-eye text-xs" id="toggle-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="pt-1">
                    <button type="submit" class="btn-primary w-full py-2 text-xs font-bold shadow-xs">
                        <i class="fa-solid fa-right-to-bracket mr-1"></i> Masuk Sekarang
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script>
        function togglePassVisibility() {
            const passInput = document.getElementById('input-password');
            const eyeIcon = document.getElementById('toggle-eye');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
