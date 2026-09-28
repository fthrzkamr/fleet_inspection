<?php
/**
 * Halaman Master Data Cabang Operasional (Admin) - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$pdo = get_db();
$user = current_user();

// Handle Tambah Cabang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_cabang') {
    $kode   = strtoupper(trim($_POST['kode_cabang'] ?? ''));
    $nama   = trim($_POST['nama_cabang'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $pic    = trim($_POST['penanggung_jawab'] ?? '');

    if (empty($kode) || empty($nama)) {
        flash_set('error', 'Kode cabang dan nama cabang wajib diisi.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO cabang (kode_cabang, nama_cabang, alamat, penanggung_jawab, status) 
                                   VALUES (:kode, :nama, :alamat, :pic, 'active')");
            $stmt->execute([
                ':kode'   => $kode,
                ':nama'   => $nama,
                ':alamat' => $alamat,
                ':pic'    => $pic
            ]);
            flash_set('success', "Cabang baru '$nama ($kode)' berhasil ditambahkan.");
            session_write_close();
            header('Location: ' . BASE_URL . '/public/master_cabang.php');
            exit;
        } catch (PDOException $e) {
            flash_set('error', 'Gagal menambahkan cabang: Kode cabang sudah digunakan atau terjadi kesalahan.');
        }
    }
}

// Handle Update Cabang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_cabang') {
    $id     = (int)($_POST['id'] ?? 0);
    $kode   = strtoupper(trim($_POST['kode_cabang'] ?? ''));
    $nama   = trim($_POST['nama_cabang'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $pic    = trim($_POST['penanggung_jawab'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if ($id <= 0 || empty($kode) || empty($nama)) {
        flash_set('error', 'Data tidak valid.');
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE cabang SET 
                                   kode_cabang = :kode, 
                                   nama_cabang = :nama, 
                                   alamat = :alamat, 
                                   penanggung_jawab = :pic, 
                                   status = :status 
                                   WHERE id = :id");
            $stmt->execute([
                ':kode'   => $kode,
                ':nama'   => $nama,
                ':alamat' => $alamat,
                ':pic'    => $pic,
                ':status' => $status,
                ':id'     => $id
            ]);
            flash_set('success', "Data cabang '$nama' berhasil diperbarui.");
            session_write_close();
            header('Location: ' . BASE_URL . '/public/master_cabang.php');
            exit;
        } catch (PDOException $e) {
            flash_set('error', 'Gagal memperbarui cabang: ' . $e->getMessage());
        }
    }
}

// Handle Toggle Status (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $id = (int)($_POST['id'] ?? 0);
    $stmtC = $pdo->prepare("SELECT status, nama_cabang FROM cabang WHERE id = :id");
    $stmtC->execute([':id' => $id]);
    $cab = $stmtC->fetch();
    if ($cab) {
        $newStatus = ($cab['status'] === 'active') ? 'inactive' : 'active';
        $pdo->prepare("UPDATE cabang SET status = :st WHERE id = :id")->execute([':st' => $newStatus, ':id' => $id]);
        flash_set('success', "Status cabang '{$cab['nama_cabang']}' diubah menjadi " . strtoupper($newStatus) . ".");
    } else {
        flash_set('error', 'Data cabang tidak ditemukan.');
    }
    session_write_close();
    header('Location: ' . BASE_URL . '/public/master_cabang.php');
    exit;
}

// Handle Hapus Cabang (POST - aman)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_cabang') {
    $id = (int)($_POST['delete_id'] ?? 0);
    $stmtC = $pdo->prepare("SELECT * FROM cabang WHERE id = :id");
    $stmtC->execute([':id' => $id]);
    $cab = $stmtC->fetch();

    if ($cab) {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM kendaraan WHERE cabang = :nama");
        $stmtCheck->execute([':nama' => $cab['nama_cabang']]);
        $assignedCount = (int)$stmtCheck->fetchColumn();

        if ($assignedCount > 0) {
            flash_set('error', "Cabang '{$cab['nama_cabang']}' tidak dapat dihapus karena masih digunakan oleh $assignedCount unit armada aktif.");
        } else {
            $pdo->prepare("DELETE FROM cabang WHERE id = :id")->execute([':id' => $id]);
            flash_set('success', "Cabang '{$cab['nama_cabang']}' berhasil dihapus dari master data.");
        }
    } else {
        flash_set('error', 'Data cabang tidak ditemukan.');
    }
    session_write_close();
    header('Location: ' . BASE_URL . '/public/master_cabang.php');
    exit;
}

// Search & Pagination Parameters
$search = trim($_GET['search'] ?? '');
$whereSql = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereSql .= " AND (c.kode_cabang LIKE :s1 OR c.nama_cabang LIKE :s2 OR c.alamat LIKE :s3 OR c.penanggung_jawab LIKE :s4)";
    $params[':s1'] = $params[':s2'] = $params[':s3'] = $params[':s4'] = "%$search%";
}

// Count Total Rows
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cabang c $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

// Pagination Setup
$pagination = get_pagination_params(10);

// Ambil Daftar Cabang
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM kendaraan WHERE cabang = c.nama_cabang) as total_armada,
        (SELECT COUNT(*) FROM kendaraan WHERE cabang = c.nama_cabang AND status = 'active') as armada_aktif
        FROM cabang c 
        $whereSql
        ORDER BY c.id ASC
        LIMIT " . (int)$pagination['offset'] . ", " . (int)$pagination['per_page'];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cabangList = $stmt->fetchAll();

// Total Counter All
$totalAllCabang = (int)$pdo->query("SELECT COUNT(*) FROM cabang")->fetchColumn();
$totalActiveCabang = (int)$pdo->query("SELECT COUNT(*) FROM cabang WHERE status = 'active'")->fetchColumn();
$totalArmadaAll = (int)$pdo->query("SELECT COUNT(*) FROM kendaraan")->fetchColumn();

$isAjax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if ($isAjax) {
    require __DIR__ . '/../includes/partial_master_cabang_table.php';
    exit;
}

$pageTitle = 'Master Data Cabang Operasional';
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<div class="space-y-6">
    <!-- Flash Messages -->
    <?= render_flash_messages() ?>

    <!-- Header Title & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-building text-blue-700"></i> Master Data Cabang Operasional
            </h1>
            <p class="text-slate-500 text-xs mt-0.5">Kelola data lokasi, pool armada, dan penanggung jawab cabang operasional.</p>
        </div>
        <div>
            <button type="button" onclick="openModal('modal-tambah-cabang')" class="btn-primary text-xs py-2 px-3.5">
                <i class="fa-solid fa-circle-plus mr-1"></i> Tambah Cabang Baru
            </button>
        </div>
    </div>

    <!-- Stats Quick Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="stat-card">
            <div class="text-[11px] font-bold uppercase text-slate-500">Total Cabang</div>
            <div class="text-2xl font-black text-slate-900 mt-1"><?= $totalAllCabang ?> <span class="text-xs font-normal text-slate-500">Lokasi</span></div>
        </div>
        <div class="stat-card bg-emerald-50/50 border-emerald-200">
            <div class="text-[11px] font-bold uppercase text-emerald-700">Cabang Aktif</div>
            <div class="text-2xl font-black text-emerald-800 mt-1"><?= $totalActiveCabang ?> <span class="text-xs font-normal text-emerald-600">Lokasi</span></div>
        </div>
        <div class="stat-card bg-blue-50/50 border-blue-200">
            <div class="text-[11px] font-bold uppercase text-blue-700">Total Armada Terhubung</div>
            <div class="text-2xl font-black text-blue-800 mt-1"><?= $totalArmadaAll ?> <span class="text-xs font-normal text-blue-600">Unit</span></div>
        </div>
    </div>

    <div id="ajax-table-wrap" class="space-y-6">
        <?php require __DIR__ . '/../includes/partial_master_cabang_table.php'; ?>
    </div>
</div>

<!-- Modal Tambah Cabang Baru -->
<div id="modal-tambah-cabang" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-md w-full p-6 relative">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-circle-plus text-blue-700"></i> Tambah Cabang Operasional
                </h3>
                <p class="text-xs text-slate-500">Daftarkan kantor atau pool penempatan armada baru.</p>
            </div>
            <button type="button" onclick="closeModal('modal-tambah-cabang')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-3.5">
            <input type="hidden" name="action" value="create_cabang">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Kode Cabang <span class="text-rose-500">*</span></label>
                <input type="text" name="kode_cabang" required placeholder="Misal: JKT-PST, SBY, BDG" 
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 uppercase font-mono focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Cabang / Lokasi Pool <span class="text-rose-500">*</span></label>
                <input type="text" name="nama_cabang" required placeholder="Misal: Jakarta Pusat (Pusat), Surabaya" 
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Lengkap Kantor / Pool</label>
                <textarea name="alamat" rows="2" placeholder="Masukkan alamat lengkap cabang..." 
                          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Penanggung Jawab (PIC Cabang)</label>
                <input type="text" name="penanggung_jawab" placeholder="Misal: Budi Santoso / Koordinator Pool" 
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-tambah-cabang')" class="btn-secondary text-xs py-2 px-3.5">
                    Batal
                </button>
                <button type="submit" class="btn-primary text-xs py-2 px-4">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan Cabang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Cabang -->
<div id="modal-edit-cabang" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-md w-full p-6 relative">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-blue-700"></i> Edit Data Cabang
                </h3>
                <p class="text-xs text-slate-500">Perbarui informasi cabang operasional.</p>
            </div>
            <button type="button" onclick="closeModal('modal-edit-cabang')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-3.5">
            <input type="hidden" name="action" value="update_cabang">
            <input type="hidden" name="id" id="edit-id">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Kode Cabang <span class="text-rose-500">*</span></label>
                <input type="text" name="kode_cabang" id="edit-kode" required 
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 uppercase font-mono focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Cabang <span class="text-rose-500">*</span></label>
                <input type="text" name="nama_cabang" id="edit-nama" required 
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Lengkap</label>
                <textarea name="alamat" id="edit-alamat" rows="2" 
                          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Penanggung Jawab (PIC)</label>
                <input type="text" name="penanggung_jawab" id="edit-pic" 
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Status Operasional</label>
                <select name="status" id="edit-status" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="active">Active (Aktif)</option>
                    <option value="inactive">Inactive (Tidak Aktif)</option>
                </select>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-edit-cabang')" class="btn-secondary text-xs py-2 px-3.5">
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
function openEditModal(cabang) {
    document.getElementById('edit-id').value = cabang.id;
    document.getElementById('edit-kode').value = cabang.kode_cabang;
    document.getElementById('edit-nama').value = cabang.nama_cabang;
    document.getElementById('edit-alamat').value = cabang.alamat || '';
    document.getElementById('edit-pic').value = cabang.penanggung_jawab || '';
    document.getElementById('edit-status').value = cabang.status || 'active';
    openModal('modal-edit-cabang');
}

</script>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>