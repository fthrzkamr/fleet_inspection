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

// Batasi export ke cabang sendiri untuk role pic yang sudah di-set cabang-nya
$scopeCabang = ($user['role'] === 'pic' && !empty($user['cabang'])) ? $user['cabang'] : null;

// Semicolon dipakai sebagai delimiter (bukan koma) supaya Excel dengan region Indonesia
// otomatis memecah kolom dengan benar saat file CSV dibuka langsung (double-click).
$delimiter = ';';

if ($type === 'servis') {
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
