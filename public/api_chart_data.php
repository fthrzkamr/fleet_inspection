<?php
/**
 * API Endpoint JSON untuk Suplai Data Chart.js pada Dashboard
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = get_db();
$user = current_user();

// Batasi data ke cabang sendiri untuk role petugas/pic yang sudah di-set cabang-nya.
$scopeCabang = (in_array($user['role'], ['petugas', 'pic']) && !empty($user['cabang'])) ? $user['cabang'] : null;

try {
    // 1. Top Kendaraan Paling Sering Minor & Mayor (Agregat riwayat_tindak_lanjut)
    $sqlTop = "SELECT k.asset_id, k.no_polisi, k.merk, k.model,
               SUM(CASE WHEN r.jenis = 'mayor_unsafe' THEN 1 ELSE 0 END) as total_mayor,
               SUM(CASE WHEN r.jenis = 'minor_service' THEN 1 ELSE 0 END) as total_minor,
               COUNT(r.id) as total_kendala
               FROM riwayat_tindak_lanjut r
               JOIN kendaraan k ON r.kendaraan_id = k.id"
               . ($scopeCabang ? " WHERE k.cabang = :cb1" : "") . "
               GROUP BY k.id
               ORDER BY total_mayor DESC, total_minor DESC, total_kendala DESC
               LIMIT 6";
    $stmtTop = $pdo->prepare($sqlTop);
    $stmtTop->execute($scopeCabang ? [':cb1' => $scopeCabang] : []);
    $topVehicles = $stmtTop->fetchAll();

    $topLabels = [];
    $topMayorData = [];
    $topMinorData = [];

    foreach ($topVehicles as $tv) {
        $topLabels[] = $tv['no_polisi'] . ' (' . $tv['asset_id'] . ')';
        $topMayorData[] = (int)$tv['total_mayor'];
        $topMinorData[] = (int)$tv['total_minor'];
    }

    // 2. Tren Inspeksi & Hasil per Bulan (6 Bulan Terakhir)
    $sqlTrend = "SELECT
                 DATE_FORMAT(i.tanggal_mulai, '%Y-%m') as bulan_key,
                 DATE_FORMAT(i.tanggal_mulai, '%b %Y') as bulan_label,
                 SUM(CASE WHEN i.hasil_akhir = 'ok' THEN 1 ELSE 0 END) as count_ok,
                 SUM(CASE WHEN i.hasil_akhir = 'minor' THEN 1 ELSE 0 END) as count_minor,
                 SUM(CASE WHEN i.hasil_akhir = 'mayor' THEN 1 ELSE 0 END) as count_mayor,
                 COUNT(i.id) as count_total
                 FROM inspeksi i
                 JOIN kendaraan k ON i.kendaraan_id = k.id
                 WHERE i.tanggal_selesai IS NOT NULL" . ($scopeCabang ? " AND k.cabang = :cb2" : "") . "
                 GROUP BY bulan_key, bulan_label
                 ORDER BY bulan_key ASC
                 LIMIT 6";
    $stmtTrend = $pdo->prepare($sqlTrend);
    $stmtTrend->execute($scopeCabang ? [':cb2' => $scopeCabang] : []);
    $trends = $stmtTrend->fetchAll();

    $trendLabels = [];
    $trendOk = [];
    $trendMinor = [];
    $trendMayor = [];

    // Jika data tren masih sedikit, sediakan minimal bulan berjalan
    if (empty($trends)) {
        $trendLabels[] = date('M Y');
        $trendOk[] = 0;
        $trendMinor[] = 0;
        $trendMayor[] = 0;
    } else {
        foreach ($trends as $tr) {
            $trendLabels[] = $tr['bulan_label'];
            $trendOk[]     = (int)$tr['count_ok'];
            $trendMinor[]  = (int)$tr['count_minor'];
            $trendMayor[]  = (int)$tr['count_mayor'];
        }
    }

    // 3. Distribusi Kondisi Armada Terkini (Real-time Condition)
    $sqlDist = "SELECT 
                SUM(CASE WHEN last_hasil = 'mayor' THEN 1 ELSE 0 END) as total_stop,
                SUM(CASE WHEN last_hasil = 'minor' THEN 1 ELSE 0 END) as total_service,
                SUM(CASE WHEN last_hasil = 'ok' OR last_hasil IS NULL THEN 1 ELSE 0 END) as total_ready
                FROM (
                    SELECT k.id,
                    (SELECT hasil_akhir FROM inspeksi WHERE kendaraan_id = k.id AND tanggal_selesai IS NOT NULL ORDER BY id DESC LIMIT 1) as last_hasil
                    FROM kendaraan k
                    WHERE k.status = 'active'" . ($scopeCabang ? " AND k.cabang = :cb3" : "") . "
                ) as active_fleet";
    $stmtDist = $pdo->prepare($sqlDist);
    $stmtDist->execute($scopeCabang ? [':cb3' => $scopeCabang] : []);
    $dist = $stmtDist->fetch();

    echo json_encode([
        'status' => 'success',
        'data'   => [
            'top_issues' => [
                'labels' => $topLabels,
                'mayor'  => $topMayorData,
                'minor'  => $topMinorData
            ],
            'trends' => [
                'labels' => $trendLabels,
                'ok'     => $trendOk,
                'minor'  => $trendMinor,
                'mayor'  => $trendMayor
            ],
            'distribution' => [
                'ready'   => (int)($dist['total_ready'] ?? 0),
                'service' => (int)($dist['total_service'] ?? 0),
                'stop'    => (int)($dist['total_stop'] ?? 0)
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
