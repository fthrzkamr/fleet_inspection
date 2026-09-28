<?php
/**
 * Halaman Kelola User / Akun Petugas (Admin) - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$pdo = get_db();
$user = current_user();

// Handle Tambah User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'petugas';
    $cabang   = trim($_POST['cabang'] ?? '');
    $noWa     = trim($_POST['no_wa'] ?? '');

    if (empty($nama) || empty($username) || strlen($password) < 6 || !in_array($role, ['admin', 'petugas', 'pic'])) {
        flash_set('error', 'Nama, username wajib diisi, role harus valid, dan password minimal 6 karakter.');
    } elseif (in_array($role, ['petugas', 'pic']) && empty($cabang)) {
        flash_set('error', 'Cabang wajib dipilih untuk role Petugas dan PIC (data yang tampil untuk mereka dibatasi sesuai cabang).');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (nama, username, password_hash, role, cabang, no_wa)
                                   VALUES (:nama, :username, :hash, :role, :cabang, :no_wa)");
            $stmt->execute([
                ':nama'     => $nama,
                ':username' => $username,
                ':hash'     => password_hash($password, PASSWORD_DEFAULT),
                ':role'     => $role,
                ':cabang'   => $cabang ?: null,
                ':no_wa'    => $noWa ?: null
            ]);
            flash_set('success', "User '$nama' berhasil ditambahkan.");
            session_write_close();
            header('Location: ' . BASE_URL . '/public/kelola_user.php');
            exit;
        } catch (PDOException $e) {
            flash_set('error', 'Gagal menambahkan user: Username sudah digunakan atau terjadi kesalahan.');
        }
    }
}

// Handle Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $id       = (int)($_POST['id'] ?? 0);
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'petugas';
    $cabang   = trim($_POST['cabang'] ?? '');
    $noWa     = trim($_POST['no_wa'] ?? '');

    if ($id <= 0 || empty($nama) || empty($username) || !in_array($role, ['admin', 'petugas', 'pic'])) {
        flash_set('error', 'Data tidak valid.');
    } elseif ($password !== '' && strlen($password) < 6) {
        flash_set('error', 'Password baru minimal 6 karakter (kosongkan jika tidak ingin mengubah password).');
    } elseif (in_array($role, ['petugas', 'pic']) && empty($cabang)) {
        flash_set('error', 'Cabang wajib dipilih untuk role Petugas dan PIC (data yang tampil untuk mereka dibatasi sesuai cabang).');
    } else {
        try {
            if ($password !== '') {
                $stmt = $pdo->prepare("UPDATE users SET nama = :nama, username = :username, role = :role,
                                       cabang = :cabang, no_wa = :no_wa, password_hash = :hash WHERE id = :id");
                $stmt->execute([
                    ':nama' => $nama, ':username' => $username, ':role' => $role,
                    ':cabang' => $cabang ?: null, ':no_wa' => $noWa ?: null,
                    ':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nama = :nama, username = :username, role = :role,
                                       cabang = :cabang, no_wa = :no_wa WHERE id = :id");
                $stmt->execute([
                    ':nama' => $nama, ':username' => $username, ':role' => $role,
                    ':cabang' => $cabang ?: null, ':no_wa' => $noWa ?: null, ':id' => $id
                ]);
            }

            // Sinkronkan session jika admin sedang mengedit akunnya sendiri
            if ($id === (int)$user['id']) {
                $_SESSION['user_nama']   = $nama;
                $_SESSION['user_role']   = $role;
                $_SESSION['user_cabang'] = $cabang;
            }

            flash_set('success', "Data user '$nama' berhasil diperbarui.");
            session_write_close();
            header('Location: ' . BASE_URL . '/public/kelola_user.php');
            exit;
        } catch (PDOException $e) {
            flash_set('error', 'Gagal memperbarui user: Username sudah digunakan atau terjadi kesalahan.');
        }
    }
}

// Handle Hapus User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $id = (int)($_POST['delete_id'] ?? 0);

    if ($id === (int)$user['id']) {
        flash_set('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
    } else {
        $stmtU = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmtU->execute([':id' => $id]);
        $target = $stmtU->fetch();

        if (!$target) {
            flash_set('error', 'Data user tidak ditemukan.');
        } else {
            try {
                $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
                flash_set('success', "User '{$target['nama']}' berhasil dihapus.");
            } catch (PDOException $e) {
                flash_set('error', "User '{$target['nama']}' tidak dapat dihapus karena masih memiliki riwayat inspeksi terkait.");
            }
        }
    }
    session_write_close();
    header('Location: ' . BASE_URL . '/public/kelola_user.php');
    exit;
}

// Search & Pagination
$search = trim($_GET['search'] ?? '');
$whereSql = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereSql .= " AND (nama LIKE :s1 OR username LIKE :s2 OR cabang LIKE :s3)";
    $params[':s1'] = $params[':s2'] = $params[':s3'] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$pagination = get_pagination_params(10);

$sql = "SELECT * FROM users $whereSql ORDER BY id ASC LIMIT " . (int)$pagination['offset'] . ", " . (int)$pagination['per_page'];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$userList = $stmt->fetchAll();

$totalAllUser = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmin   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalPetugas = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'petugas'")->fetchColumn();

$cabangOptions = get_cabang_list($pdo, false);

$isAjax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if ($isAjax) {
    require __DIR__ . '/../includes/partial_kelola_user_table.php';
    exit;
}

$pageTitle = 'Kelola User & Akun Petugas';
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<div class="space-y-6">
    <!-- Header Title & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-users-gear text-blue-700"></i> Kelola User & Akun Petugas
            </h1>
            <p class="text-slate-500 text-xs mt-0.5">Kelola akun admin, PIC, dan petugas lapangan yang dapat mengakses sistem.</p>
        </div>
        <div>
            <button type="button" onclick="openModal('modal-tambah-user')" class="btn-primary text-xs py-2 px-3.5">
                <i class="fa-solid fa-user-plus mr-1"></i> Tambah User Baru
            </button>
        </div>
    </div>

    <!-- Stats Quick Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="stat-card">
            <div class="text-[11px] font-bold uppercase text-slate-500">Total User</div>
            <div class="text-2xl font-black text-slate-900 mt-1"><?= $totalAllUser ?> <span class="text-xs font-normal text-slate-500">Akun</span></div>
        </div>
        <div class="stat-card bg-blue-50/50 border-blue-200">
            <div class="text-[11px] font-bold uppercase text-blue-700">Admin</div>
            <div class="text-2xl font-black text-blue-800 mt-1"><?= $totalAdmin ?> <span class="text-xs font-normal text-blue-600">Akun</span></div>
        </div>
        <div class="stat-card bg-emerald-50/50 border-emerald-200">
            <div class="text-[11px] font-bold uppercase text-emerald-700">Petugas</div>
            <div class="text-2xl font-black text-emerald-800 mt-1"><?= $totalPetugas ?> <span class="text-xs font-normal text-emerald-600">Akun</span></div>
        </div>
    </div>

    <div id="ajax-table-wrap" class="space-y-6">
        <?php require __DIR__ . '/../includes/partial_kelola_user_table.php'; ?>
    </div>
</div>

<!-- Modal Tambah User -->
<div id="modal-tambah-user" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-md w-full p-6 relative">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-user-plus text-blue-700"></i> Tambah User Baru
                </h3>
                <p class="text-xs text-slate-500">Buat akun baru untuk admin, PIC, atau petugas lapangan.</p>
            </div>
            <button type="button" onclick="closeModal('modal-tambah-user')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-3.5">
            <input type="hidden" name="action" value="create_user">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                <input type="text" name="nama" required placeholder="Misal: Budi Santoso"
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
                <input type="text" name="username" required placeholder="Misal: budi.santoso"
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 font-mono focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Password <span class="text-rose-500">*</span></label>
                <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter"
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Role <span class="text-rose-500">*</span></label>
                <select name="role" required onchange="toggleCabangRequired(this, 'cabang-field-tambah')" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="petugas">Petugas (Scan & Inspeksi Lapangan)</option>
                    <option value="pic">PIC (Lihat Dashboard & Riwayat)</option>
                    <option value="admin">Admin (Akses Penuh)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Cabang Penempatan <span id="cabang-required-mark-tambah" class="text-rose-500">*</span>
                </label>
                <select name="cabang" id="cabang-field-tambah" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="">-- Pilih Cabang --</option>
                    <?php foreach ($cabangOptions as $c): ?>
                        <option value="<?= htmlspecialchars($c['nama_cabang']) ?>"><?= htmlspecialchars($c['nama_cabang']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-slate-500 mt-1">Wajib untuk Petugas & PIC — data yang mereka lihat dibatasi sesuai cabang ini.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">No. WhatsApp</label>
                <input type="text" name="no_wa" placeholder="Misal: 6281234567890"
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-tambah-user')" class="btn-secondary text-xs py-2 px-3.5">
                    Batal
                </button>
                <button type="submit" class="btn-primary text-xs py-2 px-4">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit User -->
<div id="modal-edit-user" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-md w-full p-6 relative">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-blue-700"></i> Edit User
                </h3>
                <p class="text-xs text-slate-500">Perbarui data akun. Kosongkan password jika tidak ingin mengubahnya.</p>
            </div>
            <button type="button" onclick="closeModal('modal-edit-user')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-3.5">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="id" id="edit-user-id">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                <input type="text" name="nama" id="edit-user-nama" required
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
                <input type="text" name="username" id="edit-user-username" required
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 font-mono focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Password Baru</label>
                <input type="password" name="password" minlength="6" placeholder="Kosongkan jika tidak diubah"
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Role <span class="text-rose-500">*</span></label>
                <select name="role" id="edit-user-role" required onchange="toggleCabangRequired(this, 'edit-user-cabang')" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="petugas">Petugas (Scan & Inspeksi Lapangan)</option>
                    <option value="pic">PIC (Lihat Dashboard & Riwayat)</option>
                    <option value="admin">Admin (Akses Penuh)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Cabang Penempatan <span id="cabang-required-mark-edit" class="text-rose-500">*</span>
                </label>
                <select name="cabang" id="edit-user-cabang" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="">-- Pilih Cabang --</option>
                    <?php foreach ($cabangOptions as $c): ?>
                        <option value="<?= htmlspecialchars($c['nama_cabang']) ?>"><?= htmlspecialchars($c['nama_cabang']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">No. WhatsApp</label>
                <input type="text" name="no_wa" id="edit-user-nowa"
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-edit-user')" class="btn-secondary text-xs py-2 px-3.5">
                    Batal
                </button>
                <button type="submit" class="btn-primary text-xs py-2 px-4">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUserModal(u) {
    document.getElementById('edit-user-id').value = u.id;
    document.getElementById('edit-user-nama').value = u.nama;
    document.getElementById('edit-user-username').value = u.username;
    document.getElementById('edit-user-role').value = u.role;
    document.getElementById('edit-user-cabang').value = u.cabang || '';
    document.getElementById('edit-user-nowa').value = u.no_wa || '';
    toggleCabangRequired(document.getElementById('edit-user-role'), 'edit-user-cabang');
    openModal('modal-edit-user');
}

/**
 * Cabang wajib diisi untuk role Petugas & PIC (data mereka dibatasi per cabang),
 * opsional untuk Admin (akses semua cabang).
 */
function toggleCabangRequired(roleSelect, cabangFieldId) {
    const cabangField = document.getElementById(cabangFieldId);
    const markId = cabangFieldId === 'edit-user-cabang' ? 'cabang-required-mark-edit' : 'cabang-required-mark-tambah';
    const mark = document.getElementById(markId);
    const isRequired = roleSelect.value !== 'admin';
    cabangField.required = isRequired;
    if (mark) mark.style.display = isRequired ? '' : 'none';
}

function confirmDeleteUser(id, nama) {
    showConfirmModal({
        title: 'Hapus User ' + nama + '?',
        message: 'Akun "' + nama + '" akan dihapus permanen dan tidak dapat digunakan untuk login lagi. Tindakan ini tidak dapat dibatalkan.',
        confirmText: 'Ya, Hapus User',
        confirmType: 'danger',
        icon: 'fa-solid fa-trash-can',
        onConfirm: function() {
            var form = document.getElementById('delete-user-form-' + id);
            if (form) form.submit();
        }
    });
}
</script>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>
