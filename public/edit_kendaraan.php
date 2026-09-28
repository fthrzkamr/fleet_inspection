<?php
/**
 * Halaman Edit Kendaraan & Refresh Armada (Admin) - Enterprise UI
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin', 'pic']);

$pdo = get_db();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM kendaraan WHERE id = :id");
$stmt->execute([':id' => $id]);
$kendaraan = $stmt->fetch();

if (!$kendaraan) {
    flash_set('error', 'Data kendaraan tidak ditemukan.');
    header('Location: ' . BASE_URL . '/public/daftar_kendaraan.php');
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editType = $_POST['edit_type'] ?? '';

    if ($editType === 'perubahan_identitas') {
        $no_polisi = strtoupper(trim($_POST['no_polisi'] ?? ''));
        $merk      = trim($_POST['merk'] ?? '');
        $model     = trim($_POST['model'] ?? '');
        $jenis_kendaraan = ($_POST['jenis_kendaraan'] ?? 'Mobil') === 'Motor' ? 'Motor' : 'Mobil';
        $vin       = strtoupper(trim($_POST['vin'] ?? ''));
        $cabang    = trim($_POST['cabang'] ?? '');
        $status    = trim($_POST['status'] ?? 'active');
        $reason    = ($status === 'inactive') ? trim($_POST['status_inactive_reason'] ?? 'lainnya') : null;
        $tgl_stnk  = !empty($_POST['tgl_stnk_expired']) ? $_POST['tgl_stnk_expired'] : null;
        $tgl_kir   = !empty($_POST['tgl_kir_expired']) ? $_POST['tgl_kir_expired'] : null;
        $tgl_plat  = !empty($_POST['tgl_plat_expired']) ? $_POST['tgl_plat_expired'] : null;

        if (empty($no_polisi) || empty($merk) || empty($model) || empty($cabang)) {
            flash_set('error', 'Semua field wajib diisi.');
        } else {
            try {
                $dataLama = json_encode($kendaraan);
                $dataBaru = json_encode([
                    'no_polisi' => $no_polisi,
                    'merk'      => $merk,
                    'model'     => $model,
                    'jenis_kendaraan' => $jenis_kendaraan,
                    'vin'       => $vin,
                    'cabang'    => $cabang,
                    'status'    => $status,
                    'status_inactive_reason' => $reason,
                    'tgl_stnk_expired' => $tgl_stnk,
                    'tgl_kir_expired'  => $tgl_kir,
                    'tgl_plat_expired' => $tgl_plat
                ]);

                $stmtUpdate = $pdo->prepare("UPDATE kendaraan SET
                    no_polisi = :no_polisi,
                    merk = :merk,
                    model = :model,
                    jenis_kendaraan = :jenis_kendaraan,
                    vin = :vin,
                    cabang = :cabang,
                    status = :status,
                    status_inactive_reason = :reason,
                    tgl_stnk_expired = :tgl_stnk,
                    tgl_kir_expired = :tgl_kir,
                    tgl_plat_expired = :tgl_plat
                    WHERE id = :id");

                $stmtUpdate->execute([
                    ':no_polisi' => $no_polisi,
                    ':merk'      => $merk,
                    ':model'     => $model,
                    ':jenis_kendaraan' => $jenis_kendaraan,
                    ':vin'       => $vin,
                    ':cabang'    => $cabang,
                    ':status'    => $status,
                    ':reason'    => $reason,
                    ':tgl_stnk'  => $tgl_stnk,
                    ':tgl_kir'   => $tgl_kir,
                    ':tgl_plat'  => $tgl_plat,
                    ':id'        => $id
                ]);

                $stmtLog = $pdo->prepare("INSERT INTO kendaraan_log_perubahan (kendaraan_id, jenis, data_lama, data_baru, oleh_user_id) 
                                          VALUES (:kid, 'perubahan_identitas', :dl, :db, :uid)");
                $stmtLog->execute([
                    ':kid' => $id,
                    ':dl'  => $dataLama,
                    ':db'  => $dataBaru,
                    ':uid' => $user['id']
                ]);

                flash_set('success', 'Identitas kendaraan berhasil diperbarui. Asset ID & QR Code tetap tersambung.');
                session_write_close();
                header('Location: ' . BASE_URL . '/public/edit_kendaraan.php?id=' . $id);
                exit;
            } catch (Exception $e) {
                flash_set('error', 'Gagal memperbarui data: ' . $e->getMessage());
            }
        }

    } elseif ($editType === 'refresh_armada') {
        $reasonOld  = trim($_POST['old_inactive_reason'] ?? 'dijual');
        $new_nopol  = strtoupper(trim($_POST['new_no_polisi'] ?? ''));
        $new_merk   = trim($_POST['new_merk'] ?? '');
        $new_model  = trim($_POST['new_model'] ?? '');
        $new_jenis_kendaraan = ($_POST['new_jenis_kendaraan'] ?? 'Mobil') === 'Motor' ? 'Motor' : 'Mobil';
        $new_vin    = strtoupper(trim($_POST['new_vin'] ?? ''));
        $new_cabang = trim($_POST['new_cabang'] ?? $kendaraan['cabang']);
        $new_stnk   = !empty($_POST['new_tgl_stnk']) ? $_POST['new_tgl_stnk'] : null;
        $new_kir    = !empty($_POST['new_tgl_kir']) ? $_POST['new_tgl_kir'] : null;
        $new_plat   = !empty($_POST['new_tgl_plat']) ? $_POST['new_tgl_plat'] : null;

        if (empty($new_nopol) || empty($new_merk) || empty($new_model)) {
            flash_set('error', 'Harap lengkapi informasi armada pengganti yang baru.');
        } else {
            try {
                $pdo->beginTransaction();

                $stmtInact = $pdo->prepare("UPDATE kendaraan SET status = 'inactive', status_inactive_reason = :reason WHERE id = :id");
                $stmtInact->execute([':reason' => $reasonOld, ':id' => $id]);

                $newAssetId = generate_asset_id($pdo);
                $newQrPath = generate_vehicle_qr($newAssetId);

                $stmtNew = $pdo->prepare("INSERT INTO kendaraan (asset_id, no_polisi, merk, model, jenis_kendaraan, vin, cabang, status, tgl_stnk_expired, tgl_kir_expired, tgl_plat_expired, qr_code_path)
                                         VALUES (:asset_id, :nopol, :merk, :model, :jenis_kendaraan, :vin, :cabang, 'active', :stnk, :kir, :plat, :qr)");
                $stmtNew->execute([
                    ':asset_id' => $newAssetId,
                    ':nopol'    => $new_nopol,
                    ':merk'     => $new_merk,
                    ':model'    => $new_model,
                    ':jenis_kendaraan' => $new_jenis_kendaraan,
                    ':vin'      => $new_vin,
                    ':cabang'   => $new_cabang,
                    ':stnk'     => $new_stnk,
                    ':kir'      => $new_kir,
                    ':plat'     => $new_plat,
                    ':qr'       => $newQrPath
                ]);
                $newKendaraanId = $pdo->lastInsertId();

                $stmtLog = $pdo->prepare("INSERT INTO kendaraan_log_perubahan (kendaraan_id, jenis, data_lama, data_baru, asset_id_baru, oleh_user_id) 
                                          VALUES (:old_kid, 'refresh_armada', :dl, :db, :new_asset, :uid)");
                $stmtLog->execute([
                    ':old_kid'   => $id,
                    ':dl'        => json_encode($kendaraan),
                    ':db'        => json_encode([
                        'id'        => $newKendaraanId,
                        'asset_id'  => $newAssetId,
                        'no_polisi' => $new_nopol,
                        'merk'      => $new_merk,
                        'model'     => $new_model
                    ]),
                    ':new_asset' => $newAssetId,
                    ':uid'       => $user['id']
                ]);

                $pdo->commit();

                flash_set('success', "Refresh Armada Berhasil! Unit lama ({$kendaraan['asset_id']}) dinonaktifkan ($reasonOld) dan unit baru ($newAssetId - $new_nopol) berhasil didaftarkan.");
                session_write_close();
                header('Location: ' . BASE_URL . '/public/edit_kendaraan.php?id=' . $newKendaraanId);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Gagal memproses refresh armada: ' . $e->getMessage());
            }
        }
    }
}

// Ambil riwayat log perubahan
$stmtLogs = $pdo->prepare("SELECT l.*, u.nama as user_nama 
                          FROM kendaraan_log_perubahan l 
                          LEFT JOIN users u ON l.oleh_user_id = u.id 
                          WHERE l.kendaraan_id = :id 
                          ORDER BY l.id DESC");
$stmtLogs->execute([':id' => $id]);
$logs = $stmtLogs->fetchAll();

$pageTitle = 'Edit Data Armada: ' . $kendaraan['asset_id'];
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Flash Messages -->
    <?= render_flash_messages() ?>

    <!-- Header Back & Overview -->
    <div class="flex items-center justify-between">
        <a href="<?= BASE_URL ?>/public/daftar_kendaraan.php" class="text-slate-600 hover:text-slate-900 text-xs font-bold flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Armada
        </a>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/public/cetak_qr.php?id=<?= $kendaraan['id'] ?>" class="btn-secondary text-xs py-1.5 px-3">
                <i class="fa-solid fa-print"></i> Cetak QR
            </a>
            <a href="<?= BASE_URL ?>/public/riwayat_inspeksi.php?kendaraan_id=<?= $kendaraan['id'] ?>" class="btn-secondary text-xs py-1.5 px-3">
                <i class="fa-solid fa-clock-rotate-left"></i> Riwayat
            </a>
        </div>
    </div>

    <!-- Summary Box -->
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center gap-5">
        <div class="w-16 h-16 bg-slate-50 p-2 rounded-xl border border-slate-200 flex-shrink-0 flex items-center justify-center">
            <?php if (file_exists(BASE_PATH . '/' . $kendaraan['qr_code_path'])): ?>
                <img src="<?= BASE_URL . '/' . $kendaraan['qr_code_path'] . '?v=' . filemtime(BASE_PATH . '/' . $kendaraan['qr_code_path']) ?>" alt="QR" class="w-full h-full object-contain">
            <?php else: ?>
                <i class="fa-solid fa-qrcode text-2xl text-slate-600"></i>
            <?php endif; ?>
        </div>
        <div class="flex-1 text-center sm:text-left">
            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                <span class="text-xl font-black text-slate-900"><?= htmlspecialchars($kendaraan['no_polisi']) ?></span>
                <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-blue-50 text-blue-700 border border-blue-200"><?= htmlspecialchars($kendaraan['asset_id']) ?></span>
                <?= format_badge_status($kendaraan['status'], $kendaraan['status_inactive_reason']) ?>
            </div>
            <div class="text-xs text-slate-600 font-semibold mt-1"><?= htmlspecialchars($kendaraan['merk']) ?> <?= htmlspecialchars($kendaraan['model']) ?> &bull; <span>Cabang <?= htmlspecialchars($kendaraan['cabang']) ?></span></div>
            <div class="text-[11px] text-slate-400 font-mono mt-0.5">VIN: <?= htmlspecialchars($kendaraan['vin'] ?: '-') ?></div>
        </div>
    </div>

    <!-- Workflow Selector Tabs -->
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
        <div class="mb-5 pb-3 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-code-branch text-blue-700"></i> Tentukan Alur Perubahan Data:
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Pilih alur perubahan sesuai dengan kondisi fisik unit di operasional.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <!-- Option 1: Unit Sama -->
            <label onclick="switchEditTab('tab-identitas')" class="cursor-pointer">
                <input type="radio" name="flow_choice" id="radio-identitas" checked class="peer hidden">
                <div class="p-4 rounded-xl border-2 border-slate-200 bg-slate-50 peer-checked:border-blue-700 peer-checked:bg-blue-50/50 transition-all h-full">
                    <div class="flex items-center gap-2 font-bold text-xs text-slate-800 peer-checked:text-blue-700">
                        <i class="fa-solid fa-id-card text-sm"></i>
                        <span>1. Perubahan Identitas (Unit Sama)</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                        Ganti nomor plat, perpanjang STNK/KIR, atau mutasi cabang. 
                        <b class="text-slate-700">Asset ID & QR Code TIDAK berubah</b> dan seluruh riwayat inspeksi tetap tersambung.
                    </p>
                </div>
            </label>

            <!-- Option 2: Refresh Armada (Ganti Unit) -->
            <label onclick="switchEditTab('tab-refresh')" class="cursor-pointer">
                <input type="radio" name="flow_choice" id="radio-refresh" class="peer hidden">
                <div class="p-4 rounded-xl border-2 border-slate-200 bg-slate-50 peer-checked:border-amber-600 peer-checked:bg-amber-50/50 transition-all h-full">
                    <div class="flex items-center gap-2 font-bold text-xs text-slate-800 peer-checked:text-amber-700">
                        <i class="fa-solid fa-arrows-rotate text-sm"></i>
                        <span>2. Refresh Armada (Ganti Unit Baru)</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                        Penggantian mobil lama dengan unit fisik baru. Unit lama dinonaktifkan (arsip tersimpan), 
                        dan <b class="text-slate-700">unit baru akan otomatis dibuatkan Asset ID & QR Code baru</b>.
                    </p>
                </div>
            </label>
        </div>

        <!-- FORM 1: Perubahan Identitas -->
        <div id="tab-identitas" class="space-y-4">
            <form method="POST" action="" class="space-y-3.5">
                <input type="hidden" name="edit_type" value="perubahan_identitas">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Polisi (Plat)</label>
                        <input type="text" name="no_polisi" value="<?= htmlspecialchars($kendaraan['no_polisi']) ?>" required
                               class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 uppercase focus:border-blue-600 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Cabang Penempatan</label>
                        <select name="cabang" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                            <?php 
                                $allCabang = get_cabang_list($pdo, false);
                                foreach ($allCabang as $c): 
                                    $cName = $c['nama_cabang'];
                            ?>
                                <option value="<?= htmlspecialchars($cName) ?>" <?= $kendaraan['cabang'] === $cName ? 'selected' : '' ?>><?= htmlspecialchars($cName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Merk Kendaraan</label>
                        <input type="text" name="merk" value="<?= htmlspecialchars($kendaraan['merk']) ?>" required
                               class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Model / Tipe</label>
                        <input type="text" name="model" value="<?= htmlspecialchars($kendaraan['model']) ?>" required
                               class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kendaraan</label>
                        <select name="jenis_kendaraan" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                            <option value="Mobil" <?= $kendaraan['jenis_kendaraan'] === 'Mobil' ? 'selected' : '' ?>>Mobil</option>
                            <option value="Motor" <?= $kendaraan['jenis_kendaraan'] === 'Motor' ? 'selected' : '' ?>>Motor</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Rangka (VIN)</label>
                        <input type="text" name="vin" value="<?= htmlspecialchars($kendaraan['vin'] ?? '') ?>"
                               class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 uppercase focus:border-blue-600 outline-none font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Status Operasional</label>
                        <select name="status" id="select-status" onchange="toggleReasonInput(this.value)" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                            <option value="active" <?= $kendaraan['status'] === 'active' ? 'selected' : '' ?>>Active (Siap Jalan)</option>
                            <option value="peremajaan" <?= $kendaraan['status'] === 'peremajaan' ? 'selected' : '' ?>>Peremajaan (Perbaikan)</option>
                            <option value="inactive" <?= $kendaraan['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Tidak Beroperasi)</option>
                        </select>
                    </div>
                </div>

                <div id="reason-container" class="<?= $kendaraan['status'] === 'inactive' ? '' : 'hidden' ?>">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Alasan Inactive</label>
                    <select name="status_inactive_reason" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                        <option value="dijual" <?= $kendaraan['status_inactive_reason'] === 'dijual' ? 'selected' : '' ?>>Unit Dijual</option>
                        <option value="lainnya" <?= $kendaraan['status_inactive_reason'] === 'lainnya' ? 'selected' : '' ?>>Lainnya / Dihapus dari Operasional</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Masa Berlaku STNK <span class="text-slate-400 font-normal">(1 Tahun)</span></label>
                        <input type="date" name="tgl_stnk_expired" value="<?= htmlspecialchars($kendaraan['tgl_stnk_expired'] ?? '') ?>"
                               class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Masa Berlaku KIR</label>
                        <input type="date" name="tgl_kir_expired" value="<?= htmlspecialchars($kendaraan['tgl_kir_expired'] ?? '') ?>"
                               class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Masa Berlaku Plat (TNKB) <span class="text-slate-400 font-normal">(5 Tahun)</span></label>
                        <input type="date" name="tgl_plat_expired" value="<?= htmlspecialchars($kendaraan['tgl_plat_expired'] ?? '') ?>"
                               class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    </div>
                </div>

                <div class="pt-3 flex items-center justify-end border-t border-slate-100">
                    <button type="submit" class="btn-primary text-xs py-2 px-5">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan Perubahan Identitas
                    </button>
                </div>
            </form>
        </div>

        <!-- FORM 2: Refresh Armada (Ganti Unit) -->
        <div id="tab-refresh" class="hidden space-y-4">
            <form method="POST" action="" class="space-y-4" onsubmit="return confirmFormSubmit(event, this, { title: 'Konfirmasi Refresh Armada Baru', message: 'Unit fisik lama akan dinonaktifkan (tersimpan sebagai arsip) dan unit baru akan otomatis diterbitkan Asset ID serta QR Code baru. Lanjutkan?', confirmText: 'Proses Refresh Armada', confirmType: 'warning' })">
                <input type="hidden" name="edit_type" value="refresh_armada">

                <div class="p-3.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-xs">
                    <div class="font-bold flex items-center gap-1.5 mb-1 text-amber-800">
                        <i class="fa-solid fa-triangle-exclamation"></i> Status Unit Lama (<?= htmlspecialchars($kendaraan['asset_id']) ?> - <?= htmlspecialchars($kendaraan['no_polisi']) ?>):
                    </div>
                    Unit lama akan diubah statusnya menjadi <b>Inactive</b>. Semua riwayat inspeksi sebelumnya tetap aman dan tersimpan untuk audit.
                    <div class="mt-2.5">
                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">Alasan Penonaktifan Unit Lama:</label>
                        <select name="old_inactive_reason" class="w-full sm:w-64 px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-amber-600 outline-none">
                            <option value="dijual">Unit Dijual / Lelang</option>
                            <option value="lainnya">Unit Rusak Total / Ditarik</option>
                        </select>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3 flex items-center gap-1.5">
                        <i class="fa-solid fa-plus-circle text-emerald-600"></i> Informasi Armada Baru Pengganti:
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Polisi Baru <span class="text-rose-500">*</span></label>
                            <input type="text" name="new_no_polisi" required placeholder="Misal: B 9999 NEW"
                                   class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 uppercase focus:border-amber-600 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Cabang Penempatan</label>
                            <select name="new_cabang" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-amber-600 outline-none">
                                <?php 
                                    $activeCabang = get_cabang_list($pdo, true);
                                    foreach ($activeCabang as $c): 
                                        $cName = $c['nama_cabang'];
                                ?>
                                    <option value="<?= htmlspecialchars($cName) ?>" <?= $kendaraan['cabang'] === $cName ? 'selected' : '' ?>><?= htmlspecialchars($cName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Merk Kendaraan Baru <span class="text-rose-500">*</span></label>
                            <input type="text" name="new_merk" required placeholder="Misal: Isuzu, Toyota, Mitsubishi"
                                   class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-amber-600 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Model / Tipe Baru <span class="text-rose-500">*</span></label>
                            <input type="text" name="new_model" required placeholder="Misal: Traga Blind Van, All New Avanza"
                                   class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-amber-600 outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kendaraan Baru <span class="text-rose-500">*</span></label>
                            <select name="new_jenis_kendaraan" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-amber-600 outline-none">
                                <option value="Mobil">Mobil</option>
                                <option value="Motor">Motor</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Rangka (VIN) Baru</label>
                            <input type="text" name="new_vin" placeholder="Misal: MHK..."
                                   class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 uppercase focus:border-amber-600 outline-none font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Masa Berlaku STNK</label>
                            <input type="date" name="new_tgl_stnk"
                                   class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-amber-600 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Masa Berlaku KIR</label>
                            <input type="date" name="new_tgl_kir"
                                   class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-amber-600 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Masa Berlaku Plat (TNKB)</label>
                            <input type="date" name="new_tgl_plat"
                                   class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-amber-600 outline-none">
                        </div>
                    </div>
                </div>

                <div class="pt-3 flex items-center justify-end border-t border-slate-100">
                    <button type="submit" class="btn-primary bg-amber-600 hover:bg-amber-700 border-amber-700 text-xs py-2 px-5">
                        <i class="fa-solid fa-arrows-rotate mr-1"></i> Proses Refresh Armada Baru
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Riwayat Log Perubahan -->
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3 flex items-center gap-1.5">
            <i class="fa-solid fa-clock-rotate-left text-blue-700"></i> Riwayat Log Audit Perubahan Unit
        </h3>

        <?php if (empty($logs)): ?>
            <p class="text-xs text-slate-400 italic">Belum ada catatan perubahan atau refresh armada untuk kendaraan ini.</p>
        <?php else: ?>
            <div class="space-y-2.5">
                <?php foreach ($logs as $l): ?>
                    <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="font-bold <?= $l['jenis'] === 'refresh_armada' ? 'text-amber-700' : 'text-blue-700' ?>">
                                <i class="fa-solid <?= $l['jenis'] === 'refresh_armada' ? 'fa-arrows-rotate' : 'fa-id-card' ?> mr-1"></i>
                                <?= $l['jenis'] === 'refresh_armada' ? 'Refresh Armada (Ganti Unit)' : 'Perubahan Identitas' ?>
                            </span>
                            <span class="text-slate-400 text-[11px]"><?= format_date_id($l['tanggal']) ?></span>
                        </div>
                        <div class="text-slate-600">
                            Diproses oleh: <span class="font-semibold text-slate-800"><?= htmlspecialchars($l['user_nama'] ?: 'Sistem') ?></span>
                            <?php if (!empty($l['asset_id_baru'])): ?>
                                &bull; Unit Baru: <code class="text-emerald-700 font-bold"><?= htmlspecialchars($l['asset_id_baru']) ?></code>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchEditTab(tabId) {
    document.getElementById('tab-identitas').classList.add('hidden');
    document.getElementById('tab-refresh').classList.add('hidden');
    document.getElementById(tabId).classList.remove('hidden');
}

function toggleReasonInput(val) {
    const rc = document.getElementById('reason-container');
    if (val === 'inactive') {
        rc.classList.remove('hidden');
    } else {
        rc.classList.add('hidden');
    }
}
</script>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>
