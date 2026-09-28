<?php
/**
 * Koneksi Database Menggunakan PDO dengan Prepared Statements
 * Menjamin keamanan dari SQL Injection dan efisiensi koneksi
 */

require_once __DIR__ . '/../config.php';

function get_db() {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Tampilkan pesan error ramah jika database belum diinstall
            die('<div style="font-family:sans-serif;padding:30px;max-width:600px;margin:50px auto;background:#fee2e2;border:1px solid #ef4444;border-radius:12px;color:#991b1b;">
                    <h2 style="margin-top:0;">Gagal Terhubung ke Database MySQL</h2>
                    <p>Database <code>' . htmlspecialchars(DB_NAME) . '</code> belum tersedia atau MySQL belum aktif.</p>
                    <p>Pesan Error: <i>' . htmlspecialchars($e->getMessage()) . '</i></p>
                    <p><a href="' . BASE_URL . '/public/install.php" style="display:inline-block;padding:10px 18px;background:#ef4444;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">Jalankan Installer / Setup Database Sekarang &rarr;</a></p>
                </div>');
        }
    }
    
    return $pdo;
}
