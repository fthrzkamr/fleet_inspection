<?php
/**
 * Partial: Filter bar + Ringkasan + Tabel Log Aktivitas Pengguna
 * Di-include untuk respons AJAX (fragment) dan halaman penuh.
 */

// Mapping Warna Badge Aksi
$actionBadgeColors = [
    'LOGIN'            => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'LOGIN_GAGAL'      => 'bg-rose-50 text-rose-700 border-rose-200',
    'LOGOUT'           => 'bg-slate-100 text-slate-700 border-slate-300',
    'TAMBAH_KENDARAAN' => 'bg-blue-50 text-blue-700 border-blue-200',
    'EDIT_KENDARAAN'   => 'bg-amber-50 text-amber-700 border-amber-200',
    'HAPUS_KENDARAAN'  => 'bg-rose-50 text-rose-700 border-rose-200',
    'REFRESH_ARMADA'   => 'bg-purple-50 text-purple-700 border-purple-200',
    'STATUS_KENDARAAN' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    'MULAI_INSPEKSI'   => 'bg-sky-50 text-sky-700 border-sky-200',
    'SELESAI_INSPEKSI' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'TAMBAH_SERVIS'    => 'bg-teal-50 text-teal-700 border-teal-200',
    'EDIT_SERVIS'      => 'bg-amber-50 text-amber-700 border-amber-200',
    'HAPUS_SERVIS'     => 'bg-rose-50 text-rose-700 border-rose-200',
    'IMPORT_SERVIS'    => 'bg-cyan-50 text-cyan-700 border-cyan-200',
    'TAMBAH_CABANG'    => 'bg-blue-50 text-blue-700 border-blue-200',
    'EDIT_CABANG'      => 'bg-amber-50 text-amber-700 border-amber-200',
    'STATUS_CABANG'    => 'bg-violet-50 text-violet-700 border-violet-200',
    'HAPUS_CABANG'     => 'bg-rose-50 text-rose-700 border-rose-200',
    'TAMBAH_USER'      => 'bg-blue-50 text-blue-700 border-blue-200',
    'EDIT_USER'        => 'bg-amber-50 text-amber-700 border-amber-200',
    'HAPUS_USER'       => 'bg-rose-50 text-rose-700 border-rose-200',
    'EXPORT_EXCEL'     => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    'CETAK_QR'         => 'bg-violet-50 text-violet-700 border-violet-200',
];

// Mapping Icon Modul
$moduleIcons = [
    'Autentikasi'   => 'fa-solid fa-key text-slate-500',
    'Kendaraan'     => 'fa-solid fa-truck text-blue-600',
    'Inspeksi'      => 'fa-solid fa-clipboard-check text-emerald-600',
    'Servis'        => 'fa-solid fa-screwdriver-wrench text-teal-600',
    'Master Cabang' => 'fa-solid fa-building text-purple-600',
    'Kelola User'   => 'fa-solid fa-users-gear text-amber-600',
    'Laporan'       => 'fa-solid fa-file-excel text-emerald-700'
];

$roleBadgeColors = [
    'admin'   => 'bg-blue-100 text-blue-800 border-blue-200',
    'petugas' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    'pic'     => 'bg-purple-100 text-purple-800 border-purple-200'
];

$hasActiveFilter = !empty($filterQ) || !empty($filterUserId) || !empty($filterRole) 
                || !empty($filterModule) || !empty($filterAction) || !empty($filterCabang) 
                || !empty($filterTglAwal) || !empty($filterTglAkhir);

// Build URL query untuk export
$exportParams = $_GET;
$exportParams['type'] = 'log_aktivitas';
unset($exportParams['ajax'], $exportParams['page']);
$exportUrl = BASE_URL . '/public/export_excel.php?' . http_build_query($exportParams);
?>

<!-- Ringkasan Statistik Log (KPI Cards) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-list-check text-base"></i>
        </div>
        <div class="min-w-0">
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider truncate">Total Log</div>
            <div class="text-lg font-bold text-slate-900"><?= number_format($totalRows, 0, ',', '.') ?></div>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-calendar-day text-base"></i>
        </div>
        <div class="min-w-0">
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider truncate">Hari Ini</div>
            <div class="text-lg font-bold text-slate-900"><?= number_format($statTotalToday, 0, ',', '.') ?></div>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-user-check text-base"></i>
        </div>
        <div class="min-w-0">
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider truncate">User Teraktif</div>
            <div class="text-sm font-bold text-slate-900 truncate" title="<?= htmlspecialchars($statTopUser['user_nama'] ?? '-') ?>">
                <?= htmlspecialchars($statTopUser['user_nama'] ?? '-') ?>
            </div>
            <div class="text-[10px] text-slate-400 font-medium"><?= (int)($statTopUser['total'] ?? 0) ?> aksi</div>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-layer-group text-base"></i>
        </div>
        <div class="min-w-0">
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider truncate">Modul Teratas</div>
            <div class="text-sm font-bold text-slate-900 truncate" title="<?= htmlspecialchars($statTopModule['module'] ?? '-') ?>">
                <?= htmlspecialchars($statTopModule['module'] ?? '-') ?>
            </div>
            <div class="text-[10px] text-slate-400 font-medium"><?= (int)($statTopModule['total'] ?? 0) ?> aksi</div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
    <form method="GET" class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
            <!-- Keyword Search -->
            <div class="lg:col-span-3">
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Cari Kata Kunci</label>
                <div class="relative">
                    <input type="text" name="q" value="<?= htmlspecialchars($filterQ) ?>" 
                           placeholder="Cari deskripsi, target, IP..." 
                           class="w-full py-2 pl-8 pr-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 text-slate-400 text-xs"></i>
                </div>
            </div>

            <!-- Filter User -->
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">User</label>
                <select name="user_id" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="">-- Semua User --</option>
                    <?php foreach ($allUsers as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $filterUserId === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nama']) ?> (@<?= htmlspecialchars($u['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Role -->
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Role</label>
                <select name="role" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="">-- Semua Role --</option>
                    <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="petugas" <?= $filterRole === 'petugas' ? 'selected' : '' ?>>Petugas Lapangan</option>
                    <option value="pic" <?= $filterRole === 'pic' ? 'selected' : '' ?>>PIC Armada</option>
                </select>
            </div>

            <!-- Filter Modul -->
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Modul</label>
                <select name="module" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="">-- Semua Modul --</option>
                    <?php foreach ($allModules as $mod): ?>
                        <option value="<?= htmlspecialchars($mod) ?>" <?= $filterModule === $mod ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mod) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Cabang -->
            <div class="lg:col-span-3">
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Cabang</label>
                <select name="cabang" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="">-- Semua Cabang --</option>
                    <?php foreach ($allCabang as $cb): ?>
                        <option value="<?= htmlspecialchars($cb['nama_cabang']) ?>" <?= $filterCabang === $cb['nama_cabang'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cb['nama_cabang']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Baris Kedua: Rentang Tanggal + Tombol Aksi -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 pt-1 border-t border-slate-100">
            <div class="lg:col-span-3">
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Dari Tanggal</label>
                <input type="date" name="tgl_awal" value="<?= htmlspecialchars($filterTglAwal) ?>"
                       class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div class="lg:col-span-3">
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Sampai Tanggal</label>
                <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($filterTglAkhir) ?>"
                       class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div class="lg:col-span-6 flex items-end gap-2 justify-end">
                <button type="submit" class="btn-primary py-2 px-4 text-xs flex items-center gap-1.5 shadow-xs">
                    <i class="fa-solid fa-filter"></i> Terapkan Filter
                </button>

                <?php if ($hasActiveFilter): ?>
                    <a href="?" class="btn-secondary py-2 px-3 text-xs flex items-center gap-1.5" title="Reset Semua Filter">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                <?php endif; ?>

                <a href="<?= $exportUrl ?>" target="_blank" data-no-ajax class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-xs transition-colors" title="Export Log ke Excel / CSV">
                    <i class="fa-solid fa-file-excel"></i> Export CSV
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Tabel Log Aktivitas -->
<div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700">
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
                <tr>
                    <th class="py-3 px-3.5 w-12 text-center">No</th>
                    <th class="py-3 px-3.5 whitespace-nowrap">Waktu</th>
                    <th class="py-3 px-3.5 whitespace-nowrap">Pengguna</th>
                    <th class="py-3 px-3.5 whitespace-nowrap">Modul & Aksi</th>
                    <th class="py-3 px-3.5">Deskripsi Aktivitas</th>
                    <th class="py-3 px-3.5 whitespace-nowrap">Target / Ref</th>
                    <th class="py-3 px-3.5 whitespace-nowrap">IP & Device</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <i class="fa-solid fa-clock-rotate-left text-3xl mb-2.5 block opacity-40"></i>
                            <div class="font-medium text-slate-600">Belum ada riwayat aktivitas yang sesuai filter.</div>
                            <div class="text-[11px] text-slate-400 mt-0.5">Coba ubah kata kunci atau bersihkan filter di atas.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = (int)$pagination['offset'] + 1;
                    foreach ($logs as $log): 
                        $actionBadge = $actionBadgeColors[$log['action']] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                        $modIcon = $moduleIcons[$log['module']] ?? 'fa-solid fa-cube text-slate-500';
                        $roleBadge = $roleBadgeColors[$log['user_role']] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                        $timeAgo = time_ago_id($log['created_at']);
                    ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <!-- No -->
                            <td class="py-3 px-3.5 text-center text-slate-400 font-mono text-[11px]">
                                <?= $no++ ?>
                            </td>

                            <!-- Waktu -->
                            <td class="py-3 px-3.5 whitespace-nowrap">
                                <div class="font-bold text-slate-900"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                                <div class="text-[11px] text-slate-500 font-mono"><?= date('H:i:s', strtotime($log['created_at'])) ?> WIB</div>
                                <div class="text-[10px] text-blue-600 font-medium"><?= $timeAgo ?></div>
                            </td>

                            <!-- Pengguna -->
                            <td class="py-3 px-3.5">
                                <div class="font-bold text-slate-900 leading-tight"><?= htmlspecialchars($log['user_nama']) ?></div>
                                <div class="text-[11px] text-slate-500 font-mono">@<?= htmlspecialchars($log['user_username']) ?></div>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase border <?= $roleBadge ?>">
                                        <?= htmlspecialchars($log['user_role']) ?>
                                    </span>
                                    <?php if (!empty($log['cabang'])): ?>
                                        <span class="text-[10px] text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200 truncate max-w-[130px]" title="<?= htmlspecialchars($log['cabang']) ?>">
                                            <i class="fa-solid fa-location-dot text-[9px] text-slate-400 mr-0.5"></i><?= htmlspecialchars($log['cabang']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Modul & Aksi -->
                            <td class="py-3 px-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-1.5 text-slate-700 font-semibold mb-1">
                                    <i class="<?= $modIcon ?> w-3.5 text-center"></i>
                                    <span><?= htmlspecialchars($log['module']) ?></span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border <?= $actionBadge ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?>
                                </span>
                            </td>

                            <!-- Deskripsi -->
                            <td class="py-3 px-3.5 text-slate-800 leading-relaxed max-w-md">
                                <span class="text-xs"><?= htmlspecialchars($log['description']) ?></span>
                            </td>

                            <!-- Target / Ref -->
                            <td class="py-3 px-3.5 whitespace-nowrap">
                                <?php if (!empty($log['target_id'])): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 text-slate-800 border border-slate-200 font-mono text-[11px] font-semibold">
                                        <?= htmlspecialchars($log['target_id']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- IP & Device -->
                            <td class="py-3 px-3.5 whitespace-nowrap text-slate-500 text-[11px]">
                                <div class="font-mono text-slate-700">
                                    <i class="fa-solid fa-network-wired text-[10px] text-slate-400 mr-1"></i><?= htmlspecialchars($log['ip_address'] ?: '127.0.0.1') ?>
                                </div>
                                <?php if (!empty($log['user_agent'])): ?>
                                    <div class="text-[10px] text-slate-400 truncate max-w-[140px] mt-0.5" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                        <?= htmlspecialchars(substr($log['user_agent'], 0, 35)) ?>...
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <?php if ($totalRows > 0): ?>
        <div class="px-4 py-3 bg-slate-50 border-t border-slate-200">
            <?= render_pagination($pagination, $totalRows) ?>
        </div>
    <?php endif; ?>
</div>

