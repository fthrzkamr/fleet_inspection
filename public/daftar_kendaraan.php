<?php
/**
 * Halaman Manajemen Kendaraan & Registrasi Armada (Admin) - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin', 'pic']);

$pdo = get_db();
$user = current_user();

// Handle Registrasi Kendaraan Baru (POST)
$newVehicleCreated = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_kendaraan') {
    $no_polisi = strtoupper(trim($_POST['no_polisi'] ?? ''));
    $merk      = trim($_POST['merk'] ?? '');
    $model     = trim($_POST['model'] ?? '');
    $jenis_kendaraan = ($_POST['jenis_kendaraan'] ?? 'Mobil') === 'Motor' ? 'Motor' : 'Mobil';
    $vin       = strtoupper(trim($_POST['vin'] ?? ''));
    $cabang    = trim($_POST['cabang'] ?? '');
    $tgl_stnk  = !empty($_POST['tgl_stnk_expired']) ? $_POST['tgl_stnk_expired'] : null;
    $tgl_kir   = !empty($_POST['tgl_kir_expired']) ? $_POST['tgl_kir_expired'] : null;
    $tgl_plat  = !empty($_POST['tgl_plat_expired']) ? $_POST['tgl_plat_expired'] : null;

    if (empty($no_polisi) || empty($merk) || empty($model) || empty($cabang)) {
        flash_set('error', 'Semua kolom bertanda bintang (*) wajib diisi.');
    } else {
        try {
            // Generate Asset ID unik (FLEET-XXX)
            $asset_id = generate_asset_id($pdo);
            
            // Generate QR Code File
            $qrPath = generate_vehicle_qr($asset_id);

            $stmt = $pdo->prepare("INSERT INTO kendaraan (asset_id, no_polisi, merk, model, jenis_kendaraan, vin, cabang, status, tgl_stnk_expired, tgl_kir_expired, tgl_plat_expired, qr_code_path)
                                   VALUES (:asset_id, :no_polisi, :merk, :model, :jenis_kendaraan, :vin, :cabang, 'active', :tgl_stnk, :tgl_kir, :tgl_plat, :qr_path)");
            $stmt->execute([
                ':asset_id'  => $asset_id,
                ':no_polisi' => $no_polisi,
                ':merk'      => $merk,
                ':model'     => $model,
                ':jenis_kendaraan' => $jenis_kendaraan,
                ':vin'       => $vin,
                ':cabang'    => $cabang,
                ':tgl_stnk'  => $tgl_stnk,
                ':tgl_kir'   => $tgl_kir,
                ':tgl_plat'  => $tgl_plat,
                ':qr_path'   => $qrPath
            ]);

            flash_set('success', "Armada baru berhasil didaftarkan dengan ID $asset_id dan QR Code telah dibuat.");
            session_write_close();
            header('Location: ' . BASE_URL . '/public/daftar_kendaraan.php');
            exit;
        } catch (PDOException $e) {
            flash_set('error', 'Gagal mendaftarkan armada: ' . $e->getMessage());
        }
    }
}

// Filter & Search Parameters
$search = trim($_GET['search'] ?? '');
$filterCabang = trim($_GET['cabang'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterTglAwal = trim($_GET['tgl_awal'] ?? '');
$filterTglAkhir = trim($_GET['tgl_akhir'] ?? '');

$whereSql = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereSql .= " AND (k.asset_id LIKE :search1 OR k.no_polisi LIKE :search2 OR k.merk LIKE :search3 OR k.model LIKE :search4 OR k.vin LIKE :search5)";
    $params[':search1'] = $params[':search2'] = $params[':search3'] = $params[':search4'] = $params[':search5'] = "%$search%";
}

if (!empty($filterCabang)) {
    $whereSql .= " AND k.cabang = :cabang";
    $params[':cabang'] = $filterCabang;
}

if (!empty($filterStatus)) {
    $whereSql .= " AND k.status = :status";
    $params[':status'] = $filterStatus;
}

// Filter berdasarkan tanggal servis: hanya tampilkan kendaraan yang punya
// riwayat servis di rentang tanggal tersebut (join ke tabel riwayat_servis)
if (!empty($filterTglAwal) || !empty($filterTglAkhir)) {
    $whereSql .= " AND EXISTS (SELECT 1 FROM riwayat_servis rs WHERE rs.kendaraan_id = k.id";
    if (!empty($filterTglAwal)) {
        $whereSql .= " AND rs.tanggal_servis >= :tgl_awal";
        $params[':tgl_awal'] = $filterTglAwal;
    }
    if (!empty($filterTglAkhir)) {
        $whereSql .= " AND rs.tanggal_servis <= :tgl_akhir";
        $params[':tgl_akhir'] = $filterTglAkhir;
    }
    $whereSql .= ")";
}

// Batasi data ke cabang sendiri untuk role pic yang sudah di-set cabang-nya
$scopeCabang = ($user['role'] === 'pic' && !empty($user['cabang'])) ? $user['cabang'] : null;
if ($scopeCabang) {
    $whereSql .= " AND k.cabang = :scopeCb";
    $params[':scopeCb'] = $scopeCabang;
}

// Count Total Rows
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM kendaraan k $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

// Pagination Setup
$pagination = get_pagination_params(10);

$sql = "SELECT k.*, 
        (SELECT hasil_akhir FROM inspeksi WHERE kendaraan_id = k.id ORDER BY id DESC LIMIT 1) as last_hasil_inspeksi,
        (SELECT tanggal_mulai FROM inspeksi WHERE kendaraan_id = k.id ORDER BY id DESC LIMIT 1) as last_tgl_inspeksi
        FROM kendaraan k $whereSql
        ORDER BY k.id DESC 
        LIMIT " . (int)$pagination['offset'] . ", " . (int)$pagination['per_page'];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$kendaraanList = $stmt->fetchAll();

// Stat Counters
$statCabangSql = $scopeCabang ? " AND cabang = :scopeCb" : "";
$statCabangParams = $scopeCabang ? [':scopeCb' => $scopeCabang] : [];

$stmtCA = $pdo->prepare("SELECT COUNT(*) FROM kendaraan WHERE status = 'active'" . $statCabangSql);
$stmtCA->execute($statCabangParams);
$countActive = (int)$stmtCA->fetchColumn();

$stmtCP = $pdo->prepare("SELECT COUNT(*) FROM kendaraan WHERE status = 'peremajaan'" . $statCabangSql);
$stmtCP->execute($statCabangParams);
$countPeremajaan = (int)$stmtCP->fetchColumn();

$stmtCI = $pdo->prepare("SELECT COUNT(*) FROM kendaraan WHERE status = 'inactive'" . $statCabangSql);
$stmtCI->execute($statCabangParams);
$countInactive = (int)$stmtCI->fetchColumn();

// Ambang batas "2 bulan kalender", konsisten dengan query reminder di dashboard.php
$reminderThresholdDate = (new DateTime('today'))->modify('+2 months');
$reminderLeadDays = (int)(new DateTime('today'))->diff($reminderThresholdDate)->days;

$isAjax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if ($isAjax) {
    require __DIR__ . '/../includes/partial_daftar_kendaraan_table.php';
    exit;
}

$pageTitle = 'Kelola Database Armada';
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<div class="space-y-6">
    <!-- Flash Messages -->
    <?= render_flash_messages() ?>

    <!-- Header Page & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-truck text-blue-700"></i> Database Kendaraan Operasional
            </h1>
            <p class="text-slate-500 text-xs mt-0.5">Registrasi armada baru, penerbitan stiker QR code, dan pemeliharaan data armada.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/public/export_excel.php?type=kendaraan" class="btn-secondary text-xs py-2 px-3" title="Export Excel / CSV">
                <i class="fa-solid fa-file-excel text-emerald-600"></i> Export
            </a>
            <button type="button" onclick="openModal('modal-tambah-kendaraan')" class="btn-primary text-xs py-2 px-3.5">
                <i class="fa-solid fa-circle-plus"></i> Tambah Armada
            </button>
        </div>
    </div>

    <!-- Quick Stats Overview -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div class="stat-card">
            <div class="text-[11px] font-bold uppercase text-slate-500">Total Terdaftar</div>
            <div class="text-2xl font-black text-slate-900 mt-1"><?= $countActive + $countPeremajaan + $countInactive ?> <span class="text-xs font-normal text-slate-500">Unit</span></div>
        </div>
        <div class="stat-card bg-emerald-50/50 border-emerald-200">
            <div class="text-[11px] font-bold uppercase text-emerald-700">Active (Siap Jalan)</div>
            <div class="text-2xl font-black text-emerald-800 mt-1"><?= $countActive ?> <span class="text-xs font-normal text-emerald-600">Unit</span></div>
        </div>
        <div class="stat-card bg-amber-50/50 border-amber-200">
            <div class="text-[11px] font-bold uppercase text-amber-700">Peremajaan (Bengkel)</div>
            <div class="text-2xl font-black text-amber-800 mt-1"><?= $countPeremajaan ?> <span class="text-xs font-normal text-amber-600">Unit</span></div>
        </div>
        <div class="stat-card bg-rose-50/50 border-rose-200">
            <div class="text-[11px] font-bold uppercase text-rose-700">Inactive / Dijual</div>
            <div class="text-2xl font-black text-rose-800 mt-1"><?= $countInactive ?> <span class="text-xs font-normal text-rose-600">Unit</span></div>
        </div>
    </div>

    <div id="ajax-table-wrap" class="space-y-6">
        <?php require __DIR__ . '/../includes/partial_daftar_kendaraan_table.php'; ?>
    </div>
</div>

<!-- Modal Tambah Armada Baru -->
<div id="modal-tambah-kendaraan" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-lg w-full p-6 relative max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-circle-plus text-blue-700"></i> Registrasi Kendaraan Baru
                </h3>
                <p class="text-xs text-slate-500">Asset ID unik dan QR Code akan dibuat otomatis oleh sistem.</p>
            </div>
            <button type="button" onclick="closeModal('modal-tambah-kendaraan')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-3.5">
            <input type="hidden" name="action" value="create_kendaraan">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Polisi <span class="text-rose-500">*</span></label>
                    <input type="text" name="no_polisi" required placeholder="Misal: B 1234 XYZ" 
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 uppercase focus:border-blue-600 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Cabang Operasional <span class="text-rose-500">*</span></label>
                    <select name="cabang" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                        <option value="">-- Pilih Cabang --</option>
                        <?php 
                            $activeCabang = get_cabang_list($pdo, true);
                            foreach ($activeCabang as $c): 
                        ?>
                            <option value="<?= htmlspecialchars($c['nama_cabang']) ?>"><?= htmlspecialchars($c['nama_cabang']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Merk Kendaraan <span class="text-rose-500">*</span></label>
                    <input type="text" name="merk" required placeholder="Misal: Toyota, Daihatsu, Isuzu"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Model / Tipe <span class="text-rose-500">*</span></label>
                    <input type="text" name="model" required placeholder="Misal: Avanza 1.3 G, Gran Max Box"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kendaraan <span class="text-rose-500">*</span></label>
                <select name="jenis_kendaraan" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="Mobil">Mobil</option>
                    <option value="Motor">Motor</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Rangka (VIN)</label>
                <input type="text" name="vin" placeholder="Misal: MHKM1BA3J8K001234" 
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 uppercase focus:border-blue-600 outline-none font-mono">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Masa Berlaku STNK</label>
                    <input type="date" name="tgl_stnk_expired"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Masa Berlaku KIR</label>
                    <input type="date" name="tgl_kir_expired"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Masa Berlaku Plat (TNKB)</label>
                    <input type="date" name="tgl_plat_expired"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-tambah-kendaraan')" class="btn-secondary text-xs py-2 px-3.5">
                    Batal
                </button>
                <button type="submit" class="btn-primary text-xs py-2 px-4">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan Armada
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>
