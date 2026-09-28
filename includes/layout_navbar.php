<?php
/**
 * Layout Sidebar Component - Enterprise Theme
 */
$currentUser = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME']);

$roleBadge = [
    'admin'   => 'bg-blue-900 text-blue-200 border-blue-700',
    'petugas' => 'bg-emerald-900 text-emerald-200 border-emerald-700',
    'pic'     => 'bg-purple-900 text-purple-200 border-purple-700'
][$currentUser['role'] ?? ''] ?? 'bg-slate-800 text-slate-300';
?>
<?php
    // Daftar menu sidebar (satu sumber, dipakai untuk versi desktop & mobile)
    ob_start();
    if ($currentUser):
        if (in_array($currentUser['role'], ['admin', 'pic'])):
?>
        <a href="<?= BASE_URL ?>/public/dashboard.php" class="nav-link <?= $currentScript === 'dashboard.php' ? 'nav-link-active' : '' ?>">
            <i class="fa-solid fa-chart-pie w-4 text-center"></i> Dashboard
        </a>
<?php
        endif;
        if (in_array($currentUser['role'], ['admin', 'pic'])):
?>
        <a href="<?= BASE_URL ?>/public/daftar_kendaraan.php" class="nav-link <?= in_array($currentScript, ['daftar_kendaraan.php', 'edit_kendaraan.php']) ? 'nav-link-active' : '' ?>">
            <i class="fa-solid fa-truck w-4 text-center"></i> Kelola Armada
        </a>
        <a href="<?= BASE_URL ?>/public/riwayat_servis.php" class="nav-link <?= $currentScript === 'riwayat_servis.php' ? 'nav-link-active' : '' ?>">
            <i class="fa-solid fa-screwdriver-wrench w-4 text-center"></i> Riwayat Servis
        </a>
<?php
        endif;
        if ($currentUser['role'] === 'admin'):
?>
        <a href="<?= BASE_URL ?>/public/master_cabang.php" class="nav-link <?= $currentScript === 'master_cabang.php' ? 'nav-link-active' : '' ?>">
            <i class="fa-solid fa-building w-4 text-center"></i> Master Cabang
        </a>
        <a href="<?= BASE_URL ?>/public/kelola_user.php" class="nav-link <?= $currentScript === 'kelola_user.php' ? 'nav-link-active' : '' ?>">
            <i class="fa-solid fa-users-gear w-4 text-center"></i> Kelola User
        </a>
<?php
        endif;
?>
        <a href="<?= BASE_URL ?>/public/scan.php" class="nav-link <?= in_array($currentScript, ['scan.php', 'inspeksi_mulai.php', 'inspeksi_eksterior.php', 'inspeksi_interior.php', 'inspeksi_mesin.php', 'inspeksi_kakikaki.php', 'inspeksi_hasil.php']) ? 'nav-link-active' : '' ?>">
            <i class="fa-solid fa-qrcode w-4 text-center text-emerald-400"></i> Scan & Cek Lapangan
        </a>
        <a href="<?= BASE_URL ?>/public/riwayat_inspeksi.php" class="nav-link <?= $currentScript === 'riwayat_inspeksi.php' ? 'nav-link-active' : '' ?>">
            <i class="fa-solid fa-clock-rotate-left w-4 text-center"></i> Riwayat Inspeksi
        </a>
<?php
    else:
?>
        <a href="<?= BASE_URL ?>/public/login.php" class="nav-link">
            <i class="fa-solid fa-right-to-bracket w-4 text-center"></i> Masuk Akun
        </a>
<?php
    endif;
    $sidebarLinks = ob_get_clean();
?>

<!-- Sidebar Desktop -->
<aside class="hidden lg:flex flex-col w-60 shrink-0 h-screen sticky top-0 bg-slate-900 border-r border-slate-800 text-white no-print">
    <a href="<?= BASE_URL ?>/public/index.php" class="h-16 flex items-center gap-2.5 px-4 border-b border-slate-800 shrink-0">
        <div class="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center text-white shadow-sm shrink-0">
            <i class="fa-solid fa-truck-ramp-box text-base"></i>
        </div>
        <div class="min-w-0">
            <div class="font-bold text-white text-sm leading-tight truncate"><?= APP_NAME ?></div>
            <div class="text-[10px] text-slate-400 truncate">Operasional Armada</div>
        </div>
    </a>

    <nav class="flex-1 overflow-y-auto py-3 px-2.5 space-y-1"><?= $sidebarLinks ?></nav>

    <?php if ($currentUser): ?>
        <div class="border-t border-slate-800 p-3 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-slate-100 truncate"><?= htmlspecialchars($currentUser['nama']) ?></div>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="uppercase font-bold text-[9px] px-1.5 py-0.5 rounded border shrink-0 <?= $roleBadge ?>"><?= htmlspecialchars($currentUser['role']) ?></span>
                        <span class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($currentUser['cabang'] ?: 'Pusat') ?></span>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/public/logout.php" title="Keluar Akun" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white flex items-center justify-center transition-colors text-xs shrink-0">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>
</aside>

<!-- Kolom Konten: topbar mobile + drawer + konten utama, disatukan supaya
     tersusun vertikal (bukan sejajar horizontal dengan sidebar) -->
<div class="flex-1 min-w-0 flex flex-col">

<!-- Topbar Mobile (hanya tampil di layar sempit) -->
<div class="lg:hidden sticky top-0 z-40 bg-slate-900 border-b border-slate-800 text-white no-print">
    <div class="flex items-center justify-between h-16 px-4">
        <a href="<?= BASE_URL ?>/public/index.php" class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center text-white shadow-sm shrink-0">
                <i class="fa-solid fa-truck-ramp-box text-base"></i>
            </div>
            <div class="font-bold text-white text-sm truncate"><?= APP_NAME ?></div>
        </a>
        <button type="button" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="w-9 h-9 rounded-lg bg-slate-800 text-slate-300 hover:text-white flex items-center justify-center shrink-0">
            <i class="fa-solid fa-bars text-base"></i>
        </button>
    </div>
</div>

<!-- Sidebar Drawer Mobile -->
<div id="mobile-menu" class="hidden lg:hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/50" onclick="document.getElementById('mobile-menu').classList.add('hidden')"></div>
    <div class="absolute left-0 top-0 h-full w-64 bg-slate-900 border-r border-slate-800 text-white flex flex-col">
        <div class="h-16 flex items-center justify-between px-4 border-b border-slate-800 shrink-0">
            <div class="font-bold text-white text-sm"><?= APP_NAME ?></div>
            <button type="button" onclick="document.getElementById('mobile-menu').classList.add('hidden')" class="w-8 h-8 rounded-lg bg-slate-800 text-slate-300 hover:text-white flex items-center justify-center">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <?php if ($currentUser): ?>
            <div class="p-3 border-b border-slate-800 shrink-0">
                <div class="text-xs font-bold text-white"><?= htmlspecialchars($currentUser['nama']) ?></div>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="uppercase font-bold text-[9px] px-1.5 py-0.5 rounded border shrink-0 <?= $roleBadge ?>"><?= htmlspecialchars($currentUser['role']) ?></span>
                    <span class="text-[11px] text-slate-400"><?= htmlspecialchars($currentUser['cabang'] ?: 'Pusat') ?></span>
                </div>
            </div>
        <?php endif; ?>

        <nav class="flex-1 overflow-y-auto py-3 px-2.5 space-y-1"><?= $sidebarLinks ?></nav>

        <?php if ($currentUser): ?>
            <div class="border-t border-slate-800 p-3 shrink-0">
                <a href="<?= BASE_URL ?>/public/logout.php" class="nav-link text-rose-300 hover:bg-rose-900/40 hover:text-rose-200">
                    <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center"></i> Keluar Akun
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<main class="flex-1 flex flex-col">
    <div class="flex-1 w-full px-4 sm:px-6 lg:px-8 py-6">
        <?= render_flash_messages() ?>
