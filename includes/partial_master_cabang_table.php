<?php
/**
 * Partial: Search bar + Tabel Cabang (Master Cabang)
 * Di-include dua kali: untuk respons AJAX (fragment saja) dan halaman penuh.
 * Variabel yang dipakai sudah disiapkan oleh public/master_cabang.php sebelum include.
 */
?>
<!-- Search & Filter Bar -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
    <form method="GET" class="flex flex-col sm:flex-row gap-2.5">
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari Kode Cabang, Nama Pool, Alamat, atau PIC..."
                   class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:border-blue-600 outline-none">
        </div>
        <button type="submit" class="btn-primary text-xs py-2 px-4">
            <i class="fa-solid fa-filter mr-1"></i> Cari
        </button>
        <?php if (!empty($search)): ?>
            <a href="?" class="btn-secondary text-xs py-2 px-3 flex items-center justify-center" title="Reset">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Table of Branches -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700">
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
                <tr>
                    <th class="py-3 px-4">Kode</th>
                    <th class="py-3 px-4">Nama Cabang / Pool</th>
                    <th class="py-3 px-4">Alamat Kantor / Pool</th>
                    <th class="py-3 px-4">Penanggung Jawab (PIC)</th>
                    <th class="py-3 px-4">Armada</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($cabangList)): ?>
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400">
                            <i class="fa-solid fa-building text-2xl mb-2 block opacity-40"></i>
                            Tidak ditemukan master data cabang yang sesuai filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($cabangList as $c): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3.5 px-4 font-mono font-bold text-blue-700">
                                <?= htmlspecialchars($c['kode_cabang']) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900"><?= htmlspecialchars($c['nama_cabang']) ?></div>
                            </td>
                            <td class="py-3.5 px-4 max-w-xs truncate text-slate-600">
                                <?= htmlspecialchars($c['alamat'] ?: '-') ?>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-700">
                                <?= htmlspecialchars($c['penanggung_jawab'] ?: '-') ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-slate-900"><?= $c['total_armada'] ?></span> <span class="text-slate-500 text-[11px]">(<?= $c['armada_aktif'] ?> Aktif)</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if ($c['status'] === 'active'): ?>
                                    <span class="badge badge-success text-[10px]"><i class="fa-solid fa-circle-check"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral text-[10px]"><i class="fa-solid fa-ban"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <!-- Button Edit Modal -->
                                    <button type="button"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($c)) ?>)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-blue-700" title="Edit Cabang">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Toggle Status (POST Form) -->
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit"
                                                class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 <?= $c['status'] === 'active' ? 'text-amber-700' : 'text-emerald-700' ?>"
                                                title="<?= $c['status'] === 'active' ? 'Nonaktifkan Cabang' : 'Aktifkan Cabang' ?>">
                                            <i class="fa-solid <?= $c['status'] === 'active' ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>

                                    <!-- Delete (POST Form + Modal Confirm) -->
                                    <form method="POST" action="" class="inline" id="delete-form-<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="action" value="delete_cabang">
                                        <input type="hidden" name="delete_id" value="<?= (int)$c['id'] ?>">
                                        <button type="button"
                                                onclick="confirmDeleteCabang(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['nama_cabang'], ENT_QUOTES) ?>')"
                                                class="p-1.5 rounded-lg bg-slate-100 hover:bg-rose-100 text-rose-700" title="Hapus Cabang">
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
    <!-- Pagination Component -->
    <?= render_pagination($pagination['page'], $totalRows, $pagination['per_page']) ?>
</div>
