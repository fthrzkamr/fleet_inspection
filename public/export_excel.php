<?php
/**
 * Export Data Armada & Riwayat Inspeksi ke Excel / CSV dengan UTF-8 BOM
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin', 'pic']);

$pdo = get_db();
$user = current_user();
$type = $_GET['type'] ?? 'kendaraan';

if ($type === 'servis' && $user['role'] !== 'admin') {
    $_SESSION['flash_error'] = 'Anda tidak memiliki hak akses untuk fitur Riwayat Servis.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

if (in_array($type, ['log_aktivitas', 'activity_log']) && !in_array($user['role'], ['admin', 'pic'])) {
    $_SESSION['flash_error'] = 'Anda tidak memiliki hak akses untuk export Log Aktivitas.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Batasi export ke cabang sendiri untuk role pic yang sudah di-set cabang-nya
$scopeCabang = ($user['role'] === 'pic' && !empty($user['cabang'])) ? $user['cabang'] : null;

log_activity('EXPORT_EXCEL', 'Laporan', "Mengunduh export data $type ke file CSV / Excel", $type);

// Semicolon dipakai sebagai delimiter (bukan koma) supaya Excel dengan region Indonesia
// otomatis memecah kolom dengan benar saat file CSV dibuka langsung (double-click).
$delimiter = ';';

if (in_array($type, ['log_aktivitas', 'activity_log'])) {
    $filename = 'log_aktivitas_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, ['No', 'ID Log', 'Waktu', 'Nama User', 'Username', 'Role', 'Cabang', 'Modul', 'Aksi', 'Deskripsi Aktivitas', 'Target / Ref', 'IP Address', 'User Agent'], $delimiter);

    $filterQ        = trim($_GET['q'] ?? '');
    $filterUserId   = (int)($_GET['user_id'] ?? 0);
    $filterRole     = trim($_GET['role'] ?? '');
    $filterModule   = trim($_GET['module'] ?? '');
    $filterAction   = trim($_GET['action'] ?? '');
    $filterCabang   = trim($_GET['cabang'] ?? '');
    $filterTglAwal  = trim($_GET['tgl_awal'] ?? '');
    $filterTglAkhir = trim($_GET['tgl_akhir'] ?? '');

    $whereSql = " WHERE 1=1";
    $params   = [];

    if (!empty($filterQ)) {
        $whereSql .= " AND (description LIKE :q1 OR user_nama LIKE :q2 OR user_username LIKE :q3 OR target_id LIKE :q4 OR ip_address LIKE :q5)";
        $params[':q1'] = "%$filterQ%";
        $params[':q2'] = "%$filterQ%";
        $params[':q3'] = "%$filterQ%";
        $params[':q4'] = "%$filterQ%";
        $params[':q5'] = "%$filterQ%";
    }
    if ($filterUserId > 0) {
        $whereSql .= " AND user_id = :uid";
        $params[':uid'] = $filterUserId;
    }
    if (!empty($filterRole)) {
        $whereSql .= " AND user_role = :role";
        $params[':role'] = $filterRole;
    }
    if (!empty($filterModule)) {
        $whereSql .= " AND module = :mod";
        $params[':mod'] = $filterModule;
    }
    if (!empty($filterAction)) {
        $whereSql .= " AND action = :act";
        $params[':act'] = $filterAction;
    }
    if ($scopeCabang) {
        $whereSql .= " AND cabang = :scopeCb";
        $params[':scopeCb'] = $scopeCabang;
    } elseif (!empty($filterCabang)) {
        $whereSql .= " AND cabang = :cabang";
        $params[':cabang'] = $filterCabang;
    }
    if (!empty($filterTglAwal)) {
        $whereSql .= " AND DATE(created_at) >= :tgl_awal";
        $params[':tgl_awal'] = $filterTglAwal;
    }
    if (!empty($filterTglAkhir)) {
        $whereSql .= " AND DATE(created_at) <= :tgl_akhir";
        $params[':tgl_akhir'] = $filterTglAkhir;
    }

    $stmt = $pdo->prepare("SELECT * FROM activity_logs $whereSql ORDER BY id DESC");
    $stmt->execute($params);

    $no = 1;
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $no++,
            $row['id'],
            $row['created_at'] ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : '',
            $row['user_nama'],
            $row['user_username'],
            strtoupper($row['user_role']),
            $row['cabang'] ?: '-',
            $row['module'],
            $row['action'],
            $row['description'],
            $row['target_id'] ?: '-',
            $row['ip_address'] ?: '-',
            $row['user_agent'] ?: '-'
        ], $delimiter);
    }

    fclose($output);
    exit;

} elseif ($type === 'servis') {
    $filename = 'riwayat_servis_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['Tanggal', 'Jenis Kendaraan', 'Merk Kendaraan', 'Plat Nomor', 'Cabang', 'Proyek', 'Keterangan Service', 'Kilometer Service (KM)', 'Nominal (Rp)', 'Yang Belum Diganti', 'Catatan Bengkel'], $delimiter);

    $filterKendaraanId = (int)($_GET['kendaraan_id'] ?? 0);
    $filterTglAwal = trim($_GET['tgl_awal'] ?? '');
    $filterTglAkhir = trim($_GET['tgl_akhir'] ?? '');
    $filterCabang = trim($_GET['cabang'] ?? '');
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
    if (!empty($filterCabang)) {
        $whereSql .= " AND k.cabang = :cabang";
        $params[':cabang'] = $filterCabang;
    }
    if ($scopeCabang) {
        $whereSql .= " AND k.cabang = :cb";
        $params[':cb'] = $scopeCabang;
    }

    $stmt = $pdo->prepare("SELECT s.*, k.no_polisi, k.merk, k.model, k.cabang, k.jenis_kendaraan
                           FROM riwayat_servis s
                           LEFT JOIN kendaraan k ON s.kendaraan_id = k.id
                           $whereSql
                           ORDER BY s.tanggal_servis DESC, s.id DESC");
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['tanggal_servis'] ? date('d/m/Y', strtotime($row['tanggal_servis'])) : '',
            $row['jenis_kendaraan'] ?: 'Umum',
            $row['model'] ?: '',
            $row['no_polisi'] ?: '',
            $row['cabang'] ?: '',
            $row['proyek'] ?: '',
            $row['jenis_servis'],
            $row['km_servis'] ?? '',
            $row['nominal'] ?? '',
            $row['belum_diganti'] ?: '',
            $row['catatan_bengkel'] ?: ''
        ], $delimiter);
    }
    fclose($output);
    exit;

} elseif ($type === 'inspeksi') {
    $filename = 'rekap_inspeksi_armada_' . date('Ymd_His') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Microsoft Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, ['ID Sesi', 'Tanggal Mulai', 'Tanggal Selesai', 'Asset ID', 'Nomor Polisi', 'Merk & Model', 'Cabang', 'Petugas Inspeksi', 'Odometer (KM)', 'Level BBM', 'Hasil Akhir', 'Catatan Umum'], $delimiter);

    $stmt = $pdo->prepare("SELECT i.*, k.asset_id, k.no_polisi, k.merk, k.model, k.cabang, u.nama as petugas_nama
                         FROM inspeksi i
                         JOIN kendaraan k ON i.kendaraan_id = k.id
                         LEFT JOIN users u ON i.petugas_id = u.id
                         WHERE i.tanggal_selesai IS NOT NULL" . ($scopeCabang ? " AND k.cabang = :cb" : "") . "
                         ORDER BY i.id DESC");
    $stmt->execute($scopeCabang ? [':cb' => $scopeCabang] : []);

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['tanggal_mulai'] ? date('d/m/Y H:i', strtotime($row['tanggal_mulai'])) : '',
            $row['tanggal_selesai'] ? date('d/m/Y H:i', strtotime($row['tanggal_selesai'])) : '',
            $row['asset_id'],
            $row['no_polisi'],
            $row['merk'] . ' ' . $row['model'],
            $row['cabang'],
            $row['petugas_nama'] ?: 'Petugas',
            $row['odometer'] ?? '',
            $row['bbm_level'],
            strtoupper($row['hasil_akhir']),
            $row['catatan_umum']
        ], $delimiter);
    }
    fclose($output);
    exit;

} else {
    // Export Data Kendaraan
    $filename = 'database_kendaraan_' . date('Ymd_His') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, ['Asset ID', 'Nomor Polisi', 'Merk', 'Model', 'Jenis Kendaraan', 'VIN / No Rangka', 'Cabang', 'Status', 'Alasan Inactive', 'Expired STNK', 'Expired KIR', 'Expired Plat (TNKB)', 'Tanggal Registrasi'], $delimiter);

    $stmt = $pdo->prepare("SELECT * FROM kendaraan" . ($scopeCabang ? " WHERE cabang = :cb" : "") . " ORDER BY id ASC");
    $stmt->execute($scopeCabang ? [':cb' => $scopeCabang] : []);

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['asset_id'],
            $row['no_polisi'],
            $row['merk'],
            $row['model'],
            $row['jenis_kendaraan'],
            $row['vin'],
            $row['cabang'],
            strtoupper($row['status']),
            $row['status_inactive_reason'] ?: '',
            $row['tgl_stnk_expired'] ? date('d/m/Y', strtotime($row['tgl_stnk_expired'])) : '',
            $row['tgl_kir_expired'] ? date('d/m/Y', strtotime($row['tgl_kir_expired'])) : '',
            $row['tgl_plat_expired'] ? date('d/m/Y', strtotime($row['tgl_plat_expired'])) : '',
            $row['created_at'] ? date('d/m/Y H:i', strtotime($row['created_at'])) : ''
        ], $delimiter);
    }
    fclose($output);
    exit;
}
