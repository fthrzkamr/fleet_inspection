<?php
/**
 * User Logout Action
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

logout_user();
session_start();
flash_set('info', 'Anda telah berhasil keluar dari sistem.');
header('Location: ' . BASE_URL . '/public/login.php');
exit;
