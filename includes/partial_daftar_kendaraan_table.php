<?php
/**
 * Partial: Filter bar + Tabel Database Armada (Kelola Armada)
 * Di-include dua kali: untuk respons AJAX (fragment saja) dan halaman penuh.
 * Variabel yang dipakai sudah disiapkan oleh public/daftar_kendaraan.php sebelum include.
 */
?>
<!-- Filter & Search Bar -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
        <div class="sm:col-span-5 relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari Plat Nomor, Asset ID, Merk, VIN..."
                   class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:border-blue-600 outline-none">
        </div>

        <div class="sm:col-span-3">
            <select name="cabang" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                <option value="">-- Semua Cabang --</option>
                <?php
                    $cabangMasterList = get_cabang_list($pdo, false);
                    foreach ($cabangMasterList as $c):
                        $cName = $c['nama_cabang'];
                ?>
                    <option value="<?= htmlspecialchars($cName) ?>" <?= $filterCabang === $cName ? 'selected' : '' ?>><?= htmlspecialchars($cName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sm:col-span-2">
            <select name="status" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                <option value="">-- Semua Status --</option>
                <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="peremajaan" <?= $filterStatus === 'peremajaan' ? 'selected' : '' ?>>Peremajaan</option>
                <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="sm:col-span-2 flex gap-2">
            <button type="submit" class="btn-primary w-full py-2 text-xs">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <?php if (!empty($search) || !empty($filterCabang) || !empty($filterStatus)): ?>
                <a href="?" class="btn-secondary py-2 px-3 text-xs" title="Reset Filter">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Vehicles Table -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700">
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
                <tr>
                    <th class="py-3 px-4 text-center w-10">No</th>
                    <th class="py-3 px-4">Asset ID & Plat</th>
                    <th class="py-3 px-4">Merk & Tipe</th>
                    <th class="py-3 px-4">Cabang</th>
                    <th class="py-3 px-4">Status Dokumen</th>
                    <th class="py-3 px-4">Status Armada</th>
                    <th class="py-3 px-4">Inspeksi Terakhir</th>
                    <th class="py-3 px-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($kendaraanList)): ?>
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-400">
                            <i class="fa-solid fa-truck text-2xl mb-2 block opacity-40"></i>
                            Tidak ditemukan data armada yang sesuai pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $noUrut = $pagination['offset']; ?>
                    <?php foreach ($kendaraanList as $k): ?>
                        <?php $noUrut++; ?>
                        <?php
                            $stnkExp = $k['tgl_stnk_expired'] ? strtotime($k['tgl_stnk_expired']) : null;
                            $kirExp  = $k['tgl_kir_expired'] ? strtotime($k['tgl_kir_expired']) : null;
                            $platExp = $k['tgl_plat_expired'] ? strtotime($k['tgl_plat_expired']) : null;
                            $now = time();
                            $stnkDays = $stnkExp ? round(($stnkExp - $now) / 86400) : null;
                            $kirDays  = $kirExp ? round(($kirExp - $now) / 86400) : null;
                            $platDays = $platExp ? round(($platExp - $now) / 86400) : null;
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <!-- No Urut -->
                            <td class="py-3.5 px-4 text-center text-slate-500 font-semibold"><?= $noUrut ?></td>

                            <!-- Asset ID & Plat -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2.5">
                                    <a href="<?= BASE_URL ?>/public/cetak_qr.php?id=<?= $k['id'] ?>" title="Lihat & Cetak QR" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-blue-100 border border-slate-200 flex items-center justify-center text-blue-700 transition-colors">
                                        <i class="fa-solid fa-qrcode text-sm"></i>
                                    </a>
                                    <div>
                                        <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($k['no_polisi']) ?></div>
                                        <div class="text-[11px] font-mono text-blue-700 font-semibold"><?= htmlspecialchars($k['asset_id']) ?></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Merk & Model -->
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-slate-800"><?= htmlspecialchars($k['merk']) ?> <?= htmlspecialchars($k['model']) ?></div>
                                <div class="text-[10px] text-slate-400 font-mono">VIN: <?= htmlspecialchars($k['vin'] ?: '-') ?></div>
                            </td>

                            <!-- Cabang -->
                            <td class="py-3.5 px-4">
                                <span class="text-slate-600 font-medium">
                                    <i class="fa-solid fa-location-dot text-[10px] text-slate-400 mr-1"></i>
                                    <?= htmlspecialchars($k['cabang']) ?>
                                </span>
                            </td>

                            <!-- Status Dokumen -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-[11px]">
                                        <span class="font-bold text-slate-500">STNK:</span>
                                        <?php if ($stnkExp): ?>
                                            <?php if ($stnkDays < 0): ?>
                                                <span class="text-rose-600 font-bold"><i class="fa-solid fa-circle-exclamation"></i> Lewat <?= abs($stnkDays) ?> hari</span>
                                            <?php elseif ($stnkDays <= $reminderLeadDays): ?>
                                                <span class="text-amber-600 font-bold"><i class="fa-solid fa-clock"></i> Sisa <?= $stnkDays ?> hari</span>
                                            <?php else: ?>
                                                <span class="text-slate-600"><?= date('d/m/Y', $stnkExp) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-slate-400">-</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex items-center gap-1.5 text-[11px]">
                                        <span class="font-bold text-slate-500">KIR:</span>
                                        <?php if ($kirExp): ?>
                                            <?php if ($kirDays < 0): ?>
                                                <span class="text-rose-600 font-bold"><i class="fa-solid fa-circle-exclamation"></i> Lewat <?= abs($kirDays) ?> hari</span>
                                            <?php elseif ($kirDays <= $reminderLeadDays): ?>
                                                <span class="text-amber-600 font-bold"><i class="fa-solid fa-clock"></i> Sisa <?= $kirDays ?> hari</span>
                                            <?php else: ?>
                                                <span class="text-slate-600"><?= date('d/m/Y', $kirExp) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-slate-400">-</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex items-center gap-1.5 text-[11px]">
                                        <span class="font-bold text-slate-500">Plat:</span>
                                        <?php if ($platExp): ?>
                                            <?php if ($platDays < 0): ?>
                                                <span class="text-rose-600 font-bold"><i class="fa-solid fa-circle-exclamation"></i> Lewat <?= abs($platDays) ?> hari</span>
                                            <?php elseif ($platDays <= $reminderLeadDays): ?>
                                                <span class="text-amber-600 font-bold"><i class="fa-solid fa-clock"></i> Sisa <?= $platDays ?> hari</span>
                                            <?php else: ?>
                                                <span class="text-slate-600"><?= date('d/m/Y', $platExp) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-slate-400">-</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Status Operasional -->
                            <td class="py-3.5 px-4">
                                <?= format_badge_status($k['status'], $k['status_inactive_reason']) ?>
                            </td>

                            <!-- Hasil Inspeksi Terakhir -->
                            <td class="py-3.5 px-4">
                                <?php if ($k['last_hasil_inspeksi']): ?>
                                    <div>
                                        <?= format_badge_hasil($k['last_hasil_inspeksi']) ?>
                                        <div class="text-[10px] text-slate-400 mt-0.5"><?= format_date_id($k['last_tgl_inspeksi']) ?></div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-400 text-[11px] italic">Belum dicek</span>
                                <?php endif; ?>
                            </td>

                            <!-- Aksi -->
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="<?= BASE_URL ?>/public/cetak_qr.php?id=<?= $k['id'] ?>" class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700" title="Cetak Stiker QR">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>/public/edit_kendaraan.php?id=<?= $k['id'] ?>" class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-amber-700" title="Edit / Refresh Armada">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>/public/riwayat_inspeksi.php?kendaraan_id=<?= $k['id'] ?>" class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-blue-700" title="Riwayat Inspeksi">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </a>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <form method="POST" action="" class="inline" id="delete-kendaraan-form-<?= (int)$k['id'] ?>">
                                            <input type="hidden" name="action" value="delete_kendaraan">
                                            <input type="hidden" name="delete_id" value="<?= (int)$k['id'] ?>">
                                            <button type="button"
                                                    onclick="confirmDeleteKendaraan(<?= (int)$k['id'] ?>, '<?= htmlspecialchars($k['no_polisi'] . ' (' . $k['asset_id'] . ')', ENT_QUOTES) ?>')"
                                                    class="p-1.5 rounded-lg bg-slate-100 hover:bg-rose-100 text-rose-700" title="Hapus Permanen">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
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
