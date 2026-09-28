<?php
/**
 * Inspeksi Step 2: Interior & Kelistrikan - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = get_db();
$user = current_user();

$inspeksiId = (int)($_GET['inspeksi_id'] ?? 0);
$stmt = $pdo->prepare("SELECT i.*, k.asset_id, k.no_polisi, k.merk, k.model, k.cabang 
                      FROM inspeksi i 
                      JOIN kendaraan k ON i.kendaraan_id = k.id 
                      WHERE i.id = :id");
$stmt->execute([':id' => $inspeksiId]);
$inspeksi = $stmt->fetch();

if (!$inspeksi) {
    flash_set('error', 'Sesi inspeksi tidak valid atau tidak ditemukan.');
    header('Location: ' . BASE_URL . '/public/scan.php');
    exit;
}

$kategori = 'interior_kelistrikan';
$stepNumber = 2;
$itemsTemplate = get_checklist_template($kategori);

// Ambil data checklist yang sudah pernah disimpan sebelumnya
$stmtDetails = $pdo->prepare("SELECT * FROM inspeksi_detail WHERE inspeksi_id = :iid AND kategori = :kat");
$stmtDetails->execute([':iid' => $inspeksiId, ':kat' => $kategori]);
$existingDetails = [];
while ($row = $stmtDetails->fetch()) {
    $existingDetails[$row['item_nama']] = $row;
}

// Handle Form POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $odometer = !empty($_POST['odometer']) ? (int)$_POST['odometer'] : null;
    $bbmLevel = trim($_POST['bbm_level'] ?? '');
    $itemsPost = $_POST['items'] ?? [];

    $allFilled = true;
    foreach ($itemsTemplate as $tpl) {
        if (!isset($itemsPost[$tpl['name']]['kondisi']) || empty($itemsPost[$tpl['name']]['kondisi'])) {
            $allFilled = false;
            break;
        }
    }

    if (!$allFilled) {
        flash_set('error', 'Harap tentukan kondisi untuk setiap item checklist interior.');
    } else {
        try {
            $pdo->beginTransaction();

            $newProgress = max((int)$inspeksi['page_progress'], 2);
            $stmtUpIns = $pdo->prepare("UPDATE inspeksi SET odometer = :odo, bbm_level = :bbm, page_progress = :prog WHERE id = :id");
            $stmtUpIns->execute([
                ':odo'  => $odometer,
                ':bbm'  => $bbmLevel,
                ':prog' => $newProgress,
                ':id'   => $inspeksiId
            ]);

            $stmtCheck = $pdo->prepare("SELECT id, foto_path FROM inspeksi_detail WHERE inspeksi_id = :iid AND item_nama = :nama");

            foreach ($itemsTemplate as $idx => $tpl) {
                $nama = $tpl['name'];
                $kondisi = $itemsPost[$nama]['kondisi'] ?? 'ok';
                $catatan = trim($itemsPost[$nama]['catatan'] ?? '');

                $stmtCheck->execute([':iid' => $inspeksiId, ':nama' => $nama]);
                $existing = $stmtCheck->fetch();
                $fotoPath = $existing ? $existing['foto_path'] : null;

                $fileKey = 'foto_' . $idx;
                if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $uploadedPath = handle_inspection_upload($_FILES[$fileKey], $inspeksi['asset_id'], $inspeksi['tanggal_mulai'], 'int_' . $idx);
                    if ($uploadedPath) {
                        $fotoPath = $uploadedPath;
                    }
                }

                if ($existing) {
                    $stmtUp = $pdo->prepare("UPDATE inspeksi_detail SET kondisi = :kondisi, foto_path = :foto, catatan = :catatan WHERE id = :id");
                    $stmtUp->execute([
                        ':kondisi' => $kondisi,
                        ':foto'    => $fotoPath,
                        ':catatan' => $catatan,
                        ':id'      => $existing['id']
                    ]);
                } else {
                    $stmtIn = $pdo->prepare("INSERT INTO inspeksi_detail (inspeksi_id, kategori, item_nama, kondisi, foto_path, catatan) 
                                             VALUES (:iid, :kat, :nama, :kondisi, :foto, :catatan)");
                    $stmtIn->execute([
                        ':iid'     => $inspeksiId,
                        ':kat'     => $kategori,
                        ':nama'    => $nama,
                        ':kondisi' => $kondisi,
                        ':foto'    => $fotoPath,
                        ':catatan' => $catatan
                    ]);
                }
            }

            $pdo->commit();

            flash_set('success', 'Bagian Interior & Kelistrikan berhasil disimpan.');
            header('Location: ' . BASE_URL . '/public/inspeksi_mesin.php?inspeksi_id=' . $inspeksiId);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            flash_set('error', 'Gagal menyimpan checklist interior: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Tahap 2: Interior & Kelistrikan - ' . $inspeksi['asset_id'];
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<div class="max-w-2xl mx-auto space-y-4">
    <!-- Vehicle Summary Header -->
    <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-700 font-bold text-sm">
                <i class="fa-solid fa-gauge-high"></i>
            </div>
            <div>
                <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($inspeksi['no_polisi']) ?> <span class="font-mono text-blue-700 text-xs font-semibold">(<?= htmlspecialchars($inspeksi['asset_id']) ?>)</span></div>
                <div class="text-[11px] text-slate-500"><?= htmlspecialchars($inspeksi['merk']) ?> <?= htmlspecialchars($inspeksi['model']) ?> &bull; <?= htmlspecialchars($inspeksi['cabang']) ?></div>
            </div>
        </div>
        <span class="text-xs font-bold px-2.5 py-1 rounded bg-slate-100 text-slate-700 border border-slate-200">
            Tahap 2 / 4
        </span>
    </div>

    <!-- Step Progress Tracker -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <div class="step-indicator">
            <div class="step-indicator-progress" style="width: 50%;"></div>
            <a href="<?= BASE_URL ?>/public/inspeksi_eksterior.php?inspeksi_id=<?= $inspeksiId ?>" class="step-node completed" title="Tahap 1: Selesai"><i class="fa-solid fa-check text-xs"></i></a>
            <div class="step-node active" title="Tahap 2: Interior">2</div>
            <div class="step-node" title="Tahap 3: Mesin">3</div>
            <div class="step-node" title="Tahap 4: Kaki-kaki">4</div>
        </div>
        <div class="flex justify-between text-[11px] font-bold text-slate-500 mt-2 px-1">
            <span class="text-emerald-700">A. Eksterior</span>
            <span class="text-blue-700">B. Interior</span>
            <span>C. Mesin</span>
            <span>D. Kaki-kaki</span>
        </div>
    </div>

    <!-- Form Checklist -->
    <form method="POST" action="" enctype="multipart/form-data" class="space-y-3.5">
        <!-- Odometer & Fuel Level Box -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5 border-b border-slate-100 pb-2">
                <i class="fa-solid fa-gauge text-blue-700"></i> Catatan Kilometer & Level BBM
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <!-- Odometer -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Angka Odometer (KM)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-road text-xs"></i>
                        </span>
                        <input type="number" name="odometer" value="<?= htmlspecialchars($inspeksi['odometer'] ?? '') ?>" placeholder="Misal: 45200" 
                               class="w-full pl-8 pr-12 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none font-mono">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-[11px] font-bold text-slate-400">
                            KM
                        </span>
                    </div>
                </div>

                <!-- BBM Level Selector -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Indikator Level BBM</label>
                    <div class="grid grid-cols-5 gap-1.5">
                        <?php 
                            $bbmOptions = ['Full', '3/4', '1/2', '1/4', 'E'];
                            $currentBbm = $inspeksi['bbm_level'] ?? 'Full';
                        ?>
                        <?php foreach ($bbmOptions as $opt): ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="bbm_level" value="<?= $opt ?>" <?= $currentBbm === $opt ? 'checked' : '' ?> class="peer hidden">
                                <div class="py-2 text-center rounded-lg border border-slate-300 bg-slate-50 peer-checked:border-blue-700 peer-checked:bg-blue-50 peer-checked:text-blue-700 text-xs font-bold text-slate-700 transition-all">
                                    <?= $opt ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                <i class="fa-solid fa-list-check text-blue-700"></i> Formulir B: Interior & Kelistrikan
            </h2>
            <span class="text-[11px] text-slate-500 font-semibold">4 Item Pemeriksaan</span>
        </div>

        <?php foreach ($itemsTemplate as $idx => $tpl): ?>
            <?php
                $itemSaved = $existingDetails[$tpl['name']] ?? null;
                $currentKondisi = $itemSaved['kondisi'] ?? 'ok';
                $currentCatatan = $itemSaved['catatan'] ?? '';
                $currentFoto = $itemSaved['foto_path'] ?? null;
            ?>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                <div>
                    <div class="font-bold text-xs sm:text-sm text-slate-900 flex items-center gap-2">
                        <span><?= ($idx + 1) ?>. <?= htmlspecialchars($tpl['name']) ?></span>
                        <?php if ($tpl['critical']): ?>
                            <span class="badge badge-danger text-[9px] uppercase"><i class="fa-solid fa-triangle-exclamation"></i> Kritis</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($tpl['desc']) ?></p>
                </div>

                <!-- Condition Radio Selector Group -->
                <div class="condition-group pt-1">
                    <label class="condition-option">
                        <input type="radio" name="items[<?= htmlspecialchars($tpl['name']) ?>][kondisi]" value="ok" <?= $currentKondisi === 'ok' ? 'checked' : '' ?>>
                        <div class="condition-card card-ok">
                            <i class="fa-solid fa-circle-check text-base mb-1 text-emerald-600"></i>
                            <span class="text-xs font-bold text-slate-700">OK / Normal</span>
                        </div>
                    </label>

                    <label class="condition-option">
                        <input type="radio" name="items[<?= htmlspecialchars($tpl['name']) ?>][kondisi]" value="perlu_perhatian" <?= $currentKondisi === 'perlu_perhatian' ? 'checked' : '' ?>>
                        <div class="condition-card card-warning">
                            <i class="fa-solid fa-triangle-exclamation text-base mb-1 text-amber-600"></i>
                            <span class="text-xs font-bold text-slate-700">Perlu Perhatian</span>
                        </div>
                    </label>

                    <label class="condition-option">
                        <input type="radio" name="items[<?= htmlspecialchars($tpl['name']) ?>][kondisi]" value="rusak" <?= $currentKondisi === 'rusak' ? 'checked' : '' ?>>
                        <div class="condition-card card-danger">
                            <i class="fa-solid fa-circle-xmark text-base mb-1 text-rose-600"></i>
                            <span class="text-xs font-bold text-slate-700">Rusak / Mati</span>
                        </div>
                    </label>
                </div>

                <!-- Notes & Camera Photo Upload Input -->
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 pt-2 border-t border-slate-100">
                    <div class="sm:col-span-8">
                        <input type="text" name="items[<?= htmlspecialchars($tpl['name']) ?>][catatan]" value="<?= htmlspecialchars($currentCatatan) ?>" placeholder="Catatan opsional (misal: klakson lemah, AC kurang dingin)..." 
                               class="w-full px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:border-blue-600 outline-none">
                    </div>

                    <div class="sm:col-span-4 flex items-center gap-2">
                        <label class="flex-1 cursor-pointer py-1.5 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 text-xs font-semibold flex items-center justify-center gap-1.5 transition-all text-center">
                            <i class="fa-solid fa-camera text-blue-700"></i> Foto Bukti
                            <input type="file" name="foto_<?= $idx ?>" accept="image/*" capture="environment" data-preview-target="preview_int_<?= $idx ?>" class="hidden">
                        </label>
                    </div>
                </div>

                <!-- Photo Preview Box -->
                <div id="preview_int_<?= $idx ?>_container" class="<?= empty($currentFoto) ? 'hidden' : '' ?> pt-1">
                    <div class="relative inline-block border border-slate-300 rounded-lg overflow-hidden shadow-xs">
                        <img id="preview_int_<?= $idx ?>" src="<?= !empty($currentFoto) ? BASE_URL . '/' . $currentFoto : '' ?>" alt="Foto Item" class="w-28 h-20 object-cover">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Action Submit & Next Navigation -->
        <div class="pt-2 pb-6 flex items-center justify-between gap-3">
            <a href="<?= BASE_URL ?>/public/inspeksi_eksterior.php?inspeksi_id=<?= $inspeksiId ?>" class="btn-secondary text-xs py-2.5 px-4">
                <i class="fa-solid fa-arrow-left mr-1"></i> Tahap 1
            </a>
            <button type="submit" class="btn-primary flex-1 py-2.5 text-xs sm:text-sm font-bold">
                Simpan & Lanjut ke Tahap 3 (Mesin) <i class="fa-solid fa-arrow-right ml-1"></i>
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>
