<?php
/**
 * User Logout Action
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    $user = current_user();
    log_activity('LOGOUT', 'Autentikasi', 'User berhasil keluar (logout) dari sistem', $user['username'], $user);
}
logout_user();
session_start();
flash_set('info', 'Anda telah berhasil keluar dari sistem.');
header('Location: ' . BASE_URL . '/public/login.php');
exit;
