<?php
/**
 * Partial: Tabel Status Operasional Armada Terkini (Dashboard)
 * Di-include dua kali: untuk respons AJAX (fragment saja) dan halaman penuh.
 * Variabel yang dipakai ($tableSearch, $armadaTableList, $tablePagination, $totalTableRows)
 * sudah disiapkan oleh public/dashboard.php sebelum file ini di-include.
 */
?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
    <div>
        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-check text-blue-700"></i> Status Operasional Armada Terkini
        </h3>
        <p class="text-xs text-slate-500">Status terkini hasil pemeriksaan fisik setiap unit kendaraan.</p>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <input type="text" name="q" value="<?= htmlspecialchars($tableSearch) ?>" placeholder="Cari Plat / Cabang..."
               class="px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
        <button type="submit" class="btn-secondary text-xs py-1.5 px-3">
            <i class="fa-solid fa-magnifying-glass"></i>
        </button>
        <?php if (!empty($tableSearch)): ?>
            <a href="?" class="btn-secondary text-xs py-1.5 px-2.5" title="Reset">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        <?php endif; ?>
    </form>
</div>

<div class="overflow-x-auto">
    <table class="w-full text-left text-xs text-slate-700" id="liveFleetTable">
        <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
            <tr>
                <th class="py-3 px-3">Asset ID & Plat</th>
                <th class="py-3 px-3">Merk & Model</th>
                <th class="py-3 px-3">Cabang</th>
                <th class="py-3 px-3">Status Armada</th>
                <th class="py-3 px-3">Hasil Terakhir</th>
                <th class="py-3 px-3">Waktu Cek</th>
                <th class="py-3 px-3 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($armadaTableList)): ?>
                <tr>
                    <td colspan="7" class="py-8 text-center text-slate-400">
                        <i class="fa-solid fa-truck text-2xl mb-2 block opacity-40"></i>
                        Tidak ditemukan armada yang sesuai filter.
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($armadaTableList as $a): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="py-3 px-3">
                        <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($a['no_polisi']) ?></div>
                        <div class="text-[11px] font-mono text-blue-700 font-semibold"><?= htmlspecialchars($a['asset_id']) ?></div>
                    </td>
                    <td class="py-3 px-3">
                        <div class="font-semibold text-slate-800"><?= htmlspecialchars($a['merk']) ?> <?= htmlspecialchars($a['model']) ?></div>
                    </td>
                    <td class="py-3 px-3">
                        <span class="text-slate-600"><i class="fa-solid fa-location-dot text-[10px] text-slate-400 mr-1"></i> <?= htmlspecialchars($a['cabang']) ?></span>
                    </td>
                    <td class="py-3 px-3">
                        <?= format_badge_status($a['status']) ?>
                    </td>
                    <td class="py-3 px-3">
                        <?php if ($a['last_hasil'] === 'mayor'): ?>
                            <span class="badge badge-danger badge-pulse font-bold"><i class="fa-solid fa-hand mr-1"></i> STOP (Mayor)</span>
                        <?php elseif ($a['last_hasil'] === 'minor'): ?>
                            <span class="badge badge-warning font-bold"><i class="fa-solid fa-wrench mr-1"></i> Minor Service</span>
                        <?php elseif ($a['last_hasil'] === 'ok'): ?>
                            <span class="badge badge-success font-bold"><i class="fa-solid fa-circle-check mr-1"></i> Siap Jalan (OK)</span>
                        <?php else: ?>
                            <span class="badge badge-neutral text-slate-400 italic">Belum Cek</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-3 text-slate-500 text-[11px]">
                        <?= format_date_id($a['last_tgl']) ?>
                    </td>
                    <td class="py-3 px-3 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <a href="<?= BASE_URL ?>/public/inspeksi_mulai.php?asset_id=<?= urlencode($a['asset_id']) ?>" class="p-1.5 rounded-lg bg-slate-100 hover:bg-blue-100 text-blue-700" title="Mulai / Lanjutkan Cek">
                                <i class="fa-solid fa-clipboard-check"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/public/cetak_qr.php?id=<?= $a['id'] ?>" class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700" title="Cetak QR Stiker">
                                <i class="fa-solid fa-print"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/public/riwayat_inspeksi.php?kendaraan_id=<?= $a['id'] ?>" class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700" title="Riwayat Inspeksi">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= render_pagination($tablePagination['page'], $totalTableRows, $tablePagination['per_page']) ?>
