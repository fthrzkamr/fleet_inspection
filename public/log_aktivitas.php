<?php
/**
 * Halaman Log Aktivitas Pengguna (Admin & PIC) - Enterprise UI
 * Sistem Monitoring Kendaraan
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Hak akses khusus Administrator dan PIC
require_role(['admin', 'pic']);

$pdo = get_db();
$user = current_user();

// Batasi data ke cabang sendiri untuk role pic yang sudah di-set cabang-nya.
// Admin (dan PIC yang belum di-set cabang-nya) tetap melihat semua cabang.
$scopeCabang = ($user['role'] === 'pic' && !empty($user['cabang'])) ? $user['cabang'] : null;

// Parameter Filter
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

if ($scopeCabang) {
    $whereSql .= " AND cabang = :scopeCb";
    $params[':scopeCb'] = $scopeCabang;
}

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

if (!$scopeCabang && !empty($filterCabang)) {
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

// Hitung Total Data sesuai filter
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

// Setup Pagination (20 baris per halaman)
$pagination = get_pagination_params(20);

// Ambil Data Log dengan Pagination
$sql = "SELECT * FROM activity_logs $whereSql ORDER BY id DESC LIMIT " . (int)$pagination['offset'] . ", " . (int)$pagination['per_page'];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// KPI Statistik (Disesuaikan dengan scope cabang jika PIC)
$statCabangSql = $scopeCabang ? " AND cabang = :scopeCb" : "";
$statCabangParams = $scopeCabang ? [':scopeCb' => $scopeCabang] : [];

$stmtToday = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()" . $statCabangSql);
$stmtToday->execute($statCabangParams);
$statTotalToday = (int)$stmtToday->fetchColumn();

$stmtTopUser = $pdo->prepare("SELECT user_nama, COUNT(*) as total FROM activity_logs WHERE user_nama != ''" . $statCabangSql . " GROUP BY user_nama ORDER BY total DESC LIMIT 1");
$stmtTopUser->execute($statCabangParams);
$statTopUser = $stmtTopUser->fetch();

$stmtTopModule = $pdo->prepare("SELECT module, COUNT(*) as total FROM activity_logs WHERE module != ''" . $statCabangSql . " GROUP BY module ORDER BY total DESC LIMIT 1");
$stmtTopModule->execute($statCabangParams);
$statTopModule = $stmtTopModule->fetch();

// Master Options untuk Filter Dropdown
$allUsersSql = "SELECT id, nama, username FROM users" . ($scopeCabang ? " WHERE cabang = :scopeCb" : "") . " ORDER BY nama ASC";
$stmtUsers = $pdo->prepare($allUsersSql);
$stmtUsers->execute($scopeCabang ? [':scopeCb' => $scopeCabang] : []);
$allUsers = $stmtUsers->fetchAll();

$allModules = $pdo->query("SELECT DISTINCT module FROM activity_logs ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($allModules)) {
    $allModules = ['Autentikasi', 'Kendaraan', 'Inspeksi', 'Servis', 'Master Cabang', 'Kelola User', 'Laporan'];
}
$allActions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
$allCabang  = get_cabang_list($pdo, false);

// Respons AJAX jika request berasal dari AJAX Table Navigation
$isAjax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if ($isAjax) {
    require __DIR__ . '/../includes/partial_log_aktivitas_table.php';
    exit;
}

$pageTitle = 'Log Aktivitas Pengguna';
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<div class="space-y-4">
    <!-- Flash Notification -->
    <?= render_flash_messages() ?>

    <!-- AJAX Container Table -->
    <div id="ajax-table-wrap" class="space-y-4">
        <?php require __DIR__ . '/../includes/partial_log_aktivitas_table.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>

