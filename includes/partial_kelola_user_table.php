<?php
/**
 * Partial: Search bar + Tabel User (Kelola User)
 * Di-include dua kali: untuk respons AJAX (fragment saja) dan halaman penuh.
 * Variabel yang dipakai sudah disiapkan oleh public/kelola_user.php sebelum include.
 */
?>
<!-- Search Bar -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
    <form method="GET" class="flex flex-col sm:flex-row gap-2.5">
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, username, atau cabang..."
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

<!-- Table of Users -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700">
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
                <tr>
                    <th class="py-3 px-4">Nama</th>
                    <th class="py-3 px-4">Username</th>
                    <th class="py-3 px-4">Role</th>
                    <th class="py-3 px-4">Cabang</th>
                    <th class="py-3 px-4">No. WhatsApp</th>
                    <th class="py-3 px-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($userList)): ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-400">
                            <i class="fa-solid fa-users text-2xl mb-2 block opacity-40"></i>
                            Tidak ditemukan user yang sesuai filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($userList as $u): ?>
                        <?php
                            $roleBadge = [
                                'admin'   => 'bg-blue-100 text-blue-800 border-blue-300',
                                'petugas' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                'pic'     => 'bg-purple-100 text-purple-800 border-purple-300'
                            ][$u['role']] ?? 'bg-slate-100 text-slate-700';
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900"><?= htmlspecialchars($u['nama']) ?></div>
                                <?php if ((int)$u['id'] === (int)$user['id']): ?>
                                    <span class="text-[10px] text-blue-600 font-semibold">(Akun Anda)</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-slate-700"><?= htmlspecialchars($u['username']) ?></td>
                            <td class="py-3.5 px-4">
                                <span class="uppercase font-bold text-[10px] px-1.5 py-0.5 rounded border <?= $roleBadge ?>"><?= htmlspecialchars($u['role']) ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600"><?= htmlspecialchars($u['cabang'] ?: '-') ?></td>
                            <td class="py-3.5 px-4 text-slate-600"><?= htmlspecialchars($u['no_wa'] ?: '-') ?></td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button type="button"
                                            onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u)) ?>)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-blue-700" title="Edit User">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                                        <form method="POST" action="" class="inline" id="delete-user-form-<?= (int)$u['id'] ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="delete_id" value="<?= (int)$u['id'] ?>">
                                            <button type="button"
                                                    onclick="confirmDeleteUser(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['nama'], ENT_QUOTES) ?>')"
                                                    class="p-1.5 rounded-lg bg-slate-100 hover:bg-rose-100 text-rose-700" title="Hapus User">
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
    <?= render_pagination($pagination['page'], $totalRows, $pagination['per_page']) ?>
</div>
