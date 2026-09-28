<?php
/**
 * Partial: Filter bar + Tabel Riwayat Inspeksi
 * Di-include dua kali: untuk respons AJAX (fragment saja) dan halaman penuh.
 * Variabel yang dipakai sudah disiapkan oleh public/riwayat_inspeksi.php sebelum include.
 */
?>
<!-- Filter Bar -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
        <div class="sm:col-span-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari Plat / Asset / Petugas..."
                   class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:border-blue-600 outline-none">
        </div>

        <div class="sm:col-span-3">
            <select name="kendaraan_id" data-searchable data-placeholder="Cari kendaraan..." class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                <option value="">-- Semua Kendaraan --</option>
                <?php foreach ($allVehicles as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $filterKendaraanId === (int)$v['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v['asset_id']) ?> - <?= htmlspecialchars($v['no_polisi']) ?> (<?= htmlspecialchars($v['merk']) ?> <?= htmlspecialchars($v['model']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sm:col-span-2">
            <select name="hasil" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                <option value="">-- Semua Hasil --</option>
                <option value="ok" <?= $filterHasil === 'ok' ? 'selected' : '' ?>>OK (Layak)</option>
                <option value="minor" <?= $filterHasil === 'minor' ? 'selected' : '' ?>>Minor Service</option>
                <option value="mayor" <?= $filterHasil === 'mayor' ? 'selected' : '' ?>>STOP (Mayor)</option>
            </select>
        </div>

        <div class="sm:col-span-2">
            <input type="date" name="tgl_awal" value="<?= htmlspecialchars($filterTglAwal) ?>" title="Tanggal Awal"
                   class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
        </div>

        <div class="sm:col-span-2 flex gap-2">
            <button type="submit" class="btn-primary w-full py-2 text-xs">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <?php if ($filterKendaraanId || $filterHasil || $filterTglAwal || $search): ?>
                <a href="?" class="btn-secondary py-2 px-3 text-xs" title="Reset">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Table of Inspection Sessions -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full table-fixed text-left text-xs text-slate-700">
            <colgroup>
                <col style="width:12%">
                <col style="width:17%">
                <col style="width:14%">
                <col style="width:12%">
                <col style="width:11%">
                <col style="width:19%">
                <col style="width:15%">
            </colgroup>
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
                <tr>
                    <th class="py-3 px-4">Tanggal & Waktu</th>
                    <th class="py-3 px-4">Armada & Plat</th>
                    <th class="py-3 px-4">Petugas Inspektur</th>
                    <th class="py-3 px-4">Odometer & BBM</th>
                    <th class="py-3 px-4">Hasil Evaluasi</th>
                    <th class="py-3 px-4">Catatan Petugas</th>
                    <th class="py-3 px-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($inspeksiList)): ?>
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400">
                            <i class="fa-solid fa-clipboard-list text-2xl mb-2 block opacity-40"></i>
                            Tidak ada catatan riwayat inspeksi yang sesuai filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inspeksiList as $row): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3.5 px-4 font-medium text-slate-800 truncate">
                                <?= format_date_id($row['tanggal_mulai'], false) ?>
                            </td>
                            <td class="py-3.5 px-4 overflow-hidden">
                                <div class="font-bold text-slate-900 text-sm truncate"><?= htmlspecialchars($row['no_polisi']) ?></div>
                                <div class="text-[11px] font-mono text-blue-700 font-semibold truncate"><?= htmlspecialchars($row['asset_id']) ?> &bull; <span class="text-slate-500 font-normal"><?= htmlspecialchars($row['cabang']) ?></span></div>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-700 truncate">
                                <i class="fa-solid fa-user-check text-[11px] text-emerald-600 mr-1"></i>
                                <?= htmlspecialchars($row['petugas_nama'] ?: 'Petugas') ?>
                            </td>
                            <td class="py-3.5 px-4 overflow-hidden">
                                <span class="font-bold text-slate-900 truncate block"><?= number_format((int)$row['odometer'], 0, ',', '.') ?> KM</span>
                                <div class="text-[10px] text-amber-700 font-semibold truncate">BBM: <?= htmlspecialchars($row['bbm_level'] ?: '-') ?></div>
                            </td>
                            <td class="py-3.5 px-4">
                                <?= format_badge_hasil($row['hasil_akhir']) ?>
                            </td>
                            <td class="py-3.5 px-4 truncate text-slate-500 text-[11px]">
                                <?= !empty($row['catatan_umum']) ? htmlspecialchars($row['catatan_umum']) : '-' ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="?inspeksi_id=<?= $row['id'] ?>#detail-modal" data-no-ajax class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-blue-700" title="Lihat Rincian">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <?php if (in_array($user['role'], ['admin', 'pic'])): ?>
                                        <button type="button"
                                                onclick='openEditInspeksiModal(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-blue-700" title="Edit Ringkasan">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- Pagination Component -->
    <?= render_pagination($pagination['page'], $totalRows, $pagination['per_page']) ?>
</div>
