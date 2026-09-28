<?php
/**
 * Halaman Riwayat Inspeksi Armada & Detail Log - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = get_db();
$user = current_user();

// Handle Edit Ringkasan Inspeksi (Odometer, BBM, Hasil Akhir, Catatan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_inspeksi') {
    if (!in_array($user['role'], ['admin', 'pic'])) {
        flash_set('error', 'Anda tidak memiliki izin untuk mengubah data inspeksi.');
        header('Location: ' . BASE_URL . '/public/riwayat_inspeksi.php');
        exit;
    }

    $editId       = (int)($_POST['id'] ?? 0);
    $editOdometer = trim($_POST['odometer'] ?? '');
    $editBbm      = trim($_POST['bbm_level'] ?? '');
    $editHasil    = trim($_POST['hasil_akhir'] ?? '');
    $editCatatan  = trim($_POST['catatan_umum'] ?? '');

    $stmtCheck = $pdo->prepare("SELECT k.cabang FROM inspeksi i JOIN kendaraan k ON i.kendaraan_id = k.id WHERE i.id = :id");
    $stmtCheck->execute([':id' => $editId]);
    $targetCabang = $stmtCheck->fetchColumn();

    $scopeCabangEdit = ($user['role'] === 'pic' && !empty($user['cabang'])) ? $user['cabang'] : null;

    if ($editId <= 0 || $targetCabang === false || !in_array($editHasil, ['ok', 'minor', 'mayor'])) {
        flash_set('error', 'Data tidak valid.');
    } elseif ($scopeCabangEdit && $targetCabang !== $scopeCabangEdit) {
        flash_set('error', 'Anda tidak memiliki akses untuk mengubah data inspeksi cabang lain.');
    } else {
        $stmtUpdate = $pdo->prepare("UPDATE inspeksi SET odometer = :odo, bbm_level = :bbm, hasil_akhir = :hasil, catatan_umum = :cat WHERE id = :id");
        $stmtUpdate->execute([
            ':odo'   => $editOdometer !== '' ? (int)$editOdometer : null,
            ':bbm'   => $editBbm ?: null,
            ':hasil' => $editHasil,
            ':cat'   => $editCatatan ?: null,
            ':id'    => $editId
        ]);
        flash_set('success', 'Ringkasan hasil inspeksi berhasil diperbarui.');
    }

    session_write_close();
    header('Location: ' . BASE_URL . '/public/riwayat_inspeksi.php');
    exit;
}

// Parameters Filter
$filterKendaraanId = (int)($_GET['kendaraan_id'] ?? 0);
$filterHasil       = trim($_GET['hasil'] ?? '');
$filterTglAwal     = trim($_GET['tgl_awal'] ?? '');
$filterTglAkhir    = trim($_GET['tgl_akhir'] ?? '');
$search            = trim($_GET['search'] ?? '');
$viewDetailId      = (int)($_GET['inspeksi_id'] ?? 0);

// Build Query
$whereSql = " WHERE i.tanggal_selesai IS NOT NULL";
$params = [];

if ($filterKendaraanId > 0) {
    $whereSql .= " AND i.kendaraan_id = :kid";
    $params[':kid'] = $filterKendaraanId;
}

if (!empty($filterHasil)) {
    $whereSql .= " AND i.hasil_akhir = :hasil";
    $params[':hasil'] = $filterHasil;
}

if (!empty($filterTglAwal)) {
    $whereSql .= " AND DATE(i.tanggal_mulai) >= :tgl_awal";
    $params[':tgl_awal'] = $filterTglAwal;
}

if (!empty($filterTglAkhir)) {
    $whereSql .= " AND DATE(i.tanggal_mulai) <= :tgl_akhir";
    $params[':tgl_akhir'] = $filterTglAkhir;
}

if (!empty($search)) {
    $whereSql .= " AND (k.asset_id LIKE :s1 OR k.no_polisi LIKE :s2 OR u.nama LIKE :s3)";
    $params[':s1'] = $params[':s2'] = $params[':s3'] = "%$search%";
}

// Batasi data ke cabang sendiri untuk role petugas/pic yang sudah di-set cabang-nya
$scopeCabang = (in_array($user['role'], ['petugas', 'pic']) && !empty($user['cabang'])) ? $user['cabang'] : null;
if ($scopeCabang) {
    $whereSql .= " AND k.cabang = :scopeCb";
    $params[':scopeCb'] = $scopeCabang;
}

// Count Total Rows
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM inspeksi i JOIN kendaraan k ON i.kendaraan_id = k.id LEFT JOIN users u ON i.petugas_id = u.id $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

// Pagination Setup
$pagination = get_pagination_params(10);

$sql = "SELECT i.*, k.asset_id, k.no_polisi, k.merk, k.model, k.cabang, u.nama as petugas_nama 
        FROM inspeksi i 
        JOIN kendaraan k ON i.kendaraan_id = k.id 
        LEFT JOIN users u ON i.petugas_id = u.id 
        $whereSql
        ORDER BY i.id DESC
        LIMIT " . (int)$pagination['offset'] . ", " . (int)$pagination['per_page'];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inspeksiList = $stmt->fetchAll();

// Detail Inspection jika ada yang dipilih untuk modal
$selectedInspeksi = null;
$selectedDetails = [];
$selectedTindakLanjut = [];

if ($viewDetailId > 0) {
    $stmtSel = $pdo->prepare("SELECT i.*, k.asset_id, k.no_polisi, k.merk, k.model, k.cabang, k.vin, u.nama as petugas_nama 
                             FROM inspeksi i 
                             JOIN kendaraan k ON i.kendaraan_id = k.id 
                             LEFT JOIN users u ON i.petugas_id = u.id 
                             WHERE i.id = :id");
    $stmtSel->execute([':id' => $viewDetailId]);
    $selectedInspeksi = $stmtSel->fetch();

    if ($selectedInspeksi) {
        $stmtD = $pdo->prepare("SELECT * FROM inspeksi_detail WHERE inspeksi_id = :id ORDER BY id ASC");
        $stmtD->execute([':id' => $viewDetailId]);
        $selectedDetails = $stmtD->fetchAll();

        $stmtTL = $pdo->prepare("SELECT * FROM riwayat_tindak_lanjut WHERE inspeksi_id = :id");
        $stmtTL->execute([':id' => $viewDetailId]);
        $selectedTindakLanjut = $stmtTL->fetchAll();
    }
}

$stmtAllVehicles = $pdo->prepare("SELECT id, asset_id, no_polisi, merk, model FROM kendaraan" . ($scopeCabang ? " WHERE cabang = :cb" : "") . " ORDER BY asset_id ASC");
$stmtAllVehicles->execute($scopeCabang ? [':cb' => $scopeCabang] : []);
$allVehicles = $stmtAllVehicles->fetchAll();

$isAjax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if ($isAjax) {
    require __DIR__ . '/../includes/partial_riwayat_inspeksi_table.php';
    exit;
}

$pageTitle = 'Riwayat Inspeksi Armada';
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<div class="space-y-6">
    <!-- Header Title & Export -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-blue-700"></i> Riwayat & Laporan Inspeksi Lapangan
            </h1>
            <p class="text-slate-500 text-xs mt-0.5">Daftar rekam jejak pemeriksaan fisik kendaraan operasional.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/public/export_excel.php?type=inspeksi" class="btn-secondary text-xs py-2 px-3">
                <i class="fa-solid fa-file-excel text-emerald-600"></i> Export
            </a>
            <a href="<?= BASE_URL ?>/public/scan.php" class="btn-primary text-xs py-2 px-3.5">
                <i class="fa-solid fa-qrcode"></i> Scan Baru
            </a>
        </div>
    </div>

    <div id="ajax-table-wrap" class="space-y-6">
        <?php require __DIR__ . '/../includes/partial_riwayat_inspeksi_table.php'; ?>
    </div>
</div>

<!-- Modal Detail Checklist Inspeksi Lengkap -->
<?php if ($selectedInspeksi): ?>
    <div id="detail-modal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-2xl w-full p-6 relative max-h-[90vh] overflow-y-auto space-y-4">
            <!-- Modal Header -->
            <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-slate-900"><?= htmlspecialchars($selectedInspeksi['no_polisi']) ?></h3>
                        <span class="font-mono text-blue-700 text-xs px-2 py-0.5 rounded bg-blue-50 font-bold"><?= htmlspecialchars($selectedInspeksi['asset_id']) ?></span>
                        <?= format_badge_hasil($selectedInspeksi['hasil_akhir']) ?>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Diperiksa oleh <b><?= htmlspecialchars($selectedInspeksi['petugas_nama'] ?: 'Petugas') ?></b> pada <?= format_date_id($selectedInspeksi['tanggal_selesai']) ?>
                    </p>
                </div>
                <a href="<?= BASE_URL ?>/public/riwayat_inspeksi.php" class="text-slate-400 hover:text-slate-600 text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>

            <!-- Follow-up Alert if present -->
            <?php if (!empty($selectedTindakLanjut)): ?>
                <?php foreach ($selectedTindakLanjut as $tl): ?>
                    <div class="p-3 rounded-lg <?= $tl['jenis'] === 'mayor_unsafe' ? 'bg-rose-50 border border-rose-200 text-rose-800' : 'bg-amber-50 border border-amber-200 text-amber-800' ?> text-xs space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <i class="fa-solid <?= $tl['jenis'] === 'mayor_unsafe' ? 'fa-hand' : 'fa-wrench' ?>"></i>
                            <span>Catatan Tindak Lanjut: <?= $tl['jenis'] === 'mayor_unsafe' ? 'MAYOR (STOP OPERASI)' : 'MINOR SERVICE' ?></span>
                        </div>
                        <p><?= htmlspecialchars($tl['keterangan']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Inspection Checklist Items Breakdown -->
            <div class="space-y-2.5">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Rincian Checklist 16 Item:</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($selectedDetails as $d): ?>
                        <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs space-y-1">
                            <div class="flex items-start justify-between gap-1">
                                <span class="font-semibold text-slate-800"><?= htmlspecialchars($d['item_nama']) ?></span>
                                <div>
                                    <?php if ($d['kondisi'] === 'ok'): ?>
                                        <span class="badge badge-success text-[9px]">OK</span>
                                    <?php elseif ($d['kondisi'] === 'perlu_perhatian'): ?>
                                        <span class="badge badge-warning text-[9px]">Perhatian</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger text-[9px]">Rusak</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($d['catatan'])): ?>
                                <div class="text-[11px] text-slate-500 italic">Catatan: <?= htmlspecialchars($d['catatan']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($d['foto_path']) && file_exists(BASE_PATH . '/' . $d['foto_path'])): ?>
                                <div class="pt-0.5">
                                    <a href="<?= BASE_URL . '/' . $d['foto_path'] ?>" target="_blank" class="inline-flex items-center gap-1 text-[10px] text-blue-700 hover:underline font-semibold">
                                        <i class="fa-solid fa-camera"></i> Foto Bukti
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Notes -->
            <?php if (!empty($selectedInspeksi['catatan_umum'])): ?>
                <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs">
                    <span class="font-bold text-slate-600 block mb-0.5">Catatan Kesimpulan:</span>
                    <p class="text-slate-800"><?= nl2br(htmlspecialchars($selectedInspeksi['catatan_umum'])) ?></p>
                </div>
            <?php endif; ?>

            <div class="pt-2 border-t border-slate-100 text-right">
                <a href="<?= BASE_URL ?>/public/riwayat_inspeksi.php" class="btn-primary text-xs py-1.5 px-4">
                    Tutup Detail
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Edit Ringkasan Inspeksi -->
<?php if (in_array($user['role'], ['admin', 'pic'])): ?>
<div id="modal-edit-inspeksi" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-md w-full p-6 relative max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-blue-700"></i> Edit Ringkasan Inspeksi
                </h3>
                <p class="text-xs text-slate-500">Hanya ringkasan hasil yang bisa diubah. Rincian 16 item checklist tetap sesuai hasil pemeriksaan asli.</p>
            </div>
            <button type="button" onclick="closeModal('modal-edit-inspeksi')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-3.5">
            <input type="hidden" name="action" value="update_inspeksi">
            <input type="hidden" name="id" id="edit-inspeksi-id">

            <div id="edit-inspeksi-vehicle-info" class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-700"></div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Odometer (KM)</label>
                    <input type="number" name="odometer" id="edit-inspeksi-odometer" min="0"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Level BBM</label>
                    <select name="bbm_level" id="edit-inspeksi-bbm" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                        <option value="Full">Full</option>
                        <option value="3/4">3/4</option>
                        <option value="1/2">1/2</option>
                        <option value="1/4">1/4</option>
                        <option value="E">E (Kosong)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Hasil Akhir <span class="text-rose-500">*</span></label>
                <select name="hasil_akhir" id="edit-inspeksi-hasil" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="ok">OK (Layak)</option>
                    <option value="minor">Minor Service</option>
                    <option value="mayor">STOP (Mayor)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Kesimpulan</label>
                <textarea name="catatan_umum" id="edit-inspeksi-catatan" rows="3" placeholder="Catatan kesimpulan (opsional)..."
                          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none"></textarea>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-edit-inspeksi')" class="btn-secondary text-xs py-2 px-3.5">
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
function openEditInspeksiModal(row) {
    document.getElementById('edit-inspeksi-id').value = row.id;
    document.getElementById('edit-inspeksi-vehicle-info').textContent = row.asset_id + ' - ' + row.no_polisi;
    document.getElementById('edit-inspeksi-odometer').value = row.odometer || '';
    document.getElementById('edit-inspeksi-bbm').value = row.bbm_level || 'Full';
    document.getElementById('edit-inspeksi-hasil').value = row.hasil_akhir || 'ok';
    document.getElementById('edit-inspeksi-catatan').value = row.catatan_umum || '';
    openModal('modal-edit-inspeksi');
}
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>
