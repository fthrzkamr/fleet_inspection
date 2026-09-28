<?php
/**
 * Partial: Filter bar + Tabel Riwayat Servis
 * Di-include dua kali: untuk respons AJAX (fragment saja) dan halaman penuh.
 * Variabel yang dipakai sudah disiapkan oleh public/riwayat_servis.php sebelum include.
 */
?>
<!-- Filter Bar -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
        <div class="sm:col-span-6">
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
            <input type="date" name="tgl_awal" value="<?= htmlspecialchars($filterTglAwal) ?>" title="Servis Dari Tanggal"
                   class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
        </div>

        <div class="sm:col-span-2">
            <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($filterTglAkhir) ?>" title="Servis Sampai Tanggal"
                   class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
        </div>

        <div class="sm:col-span-2 flex gap-2">
            <button type="submit" class="btn-primary w-full py-2 text-xs">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <?php if ($filterKendaraanId || $filterTglAwal || $filterTglAkhir): ?>
                <a href="?" class="btn-secondary py-2 px-3 text-xs" title="Reset">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Table of Service Records -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700">
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
                <tr>
                    <th class="py-3 px-4 text-center w-10">No</th>
                    <th class="py-3 px-4">Tanggal</th>
                    <th class="py-3 px-4">Kendaraan</th>
                    <th class="py-3 px-4">Keterangan Service</th>
                    <th class="py-3 px-4">KM / Nominal</th>
                    <th class="py-3 px-4">Dicatat Oleh</th>
                    <th class="py-3 px-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($servisList)): ?>
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400">
                            <i class="fa-solid fa-screwdriver-wrench text-2xl mb-2 block opacity-40"></i>
                            Belum ada catatan riwayat servis.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $noUrut = $pagination['offset']; ?>
                    <?php foreach ($servisList as $s): ?>
                        <?php $noUrut++; ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3.5 px-4 text-center text-slate-500 font-semibold"><?= $noUrut ?></td>
                            <td class="py-3.5 px-4 font-medium text-slate-800 whitespace-nowrap">
                                <?= format_date_id($s['tanggal_servis'], false) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if ($s['kendaraan_id']): ?>
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold uppercase mb-0.5 <?= $s['jenis_kendaraan'] === 'Motor' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-blue-50 text-blue-700 border border-blue-200' ?>"><?= htmlspecialchars($s['jenis_kendaraan']) ?></span>
                                    <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($s['no_polisi']) ?></div>
                                    <div class="text-[11px] font-mono text-blue-700 font-semibold"><?= htmlspecialchars($s['asset_id']) ?></div>
                                <?php else: ?>
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-500 border border-slate-200">Umum</span>
                                    <div class="text-[11px] text-slate-400 italic mt-0.5">Tidak terkait kendaraan tertentu</div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 max-w-xs">
                                <div class="font-semibold text-slate-800"><?= htmlspecialchars($s['jenis_servis']) ?></div>
                                <?php if (!empty($s['bengkel'])): ?>
                                    <div class="text-[11px] text-slate-500"><i class="fa-solid fa-warehouse text-[10px] mr-1"></i><?= htmlspecialchars($s['bengkel']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-700 whitespace-nowrap">
                                <div><?= $s['km_servis'] !== null ? number_format((int)$s['km_servis'], 0, ',', '.') . ' KM' : '-' ?></div>
                                <div class="text-[11px] text-emerald-700 font-semibold"><?= $s['nominal'] !== null ? 'Rp ' . number_format((int)$s['nominal'], 0, ',', '.') : '-' ?></div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 whitespace-nowrap">
                                <?= htmlspecialchars($s['dicatat_oleh'] ?: '-') ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button type="button"
                                            onclick='openDetailServisModal(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700" title="Detail">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <button type="button"
                                            onclick='openEditServisModal(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-blue-700" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" action="" class="inline" id="delete-servis-form-<?= (int)$s['id'] ?>">
                                        <input type="hidden" name="action" value="delete_servis">
                                        <input type="hidden" name="delete_id" value="<?= (int)$s['id'] ?>">
                                        <button type="button"
                                                onclick="confirmDeleteServis(<?= (int)$s['id'] ?>, '<?= htmlspecialchars($s['jenis_servis'], ENT_QUOTES) ?>')"
                                                class="p-1.5 rounded-lg bg-slate-100 hover:bg-rose-100 text-rose-700" title="Hapus">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($pagination['page'], $totalRows, $pagination['per_page']) ?>
</div>
