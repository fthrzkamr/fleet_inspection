<?php
/**
 * Halaman Dashboard Rekapitulasi & Analitik Armada (Admin & PIC) - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin', 'pic']);

$pdo = get_db();
$user = current_user();

// Batasi data ke cabang sendiri untuk role petugas/pic yang sudah di-set cabang-nya.
// Admin (dan petugas/pic yang belum punya cabang) tetap melihat semua data.
$scopeCabang = (in_array($user['role'], ['petugas', 'pic']) && !empty($user['cabang'])) ? $user['cabang'] : null;

// 1. Stat Counters Utama
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM kendaraan" . ($scopeCabang ? " WHERE cabang = :cb" : ""));
$stmtTotal->execute($scopeCabang ? [':cb' => $scopeCabang] : []);
$totalKendaraan = (int)$stmtTotal->fetchColumn();

$stmtActive = $pdo->prepare("SELECT COUNT(*) FROM kendaraan WHERE status = 'active'" . ($scopeCabang ? " AND cabang = :cb" : ""));
$stmtActive->execute($scopeCabang ? [':cb' => $scopeCabang] : []);
$countActive = (int)$stmtActive->fetchColumn();

// Sesi Inspeksi Hari Ini
$sqlInspeksiHariIni = "SELECT COUNT(*) FROM inspeksi i JOIN kendaraan k ON i.kendaraan_id = k.id
                       WHERE DATE(i.tanggal_mulai) = CURDATE() AND i.tanggal_selesai IS NOT NULL"
                       . ($scopeCabang ? " AND k.cabang = :cb" : "");
$stmtInspeksiHariIni = $pdo->prepare($sqlInspeksiHariIni);
$stmtInspeksiHariIni->execute($scopeCabang ? [':cb' => $scopeCabang] : []);
$inspeksiHariIni = (int)$stmtInspeksiHariIni->fetchColumn();

// Status Kondisi Terkini Armada Aktif (Berdasarkan hasil inspeksi terakhir)
$sqlArmadaStatus = "SELECT k.id, k.asset_id, k.no_polisi, k.merk, k.model, k.cabang, k.status, k.tgl_stnk_expired, k.tgl_kir_expired,
                    i.id as last_inspeksi_id, i.hasil_akhir as last_hasil, i.tanggal_selesai as last_tgl
                    FROM kendaraan k
                    LEFT JOIN inspeksi i ON i.id = (
                        SELECT id FROM inspeksi
                        WHERE kendaraan_id = k.id AND tanggal_selesai IS NOT NULL
                        ORDER BY id DESC LIMIT 1
                    )
                    WHERE k.status != 'inactive'" . ($scopeCabang ? " AND k.cabang = :cb" : "") . "
                    ORDER BY
                    CASE
                        WHEN i.hasil_akhir = 'mayor' THEN 1
                        WHEN i.hasil_akhir = 'minor' THEN 2
                        ELSE 3
                    END, k.id ASC";
$stmtArmadaStatus = $pdo->prepare($sqlArmadaStatus);
$stmtArmadaStatus->execute($scopeCabang ? [':cb' => $scopeCabang] : []);
$armadaList = $stmtArmadaStatus->fetchAll();

$countStop = 0;
$countMinor = 0;
$countReady = 0;

foreach ($armadaList as $a) {
    if ($a['last_hasil'] === 'mayor') {
        $countStop++;
    } elseif ($a['last_hasil'] === 'minor') {
        $countMinor++;
    } else {
        $countReady++;
    }
}

// 1b. Versi ber-pagination & bisa dicari dari tabel yang sama, khusus untuk ditampilkan di tabel
$tableSearch = trim($_GET['q'] ?? '');
$tableWhere = " WHERE k.status != 'inactive'";
$tableParams = [];
if (!empty($tableSearch)) {
    $tableWhere .= " AND (k.no_polisi LIKE :q1 OR k.asset_id LIKE :q2 OR k.cabang LIKE :q3 OR k.merk LIKE :q4 OR k.model LIKE :q5)";
    $tableParams[':q1'] = $tableParams[':q2'] = $tableParams[':q3'] = $tableParams[':q4'] = $tableParams[':q5'] = "%$tableSearch%";
}
if ($scopeCabang) {
    $tableWhere .= " AND k.cabang = :scopeCb";
    $tableParams[':scopeCb'] = $scopeCabang;
}

$countTableStmt = $pdo->prepare("SELECT COUNT(*) FROM kendaraan k $tableWhere");
$countTableStmt->execute($tableParams);
$totalTableRows = (int)$countTableStmt->fetchColumn();

$tablePagination = get_pagination_params(10);

$sqlArmadaTable = "SELECT k.id, k.asset_id, k.no_polisi, k.merk, k.model, k.cabang, k.status, k.tgl_stnk_expired, k.tgl_kir_expired,
                    i.id as last_inspeksi_id, i.hasil_akhir as last_hasil, i.tanggal_selesai as last_tgl
                    FROM kendaraan k
                    LEFT JOIN inspeksi i ON i.id = (
                        SELECT id FROM inspeksi
                        WHERE kendaraan_id = k.id AND tanggal_selesai IS NOT NULL
                        ORDER BY id DESC LIMIT 1
                    )
                    $tableWhere
                    ORDER BY
                    CASE
                        WHEN i.hasil_akhir = 'mayor' THEN 1
                        WHEN i.hasil_akhir = 'minor' THEN 2
                        ELSE 3
                    END, k.id ASC
                    LIMIT " . (int)$tablePagination['offset'] . ", " . (int)$tablePagination['per_page'];
$stmtArmadaTable = $pdo->prepare($sqlArmadaTable);
$stmtArmadaTable->execute($tableParams);
$armadaTableList = $stmtArmadaTable->fetchAll();

// 2. Reminder Dokumen (STNK, KIR & Plat/TNKB expired dalam 2 bulan ke depan atau sudah lewat)
define('REMINDER_LEAD_MONTHS', 2);
// Ambang batas dalam jumlah hari, dihitung persis sama seperti "2 bulan kalender" di query SQL
// di bawah (bukan angka 60 tetap), supaya badge yang ditampilkan selalu konsisten dengan baris
// yang lolos filter SQL-nya.
$reminderThresholdDate = (new DateTime('today'))->modify('+' . REMINDER_LEAD_MONTHS . ' months');
$reminderLeadDays = (int)(new DateTime('today'))->diff($reminderThresholdDate)->days;
$sqlReminder = "SELECT id, asset_id, no_polisi, merk, model, cabang, tgl_stnk_expired, tgl_kir_expired, tgl_plat_expired,
                DATEDIFF(tgl_stnk_expired, CURDATE()) as days_stnk,
                DATEDIFF(tgl_kir_expired, CURDATE()) as days_kir,
                DATEDIFF(tgl_plat_expired, CURDATE()) as days_plat
                FROM kendaraan
                WHERE status = 'active' AND (
                    (tgl_stnk_expired IS NOT NULL AND tgl_stnk_expired <= DATE_ADD(CURDATE(), INTERVAL " . REMINDER_LEAD_MONTHS . " MONTH))
                    OR (tgl_kir_expired IS NOT NULL AND tgl_kir_expired <= DATE_ADD(CURDATE(), INTERVAL " . REMINDER_LEAD_MONTHS . " MONTH))
                    OR (tgl_plat_expired IS NOT NULL AND tgl_plat_expired <= DATE_ADD(CURDATE(), INTERVAL " . REMINDER_LEAD_MONTHS . " MONTH))
                )" . ($scopeCabang ? " AND cabang = :cb" : "") . "
                ORDER BY LEAST(
                    COALESCE(tgl_stnk_expired, '9999-12-31'),
                    COALESCE(tgl_kir_expired, '9999-12-31'),
                    COALESCE(tgl_plat_expired, '9999-12-31')
                ) ASC";
$stmtReminder = $pdo->prepare($sqlReminder);
$stmtReminder->execute($scopeCabang ? [':cb' => $scopeCabang] : []);
$reminders = $stmtReminder->fetchAll();

// 2b. Reminder Servis Berkala berdasarkan KM (Motor per 3000 KM, Mobil per 5000 KM, mulai
// diingatkan 1000 KM sebelum jatuh tempo). Hanya kendaraan yang SUDAH PERNAH tercatat KM
// servis terakhirnya yang dicek di sini — kalau belum pernah servis / KM-nya kosong, kendaraan
// itu sengaja dilewati saja (tidak dianggap 0 KM), sesuai arahan.
define('SERVIS_KM_DUE_MOTOR', 3000);
define('SERVIS_KM_DUE_MOBIL', 5000);
define('SERVIS_KM_REMINDER_BUFFER', 1000);

$sqlServisKm = "SELECT k.id, k.asset_id, k.no_polisi, k.merk, k.model, k.cabang, k.jenis_kendaraan,
                (SELECT km_servis FROM riwayat_servis WHERE kendaraan_id = k.id AND km_servis IS NOT NULL ORDER BY tanggal_servis DESC, id DESC LIMIT 1) as last_km_servis,
                (SELECT tanggal_servis FROM riwayat_servis WHERE kendaraan_id = k.id AND km_servis IS NOT NULL ORDER BY tanggal_servis DESC, id DESC LIMIT 1) as last_tgl_servis,
                (SELECT odometer FROM inspeksi WHERE kendaraan_id = k.id AND odometer IS NOT NULL AND tanggal_selesai IS NOT NULL ORDER BY tanggal_mulai DESC LIMIT 1) as current_km,
                (SELECT tanggal_mulai FROM inspeksi WHERE kendaraan_id = k.id AND odometer IS NOT NULL AND tanggal_selesai IS NOT NULL ORDER BY tanggal_mulai DESC LIMIT 1) as current_km_tgl
                FROM kendaraan k
                WHERE k.status = 'active'
                AND EXISTS (SELECT 1 FROM riwayat_servis rs WHERE rs.kendaraan_id = k.id AND rs.km_servis IS NOT NULL)
                AND EXISTS (SELECT 1 FROM inspeksi ins WHERE ins.kendaraan_id = k.id AND ins.odometer IS NOT NULL AND ins.tanggal_selesai IS NOT NULL)
                " . ($scopeCabang ? " AND k.cabang = :cb" : "");
$stmtServisKm = $pdo->prepare($sqlServisKm);
$stmtServisKm->execute($scopeCabang ? [':cb' => $scopeCabang] : []);
$servisKmCandidates = $stmtServisKm->fetchAll();

$servisKmReminders = [];
foreach ($servisKmCandidates as $c) {
    // KM sekarang harus dari bacaan SETELAH servis terakhir, kalau tidak berarti belum ada
    // data odometer yang lebih baru dari servis terakhirnya (tidak bisa dihitung selisihnya).
    if ($c['current_km_tgl'] < $c['last_tgl_servis']) continue;

    $selisihKm = (int)$c['current_km'] - (int)$c['last_km_servis'];
    if ($selisihKm < 0) continue;

    $kmDue = ($c['jenis_kendaraan'] === 'Motor') ? SERVIS_KM_DUE_MOTOR : SERVIS_KM_DUE_MOBIL;
    $kmReminderStart = $kmDue - SERVIS_KM_REMINDER_BUFFER;

    if ($selisihKm >= $kmReminderStart) {
        $c['selisih_km'] = $selisihKm;
        $c['km_due'] = $kmDue;
        $c['km_sisa'] = $kmDue - $selisihKm; // negatif = sudah lewat sekian KM
        $servisKmReminders[] = $c;
    }
}
// Urutkan yang paling mendesak (KM sisa paling sedikit / paling lewat) di atas
usort($servisKmReminders, fn($a, $b) => $a['km_sisa'] <=> $b['km_sisa']);

// 3. Data Dokumen Belum Lengkap (STNK / KIR / Plat kosong, terpisah dari reminder jatuh tempo)
$sqlIncomplete = "SELECT
                    SUM(tgl_stnk_expired IS NULL) as kosong_stnk,
                    SUM(tgl_kir_expired IS NULL) as kosong_kir,
                    SUM(tgl_plat_expired IS NULL) as kosong_plat,
                    SUM(tgl_stnk_expired IS NULL OR tgl_kir_expired IS NULL OR tgl_plat_expired IS NULL) as total_kendaraan
                  FROM kendaraan WHERE status = 'active'" . ($scopeCabang ? " AND cabang = :cb" : "");
$stmtIncomplete = $pdo->prepare($sqlIncomplete);
$stmtIncomplete->execute($scopeCabang ? [':cb' => $scopeCabang] : []);
$incompleteDocs = $stmtIncomplete->fetch();

// Jika request AJAX (pagination/ganti tampilan/cari tanpa reload halaman penuh),
// langsung render fragment tabelnya saja lalu berhenti — tidak perlu header/navbar/dsb.
$isAjax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if ($isAjax) {
    require __DIR__ . '/../includes/partial_dashboard_table.php';
    exit;
}

$pageTitle = 'Dashboard Ringkasan Armada';
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<!-- Include Chart.js via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="space-y-6">
    <!-- Header Title & Quick Action Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-blue-700"></i> Dashboard Rekapitulasi Armada
            </h1>
            <p class="text-slate-500 text-xs mt-0.5">Monitoring kondisi fisik kendaraan, alert status STOP, masa berlaku dokumen & tren inspeksi.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/public/scan.php" class="btn-primary text-xs py-2 px-3.5">
                <i class="fa-solid fa-qrcode"></i> Scan QR Lapangan
            </a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="<?= BASE_URL ?>/public/daftar_kendaraan.php" class="btn-secondary text-xs py-2 px-3">
                    <i class="fa-solid fa-truck"></i> Database Armada
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stat KPI Cards Section -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <!-- 1. Total Armada Aktif -->
        <div class="stat-card">
            <div class="flex items-center justify-between text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                <span>Armada Aktif</span>
                <i class="fa-solid fa-truck text-blue-600"></i>
            </div>
            <div class="text-2xl font-black text-slate-900 mt-2"><?= $countActive ?> <span class="text-xs font-normal text-slate-500">Unit</span></div>
            <div class="text-[11px] text-slate-500 mt-1 border-t border-slate-100 pt-1.5">Total Terdaftar: <?= $totalKendaraan ?> Unit</div>
        </div>

        <!-- 2. Armada Status STOP (Mayor) -->
        <div class="stat-card <?= $countStop > 0 ? 'bg-rose-50 border-rose-200' : '' ?>">
            <div class="flex items-center justify-between text-rose-700 text-[11px] font-bold uppercase tracking-wider">
                <span class="flex items-center gap-1.5">
                    <?php if ($countStop > 0): ?>
                        <span class="w-2 h-2 rounded-full bg-rose-600"></span>
                    <?php endif; ?>
                    Status STOP (Mayor)
                </span>
                <i class="fa-solid fa-hand text-rose-600"></i>
            </div>
            <div class="text-2xl font-black text-rose-700 mt-2"><?= $countStop ?> <span class="text-xs font-normal text-rose-500">Unit</span></div>
            <div class="text-[11px] <?= $countStop > 0 ? 'text-rose-700 font-bold' : 'text-slate-500' ?> mt-1 border-t border-slate-100 pt-1.5">
                <?= $countStop > 0 ? 'Dilarang Beroperasi' : 'Tidak ada temuan kritis' ?>
            </div>
        </div>

        <!-- 3. Armada Minor Service -->
        <div class="stat-card <?= $countMinor > 0 ? 'bg-amber-50 border-amber-200' : '' ?>">
            <div class="flex items-center justify-between text-amber-800 text-[11px] font-bold uppercase tracking-wider">
                <span>Minor Service</span>
                <i class="fa-solid fa-wrench text-amber-600"></i>
            </div>
            <div class="text-2xl font-black text-amber-800 mt-2"><?= $countMinor ?> <span class="text-xs font-normal text-amber-600">Unit</span></div>
            <div class="text-[11px] text-slate-500 mt-1 border-t border-slate-100 pt-1.5">Perlu Perawatan Berkala</div>
        </div>

        <!-- 4. Kesiapan Operasional (OK) -->
        <div class="stat-card bg-emerald-50 border-emerald-200">
            <div class="flex items-center justify-between text-emerald-800 text-[11px] font-bold uppercase tracking-wider">
                <span>Siap Jalan (OK)</span>
                <i class="fa-solid fa-circle-check text-emerald-600"></i>
            </div>
            <div class="text-2xl font-black text-emerald-800 mt-2"><?= $countReady ?> <span class="text-xs font-normal text-emerald-600">Unit</span></div>
            <div class="text-[11px] text-slate-600 mt-1 border-t border-slate-100 pt-1.5"><?= $inspeksiHariIni ?> Sesi Inspeksi Hari Ini</div>
        </div>
    </div>

    <!-- Alert Box: Document Expiry Reminder (STNK & KIR <= 2 Bulan) -->
    <?php if (!empty($reminders)): ?>
        <div class="bg-white border border-amber-200 rounded-xl p-4 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-amber-100 pb-2">
                <div class="font-bold text-xs text-amber-900 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                    <span>Peringatan Dokumen Jatuh Tempo (&le; 2 Bulan)</span>
                </div>
                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300"><?= count($reminders) ?> Kendaraan</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                <?php foreach ($reminders as $r): ?>
                    <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg flex items-start justify-between gap-2 text-xs">
                        <div>
                            <div class="font-bold text-slate-900"><?= htmlspecialchars($r['no_polisi']) ?> <span class="font-mono text-blue-700 text-[11px]">(<?= htmlspecialchars($r['asset_id']) ?>)</span></div>
                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($r['merk']) ?> &bull; <?= htmlspecialchars($r['cabang']) ?></div>
                        </div>

                        <div class="text-right space-y-1">
                            <?php if ($r['tgl_stnk_expired'] && $r['days_stnk'] <= $reminderLeadDays): ?>
                                <div>
                                    <span class="text-[10px] font-semibold text-slate-500">STNK:</span>
                                    <span class="badge <?= $r['days_stnk'] < 0 ? 'badge-danger' : 'badge-warning' ?> text-[10px]">
                                        <?= $r['days_stnk'] < 0 ? 'Lewat ' . abs($r['days_stnk']) . 'hr' : 'Sisa ' . $r['days_stnk'] . 'hr' ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if ($r['tgl_kir_expired'] && $r['days_kir'] <= $reminderLeadDays): ?>
                                <div>
                                    <span class="text-[10px] font-semibold text-slate-500">KIR:</span>
                                    <span class="badge <?= $r['days_kir'] < 0 ? 'badge-danger' : 'badge-warning' ?> text-[10px]">
                                        <?= $r['days_kir'] < 0 ? 'Lewat ' . abs($r['days_kir']) . 'hr' : 'Sisa ' . $r['days_kir'] . 'hr' ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if ($r['tgl_plat_expired'] && $r['days_plat'] <= $reminderLeadDays): ?>
                                <div>
                                    <span class="text-[10px] font-semibold text-slate-500">Plat:</span>
                                    <span class="badge <?= $r['days_plat'] < 0 ? 'badge-danger' : 'badge-warning' ?> text-[10px]">
                                        <?= $r['days_plat'] < 0 ? 'Lewat ' . abs($r['days_plat']) . 'hr' : 'Sisa ' . $r['days_plat'] . 'hr' ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Alert Box: Reminder Servis Berkala berdasarkan KM (section selalu tampil, walau kosong) -->
    <div class="bg-white border border-amber-200 rounded-xl p-4 shadow-sm space-y-3">
        <div class="flex items-center justify-between <?= !empty($servisKmReminders) ? 'border-b border-amber-100 pb-2' : '' ?>">
            <div class="font-bold text-xs text-amber-900 flex items-center gap-2">
                <i class="fa-solid fa-oil-can text-amber-600"></i>
                <span>Reminder Servis Berkala (Berdasarkan KM)</span>
            </div>
            <?php if (!empty($servisKmReminders)): ?>
                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300"><?= count($servisKmReminders) ?> Kendaraan</span>
            <?php endif; ?>
        </div>

        <?php if (empty($servisKmReminders)): ?>
            <div class="text-[11px] text-slate-500 flex items-center gap-1.5">
                <i class="fa-solid fa-circle-check text-emerald-600"></i>
                Belum ada kendaraan yang perlu diingatkan servis saat ini.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                <?php foreach ($servisKmReminders as $r): ?>
                    <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg flex items-start justify-between gap-2 text-xs">
                        <div>
                            <div class="font-bold text-slate-900"><?= htmlspecialchars($r['no_polisi']) ?> <span class="font-mono text-blue-700 text-[11px]">(<?= htmlspecialchars($r['asset_id']) ?>)</span></div>
                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($r['merk']) ?> &bull; <?= htmlspecialchars($r['cabang']) ?></div>
                            <div class="text-[10px] text-slate-400 mt-0.5">
                                Servis terakhir: <?= number_format((int)$r['last_km_servis'], 0, ',', '.') ?> KM (<?= format_date_id($r['last_tgl_servis'], false) ?>)<br>
                                KM sekarang: <?= number_format((int)$r['current_km'], 0, ',', '.') ?> KM (<?= format_date_id($r['current_km_tgl'], false) ?>)
                            </div>
                        </div>

                        <div class="text-right flex-shrink-0">
                            <span class="badge <?= $r['km_sisa'] < 0 ? 'badge-danger' : 'badge-warning' ?> text-[10px] whitespace-nowrap">
                                <?= $r['km_sisa'] < 0 ? 'Lewat ' . number_format(abs($r['km_sisa']), 0, ',', '.') . ' KM' : 'Sisa ' . number_format($r['km_sisa'], 0, ',', '.') . ' KM' ?>
                            </span>
                            <div class="text-[10px] text-slate-400 mt-1">Interval <?= number_format((int)$r['km_due'], 0, ',', '.') ?> KM</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Alert Box: Data Dokumen Belum Lengkap (STNK/KIR/Plat kosong) -->
    <?php if ((int)$incompleteDocs['total_kendaraan'] > 0): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start gap-2.5">
                <i class="fa-solid fa-circle-info text-blue-600 mt-0.5"></i>
                <div>
                    <div class="font-bold text-xs text-blue-900">
                        <?= (int)$incompleteDocs['total_kendaraan'] ?> Kendaraan Punya Data Dokumen Belum Lengkap
                    </div>
                    <div class="text-[11px] text-blue-800 mt-0.5">
                        Kosong: STNK <b><?= (int)$incompleteDocs['kosong_stnk'] ?></b> unit &bull;
                        KIR <b><?= (int)$incompleteDocs['kosong_kir'] ?></b> unit &bull;
                        Plat (TNKB) <b><?= (int)$incompleteDocs['kosong_plat'] ?></b> unit.
                        Kendaraan dengan tanggal kosong <u>tidak</u> ikut muncul di peringatan jatuh tempo di atas.
                    </div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/public/daftar_kendaraan.php" class="btn-secondary text-xs py-1.5 px-3 whitespace-nowrap flex-shrink-0">
                <i class="fa-solid fa-pen-to-square mr-1"></i> Lengkapi di Kelola Armada
            </a>
        </div>
    <?php endif; ?>

    <!-- Enterprise Analytics Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Chart 1: Kendaraan Paling Sering Minor / Mayor -->
        <div class="lg:col-span-7 bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-3">
            <div class="border-b border-slate-100 pb-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-chart-simple text-blue-700"></i> Kendaraan dengan Riwayat Kendala Terbanyak
                </h3>
                <p class="text-[11px] text-slate-500">Akumulasi riwayat temuan perbaikan (Mayor & Minor) per unit</p>
            </div>
            <div class="relative h-64 w-full">
                <canvas id="chartTopIssues"></canvas>
            </div>
        </div>

        <!-- Chart 2: Proporsi Status Armada -->
        <div class="lg:col-span-5 bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-3">
            <div class="border-b border-slate-100 pb-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-chart-pie text-emerald-700"></i> Status Kesiapan Fisik Armada
                </h3>
                <p class="text-[11px] text-slate-500">Distribusi kelayakan operasional kendaraan aktif saat ini</p>
            </div>
            <div class="relative h-64 w-full flex items-center justify-center">
                <canvas id="chartDistribution"></canvas>
            </div>
        </div>

        <!-- Chart 3: Tren Inspeksi Bulanan -->
        <div class="lg:col-span-12 bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-3">
            <div class="border-b border-slate-100 pb-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-chart-line text-indigo-700"></i> Tren Frekuensi & Hasil Inspeksi Bulanan
                </h3>
                <p class="text-[11px] text-slate-500">Statistik hasil inspeksi (OK, Minor Service, Mayor STOP) 6 bulan terakhir</p>
            </div>
            <div class="relative h-60 w-full">
                <canvas id="chartTrends"></canvas>
            </div>
        </div>
    </div>

    <!-- Live Fleet Operational Status Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden p-5 space-y-3">
        <div id="ajax-table-wrap" class="space-y-3">
            <?php require __DIR__ . '/../includes/partial_dashboard_table.php'; ?>
        </div>
    </div>

<!-- Script Inisialisasi Chart.js - Enterprise Colors -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('<?= BASE_URL ?>/public/api_chart_data.php')
        .then(res => res.json())
        .then(response => {
            if (response.status === 'success') {
                const data = response.data;
                renderTopIssuesChart(data.top_issues);
                renderDistributionChart(data.distribution);
                renderTrendsChart(data.trends);
            }
        })
        .catch(err => console.error('Error fetching chart data:', err));
});

function renderTopIssuesChart(topData) {
    const ctx = document.getElementById('chartTopIssues').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: topData.labels.length ? topData.labels : ['Belum ada catatan'],
            datasets: [
                {
                    label: 'Mayor / STOP',
                    data: topData.mayor.length ? topData.mayor : [0],
                    backgroundColor: '#dc2626',
                    borderRadius: 4
                },
                {
                    label: 'Minor Service',
                    data: topData.minor.length ? topData.minor : [0],
                    backgroundColor: '#d97706',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#334155', font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' } }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#64748b', font: { size: 11 } },
                    grid: { color: '#f1f5f9' }
                },
                y: {
                    ticks: { color: '#64748b', stepSize: 1 },
                    grid: { color: '#f1f5f9' }
                }
            }
        }
    });
}

function renderDistributionChart(distData) {
    const ctx = document.getElementById('chartDistribution').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Siap Jalan (OK)', 'Minor Service', 'STOP (Mayor)'],
            datasets: [{
                data: [distData.ready, distData.service, distData.stop],
                backgroundColor: ['#059669', '#d97706', '#dc2626'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#334155', font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' }, padding: 15 }
                }
            },
            cutout: '65%'
        }
    });
}

function renderTrendsChart(trendData) {
    const ctx = document.getElementById('chartTrends').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: trendData.labels,
            datasets: [
                {
                    label: 'Hasil OK',
                    data: trendData.ok,
                    backgroundColor: '#059669',
                    borderRadius: 4
                },
                {
                    label: 'Hasil Minor',
                    data: trendData.minor,
                    backgroundColor: '#d97706',
                    borderRadius: 4
                },
                {
                    label: 'Hasil Mayor (STOP)',
                    data: trendData.mayor,
                    backgroundColor: '#dc2626',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#334155', font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' } }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#64748b' },
                    grid: { color: '#f1f5f9' }
                },
                y: {
                    ticks: { color: '#64748b', stepSize: 1 },
                    grid: { color: '#f1f5f9' }
                }
            }
        }
    });
}

</script>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>
