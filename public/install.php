<?php
/**
 * Database Auto-Installer & Seeder Script - Enterprise UI
 * Fleet Inspection Web App
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/qrcode_helper.php';

// Kunci installer dengan login admin HANYA jika sistem sudah pernah di-install
// sebelumnya (supaya proses setup awal di server baru yang masih kosong tetap bisa jalan)
try {
    $checkPdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $alreadyInstalled = (int)$checkPdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0;
} catch (Exception $e) {
    $alreadyInstalled = false;
}
if ($alreadyInstalled) {
    require_role('admin');
}

$successMessages = [];
$errorMessages = [];
$isInstalled = false;

try {
    $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
    $pdoInit = new PDO($dsnNoDb, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $successMessages[] = "Database <b>" . DB_NAME . "</b> berhasil disiapkan.";
    
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $schemaSql = file_get_contents(__DIR__ . '/../schema.sql');
    $pdo->exec($schemaSql);
    $successMessages[] = "Struktur tabel database berhasil dibuat.";

    if (!$alreadyInstalled) {
    // Seeder data demo di bawah ini HANYA jalan sekali saat sistem masih benar-benar
    // kosong (belum ada user sama sekali). Setelah data asli ada, blok ini dilewati
    // sepenuhnya supaya install.php tidak pernah menimpa/mengotori data produksi.

    // Seeder Master Cabang
    $masterCabang = [
        ['kode' => 'JKT-PST', 'nama' => 'Jakarta Pusat (Pusat)', 'alamat' => 'Jl. Jenderal Sudirman No. 45, Jakarta Pusat', 'pic' => 'Budi Santoso'],
        ['kode' => 'JKT-BRT', 'nama' => 'Jakarta Barat', 'alamat' => 'Jl. Panjang No. 12, Kebon Jeruk, Jakarta Barat', 'pic' => 'Ahmad Fauzi'],
        ['kode' => 'JKT-SEL', 'nama' => 'Jakarta Selatan', 'alamat' => 'Jl. TB Simatupang No. 88, Jakarta Selatan', 'pic' => 'Rian Hidayat'],
        ['kode' => 'JKT-TMR', 'nama' => 'Jakarta Timur', 'alamat' => 'Jl. Pemuda No. 34, Rawamangun, Jakarta Timur', 'pic' => 'Dedi Kurniawan'],
        ['kode' => 'BKS',     'nama' => 'Bekasi', 'alamat' => 'Jl. Ahmad Yani No. 10, Bekasi Selatan', 'pic' => 'Agus Prasetyo'],
        ['kode' => 'TGR',     'nama' => 'Tangerang', 'alamat' => 'Jl. MH Thamrin No. 25, Cikokol, Tangerang', 'pic' => 'Faisal Rahman'],
        ['kode' => 'BGR',     'nama' => 'Bogor', 'alamat' => 'Jl. Pajajaran No. 70, Bogor', 'pic' => 'Yusuf Maulana'],
        ['kode' => 'SBY',     'nama' => 'Surabaya', 'alamat' => 'Jl. Basuki Rahmat No. 15, Surabaya', 'pic' => 'Slamet Riyadi'],
        ['kode' => 'BDG',     'nama' => 'Bandung', 'alamat' => 'Jl. Asia Afrika No. 100, Bandung', 'pic' => 'Cecep Supriatna'],
        ['kode' => 'SMG',     'nama' => 'Semarang', 'alamat' => 'Jl. Pandanaran No. 50, Semarang', 'pic' => 'Tri Wahyudi']
    ];

    // INSERT IGNORE: tidak menimpa data cabang yang sudah ada (aman dijalankan berkali-kali)
    $stmtCab = $pdo->prepare("INSERT IGNORE INTO cabang (kode_cabang, nama_cabang, alamat, penanggung_jawab, status)
                             VALUES (:kode, :nama, :alamat, :pic, 'active')");
    foreach ($masterCabang as $mc) {
        $stmtCab->execute([
            ':kode'   => $mc['kode'],
            ':nama'   => $mc['nama'],
            ':alamat' => $mc['alamat'],
            ':pic'    => $mc['pic']
        ]);
    }
    $successMessages[] = "Master data cabang operasional berhasil disiapkan (" . count($masterCabang) . " Cabang).";

    $users = [
        [
            'nama'     => 'Super Administrator',
            'username' => 'admin',
            'password' => 'admin123',
            'role'     => 'admin',
            'cabang'   => 'Jakarta Pusat (Pusat)',
            'no_wa'    => '081299998888'
        ],
        [
            'nama'     => 'Budi Santoso (Field Inspector)',
            'username' => 'petugas',
            'password' => 'petugas123',
            'role'     => 'petugas',
            'cabang'   => 'Jakarta Pusat (Pusat)',
            'no_wa'    => '081277776666'
        ],
        [
            'nama'     => 'Hendra Wijaya (Fleet PIC)',
            'username' => 'pic',
            'password' => 'pic123',
            'role'     => 'pic',
            'cabang'   => 'Jakarta Pusat (Pusat)',
            'no_wa'    => '081255554444'
        ]
    ];

    $stmtUser = $pdo->prepare("INSERT IGNORE INTO users (nama, username, password_hash, role, cabang, no_wa)
                               VALUES (:nama, :username, :password_hash, :role, :cabang, :no_wa)");
    
    foreach ($users as $u) {
        $stmtUser->execute([
            ':nama'          => $u['nama'],
            ':username'      => $u['username'],
            ':password_hash' => password_hash($u['password'], PASSWORD_DEFAULT),
            ':role'          => $u['role'],
            ':cabang'        => $u['cabang'],
            ':no_wa'         => $u['no_wa']
        ]);
    }
    $successMessages[] = "3 Akun pengguna default (<b>admin</b>, <b>petugas</b>, <b>pic</b>) siap digunakan.";

    $kendaraan = [
        [
            'asset_id'       => 'FLEET-001',
            'no_polisi'      => 'B 1234 ABC',
            'merk'           => 'Toyota',
            'model'          => 'Avanza 1.3 G MT',
            'vin'            => 'MHKM1BA3J8K001234',
            'cabang'         => 'Jakarta Pusat (Pusat)',
            'status'         => 'active',
            'reason'         => null,
            'tgl_stnk'       => date('Y-m-d', strtotime('+8 months')),
            'tgl_kir'        => date('Y-m-d', strtotime('+3 months'))
        ],
        [
            'asset_id'       => 'FLEET-002',
            'no_polisi'      => 'B 5678 XYZ',
            'merk'           => 'Daihatsu',
            'model'          => 'Gran Max Blind Van 1.3',
            'vin'            => 'MHKV2BA3J8K005678',
            'cabang'         => 'Jakarta Pusat (Pusat)',
            'status'         => 'active',
            'reason'         => null,
            'tgl_stnk'       => date('Y-m-d', strtotime('+12 days')),
            'tgl_kir'        => date('Y-m-d', strtotime('+20 days'))
        ],
        [
            'asset_id'       => 'FLEET-003',
            'no_polisi'      => 'B 9012 DEF',
            'merk'           => 'Isuzu',
            'model'          => 'Elf NMR 71 HD Box',
            'vin'            => 'MHM2BA3J8K009012',
            'cabang'         => 'Bekasi',
            'status'         => 'active',
            'reason'         => null,
            'tgl_stnk'       => date('Y-m-d', strtotime('+5 months')),
            'tgl_kir'        => date('Y-m-d', strtotime('-5 days'))
        ],
        [
            'asset_id'       => 'FLEET-004',
            'no_polisi'      => 'B 3456 GHI',
            'merk'           => 'Mitsubishi',
            'model'          => 'L300 Pick Up Diesel',
            'vin'            => 'MHML3BA3J8K003456',
            'cabang'         => 'Tangerang',
            'status'         => 'peremajaan',
            'reason'         => null,
            'tgl_stnk'       => date('Y-m-d', strtotime('+2 months')),
            'tgl_kir'        => date('Y-m-d', strtotime('+1 month'))
        ],
        [
            'asset_id'       => 'FLEET-005',
            'no_polisi'      => 'L 7890 JKL',
            'merk'           => 'Suzuki',
            'model'          => 'Carry Futura 1.5 Box',
            'vin'            => 'MHKC1BA3J8K007890',
            'cabang'         => 'Surabaya',
            'status'         => 'inactive',
            'reason'         => 'dijual',
            'tgl_stnk'       => date('Y-m-d', strtotime('-60 days')),
            'tgl_kir'        => date('Y-m-d', strtotime('-60 days'))
        ]
    ];

    $qrDir = UPLOADS_PATH . '/qr';
    if (!is_dir($qrDir)) {
        mkdir($qrDir, 0777, true);
    }

    // INSERT IGNORE: tidak menimpa data kendaraan yang sudah ada (aman dijalankan berkali-kali)
    $stmtK = $pdo->prepare("INSERT IGNORE INTO kendaraan (asset_id, no_polisi, merk, model, vin, cabang, status, status_inactive_reason, tgl_stnk_expired, tgl_kir_expired, qr_code_path)
                            VALUES (:asset_id, :no_polisi, :merk, :model, :vin, :cabang, :status, :reason, :tgl_stnk, :tgl_kir, :qr_path)");

    foreach ($kendaraan as $k) {
        $qrPath = 'uploads/qr/' . $k['asset_id'] . '.svg';
        // Hanya generate QR baru jika belum ada, supaya tidak menimpa stiker yang sudah dicetak
        if (!file_exists(BASE_PATH . '/' . $qrPath)) {
            SimpleQRCode::saveToFile($k['asset_id'], BASE_PATH . '/' . $qrPath, 320);
        }

        $stmtK->execute([
            ':asset_id'  => $k['asset_id'],
            ':no_polisi' => $k['no_polisi'],
            ':merk'      => $k['merk'],
            ':model'     => $k['model'],
            ':vin'       => $k['vin'],
            ':cabang'    => $k['cabang'],
            ':status'    => $k['status'],
            ':reason'    => $k['reason'],
            ':tgl_stnk'  => $k['tgl_stnk'],
            ':tgl_kir'   => $k['tgl_kir'],
            ':qr_path'   => $qrPath
        ]);
    }
    $successMessages[] = "5 Unit Armada dan stiker QR Code berhasil di-generate.";
    } else {
        $successMessages[] = "Sistem sudah pernah di-install sebelumnya \u{2014} seeding data demo dilewati, data yang ada tidak diubah.";
    }

    $isInstalled = true;

} catch (Exception $e) {
    $errorMessages[] = "Terjadi kesalahan saat instalasi: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup & Installer - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/public/assets/img/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 h-screen w-screen overflow-hidden flex items-center justify-center p-3 sm:p-4">
    <div class="max-w-xl w-full my-auto bg-white border border-slate-200 rounded-2xl shadow-sm p-5 sm:p-6 overflow-y-auto max-h-[95vh]">
        <div class="flex items-center gap-3 border-b border-slate-100 pb-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-blue-700 text-white flex items-center justify-center text-lg shadow-sm">
                <i class="fa-solid fa-truck-ramp-box"></i>
            </div>
            <div>
                <h1 class="text-base font-bold text-slate-900"><?= APP_NAME ?></h1>
                <p class="text-slate-500 text-xs">Setup Wizard Inisialisasi Database</p>
            </div>
        </div>

        <?php if (!empty($errorMessages)): ?>
            <div class="mb-5 p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 space-y-1">
                <div class="font-bold flex items-center gap-1.5 text-xs text-rose-700">
                    <i class="fa-solid fa-circle-exclamation text-sm"></i> Kendala Instalasi:
                </div>
                <ul class="list-disc list-inside text-xs">
                    <?php foreach ($errorMessages as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($isInstalled): ?>
            <div class="mb-5 p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 space-y-1">
                <div class="font-bold flex items-center gap-1.5 text-xs text-emerald-700">
                    <i class="fa-solid fa-circle-check text-sm"></i> Database & Akun Pengguna Siap Digunakan
                </div>
                <ul class="list-disc list-inside text-xs space-y-0.5 mt-1">
                    <?php foreach ($successMessages as $msg): ?>
                        <li><?= $msg ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Credentials Box -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 mb-5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">
                    <i class="fa-solid fa-key text-blue-700 mr-1"></i> Akun Default:
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                    <div class="p-2.5 bg-white border border-slate-200 rounded-lg">
                        <div class="font-bold text-blue-700">Admin</div>
                        <div class="text-slate-600 mt-0.5">User: <code class="font-bold text-slate-900">admin</code></div>
                        <div class="text-slate-600">Pass: <code class="font-bold text-slate-900">admin123</code></div>
                    </div>
                    <div class="p-2.5 bg-white border border-slate-200 rounded-lg">
                        <div class="font-bold text-emerald-700">Petugas</div>
                        <div class="text-slate-600 mt-0.5">User: <code class="font-bold text-slate-900">petugas</code></div>
                        <div class="text-slate-600">Pass: <code class="font-bold text-slate-900">petugas123</code></div>
                    </div>
                    <div class="p-2.5 bg-white border border-slate-200 rounded-lg">
                        <div class="font-bold text-purple-700">PIC Fleet</div>
                        <div class="text-slate-600 mt-0.5">User: <code class="font-bold text-slate-900">pic</code></div>
                        <div class="text-slate-600">Pass: <code class="font-bold text-slate-900">pic123</code></div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2.5">
                <a href="<?= BASE_URL ?>/public/login.php" class="flex-1 text-center py-2.5 px-4 rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs transition-colors flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-right-to-bracket"></i> Masuk ke Halaman Login &rarr;
                </a>
                <a href="<?= BASE_URL ?>/public/scan.php" class="py-2.5 px-4 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors text-center flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-qrcode"></i> Buka Scanner
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
