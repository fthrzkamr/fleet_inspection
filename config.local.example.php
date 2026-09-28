<?php
/**
 * Contoh konfigurasi lokal (kredensial database per-environment).
 *
 * File ini HANYA contoh/template dan ikut tersimpan di Git.
 * Salin file ini menjadi "config.local.php" (tanpa ".example") di folder yang sama,
 * lalu isi dengan kredensial database yang sesuai untuk environment ini
 * (lokal/XAMPP atau server hosting). File "config.local.php" SENGAJA diabaikan
 * oleh Git (lihat .gitignore) supaya kredensial tiap environment tidak saling
 * menimpa saat kode disinkronkan lewat Git.
 */

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'fleet_inspection_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
