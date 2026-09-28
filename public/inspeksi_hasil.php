<?php
/**
 * Inspeksi Step 5: Evaluasi Hasil & Konfirmasi Final - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = get_db();
$user = current_user();

$inspeksiId = (int)($_GET['inspeksi_id'] ?? 0);
$stmt = $pdo->prepare("SELECT i.*, k.asset_id, k.no_polisi, k.merk, k.model, k.cabang, k.vin 
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

$stmtDetails = $pdo->prepare("SELECT * FROM inspeksi_detail WHERE inspeksi_id = :iid ORDER BY id ASC");
$stmtDetails->execute([':iid' => $inspeksiId]);
$details = $stmtDetails->fetchAll();

if (empty($details)) {
    flash_set('warning', 'Belum ada data checklist yang diisi. Harap mulai dari Step 1.');
    header('Location: ' . BASE_URL . '/public/inspeksi_eksterior.php?inspeksi_id=' . $inspeksiId);
    exit;
}

$evalResult = evaluate_inspection_status($details);

// Handle Final Confirmation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_inspection') {
    $overrideStatus = trim($_POST['hasil_override'] ?? 'auto');
    $catatanUmum    = trim($_POST['catatan_umum'] ?? '');

    $finalHasil = ($overrideStatus !== 'auto' && in_array($overrideStatus, ['ok', 'minor', 'mayor'])) 
                  ? $overrideStatus 
                  : $evalResult['hasil'];

    try {
        $pdo->beginTransaction();

        $stmtFinal = $pdo->prepare("UPDATE inspeksi SET 
            hasil_akhir = :hasil, 
            catatan_umum = :catatan, 
            tanggal_selesai = NOW(), 
            page_progress = 4 
            WHERE id = :id");
        $stmtFinal->execute([
            ':hasil'   => $finalHasil,
            ':catatan' => $catatanUmum,
            ':id'      => $inspeksiId
        ]);

        if ($finalHasil === 'mayor') {
            $keteranganAlert = "STOP ARMADA (" . $inspeksi['asset_id'] . " - " . $inspeksi['no_polisi'] . "): Ditemukan kendala Mayor kritis saat inspeksi rutin. Segera lakukan perbaikan sebelum diizinkan beroperasi.";
            if (!empty($evalResult['reasons'])) {
                $keteranganAlert .= " Temuan: " . implode(', ', $evalResult['reasons']) . ".";
            }
            if (!empty($catatanUmum)) {
                $keteranganAlert .= " Catatan: $catatanUmum";
            }

            $stmtTL = $pdo->prepare("INSERT INTO riwayat_tindak_lanjut (inspeksi_id, kendaraan_id, jenis, status_notifikasi, keterangan) 
                                     VALUES (:iid, :kid, 'mayor_unsafe', 'pending', :ket)");
            $stmtTL->execute([
                ':iid' => $inspeksiId,
                ':kid' => $inspeksi['kendaraan_id'],
                ':ket' => $keteranganAlert
            ]);

        } elseif ($finalHasil === 'minor') {
            $keteranganMinor = "Perbaikan Berkala (" . $inspeksi['asset_id'] . "): Terdapat catatan minor pada kendaraan.";
            if (!empty($evalResult['reasons'])) {
                $keteranganMinor .= " Temuan: " . implode(', ', $evalResult['reasons']) . ".";
            }
            if (!empty($catatanUmum)) {
                $keteranganMinor .= " Catatan: $catatanUmum";
            }

            $stmtTL = $pdo->prepare("INSERT INTO riwayat_tindak_lanjut (inspeksi_id, kendaraan_id, jenis, status_notifikasi, keterangan) 
                                     VALUES (:iid, :kid, 'minor_service', 'pending', :ket)");
            $stmtTL->execute([
                ':iid' => $inspeksiId,
                ':kid' => $inspeksi['kendaraan_id'],
                ':ket' => $keteranganMinor
            ]);
        }

        $pdo->commit();

        $msgSuccess = 'Inspeksi armada ' . $inspeksi['asset_id'] . ' berhasil diselesaikan dengan hasil: ' . strtoupper($finalHasil) . '.';
        flash_set('success', $msgSuccess);

        header('Location: ' . BASE_URL . '/public/riwayat_inspeksi.php?inspeksi_id=' . $inspeksiId);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        flash_set('error', 'Gagal memproses finalisasi inspeksi: ' . $e->getMessage());
    }
}

$detailsByCat = [
    'eksterior' => [],
    'interior_kelistrikan' => [],
    'mesin' => [],
    'kaki_kaki' => []
];
foreach ($details as $d) {
    $detailsByCat[$d['kategori']][] = $d;
}

$pageTitle = 'Evaluasi & Konfirmasi: ' . $inspeksi['asset_id'];
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<div class="max-w-3xl mx-auto space-y-5">
    <!-- Vehicle Summary Header -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-700 font-bold text-lg">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>
            <div>
                <div class="font-bold text-slate-900 text-sm flex items-center gap-2">
                    <?= htmlspecialchars($inspeksi['no_polisi']) ?>
                    <span class="font-mono text-blue-700 text-xs px-2 py-0.5 rounded bg-blue-50 border border-blue-200 font-bold"><?= htmlspecialchars($inspeksi['asset_id']) ?></span>
                </div>
                <div class="text-xs text-slate-500">
                    <?= htmlspecialchars($inspeksi['merk']) ?> <?= htmlspecialchars($inspeksi['model']) ?> &bull; <?= htmlspecialchars($inspeksi['cabang']) ?>
                </div>
            </div>
        </div>
        <div class="text-right text-xs">
            <div class="text-slate-500">Odometer / BBM:</div>
            <div class="font-bold text-slate-800"><?= number_format((int)$inspeksi['odometer'], 0, ',', '.') ?> KM &bull; Level <?= htmlspecialchars($inspeksi['bbm_level'] ?: '-') ?></div>
        </div>
    </div>

    <!-- Automatic Condition Evaluation Result Banner -->
    <?php if ($evalResult['hasil'] === 'mayor'): ?>
        <div class="p-5 rounded-xl bg-rose-50 border-2 border-rose-300 shadow-sm space-y-2.5">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-rose-600 text-white flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-hand"></i>
                </div>
                <div class="flex-1">
                    <span class="badge badge-danger text-xs font-bold uppercase">
                        <i class="fa-solid fa-triangle-exclamation"></i> KONDISI MAYOR — STATUS STOP
                    </span>
                    <h2 class="text-lg font-bold text-rose-950 mt-1">Armada Dilarang Beroperasi (Unsafe)</h2>
                    <p class="text-xs text-rose-800 mt-0.5 leading-relaxed">
                        Sistem mendeteksi adanya kerusakan pada komponen keselamatan kritis. Kendaraan akan otomatis ditandai sebagai <b>STOP</b> di dashboard dan membutuhkan tindakan perbaikan sebelum dapat digunakan.
                    </p>
                </div>
            </div>

            <?php if (!empty($evalResult['reasons'])): ?>
                <div class="mt-2 p-3 bg-white border border-rose-200 rounded-lg text-xs space-y-1">
                    <div class="font-bold text-rose-900 uppercase text-[10px] tracking-wider">Item Kritis Penyebab Status Mayor:</div>
                    <ul class="list-disc list-inside text-rose-700 font-semibold space-y-0.5">
                        <?php foreach ($evalResult['reasons'] as $r): ?>
                            <li><?= htmlspecialchars($r) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($evalResult['hasil'] === 'minor'): ?>
        <div class="p-5 rounded-xl bg-amber-50 border-2 border-amber-300 shadow-sm space-y-2.5">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-amber-600 text-white flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <div class="flex-1">
                    <span class="badge badge-warning text-xs font-bold uppercase">
                        <i class="fa-solid fa-triangle-exclamation"></i> KONDISI MINOR — PERLU SERVICE
                    </span>
                    <h2 class="text-lg font-bold text-amber-950 mt-1">Armada Perlu Perbaikan Berkala</h2>
                    <p class="text-xs text-amber-800 mt-0.5 leading-relaxed">
                        Ditemukan item yang perlu perhatian atau cacat non-kritis. Kendaraan masih dapat beroperasi terbatas namun tercatat di jadwal perawatan.
                    </p>
                </div>
            </div>

            <?php if (!empty($evalResult['reasons'])): ?>
                <div class="mt-2 p-3 bg-white border border-amber-200 rounded-lg text-xs space-y-1">
                    <div class="font-bold text-amber-900 uppercase text-[10px] tracking-wider">Temuan Perhatian / Minor:</div>
                    <ul class="list-disc list-inside text-amber-700 font-semibold space-y-0.5">
                        <?php foreach ($evalResult['reasons'] as $r): ?>
                            <li><?= htmlspecialchars($r) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="p-5 rounded-xl bg-emerald-50 border-2 border-emerald-300 shadow-sm space-y-2.5">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="flex-1">
                    <span class="badge badge-success text-xs font-bold uppercase">
                        <i class="fa-solid fa-shield-halved"></i> KONDISI BAIK — LAYAK JALAN
                    </span>
                    <h2 class="text-lg font-bold text-emerald-950 mt-1">Armada Siap Beroperasi Normal</h2>
                    <p class="text-xs text-emerald-800 mt-0.5 leading-relaxed">
                        Seluruh 16 item pemeriksaan dalam kondisi baik dan memenuhi standar keselamatan armada operasional.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Detailed Breakdown by 4 Categories -->
    <div class="space-y-3.5">
        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
            <i class="fa-solid fa-list-check text-blue-700"></i> Rekapitulasi Seluruh 4 Kategori Checklist
        </h3>

        <?php 
            $catMeta = [
                'eksterior' => ['title' => 'A. Eksterior & Keliling', 'icon' => 'fa-car-side', 'edit_url' => 'inspeksi_eksterior.php'],
                'interior_kelistrikan' => ['title' => 'B. Interior & Kelistrikan', 'icon' => 'fa-gauge-high', 'edit_url' => 'inspeksi_interior.php'],
                'mesin' => ['title' => 'C. Area Mesin', 'icon' => 'fa-oil-can', 'edit_url' => 'inspeksi_mesin.php'],
                'kaki_kaki' => ['title' => 'D. Kaki-kaki & Ban Serep', 'icon' => 'fa-circle-dot', 'edit_url' => 'inspeksi_kakikaki.php']
            ];
        ?>

        <?php foreach ($detailsByCat as $catKey => $itemList): ?>
            <?php $meta = $catMeta[$catKey]; ?>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-2.5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <div class="font-bold text-xs sm:text-sm text-slate-900 flex items-center gap-1.5">
                        <i class="fa-solid <?= $meta['icon'] ?> text-blue-700"></i>
                        <span><?= $meta['title'] ?></span>
                    </div>
                    <a href="<?= BASE_URL ?>/public/<?= $meta['edit_url'] ?>?inspeksi_id=<?= $inspeksiId ?>" class="text-[11px] font-semibold text-blue-700 hover:underline flex items-center gap-1">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($itemList as $it): ?>
                        <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg space-y-1.5">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-xs font-semibold text-slate-800 leading-snug"><?= htmlspecialchars($it['item_nama']) ?></span>
                                <div>
                                    <?php if ($it['kondisi'] === 'ok'): ?>
                                        <span class="badge badge-success text-[9px]">OK</span>
                                    <?php elseif ($it['kondisi'] === 'perlu_perhatian'): ?>
                                        <span class="badge badge-warning text-[9px]">Perhatian</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger text-[9px]">Rusak</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($it['catatan'])): ?>
                                <p class="text-[11px] text-slate-500 italic">"<?= htmlspecialchars($it['catatan']) ?>"</p>
                            <?php endif; ?>

                            <?php if (!empty($it['foto_path']) && file_exists(BASE_PATH . '/' . $it['foto_path'])): ?>
                                <div class="pt-0.5">
                                    <a href="<?= BASE_URL . '/' . $it['foto_path'] ?>" target="_blank" class="inline-flex items-center gap-1 text-[10px] text-blue-700 hover:underline font-semibold">
                                        <i class="fa-solid fa-image"></i> Lihat Foto Bukti
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Final Confirmation Form -->
    <form method="POST" action="" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-3.5">
        <input type="hidden" name="action" value="confirm_inspection">

        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5 border-b border-slate-100 pb-2">
            <i class="fa-solid fa-signature text-blue-700"></i> Konfirmasi & Catatan Petugas Inspeksi
        </h3>

        <!-- Override Selector -->
        <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-2">
            <label class="block text-xs font-semibold text-slate-700">
                Penentuan Status Akhir Kendaraan:
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 text-xs font-semibold">
                <label class="cursor-pointer">
                    <input type="radio" name="hasil_override" value="auto" checked class="peer hidden">
                    <div class="p-2 rounded-lg border border-slate-300 bg-white peer-checked:border-blue-700 peer-checked:bg-blue-50 peer-checked:text-blue-700 text-slate-600 text-center transition-all">
                        Otomatis (<?= strtoupper($evalResult['hasil']) ?>)
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="hasil_override" value="ok" class="peer hidden">
                    <div class="p-2 rounded-lg border border-slate-300 bg-white peer-checked:border-emerald-700 peer-checked:bg-emerald-50 peer-checked:text-emerald-700 text-slate-600 text-center transition-all">
                        Override: OK (Layak)
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="hasil_override" value="minor" class="peer hidden">
                    <div class="p-2 rounded-lg border border-slate-300 bg-white peer-checked:border-amber-700 peer-checked:bg-amber-50 peer-checked:text-amber-700 text-slate-600 text-center transition-all">
                        Override: Minor
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="hasil_override" value="mayor" class="peer hidden">
                    <div class="p-2 rounded-lg border border-slate-300 bg-white peer-checked:border-rose-700 peer-checked:bg-rose-50 peer-checked:text-rose-700 text-slate-600 text-center transition-all">
                        Override: STOP (Mayor)
                    </div>
                </label>
            </div>
            <p class="text-[10px] text-slate-500">Anda dapat mengubah hasil evaluasi jika terdapat pertimbangan teknis khusus di lapangan.</p>
        </div>

        <!-- General Notes -->
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Kesimpulan / Rekomendasi Umum Petugas</label>
            <textarea name="catatan_umum" rows="3" placeholder="Masukkan catatan kesimpulan atau instruksi tindak lanjut armada..."
                      class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:border-blue-600 outline-none leading-relaxed"></textarea>
        </div>

        <!-- Submit Buttons -->
        <div class="pt-2 flex items-center justify-between gap-3 border-t border-slate-100">
            <a href="<?= BASE_URL ?>/public/inspeksi_kakikaki.php?inspeksi_id=<?= $inspeksiId ?>" class="btn-secondary text-xs py-2.5 px-4">
                <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Tahap 4
            </a>
            <button type="submit" class="btn-primary flex-1 py-2.5 text-xs sm:text-sm font-bold">
                <i class="fa-solid fa-check-double mr-1"></i> Konfirmasi & Selesaikan Inspeksi
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>
