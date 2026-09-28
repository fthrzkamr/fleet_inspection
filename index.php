<?php
/**
 * Root Redirector ke /public/index.php
 */
require_once __DIR__ . '/config.php';
header('Location: ' . BASE_URL . '/public/index.php');
exit;
