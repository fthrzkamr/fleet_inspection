<?php
/**
 * Halaman Cetak & Download Stiker QR Code Kendaraan - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$assetId = trim($_GET['asset_id'] ?? '');

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM kendaraan WHERE id = :id");
    $stmt->execute([':id' => $id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM kendaraan WHERE asset_id = :aid");
    $stmt->execute([':aid' => $assetId]);
}
$kendaraan = $stmt->fetch();

if (!$kendaraan) {
    flash_set('error', 'Kendaraan tidak ditemukan.');
    header('Location: ' . BASE_URL . '/public/daftar_kendaraan.php');
    exit;
}

$qrFullPath = BASE_PATH . '/' . $kendaraan['qr_code_path'];
if (empty($kendaraan['qr_code_path']) || !file_exists($qrFullPath)) {
    $qrRel = generate_vehicle_qr($kendaraan['asset_id']);
    $pdo->prepare("UPDATE kendaraan SET qr_code_path = :qr WHERE id = :id")->execute([':qr' => $qrRel, ':id' => $kendaraan['id']]);
    $kendaraan['qr_code_path'] = $qrRel;
    $qrFullPath = BASE_PATH . '/' . $qrRel;
}
$qrCacheBust = '?v=' . filemtime($qrFullPath);

$pageTitle = 'Cetak Stiker QR: ' . $kendaraan['asset_id'];
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<div class="max-w-md mx-auto space-y-4 py-2">
    <!-- Action Bar (Hidden on Print) -->
    <div class="flex items-center justify-between no-print px-1">
        <a href="<?= BASE_URL ?>/public/daftar_kendaraan.php" class="text-slate-600 hover:text-slate-900 text-xs font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar
        </a>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL . '/' . $kendaraan['qr_code_path'] . $qrCacheBust ?>" download="<?= $kendaraan['asset_id'] ?>_QR.svg" class="btn-secondary text-xs py-1.5 px-3">
                <i class="fa-solid fa-download"></i> Unduh File SVG
            </a>
            <button type="button" onclick="window.print()" class="btn-primary text-xs py-1.5 px-3.5 shadow-xs">
                <i class="fa-solid fa-print"></i> Cetak Stiker Sekarang
            </button>
        </div>
    </div>

    <!-- Printable Official Sticker Card -->
    <div class="bg-white p-5 text-center text-slate-900 border-2 border-slate-900 shadow-md rounded-xl print-area qr-card-print">
        <!-- Header Sticker -->
        <div class="flex items-center justify-between border-b-2 border-slate-900 pb-2 mb-2.5">
            <div class="flex items-center gap-2 text-left">
                <div class="w-6 h-6 rounded bg-slate-900 flex items-center justify-center text-white text-xs font-bold">
                    <i class="fa-solid fa-truck"></i>
                </div>
                <div>
                    <div class="font-bold text-xs tracking-tight text-slate-900 leading-none"><?= APP_NAME ?></div>
                    <div class="text-[8px] font-extrabold text-slate-500 uppercase tracking-wider">OFFICIAL FLEET TAG</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-[8px] font-bold text-slate-400 uppercase tracking-wider">CABANG OPERASIONAL</div>
                <div class="text-xs font-bold text-slate-900"><?= htmlspecialchars($kendaraan['cabang']) ?></div>
            </div>
        </div>

        <!-- Main QR Code Image & Asset ID -->
        <div class="my-2 flex flex-col items-center justify-center">
            <div class="p-2 bg-white border border-slate-300 rounded-lg inline-block shadow-2xs">
                <img src="<?= BASE_URL . '/' . $kendaraan['qr_code_path'] . $qrCacheBust ?>" alt="QR Code <?= $kendaraan['asset_id'] ?>" class="w-40 h-40 mx-auto object-contain">
            </div>
            <div class="mt-2 font-mono font-black text-lg tracking-widest text-slate-950 bg-slate-100 px-3 py-0.5 rounded border border-slate-400 inline-block">
                <?= htmlspecialchars($kendaraan['asset_id']) ?>
            </div>
        </div>

        <!-- Vehicle Details Summary -->
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-2.5 mt-2.5 text-left grid grid-cols-2 gap-2 text-xs">
            <div>
                <div class="text-[8px] uppercase font-bold text-slate-400">Nomor Polisi</div>
                <div class="font-black text-sm text-slate-900 leading-tight"><?= htmlspecialchars($kendaraan['no_polisi']) ?></div>
            </div>
            <div>
                <div class="text-[8px] uppercase font-bold text-slate-400">Merk & Tipe</div>
                <div class="font-bold text-slate-800 truncate leading-tight"><?= htmlspecialchars($kendaraan['merk']) ?> <?= htmlspecialchars($kendaraan['model']) ?></div>
            </div>
            <div class="col-span-2 pt-1 border-t border-slate-200 flex items-center justify-between text-[9px] text-slate-500">
                <span>VIN: <b class="font-mono text-slate-800"><?= htmlspecialchars($kendaraan['vin'] ?: '-') ?></b></span>
                <span>Registrasi: <?= date('d/m/Y', strtotime($kendaraan['created_at'])) ?></span>
            </div>
        </div>

        <!-- Scan Instruction Footer -->
        <div class="mt-2.5 pt-2 border-t border-dashed border-slate-300 text-center">
            <p class="text-[9px] font-semibold text-slate-600 flex items-center justify-center gap-1">
                <i class="fa-solid fa-qrcode text-slate-800"></i> Pindai stiker ini menggunakan menu <b>Scan & Cek</b> sebelum beroperasi.
            </p>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>
