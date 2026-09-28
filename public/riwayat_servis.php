<?php
/**
 * Halaman Riwayat Servis / Perawatan Kendaraan (Admin) - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin', 'pic']);

$pdo = get_db();
$user = current_user();

// Handle Tambah Servis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_servis') {
    $kendaraanId = (int)($_POST['kendaraan_id'] ?? 0);
    $proyek      = trim($_POST['proyek'] ?? '');
    $tanggal     = trim($_POST['tanggal_servis'] ?? '');
    $km          = trim($_POST['km_servis'] ?? '');
    $nominal     = trim($_POST['nominal'] ?? '');
    $jenis       = trim($_POST['jenis_servis'] ?? '');
    $bengkel     = trim($_POST['bengkel'] ?? '');
    $catatanBengkel = trim($_POST['catatan_bengkel'] ?? '');
    $keterangan  = trim($_POST['keterangan'] ?? '');
    $belumDiganti = trim($_POST['belum_diganti'] ?? '');

    if (empty($tanggal) || empty($jenis)) {
        flash_set('error', 'Tanggal dan jenis servis wajib diisi.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO riwayat_servis (kendaraan_id, proyek, tanggal_servis, km_servis, nominal, jenis_servis, bengkel, catatan_bengkel, keterangan, belum_diganti, oleh_user_id)
                               VALUES (:kid, :proyek, :tgl, :km, :nominal, :jenis, :bengkel, :catbengkel, :ket, :belum, :uid)");
        $stmt->execute([
            ':kid'        => $kendaraanId > 0 ? $kendaraanId : null,
            ':proyek'     => $proyek ?: null,
            ':tgl'        => $tanggal,
            ':km'         => $km !== '' ? (int)$km : null,
            ':nominal'    => $nominal !== '' ? (int)str_replace(['.', ','], '', $nominal) : null,
            ':jenis'      => $jenis,
            ':bengkel'    => $bengkel ?: null,
            ':catbengkel' => $catatanBengkel ?: null,
            ':ket'        => $keterangan ?: null,
            ':belum'      => $belumDiganti ?: null,
            ':uid'        => $user['id']
        ]);
        flash_set('success', 'Riwayat servis berhasil dicatat.');
        session_write_close();
        header('Location: ' . BASE_URL . '/public/riwayat_servis.php');
        exit;
    }
}

// Handle Update Servis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_servis') {
    $id          = (int)($_POST['id'] ?? 0);
    $kendaraanId = (int)($_POST['kendaraan_id'] ?? 0);
    $proyek      = trim($_POST['proyek'] ?? '');
    $tanggal     = trim($_POST['tanggal_servis'] ?? '');
    $km          = trim($_POST['km_servis'] ?? '');
    $nominal     = trim($_POST['nominal'] ?? '');
    $jenis       = trim($_POST['jenis_servis'] ?? '');
    $bengkel     = trim($_POST['bengkel'] ?? '');
    $catatanBengkel = trim($_POST['catatan_bengkel'] ?? '');
    $keterangan  = trim($_POST['keterangan'] ?? '');
    $belumDiganti = trim($_POST['belum_diganti'] ?? '');

    if ($id <= 0 || empty($tanggal) || empty($jenis)) {
        flash_set('error', 'Data tidak valid.');
    } else {
        $stmt = $pdo->prepare("UPDATE riwayat_servis SET kendaraan_id = :kid, proyek = :proyek, tanggal_servis = :tgl,
                               km_servis = :km, nominal = :nominal, jenis_servis = :jenis, bengkel = :bengkel,
                               catatan_bengkel = :catbengkel, keterangan = :ket, belum_diganti = :belum WHERE id = :id");
        $stmt->execute([
            ':kid'        => $kendaraanId > 0 ? $kendaraanId : null,
            ':proyek'     => $proyek ?: null,
            ':tgl'        => $tanggal,
            ':km'         => $km !== '' ? (int)$km : null,
            ':nominal'    => $nominal !== '' ? (int)str_replace(['.', ','], '', $nominal) : null,
            ':jenis'      => $jenis,
            ':bengkel'    => $bengkel ?: null,
            ':catbengkel' => $catatanBengkel ?: null,
            ':ket'        => $keterangan ?: null,
            ':belum'      => $belumDiganti ?: null,
            ':id'         => $id
        ]);
        flash_set('success', 'Riwayat servis berhasil diperbarui.');
        session_write_close();
        header('Location: ' . BASE_URL . '/public/riwayat_servis.php');
        exit;
    }
}

// Handle Hapus Servis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_servis') {
    $id = (int)($_POST['delete_id'] ?? 0);
    $pdo->prepare("DELETE FROM riwayat_servis WHERE id = :id")->execute([':id' => $id]);
    flash_set('success', 'Riwayat servis berhasil dihapus.');
    session_write_close();
    header('Location: ' . BASE_URL . '/public/riwayat_servis.php');
    exit;
}

// Handle Import Riwayat Servis dari CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_servis') {
    $scopeCabangImport = ($user['role'] === 'pic' && !empty($user['cabang'])) ? $user['cabang'] : null;

    if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        flash_set('error', 'Gagal mengunggah file. Pastikan file CSV terpilih.');
        session_write_close();
        header('Location: ' . BASE_URL . '/public/riwayat_servis.php');
        exit;
    }

    $tmpPath = $_FILES['import_file']['tmp_name'];
    $raw = file_get_contents($tmpPath);
    // Buang UTF-8 BOM kalau ada, dan deteksi delimiter (';' dari fitur Export, ',' dari Excel umum)
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $firstLine = strtok($raw, "\r\n");
    $delimiter = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $rows[] = str_getcsv($line, $delimiter);
    }

    if (count($rows) < 2) {
        flash_set('error', 'File CSV kosong atau tidak ada baris data.');
        session_write_close();
        header('Location: ' . BASE_URL . '/public/riwayat_servis.php');
        exit;
    }

    // Alias per kolom (dicek sebagai substring, jadi toleran terhadap teks tambahan
    // seperti "Jenis Kendaraan (Motor/Mobil)" atau salah ketik ringan seperti "Tanngal")
    $colAliases = [
        'no_polisi'    => ['plat nomor', 'plat', 'no polisi', 'nopol'],
        'tanggal'      => ['tanggal', 'tanngal', 'tgl'],
        'jenis_servis' => ['keterangan service', 'keterangan'],
        'cabang'       => ['cabang'],
        'proyek'       => ['proyek'],
        'km_servis'    => ['kilometer service', 'kilometer', 'km servis'],
        'nominal'      => ['nominal'],
        'belum_diganti'    => ['belum diganti', 'belum di ganti', 'belum'],
        'catatan_bengkel'  => ['catatan bengkel', 'catatan'],
        'jenis_kendaraan'  => ['jenis kendaraan'],
        'model'            => ['merk kendaraan', 'jenis/merk', 'merk'],
    ];

    $detectColIndex = function ($headerCells) use ($colAliases) {
        $colIndex = [];
        foreach ($headerCells as $i => $cell) {
            $h = strtolower(trim($cell));
            if ($h === '') continue;
            foreach ($colAliases as $field => $aliases) {
                if (isset($colIndex[$field])) continue;
                foreach ($aliases as $alias) {
                    if (strpos($h, $alias) !== false) {
                        $colIndex[$field] = $i;
                        break;
                    }
                }
            }
        }
        return $colIndex;
    };

    // Cari baris header sesungguhnya di antara 10 baris pertama (file export dari
    // Excel/Python kadang punya baris ringkasan/kosong di atas baris header asli)
    $headerRowNum = null;
    $colIndex = [];
    $scanLimit = min(10, count($rows));
    for ($i = 0; $i < $scanLimit; $i++) {
        $candidate = $detectColIndex($rows[$i]);
        if (isset($candidate['no_polisi'], $candidate['tanggal'], $candidate['jenis_servis'])) {
            $headerRowNum = $i;
            $colIndex = $candidate;
            break;
        }
    }

    if ($headerRowNum === null) {
        flash_set('error', 'Format kolom tidak dikenali. Pastikan ada kolom Tanggal, Plat Nomor, dan Keterangan Service di baris header.');
        session_write_close();
        header('Location: ' . BASE_URL . '/public/riwayat_servis.php');
        exit;
    }

    $get = function ($row, $key) use ($colIndex) {
        return isset($colIndex[$key]) ? trim($row[$colIndex[$key]] ?? '') : '';
    };
    $parseDate = function ($val) {
        $val = trim($val);
        if ($val === '') return null;
        // Buang jam yang menempel di belakang tanggal (mis. "06/02/2026 00:00" dari export
        // Excel/Python yang kolomnya bertipe datetime tapi jamnya selalu tengah malam)
        $val = preg_replace('#\s+\d{1,2}:\d{2}(:\d{2})?\s*$#', '', $val);
        // DD/MM/YYYY (konvensi utama aplikasi ini)
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $val, $m)) {
            if (checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
        }
        // YYYY-MM-DD atau YYYY-MM-DD HH:MM:SS
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $val, $m)) {
            if (checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
            }
        }
        // DD-MM-YYYY
        if (preg_match('#^(\d{1,2})-(\d{1,2})-(\d{4})$#', $val, $m)) {
            if (checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
        }
        // MM/DD/YYYY (gaya Amerika) — hanya dipakai kalau angka pertama tidak valid
        // sebagai hari/bulan DD/MM di atas, supaya "8/26/2026" (jelas M/D) tertangkap
        // tanpa membuka celah salah baca untuk tanggal ambigu seperti "06/02/2026".
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $val, $m)) {
            if (checkdate((int)$m[1], (int)$m[2], (int)$m[3])) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
            }
        }
        // Fallback longgar terakhir (mis. nama bulan "12 Sep 2026")
        $ts = strtotime($val);
        return $ts ? date('Y-m-d', $ts) : null;
    };
    $parseNumber = function ($val) {
        $val = trim(preg_replace('/[^\d,.\-]/', '', $val));
        if ($val === '') return null;
        $val = str_replace(['.', ','], '', $val);
        return $val === '' || $val === '-' ? null : (int)$val;
    };

    $stmtInsert = $pdo->prepare("INSERT INTO riwayat_servis (kendaraan_id, proyek, tanggal_servis, km_servis, nominal, jenis_servis, bengkel, catatan_bengkel, keterangan, belum_diganti, oleh_user_id)
                                 VALUES (:kid, :proyek, :tgl, :km, :nominal, :jenis, :bengkel, :catbengkel, NULL, :belum, :uid)");

    $successCount = 0;
    $duplicateCount = 0;
    $failRows = [];

    for ($r = $headerRowNum + 1; $r < count($rows); $r++) {
        $row = $rows[$r];
        $rowNum = $r + 1; // nomor baris asli di file (termasuk header)

        $noPolisi = strtoupper($get($row, 'no_polisi'));
        $cabangCsv = $get($row, 'cabang');
        $tanggal = $parseDate($get($row, 'tanggal'));
        $jenisServis = $get($row, 'jenis_servis');

        if ($noPolisi === '' && $tanggal === null && $jenisServis === '') continue; // baris kosong, lewati diam-diam

        if ($tanggal === null) {
            $failRows[] = "Baris $rowNum" . ($noPolisi !== '' ? " ($noPolisi)" : '') . ": format Tanggal tidak dikenali";
            continue;
        }
        if ($jenisServis === '') {
            $failRows[] = "Baris $rowNum" . ($noPolisi !== '' ? " ($noPolisi)" : '') . ": Keterangan Service wajib diisi";
            continue;
        }

        // Plat Nomor kosong = entri umum, tidak terkait 1 kendaraan spesifik (mis. beli
        // sparepart untuk beberapa unit sekaligus). Tetap dicatat sebagai riwayat servis,
        // hanya saja tidak tertaut ke kendaraan_id manapun. Khusus admin, karena PIC yang
        // datanya dibatasi per cabang tidak bisa memastikan entri umum ini milik cabangnya.
        if ($noPolisi === '') {
            if ($scopeCabangImport) {
                $failRows[] = "Baris $rowNum: entri umum tanpa Plat Nomor hanya bisa diimpor oleh admin";
                continue;
            }
            $kendaraanId = null;
        } else {
            // Cari kendaraan berdasarkan plat nomor dulu (plat biasanya unik). Cabang dari CSV
            // hanya dipakai sebagai penentu tambahan kalau ternyata platnya kembar, karena nama
            // cabang di file sumber tidak selalu persis sama dengan nama cabang di sistem.
            $stmtFind = $pdo->prepare("SELECT id, cabang FROM kendaraan WHERE no_polisi = :nopol");
            $stmtFind->execute([':nopol' => $noPolisi]);
            $matches = $stmtFind->fetchAll();

            if (count($matches) === 0) {
                $failRows[] = "Baris $rowNum: kendaraan dengan Plat Nomor \"$noPolisi\" tidak ditemukan";
                continue;
            }
            if (count($matches) > 1) {
                if ($cabangCsv !== '') {
                    $narrowed = array_values(array_filter($matches, function ($m) use ($cabangCsv) {
                        return strcasecmp($m['cabang'], $cabangCsv) === 0;
                    }));
                    if (count($narrowed) === 1) {
                        $matches = $narrowed;
                    }
                }
                if (count($matches) > 1) {
                    $failRows[] = "Baris $rowNum: Plat Nomor \"$noPolisi\" ditemukan lebih dari 1 kali dan tidak bisa dipastikan cabangnya";
                    continue;
                }
            }

            $kendaraanId = $matches[0]['id'];
            $kendaraanCabang = $matches[0]['cabang'];

            if ($scopeCabangImport && $kendaraanCabang !== $scopeCabangImport) {
                $failRows[] = "Baris $rowNum ($noPolisi): di luar cabang Anda, dilewati";
                continue;
            }
        }

        // Cek duplikat: kombinasi Kendaraan + Tanggal + Keterangan Service yang sama persis
        // dianggap baris yang sama dari file yang pernah diimpor sebelumnya, supaya file yang
        // sama bisa diimpor berkali-kali (mis. setelah menambah baris baru) tanpa data dobel.
        $dupSql = "SELECT COUNT(*) FROM riwayat_servis WHERE tanggal_servis = :tgl AND jenis_servis = :jenis AND "
                . ($kendaraanId !== null ? "kendaraan_id = :kid" : "kendaraan_id IS NULL");
        $dupParams = [':tgl' => $tanggal, ':jenis' => $jenisServis];
        if ($kendaraanId !== null) $dupParams[':kid'] = $kendaraanId;
        $stmtDup = $pdo->prepare($dupSql);
        $stmtDup->execute($dupParams);

        if ((int)$stmtDup->fetchColumn() > 0) {
            $duplicateCount++;
            $failRows[] = "Baris $rowNum" . ($noPolisi !== '' ? " ($noPolisi)" : '') . ": sudah pernah diimpor sebelumnya (duplikat), dilewati";
            continue;
        }

        $stmtInsert->execute([
            ':kid'        => $kendaraanId,
            ':proyek'     => $get($row, 'proyek') ?: null,
            ':tgl'        => $tanggal,
            ':km'         => $parseNumber($get($row, 'km_servis')),
            ':nominal'    => $parseNumber($get($row, 'nominal')),
            ':jenis'      => $jenisServis,
            ':bengkel'    => null,
            ':catbengkel' => $get($row, 'catatan_bengkel') ?: null,
            ':belum'      => $get($row, 'belum_diganti') ?: null,
            ':uid'        => $user['id']
        ]);
        $successCount++;
    }

    if ($successCount === 0 && empty($failRows)) {
        flash_set('error', 'Tidak ada baris data yang bisa diproses dari file ini.');
    } else {
        // Disimpan sebagai data terstruktur (bukan flash string biasa) supaya daftar baris
        // gagal bisa ditampilkan lengkap & rapi, dan tidak auto-hilang sebelum sempat dibaca.
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['import_result_servis'] = [
            'success'    => $successCount,
            'duplicates' => $duplicateCount,
            'fails'      => $failRows,
        ];
    }

    session_write_close();
    header('Location: ' . BASE_URL . '/public/riwayat_servis.php');
    exit;
}

// Filter
$filterKendaraanId = (int)($_GET['kendaraan_id'] ?? 0);
$filterTglAwal = trim($_GET['tgl_awal'] ?? '');
$filterTglAkhir = trim($_GET['tgl_akhir'] ?? '');

$whereSql = " WHERE 1=1";
$params = [];

if ($filterKendaraanId > 0) {
    $whereSql .= " AND s.kendaraan_id = :kid";
    $params[':kid'] = $filterKendaraanId;
}

if (!empty($filterTglAwal)) {
    $whereSql .= " AND s.tanggal_servis >= :tgl_awal";
    $params[':tgl_awal'] = $filterTglAwal;
}

if (!empty($filterTglAkhir)) {
    $whereSql .= " AND s.tanggal_servis <= :tgl_akhir";
    $params[':tgl_akhir'] = $filterTglAkhir;
}

// Batasi data ke cabang sendiri untuk role pic yang sudah di-set cabang-nya
$scopeCabang = ($user['role'] === 'pic' && !empty($user['cabang'])) ? $user['cabang'] : null;
if ($scopeCabang) {
    $whereSql .= " AND k.cabang = :scopeCb";
    $params[':scopeCb'] = $scopeCabang;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM riwayat_servis s LEFT JOIN kendaraan k ON s.kendaraan_id = k.id $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$pagination = get_pagination_params(10);

$sql = "SELECT s.*, k.asset_id, k.no_polisi, k.merk, k.model, k.cabang, k.jenis_kendaraan, u.nama as dicatat_oleh
        FROM riwayat_servis s
        LEFT JOIN kendaraan k ON s.kendaraan_id = k.id
        LEFT JOIN users u ON s.oleh_user_id = u.id
        $whereSql
        ORDER BY s.tanggal_servis DESC, s.id DESC
        LIMIT " . (int)$pagination['offset'] . ", " . (int)$pagination['per_page'];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$servisList = $stmt->fetchAll();

$statJoinCb = $scopeCabang ? " JOIN kendaraan k2 ON s.kendaraan_id = k2.id AND k2.cabang = :scopeCb2" : "";
$statCbParams = $scopeCabang ? [':scopeCb2' => $scopeCabang] : [];

$stmtTotalServis = $pdo->prepare("SELECT COUNT(*) FROM riwayat_servis s $statJoinCb");
$stmtTotalServis->execute($statCbParams);
$totalAllServis = (int)$stmtTotalServis->fetchColumn();

$stmtServisBulanIni = $pdo->prepare("SELECT COUNT(*) FROM riwayat_servis s $statJoinCb WHERE MONTH(s.tanggal_servis) = MONTH(CURDATE()) AND YEAR(s.tanggal_servis) = YEAR(CURDATE())");
$stmtServisBulanIni->execute($statCbParams);
$totalServisBulanIni = (int)$stmtServisBulanIni->fetchColumn();

$stmtAllVehicles = $pdo->prepare("SELECT id, asset_id, no_polisi, merk, model FROM kendaraan" . ($scopeCabang ? " WHERE cabang = :cb" : "") . " ORDER BY asset_id ASC");
$stmtAllVehicles->execute($scopeCabang ? [':cb' => $scopeCabang] : []);
$allVehicles = $stmtAllVehicles->fetchAll();

$isAjax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if ($isAjax) {
    require __DIR__ . '/../includes/partial_riwayat_servis_table.php';
    exit;
}

// Ambil hasil import (kalau ada) sekali pakai, lalu buang dari session
$importResult = null;
if (!empty($_SESSION['import_result_servis'])) {
    $importResult = $_SESSION['import_result_servis'];
    unset($_SESSION['import_result_servis']);
}

$pageTitle = 'Riwayat Servis Armada';
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<div class="space-y-6">
    <!-- Flash Messages -->
    <?= render_flash_messages() ?>

    <!-- Hasil Import CSV (persisten, tidak auto-hilang, ditutup manual) -->
    <?php if ($importResult): ?>
        <?php $hasFails = !empty($importResult['fails']); ?>
        <div class="rounded-xl border <?= $hasFails ? 'border-amber-300 bg-amber-50' : 'border-emerald-300 bg-emerald-50' ?> shadow-sm overflow-hidden">
            <div class="flex items-start justify-between gap-3 p-4">
                <div class="flex items-start gap-3">
                    <i class="fa-solid <?= $hasFails ? 'fa-triangle-exclamation text-amber-600' : 'fa-circle-check text-emerald-600' ?> text-lg mt-0.5"></i>
                    <div>
                        <div class="font-bold text-sm <?= $hasFails ? 'text-amber-900' : 'text-emerald-900' ?>">
                            Hasil Import CSV
                        </div>
                        <div class="text-xs <?= $hasFails ? 'text-amber-800' : 'text-emerald-800' ?> mt-0.5">
                            <b><?= (int)$importResult['success'] ?></b> baris berhasil diimpor<?php
                                $dupCount = (int)($importResult['duplicates'] ?? 0);
                                $otherFailCount = count($importResult['fails']) - $dupCount;
                            ?><?php if ($dupCount > 0): ?>, <b><?= $dupCount ?></b> duplikat dilewati (sudah pernah diimpor)<?php endif; ?><?php if ($otherFailCount > 0): ?>, <b><?= $otherFailCount ?></b> baris gagal (lihat rincian di bawah)<?php endif; ?>.
                        </div>
                    </div>
                </div>
                <button type="button" onclick="this.closest('.rounded-xl').remove()" class="text-slate-400 hover:text-slate-700 transition-colors p-1 flex-shrink-0">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
            <?php if ($hasFails): ?>
                <div class="border-t <?= $hasFails ? 'border-amber-200' : 'border-emerald-200' ?> max-h-64 overflow-y-auto">
                    <ul class="divide-y divide-amber-100">
                        <?php foreach ($importResult['fails'] as $i => $failMsg): ?>
                            <li class="px-4 py-2 text-xs text-amber-900 flex gap-2">
                                <span class="font-mono text-amber-500 flex-shrink-0"><?= $i + 1 ?>.</span>
                                <span><?= htmlspecialchars($failMsg) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Header Title & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-screwdriver-wrench text-blue-700"></i> Riwayat Servis Armada
            </h1>
            <p class="text-slate-500 text-xs mt-0.5">Catatan histori servis / perawatan setiap unit kendaraan.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="openModal('modal-import-servis')" class="btn-secondary text-xs py-2 px-3" title="Import dari CSV">
                <i class="fa-solid fa-file-import text-blue-600"></i> Import
            </button>
            <a href="<?= BASE_URL ?>/public/export_excel.php?type=servis<?= $filterKendaraanId ? '&kendaraan_id=' . $filterKendaraanId : '' ?><?= $filterTglAwal ? '&tgl_awal=' . urlencode($filterTglAwal) : '' ?><?= $filterTglAkhir ? '&tgl_akhir=' . urlencode($filterTglAkhir) : '' ?>" class="btn-secondary text-xs py-2 px-3" title="Export Excel / CSV">
                <i class="fa-solid fa-file-excel text-emerald-600"></i> Export
            </a>
            <button type="button" onclick="openModal('modal-tambah-servis')" class="btn-primary text-xs py-2 px-3.5">
                <i class="fa-solid fa-circle-plus mr-1"></i> Catat Servis Baru
            </button>
        </div>
    </div>

    <!-- Stats Quick Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="stat-card">
            <div class="text-[11px] font-bold uppercase text-slate-500">Total Riwayat Servis</div>
            <div class="text-2xl font-black text-slate-900 mt-1"><?= $totalAllServis ?> <span class="text-xs font-normal text-slate-500">Catatan</span></div>
        </div>
        <div class="stat-card bg-blue-50/50 border-blue-200">
            <div class="text-[11px] font-bold uppercase text-blue-700">Servis Bulan Ini</div>
            <div class="text-2xl font-black text-blue-800 mt-1"><?= $totalServisBulanIni ?> <span class="text-xs font-normal text-blue-600">Catatan</span></div>
        </div>
    </div>

    <div id="ajax-table-wrap" class="space-y-6">
        <?php require __DIR__ . '/../includes/partial_riwayat_servis_table.php'; ?>
    </div>
</div>

<!-- Modal Import Riwayat Servis -->
<div id="modal-import-servis" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-lg w-full p-6 relative max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-file-import text-blue-700"></i> Import Riwayat Servis
                </h3>
                <p class="text-xs text-slate-500">Unggah file CSV berisi catatan servis untuk dimasukkan sekaligus.</p>
            </div>
            <button type="button" onclick="closeModal('modal-import-servis')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="mb-4 p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-600">
            <div class="font-bold text-slate-700 mb-1.5">Kolom yang dikenali (urutan bebas, dari baris header):</div>
            <div class="flex flex-wrap gap-1.5">
                <span class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px]">Tanggal</span>
                <span class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px]">Jenis Kendaraan</span>
                <span class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px]">Merk Kendaraan</span>
                <span class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px]">Plat Nomor</span>
                <span class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px]">Cabang</span>
                <span class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px]">Proyek</span>
                <span class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px]">Keterangan Service</span>
                <span class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px]">Kilometer Service</span>
                <span class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px]">Nominal</span>
                <span class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px]">Yang belum diganti</span>
                <span class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px]">Catatan Bengkel</span>
            </div>
            <p class="mt-2">Kendaraan dicocokkan lewat <b>Plat Nomor</b> (+ Cabang jika platnya kembar). Kolom Tanggal, Plat Nomor, dan Keterangan Service wajib terisi. Format file: <b>.csv</b> (simpan dari Excel via File &rarr; Save As &rarr; CSV).</p>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" class="space-y-3.5">
            <input type="hidden" name="action" value="import_servis">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">File CSV <span class="text-rose-500">*</span></label>
                <input type="file" name="import_file" accept=".csv,text/csv" required
                       class="w-full text-xs text-slate-700 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:font-semibold file:text-xs border border-slate-300 rounded-lg py-1.5">
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-import-servis')" class="btn-secondary text-xs py-2 px-3.5">
                    Batal
                </button>
                <button type="submit" class="btn-primary text-xs py-2 px-4">
                    <i class="fa-solid fa-upload mr-1"></i> Import Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tambah Servis -->
<div id="modal-tambah-servis" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-md w-full p-6 relative max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-circle-plus text-blue-700"></i> Catat Servis Baru
                </h3>
                <p class="text-xs text-slate-500">Tambahkan catatan histori servis kendaraan.</p>
            </div>
            <button type="button" onclick="closeModal('modal-tambah-servis')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-3.5">
            <input type="hidden" name="action" value="create_servis">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Kendaraan <span class="text-rose-500">*</span></label>
                <select name="kendaraan_id" required data-searchable data-placeholder="Ketik plat / asset ID / merk..." class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="">-- Pilih Kendaraan --</option>
                    <option value="0">-- Umum (Tidak Terkait Kendaraan Tertentu) --</option>
                    <?php foreach ($allVehicles as $v): ?>
                        <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['asset_id']) ?> - <?= htmlspecialchars($v['no_polisi']) ?> (<?= htmlspecialchars($v['merk']) ?> <?= htmlspecialchars($v['model']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Servis <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal_servis" required value="<?= date('Y-m-d') ?>"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Proyek</label>
                    <input type="text" name="proyek" placeholder="Misal: TBT"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan Service <span class="text-rose-500">*</span></label>
                <input type="text" name="jenis_servis" required placeholder="Misal: Service Tune Up, Ganti Oli dan Ganti Bohlam Sein"
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kilometer Service</label>
                    <input type="number" name="km_servis" min="0" required placeholder="Misal: 45000"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nominal (Rp)</label>
                    <input type="number" name="nominal" min="0" placeholder="Misal: 133000"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Bengkel / Vendor</label>
                <input type="text" name="bengkel" placeholder="Misal: Bengkel Jaya Motor"
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Yang Belum Diganti</label>
                <input type="text" name="belum_diganti" placeholder="Misal: Kampas Rem Belakang"
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Bengkel</label>
                <textarea name="catatan_bengkel" rows="2" placeholder="Catatan dari bengkel (opsional)..."
                          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan</label>
                <textarea name="keterangan" rows="2" placeholder="Catatan tambahan (opsional)..."
                          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none"></textarea>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-tambah-servis')" class="btn-secondary text-xs py-2 px-3.5">
                    Batal
                </button>
                <button type="submit" class="btn-primary text-xs py-2 px-4">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Servis -->
<div id="modal-edit-servis" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-md w-full p-6 relative max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-blue-700"></i> Edit Riwayat Servis
                </h3>
            </div>
            <button type="button" onclick="closeModal('modal-edit-servis')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-3.5">
            <input type="hidden" name="action" value="update_servis">
            <input type="hidden" name="id" id="edit-servis-id">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Kendaraan <span class="text-rose-500">*</span></label>
                <select name="kendaraan_id" id="edit-servis-kendaraan" required data-searchable data-placeholder="Ketik plat / asset ID / merk..." class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <option value="">-- Pilih Kendaraan --</option>
                    <option value="0">-- Umum (Tidak Terkait Kendaraan Tertentu) --</option>
                    <?php foreach ($allVehicles as $v): ?>
                        <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['asset_id']) ?> - <?= htmlspecialchars($v['no_polisi']) ?> (<?= htmlspecialchars($v['merk']) ?> <?= htmlspecialchars($v['model']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Servis <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal_servis" id="edit-servis-tanggal" required
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Proyek</label>
                    <input type="text" name="proyek" id="edit-servis-proyek" placeholder="Misal: TBT"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan Service <span class="text-rose-500">*</span></label>
                <input type="text" name="jenis_servis" id="edit-servis-jenis" required
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kilometer Service</label>
                    <input type="number" name="km_servis" id="edit-servis-km" min="0"  placeholder="Misal: 45000"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nominal (Rp)</label>
                    <input type="number" name="nominal" id="edit-servis-nominal" min="0" placeholder="Misal: 133000"
                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Bengkel / Vendor</label>
                <input type="text" name="bengkel" id="edit-servis-bengkel" placeholder="Misal: Bengkel Jaya Motor"
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Yang Belum Diganti</label>
                <input type="text" name="belum_diganti" id="edit-servis-belum" placeholder="Misal: Kampas Rem Belakang"
                       class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Bengkel</label>
                <textarea name="catatan_bengkel" id="edit-servis-catbengkel" rows="2"
                          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan</label>
                <textarea name="keterangan" id="edit-servis-keterangan" rows="2"
                          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none"></textarea>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-edit-servis')" class="btn-secondary text-xs py-2 px-3.5">
                    Batal
                </button>
                <button type="submit" class="btn-primary text-xs py-2 px-4">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detail Servis -->
<div id="modal-detail-servis" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-xl max-w-lg w-full p-6 relative max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-circle-info text-blue-700"></i> Detail Riwayat Servis
                </h3>
            </div>
            <button type="button" onclick="closeModal('modal-detail-servis')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="mb-4 p-3 rounded-lg bg-slate-50 border border-slate-200">
            <div class="flex items-center gap-2 mb-1">
                <span id="detail-servis-jenis-kendaraan" class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-50 text-blue-700 border border-blue-200"></span>
                <span id="detail-servis-nopol" class="font-bold text-slate-900 text-sm"></span>
                <span id="detail-servis-assetid" class="text-[11px] font-mono text-blue-700 font-semibold"></span>
            </div>
            <div id="detail-servis-merkmodel" class="text-xs text-slate-600"></div>
            <div id="detail-servis-cabang" class="text-xs text-slate-500"></div>
        </div>

        <dl class="space-y-3 text-xs">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <dt class="font-semibold text-slate-500 uppercase text-[10px] mb-0.5">Tanggal Servis</dt>
                    <dd id="detail-servis-tanggal" class="text-slate-800"></dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500 uppercase text-[10px] mb-0.5">Proyek</dt>
                    <dd id="detail-servis-proyek" class="text-slate-800"></dd>
                </div>
            </div>
            <div>
                <dt class="font-semibold text-slate-500 uppercase text-[10px] mb-0.5">Keterangan Service</dt>
                <dd id="detail-servis-jenis" class="text-slate-800"></dd>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <dt class="font-semibold text-slate-500 uppercase text-[10px] mb-0.5">Kilometer Service</dt>
                    <dd id="detail-servis-km" class="text-slate-800"></dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500 uppercase text-[10px] mb-0.5">Nominal</dt>
                    <dd id="detail-servis-nominal" class="text-emerald-700 font-semibold"></dd>
                </div>
            </div>
            <div>
                <dt class="font-semibold text-slate-500 uppercase text-[10px] mb-0.5">Bengkel / Vendor</dt>
                <dd id="detail-servis-bengkel" class="text-slate-800"></dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500 uppercase text-[10px] mb-0.5">Catatan Bengkel</dt>
                <dd id="detail-servis-catbengkel" class="text-slate-800"></dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500 uppercase text-[10px] mb-0.5">Yang Belum Diganti</dt>
                <dd id="detail-servis-belum" class="text-slate-800"></dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500 uppercase text-[10px] mb-0.5">Keterangan</dt>
                <dd id="detail-servis-keterangan" class="text-slate-800"></dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500 uppercase text-[10px] mb-0.5">Dicatat Oleh</dt>
                <dd id="detail-servis-oleh" class="text-slate-800"></dd>
            </div>
        </dl>

        <div class="pt-4 mt-4 flex items-center justify-end border-t border-slate-100">
            <button type="button" onclick="closeModal('modal-detail-servis')" class="btn-secondary text-xs py-2 px-3.5">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
function openDetailServisModal(s) {
    if (s.kendaraan_id) {
        document.getElementById('detail-servis-jenis-kendaraan').textContent = s.jenis_kendaraan || '-';
        document.getElementById('detail-servis-jenis-kendaraan').classList.remove('hidden');
        document.getElementById('detail-servis-nopol').textContent = s.no_polisi || '-';
        document.getElementById('detail-servis-assetid').textContent = s.asset_id || '';
        document.getElementById('detail-servis-merkmodel').textContent = [s.merk, s.model].filter(Boolean).join(' ');
        document.getElementById('detail-servis-cabang').textContent = 'Cabang ' + (s.cabang || '-');
    } else {
        document.getElementById('detail-servis-jenis-kendaraan').classList.add('hidden');
        document.getElementById('detail-servis-nopol').textContent = 'Umum (Tidak Terkait Kendaraan Tertentu)';
        document.getElementById('detail-servis-assetid').textContent = '';
        document.getElementById('detail-servis-merkmodel').textContent = '';
        document.getElementById('detail-servis-cabang').textContent = '';
    }
    document.getElementById('detail-servis-tanggal').textContent = s.tanggal_servis || '-';
    document.getElementById('detail-servis-proyek').textContent = s.proyek || '-';
    document.getElementById('detail-servis-jenis').textContent = s.jenis_servis || '-';
    document.getElementById('detail-servis-km').textContent = s.km_servis ? Number(s.km_servis).toLocaleString('id-ID') + ' KM' : '-';
    document.getElementById('detail-servis-nominal').textContent = s.nominal ? 'Rp ' + Number(s.nominal).toLocaleString('id-ID') : '-';
    document.getElementById('detail-servis-bengkel').textContent = s.bengkel || '-';
    document.getElementById('detail-servis-catbengkel').textContent = s.catatan_bengkel || '-';
    document.getElementById('detail-servis-belum').textContent = s.belum_diganti || '-';
    document.getElementById('detail-servis-keterangan').textContent = s.keterangan || '-';
    document.getElementById('detail-servis-oleh').textContent = s.dicatat_oleh || '-';
    openModal('modal-detail-servis');
}

function openEditServisModal(s) {
    document.getElementById('edit-servis-id').value = s.id;
    document.getElementById('edit-servis-kendaraan').value = s.kendaraan_id || '0';
    syncSearchableSelect(document.getElementById('edit-servis-kendaraan'));
    document.getElementById('edit-servis-tanggal').value = s.tanggal_servis;
    document.getElementById('edit-servis-proyek').value = s.proyek || '';
    document.getElementById('edit-servis-km').value = s.km_servis || '';
    document.getElementById('edit-servis-nominal').value = s.nominal || '';
    document.getElementById('edit-servis-jenis').value = s.jenis_servis;
    document.getElementById('edit-servis-bengkel').value = s.bengkel || '';
    document.getElementById('edit-servis-belum').value = s.belum_diganti || '';
    document.getElementById('edit-servis-catbengkel').value = s.catatan_bengkel || '';
    document.getElementById('edit-servis-keterangan').value = s.keterangan || '';
    openModal('modal-edit-servis');
}

function confirmDeleteServis(id, jenis) {
    showConfirmModal({
        title: 'Hapus Riwayat Servis?',
        message: 'Catatan servis "' + jenis + '" akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.',
        confirmText: 'Ya, Hapus',
        confirmType: 'danger',
        icon: 'fa-solid fa-trash-can',
        onConfirm: function() {
            var form = document.getElementById('delete-servis-form-' + id);
            if (form) form.submit();
        }
    });
}
</script>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>
