<?php
/**
 * Halaman Ringkasan Kendaraan & Start / Resume Sesi Inspeksi - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = get_db();
$user = current_user();

$assetId = trim($_GET['asset_id'] ?? '');
$kendaraanId = (int)($_GET['id'] ?? 0);

if (!empty($assetId)) {
    $stmt = $pdo->prepare("SELECT * FROM kendaraan WHERE asset_id = :aid");
    $stmt->execute([':aid' => $assetId]);
} elseif ($kendaraanId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM kendaraan WHERE id = :id");
    $stmt->execute([':id' => $kendaraanId]);
} else {
    flash_set('error', 'Asset ID kendaraan tidak valid.');
    header('Location: ' . BASE_URL . '/public/scan.php');
    exit;
}

$kendaraan = $stmt->fetch();

if (!$kendaraan) {
    flash_set('error', 'Kendaraan dengan ID tersebut tidak terdaftar di sistem.');
    header('Location: ' . BASE_URL . '/public/scan.php');
    exit;
}

// Cek sesi inspeksi aktif hari ini yang belum selesai (page_progress < 4 atau tanggal_selesai IS NULL)
$stmtActive = $pdo->prepare("SELECT * FROM inspeksi 
                            WHERE kendaraan_id = :kid 
                            AND tanggal_selesai IS NULL 
                            AND DATE(tanggal_mulai) = CURDATE() 
                            ORDER BY id DESC LIMIT 1");
$stmtActive->execute([':kid' => $kendaraan['id']]);
$activeSession = $stmtActive->fetch();

// Cek riwayat inspeksi terakhir yang sudah selesai
$stmtLast = $pdo->prepare("SELECT i.*, u.nama as petugas_nama 
                          FROM inspeksi i 
                          LEFT JOIN users u ON i.petugas_id = u.id 
                          WHERE i.kendaraan_id = :kid AND i.tanggal_selesai IS NOT NULL 
                          ORDER BY i.id DESC LIMIT 1");
$stmtLast->execute([':kid' => $kendaraan['id']]);
$lastInspection = $stmtLast->fetch();

// Action Handle: Mulai Sesi Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_new_session') {
    $stmtNew = $pdo->prepare("INSERT INTO inspeksi (kendaraan_id, petugas_id, tanggal_mulai, page_progress) 
                             VALUES (:kid, :uid, NOW(), 0)");
    $stmtNew->execute([
        ':kid' => $kendaraan['id'],
        ':uid' => $user['id']
    ]);
    $newInspeksiId = $pdo->lastInsertId();

    header('Location: ' . BASE_URL . '/public/inspeksi_eksterior.php?inspeksi_id=' . $newInspeksiId);
    exit;
}

$categoryFiles = [
    0 => 'inspeksi_eksterior.php',
    1 => 'inspeksi_interior.php',
    2 => 'inspeksi_mesin.php',
    3 => 'inspeksi_kakikaki.php',
    4 => 'inspeksi_hasil.php'
];

$pageTitle = 'Cek Fisik: ' . $kendaraan['asset_id'];
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<div class="max-w-xl mx-auto space-y-5">
    <!-- Breadcrumb / Back Navigation -->
    <div class="flex items-center justify-between">
        <a href="<?= BASE_URL ?>/public/scan.php" class="text-slate-600 hover:text-slate-900 text-xs font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Scanner
        </a>
        <span class="text-xs text-slate-500 font-mono">Kode: <?= htmlspecialchars($kendaraan['asset_id']) ?></span>
    </div>

    <!-- Vehicle Profile Card -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4">
            <!-- QR Sticker Preview -->
            <div class="w-20 h-20 bg-slate-50 p-2 rounded-xl border border-slate-200 flex-shrink-0 flex items-center justify-center">
                <?php if (file_exists(BASE_PATH . '/' . $kendaraan['qr_code_path'])): ?>
                    <img src="<?= BASE_URL . '/' . $kendaraan['qr_code_path'] . '?v=' . filemtime(BASE_PATH . '/' . $kendaraan['qr_code_path']) ?>" alt="QR" class="w-full h-full object-contain">
                <?php else: ?>
                    <i class="fa-solid fa-qrcode text-3xl text-slate-600"></i>
                <?php endif; ?>
            </div>

            <!-- Vehicle Key Data -->
            <div class="flex-1 text-center sm:text-left space-y-1">
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                    <h2 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($kendaraan['no_polisi']) ?></h2>
                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-blue-50 text-blue-700 border border-blue-200">
                        <?= htmlspecialchars($kendaraan['asset_id']) ?>
                    </span>
                    <?= format_badge_status($kendaraan['status'], $kendaraan['status_inactive_reason']) ?>
                </div>

                <div class="text-xs font-semibold text-slate-700">
                    <?= htmlspecialchars($kendaraan['merk']) ?> <?= htmlspecialchars($kendaraan['model']) ?>
                </div>

                <div class="text-xs text-slate-500 flex flex-wrap items-center justify-center sm:justify-start gap-3 pt-1">
                    <span>
                        <i class="fa-solid fa-location-dot text-slate-400 mr-1"></i> <?= htmlspecialchars($kendaraan['cabang']) ?>
                    </span>
                    <span class="font-mono">
                        VIN: <?= htmlspecialchars($kendaraan['vin'] ?: '-') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Document Expiry Status Mini Banner -->
        <?php
            $stnkExp = $kendaraan['tgl_stnk_expired'] ? strtotime($kendaraan['tgl_stnk_expired']) : null;
            $kirExp  = $kendaraan['tgl_kir_expired'] ? strtotime($kendaraan['tgl_kir_expired']) : null;
            $platExp = $kendaraan['tgl_plat_expired'] ? strtotime($kendaraan['tgl_plat_expired']) : null;
            $now = time();
            $stnkDays = $stnkExp ? round(($stnkExp - $now) / 86400) : null;
            $kirDays  = $kirExp ? round(($kirExp - $now) / 86400) : null;
            $platDays = $platExp ? round(($platExp - $now) / 86400) : null;
            // Ambang batas "2 bulan kalender", konsisten dengan query reminder di dashboard.php
            $reminderThresholdDate = (new DateTime('today'))->modify('+2 months');
            $reminderLeadDays = (int)(new DateTime('today'))->diff($reminderThresholdDate)->days;
        ?>
        <div class="mt-4 pt-3 border-t border-slate-100 grid grid-cols-3 gap-3 text-xs">
            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200">
                <div class="text-[10px] uppercase font-bold text-slate-500">Pajak STNK</div>
                <?php if ($stnkExp): ?>
                    <div class="font-bold <?= ($stnkDays <= $reminderLeadDays) ? 'text-amber-700' : 'text-slate-800' ?> mt-0.5">
                        <?= date('d M Y', $stnkExp) ?>
                        <?php if ($stnkDays <= $reminderLeadDays): ?>
                            <span class="text-[10px] block text-amber-700">(<?= $stnkDays < 0 ? 'Kadaluarsa!' : "Sisa $stnkDays hari" ?>)</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-slate-400">-</div>
                <?php endif; ?>
            </div>

            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200">
                <div class="text-[10px] uppercase font-bold text-slate-500">Masa Uji KIR</div>
                <?php if ($kirExp): ?>
                    <div class="font-bold <?= ($kirDays <= $reminderLeadDays) ? 'text-amber-700' : 'text-slate-800' ?> mt-0.5">
                        <?= date('d M Y', $kirExp) ?>
                        <?php if ($kirDays <= $reminderLeadDays): ?>
                            <span class="text-[10px] block text-amber-700">(<?= $kirDays < 0 ? 'Kadaluarsa!' : "Sisa $kirDays hari" ?>)</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-slate-400">-</div>
                <?php endif; ?>
            </div>

            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200">
                <div class="text-[10px] uppercase font-bold text-slate-500">Plat (TNKB)</div>
                <?php if ($platExp): ?>
                    <div class="font-bold <?= ($platDays <= $reminderLeadDays) ? 'text-amber-700' : 'text-slate-800' ?> mt-0.5">
                        <?= date('d M Y', $platExp) ?>
                        <?php if ($platDays <= $reminderLeadDays): ?>
                            <span class="text-[10px] block text-amber-700">(<?= $platDays < 0 ? 'Kadaluarsa!' : "Sisa $platDays hari" ?>)</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-slate-400">-</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Active Incomplete Session Banner (Resume Capability) -->
    <?php if ($activeSession): ?>
        <?php 
            $progressStep = (int)$activeSession['page_progress'];
            $targetFile = $categoryFiles[$progressStep] ?? 'inspeksi_eksterior.php';
            $stepLabels = [
                0 => 'Step 1: Eksterior & Keliling',
                1 => 'Step 2: Interior & Kelistrikan',
                2 => 'Step 3: Area Mesin',
                3 => 'Step 4: Kaki-kaki & Ban Serep',
                4 => 'Step 5: Evaluasi & Konfirmasi'
            ];
            $currentStepName = $stepLabels[$progressStep] ?? 'Step 1';
        ?>
        <div class="p-5 rounded-xl bg-blue-50 border border-blue-200 shadow-sm space-y-3.5">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-600 text-white flex items-center justify-center text-base flex-shrink-0">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-blue-950">Sesi Cek Hari Ini Masih Berjalan</h3>
                    <p class="text-xs text-blue-800/80 mt-0.5">
                        Terdapat sesi inspeksi tanggal <b class="text-blue-950"><?= date('d M Y H:i', strtotime($activeSession['tanggal_mulai'])) ?></b> yang tersimpan di <b><?= $currentStepName ?></b>.
                    </p>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="space-y-1">
                <div class="flex justify-between text-[11px] font-semibold text-blue-900">
                    <span>Kemajuan Checklist:</span>
                    <span><?= $progressStep ?> dari 4 Tahap (<?= $progressStep * 25 ?>%)</span>
                </div>
                <div class="w-full bg-blue-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $progressStep * 25 ?>%"></div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 pt-1">
                <a href="<?= BASE_URL ?>/public/<?= $targetFile ?>?inspeksi_id=<?= $activeSession['id'] ?>" class="btn-primary flex-1 py-2.5 text-xs font-bold">
                    <i class="fa-solid fa-play mr-1"></i> Lanjutkan Sesi (<?= $currentStepName ?>) &rarr;
                </a>
                
                <form method="POST" action="" onsubmit="return confirmFormSubmit(event, this, { title: 'Mulai Sesi Baru?', message: 'Mulai sesi baru akan mengabaikan progres sesi pemeriksaan sebelumnya. Lanjutkan?', confirmText: 'Ya, Mulai Baru', confirmType: 'warning' })">
                    <input type="hidden" name="action" value="start_new_session">
                    <button type="submit" class="btn-secondary w-full sm:w-auto py-2.5 text-xs">
                        <i class="fa-solid fa-rotate-right mr-1"></i> Mulai Baru
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Fresh Start Action -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm text-center space-y-3.5">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 text-lg flex items-center justify-center mx-auto border border-emerald-200">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900">Mulai Formulir Pemeriksaan Fisik</h3>
                <p class="text-xs text-slate-500 mt-0.5 max-w-sm mx-auto">
                    Pemeriksaan mencakup 4 kategori berurutan dengan opsi dokumentasi foto kamera lapangan.
                </p>
            </div>

            <form method="POST" action="" class="pt-1">
                <input type="hidden" name="action" value="start_new_session">
                <button type="submit" class="btn-primary w-full py-3 text-xs sm:text-sm font-bold">
                    <i class="fa-solid fa-play mr-1"></i> Mulai Checklist Inspeksi (Tahap 1) &rarr;
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Last Completed Inspection History Box -->
    <?php if ($lastInspection): ?>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5 mb-2.5">
                <div class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-clock-rotate-left text-blue-700"></i> Hasil Inspeksi Terakhir:
                </div>
                <?= format_badge_hasil($lastInspection['hasil_akhir']) ?>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                <div>
                    <span class="text-slate-500 text-[10px] block">Waktu Selesai:</span>
                    <span class="font-semibold text-slate-800"><?= format_date_id($lastInspection['tanggal_selesai']) ?></span>
                </div>
                <div>
                    <span class="text-slate-500 text-[10px] block">Petugas:</span>
                    <span class="font-semibold text-slate-800"><?= htmlspecialchars($lastInspection['petugas_nama'] ?: 'Petugas') ?></span>
                </div>
                <div>
                    <span class="text-slate-500 text-[10px] block">Odometer / BBM:</span>
                    <span class="font-semibold text-slate-800"><?= number_format((int)$lastInspection['odometer'], 0, ',', '.') ?> KM &bull; <?= htmlspecialchars($lastInspection['bbm_level'] ?: '-') ?></span>
                </div>
            </div>
            <?php if (!empty($lastInspection['catatan_umum'])): ?>
                <div class="mt-2.5 p-2 bg-slate-50 rounded border border-slate-200 text-xs text-slate-700">
                    <span class="font-bold text-slate-500 text-[10px] block">Catatan:</span>
                    <?= nl2br(htmlspecialchars($lastInspection['catatan_umum'])) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>
