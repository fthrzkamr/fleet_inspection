<?php
/**
 * Konfigurasi Utama Aplikasi Fleet Inspection
 * Mendukung PHP 7.4+ Native
 */

// Pengaturan Error Reporting (Otomatis: tampil di localhost, disembunyikan di server live)
$isLocalEnv = php_sapi_name() === 'cli'
    || !isset($_SERVER['HTTP_HOST'])
    || in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])
    || strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0
    || strpos($_SERVER['HTTP_HOST'], '127.0.0.1:') === 0;

error_reporting(E_ALL);
ini_set('display_errors', $isLocalEnv ? 1 : 0);
ini_set('log_errors', 1);

// Timezone Default Indonesia/Jakarta
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi Database MySQL — disimpan terpisah di config.local.php (per-environment,
// TIDAK ikut Git) supaya kredensial lokal & hosting tidak saling menimpa saat deploy.
$localConfigFile = __DIR__ . '/config.local.php';
if (file_exists($localConfigFile)) {
    require $localConfigFile;
} else {
    die('File config.local.php belum ada. Salin config.local.example.php menjadi config.local.php lalu isi kredensial database environment ini.');
}

// Konfigurasi Base URL & Path
define('APP_NAME', 'FleetGuard Digital');
define('BASE_PATH', __DIR__);
define('UPLOADS_PATH', BASE_PATH . '/uploads');

// URL Base (Otomatis mendeteksi HTTPS, Ngrok Tunnel, Cloudflare, dan Localhost)
if (php_sapi_name() === 'cli' || !isset($_SERVER['REQUEST_URI'])) {
    define('BASE_URL', 'http://localhost/fleet_inspection');
} else {
    // Deteksi protokol HTTPS termasuk reverse proxy / ngrok
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
               || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
               || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    
    $protocol = $isHttps ? "https://" : "http://";
    
    // Deteksi host (prioritas HTTP_X_FORWARDED_HOST dari proxy/ngrok jika ada)
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Jika ada multi-host di header forwarded, ambil yang pertama
    if (strpos($host, ',') !== false) {
        $host = trim(explode(',', $host)[0]);
    }
    
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    // Deteksi root subfolder seperti /fleet_inspection
    if (preg_match('#^(/[^/]+)#', $scriptName, $m) && $m[1] !== '/public') {
        define('BASE_URL', $protocol . $host . $m[1]);
    } else {
        define('BASE_URL', $protocol . $host);
    }
}

// ============================================================
// MODE MAINTENANCE
// Ubah MAINTENANCE_MODE ke true untuk menutup akses situs sementara.
// Berlaku untuk SEMUA ORANG tanpa kecuali (termasuk login) - matikan lagi
// dengan mengubah nilainya kembali ke false lalu simpan. Cukup edit dua baris
// ini, tidak perlu database atau halaman admin manapun, supaya tetap bisa
// dipakai walau ada masalah lain (termasuk kalau database down).
// ============================================================
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'Sistem sedang dalam perbaikan terjadwal. Mohon coba akses kembali beberapa saat lagi.');

if (MAINTENANCE_MODE && php_sapi_name() !== 'cli') {
    http_response_code(503);
    header('Retry-After: 3600');
    require __DIR__ . '/includes/maintenance_page.php';
    exit;
}

// Daftar Item Kritis Inspeksi (Jika kondisi 'rusak', otomatis memicu status 'Mayor / STOP')
define('CRITICAL_INSPECTION_ITEMS', [
    'Sistem Pengereman (Rem Kaki & Tangan)',
    'Kondisi Ban Luar (Gundul/Sobek)',
    'Lampu Utama & Sein',
    'Kebocoran Cairan (Oli / Radiator / Minyak Rem)',
    'Kondisi Aki & Kelistrikan Kritis',
    'Kondisi Kaki-kaki & Kemudi'
]);

// Kategori Inspeksi & Urutannya
define('INSPECTION_CATEGORIES', [
    1 => ['key' => 'eksterior', 'title' => 'Eksterior & Keliling', 'file' => 'inspeksi_eksterior.php'],
    2 => ['key' => 'interior_kelistrikan', 'title' => 'Interior & Kelistrikan', 'file' => 'inspeksi_interior.php'],
    3 => ['key' => 'mesin', 'title' => 'Area Mesin', 'file' => 'inspeksi_mesin.php'],
    4 => ['key' => 'kaki_kaki', 'title' => 'Kaki-kaki & Ban Serep', 'file' => 'inspeksi_kakikaki.php']
]);
