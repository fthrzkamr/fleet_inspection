<?php
/**
 * Root Routing Endpoint
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user['role'] === 'petugas') {
        header('Location: ' . BASE_URL . '/public/scan.php');
    } else {
        header('Location: ' . BASE_URL . '/public/dashboard.php');
    }
    exit;
} else {
    header('Location: ' . BASE_URL . '/public/login.php');
    exit;
}
