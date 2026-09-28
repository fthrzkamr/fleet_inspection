<?php
/**
 * Modul Autentikasi dan Manajemen Session Native PHP
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

// Inisialisasi session aman jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sedang login
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Ambil data user yang sedang login
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'],
        'nama'     => $_SESSION['user_nama'] ?? '',
        'username' => $_SESSION['user_username'] ?? '',
        'role'     => $_SESSION['user_role'] ?? '',
        'cabang'   => $_SESSION['user_cabang'] ?? '',
        'no_wa'    => $_SESSION['user_no_wa'] ?? ''
    ];
}

/**
 * Wajibkan user login untuk mengakses halaman
 */
function require_login() {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Silakan login terlebih dahulu untuk melanjutkan.';
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . '/public/login.php');
        exit;
    }
}

/**
 * Wajibkan role tertentu (bisa berupa string atau array role)
 */
function require_role($roles) {
    require_login();
    $user = current_user();
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    if (!in_array($user['role'], $roles)) {
        $_SESSION['flash_error'] = 'Anda tidak memiliki hak akses (Role: ' . strtoupper($user['role']) . ') untuk halaman tersebut.';
        
        // Redirect sesuai role default
        if ($user['role'] === 'petugas') {
            header('Location: ' . BASE_URL . '/public/scan.php');
        } else {
            header('Location: ' . BASE_URL . '/public/dashboard.php');
        }
        exit;
    }
}

/**
 * Proses login user
 */
function attempt_login($username, $password) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Regenerate session id untuk mencegah session fixation
        session_regenerate_id(true);
        
        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['user_nama']     = $user['nama'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['user_cabang']   = $user['cabang'];
        $_SESSION['user_no_wa']    = $user['no_wa'];
        
        return true;
    }
    
    return false;
}

/**
 * Logout dan bersihkan session
 */
function logout_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
