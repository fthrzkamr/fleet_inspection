<?php
/**
 * Helper Functions & Logika Bisnis Aplikasi Fleet Inspection
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/qrcode_helper.php';

/**
 * Ambil Daftar Master Cabang dari Database
 */
function get_cabang_list($pdo, $onlyActive = true) {
    try {
        $sql = "SELECT * FROM cabang";
        if ($onlyActive) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= " ORDER BY nama_cabang ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        // Fallback jika terjadi kendala
        return [];
    }
}

/**
 * Generate Auto-increment Asset ID (format: FLEET-001, FLEET-002, ...)
 */
function generate_asset_id($pdo) {
    $stmt = $pdo->query("SELECT asset_id FROM kendaraan WHERE asset_id LIKE 'FLEET-%' ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch();
    
    if ($last && preg_match('/FLEET-(\d+)/', $last['asset_id'], $matches)) {
        $num = (int)$matches[1] + 1;
    } else {
        // Cek total kendaraan untuk penomoran awal
        $count = (int)$pdo->query("SELECT COUNT(*) FROM kendaraan")->fetchColumn();
        $num = $count + 1;
    }
    
    return sprintf('FLEET-%03d', $num);
}

/**
 * Generate & Simpan Gambar QR Code ke folder uploads/qr/{asset_id}.svg
 * Mengembalikan path relatif dari folder root
 */
function generate_vehicle_qr($asset_id) {
    $qrDir = UPLOADS_PATH . '/qr';
    if (!is_dir($qrDir)) {
        mkdir($qrDir, 0777, true);
    }
    
    $filename = $asset_id . '.svg';
    $filePath = $qrDir . '/' . $filename;
    
    SimpleQRCode::saveToFile($asset_id, $filePath, 320);
    
    return 'uploads/qr/' . $filename;
}

/**
 * Evaluasi Status Kondisi Akhir dari seluruh item checklist
 * Aturan:
 * 1. Ada item KRITIS yang 'rusak' -> 'mayor' (Unsafe / STOP)
 * 2. Ada item non-kritis 'rusak' atau item 'perlu_perhatian' -> 'minor' (Perlu Perbaikan / Service)
 * 3. Semua item 'ok' -> 'ok' (Layak Jalan)
 */
function evaluate_inspection_status($items) {
    $criticalNames = CRITICAL_INSPECTION_ITEMS;
    
    $hasMayor = false;
    $hasMinor = false;
    $mayorReasons = [];
    $minorReasons = [];
    
    foreach ($items as $item) {
        $name = trim($item['item_nama']);
        $kondisi = strtolower(trim($item['kondisi']));
        $isCritical = false;
        
        // Cek apakah item nama ada dalam daftar kritis
        foreach ($criticalNames as $crit) {
            if (stripos($name, $crit) !== false || stripos($crit, $name) !== false) {
                $isCritical = true;
                break;
            }
        }
        
        if ($kondisi === 'rusak') {
            if ($isCritical) {
                $hasMayor = true;
                $mayorReasons[] = $name . ' (Kondisi Rusak / Kritis)';
            } else {
                $hasMinor = true;
                $minorReasons[] = $name . ' (Kondisi Rusak)';
            }
        } elseif ($kondisi === 'perlu_perhatian') {
            $hasMinor = true;
            $minorReasons[] = $name . ' (Perlu Perhatian)';
        }
    }
    
    if ($hasMayor) {
        return [
            'hasil'   => 'mayor',
            'label'   => 'MAYOR — STOP (Tidak Layak Jalan)',
            'type'    => 'danger',
            'reasons' => $mayorReasons
        ];
    }
    
    if ($hasMinor) {
        return [
            'hasil'   => 'minor',
            'label'   => 'MINOR — Perlu Service / Perbaikan Berkala',
            'type'    => 'warning',
            'reasons' => $minorReasons
        ];
    }
    
    return [
        'hasil'   => 'ok',
        'label'   => 'KONDISI BAIK (Layak Beroperasi)',
        'type'    => 'success',
        'reasons' => []
    ];
}

/**
 * Daftar Template Checklist Standar per Kategori
 */
function get_checklist_template($kategori) {
    $templates = [
        'eksterior' => [
            ['name' => 'Bodi & Cat Keseluruhan', 'desc' => 'Periksa baret, penyok, karat, atau kerusakan fisik pada bodi luar.', 'critical' => false],
            ['name' => 'Kaca Depan, Samping & Belakang', 'desc' => 'Periksa retak, pecah, jamur tebal, atau fungsi wiper & semprotan air.', 'critical' => false],
            ['name' => 'Lampu Utama & Sein', 'desc' => 'Periksa lampu dekat, jauh, sein kiri-kanan, dan lampu hazard berfungsi optimal.', 'critical' => true],
            ['name' => 'Kondisi Ban Luar (Gundul/Sobek)', 'desc' => 'Periksa ketebalan tapak ban (TWI), dinding ban, sobekan, dan benjolan.', 'critical' => true]
        ],
        'interior_kelistrikan' => [
            ['name' => 'Kebersihan & Kerapihan Kabin', 'desc' => 'Kondisi jok, karpet, plafon, dan tidak ada sampah atau bau tidak sedap.', 'critical' => false],
            ['name' => 'Sistem AC & Ventilasi Udara', 'desc' => 'Blower berfungsi di semua level kecepatan dan hembusan udara dingin normal.', 'critical' => false],
            ['name' => 'Klakson & Instrumen Dashboard', 'desc' => 'Klakson berbunyi nyaring, indikator speedometer, check engine, dan temperatur normal.', 'critical' => false],
            ['name' => 'Kelengkapan STNK & APAR', 'desc' => 'STNK asli/legalisir ada di mobil dan APAR dalam masa berlaku dengan pin terkunci.', 'critical' => false]
        ],
        'mesin' => [
            ['name' => 'Level & Kualitas Oli Mesin', 'desc' => 'Tarik dipstick oli, pastikan di antara batas MIN-MAX dan warna tidak keruh pekat/bercampur air.', 'critical' => false],
            ['name' => 'Kondisi Aki & Kelistrikan Kritis', 'desc' => 'Kutub aki bersih tanpa kerak putih, voltase stabil, dan starter mobil lancar.', 'critical' => true],
            ['name' => 'Fanbelt & Pulley Mesin', 'desc' => 'Tali kipas tidak retak, tidak berdecit, dan ketegangan belt pas.', 'critical' => false],
            ['name' => 'Kebocoran Cairan (Oli / Radiator / Minyak Rem)', 'desc' => 'Periksa ruang mesin dan kolong mobil dari rembesan/tetesan oli atau coolant.', 'critical' => true]
        ],
        'kaki_kaki' => [
            ['name' => 'Sistem Pengereman (Rem Kaki & Tangan)', 'desc' => 'Rem pakem, pedal rem tidak ambles, dan rem tangan mengunci kuat di tanjakan.', 'critical' => true],
            ['name' => 'Kondisi Kaki-kaki & Kemudi', 'desc' => 'Tidak ada bunyi abnormal di jalan bergelombang dan setir tidak membuang ke satu sisi.', 'critical' => true],
            ['name' => 'Ban Serep & Tekanan Angin', 'desc' => 'Ban serep tersedia di bagasi/kolong dengan tekanan angin siap pakai.', 'critical' => false],
            ['name' => 'Toolkit, Dongkrak & Segitiga Pengaman', 'desc' => 'Kunci roda, dongkrak mekanis/hidrolik, dan segitiga pengaman lengkap tersedia.', 'critical' => false]
        ]
    ];
    
    return $templates[$kategori] ?? [];
}

/**
 * Handle Upload Foto Inspeksi ke /uploads/{asset_id}/{tanggal_sesi}/
 */
function handle_inspection_upload($fileArray, $asset_id, $session_date, $prefix = 'item') {
    if (!isset($fileArray['tmp_name']) || empty($fileArray['tmp_name']) || $fileArray['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Sanitasi tanggal sesi untuk format folder (misal: 2026-08-20)
    $dateFolder = date('Y-m-d', strtotime($session_date));
    $targetDir = UPLOADS_PATH . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $asset_id) . '/' . $dateFolder;
    
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Validasi mime type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileArray['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return null;
    }
    
    $ext = pathinfo($fileArray['name'], PATHINFO_EXTENSION);
    if (empty($ext) || !in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'])) {
        $ext = ($mimeType === 'image/png') ? 'png' : 'jpg';
    }
    
    $uniqueName = $prefix . '_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . strtolower($ext);
    $targetFile = $targetDir . '/' . $uniqueName;
    
    if (move_uploaded_file($fileArray['tmp_name'], $targetFile)) {
        return 'uploads/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $asset_id) . '/' . $dateFolder . '/' . $uniqueName;
    }
    
    return null;
}

/**
 * Flash Message Notification Helpers
 */
function flash_set($type, $message) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_' . $type] = $message;
}

function flash_get($type) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

function render_flash_messages() {
    $types = [
        'success' => ['bg' => 'bg-emerald-50 border-emerald-300 text-emerald-950', 'icon' => 'fa-circle-check text-emerald-600', 'badge' => 'BERHASIL'],
        'error'   => ['bg' => 'bg-rose-50 border-rose-300 text-rose-950', 'icon' => 'fa-circle-exclamation text-rose-600', 'badge' => 'PERHATIAN'],
        'warning' => ['bg' => 'bg-amber-50 border-amber-300 text-amber-950', 'icon' => 'fa-triangle-exclamation text-amber-600', 'badge' => 'INFORMASI'],
        'info'    => ['bg' => 'bg-blue-50 border-blue-300 text-blue-950', 'icon' => 'fa-circle-info text-blue-600', 'badge' => 'INFO']
    ];
    
    $html = '';
    foreach ($types as $key => $style) {
        $msg = flash_get($key);
        if ($msg) {
            $html .= '<div class="alert-box flex items-start gap-3 p-4 mb-4 rounded-xl border ' . $style['bg'] . ' shadow-sm animate-fade-in" data-type="' . $key . '">';
            $html .= '<i class="fa-solid ' . $style['icon'] . ' text-lg mt-0.5 flex-shrink-0"></i>';
            $html .= '<div class="flex-1 text-xs sm:text-sm font-medium leading-relaxed">';
            $html .= '<span class="font-extrabold uppercase text-[10px] tracking-wider px-1.5 py-0.5 rounded bg-white/80 border border-slate-200 mr-1.5 inline-block">' . $style['badge'] . '</span> ';
            $html .= htmlspecialchars($msg);
            $html .= '</div>';
            $html .= '<button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-700 transition-colors p-1"><i class="fa-solid fa-xmark text-sm"></i></button>';
            $html .= '</div>';
        }
    }
    return $html;
}

/**
 * Format Tanggal Indonesia
 */
function format_date_id($datetime, $withTime = true) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00' || $datetime === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($datetime);
    $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    
    $d = date('j', $timestamp);
    $m = $months[(int)date('n', $timestamp)];
    $y = date('Y', $timestamp);
    
    if ($withTime) {
        $time = date('H:i', $timestamp);
        return "$d $m $y, $time WIB";
    }
    
    return "$d $m $y";
}

/**
 * Format Status Kendaraan Badge
 */
function format_badge_status($status, $reason = null) {
    switch ($status) {
        case 'active':
            return '<span class="badge badge-success"><i class="fa-solid fa-circle-dot mr-1 text-xs"></i> Active</span>';
        case 'peremajaan':
            return '<span class="badge badge-warning"><i class="fa-solid fa-arrows-rotate mr-1 text-xs"></i> Peremajaan</span>';
        case 'inactive':
            $reasonText = $reason ? ' (' . ucfirst($reason) . ')' : '';
            return '<span class="badge badge-danger"><i class="fa-solid fa-ban mr-1 text-xs"></i> Inactive' . htmlspecialchars($reasonText) . '</span>';
        default:
            return '<span class="badge badge-neutral">' . htmlspecialchars($status) . '</span>';
    }
}

/**
 * Format Hasil Inspeksi Badge
 */
function format_badge_hasil($hasil) {
    switch ($hasil) {
        case 'ok':
            return '<span class="badge badge-success"><i class="fa-solid fa-circle-check mr-1"></i> OK (Layak)</span>';
        case 'minor':
            return '<span class="badge badge-warning"><i class="fa-solid fa-wrench mr-1"></i> Minor Service</span>';
        case 'mayor':
            return '<span class="badge badge-danger badge-pulse"><i class="fa-solid fa-hand mr-1"></i> STOP (Mayor)</span>';
        default:
            return '<span class="badge badge-neutral"><i class="fa-solid fa-clock mr-1"></i> Belum Selesai</span>';
    }
}

/**
 * Ambil Parameter Pagination (Page, Per Page, Offset)
 */
function get_pagination_params($defaultPerPage = 10) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(5, min(100, (int)($_GET['per_page'] ?? $defaultPerPage)));
    $offset = ($page - 1) * $perPage;

    return [
        'page'     => $page,
        'per_page' => $perPage,
        'offset'   => $offset
    ];
}

/**
 * Render Komponen Navigasi Pagination Responsif & Bersih
 */
function render_pagination($currentPage, $totalItems, $perPage = 10, $customParams = null) {
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $from = ($totalItems > 0) ? (($currentPage - 1) * $perPage) + 1 : 0;
    $to   = min($currentPage * $perPage, $totalItems);

    // Ambil semua parameter URL saat ini untuk dipertahankan saat pindah halaman
    $params = ($customParams !== null) ? $customParams : $_GET;
    unset($params['page']); // Hapus parameter page lama
    unset($params['ajax']); // Jangan sampai flag internal AJAX ikut tersimpan di URL browser

    $buildUrl = function($targetPage) use ($params) {
        $queryData = array_merge($params, ['page' => $targetPage]);
        return '?' . http_build_query($queryData);
    };

    $perPageOptions = [10, 25, 50, 100];
    $buildPerPageUrl = function($newPerPage) use ($params) {
        $queryData = array_merge($params, ['per_page' => $newPerPage, 'page' => 1]);
        return '?' . http_build_query($queryData);
    };

    ob_start();
    ?>
    <div class="px-4 py-3 bg-white border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600">
        <!-- Text Info & Show Entries Selector -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
            <div class="flex items-center gap-1.5">
                <span>Tampilkan</span>
                <select onchange="ajaxNavigate(this.value)" class="px-2 py-1 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:border-blue-600 outline-none">
                    <?php foreach ($perPageOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($buildPerPageUrl($opt)) ?>" <?= $perPage == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
                <span>data</span>
            </div>
            <div>
                Menampilkan <span class="font-bold text-slate-900"><?= $from ?></span> - <span class="font-bold text-slate-900"><?= $to ?></span> dari <span class="font-bold text-slate-900"><?= $totalItems ?></span> data
                <?php if ($totalPages > 1): ?>
                    <span class="text-slate-400 text-[11px]">(Hal <?= $currentPage ?>/<?= $totalPages ?>)</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Buttons Pagination -->
        <div class="flex items-center gap-1">
            <!-- Prev Button -->
            <?php if ($currentPage > 1): ?>
                <a href="<?= $buildUrl($currentPage - 1) ?>" class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold transition-all flex items-center gap-1">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                </a>
            <?php else: ?>
                <span class="px-2.5 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-slate-300 font-semibold cursor-not-allowed flex items-center gap-1">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                </span>
            <?php endif; ?>

            <!-- Page Number Links -->
            <?php
                $startPage = max(1, $currentPage - 2);
                $endPage   = min($totalPages, $currentPage + 2);

                if ($startPage > 1) {
                    echo '<a href="' . $buildUrl(1) . '" class="w-8 h-8 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold flex items-center justify-center transition-all">1</a>';
                    if ($startPage > 2) {
                        echo '<span class="px-1 text-slate-400">...</span>';
                    }
                }

                for ($p = $startPage; $p <= $endPage; $p++) {
                    if ($p === $currentPage) {
                        echo '<span class="w-8 h-8 rounded-lg bg-blue-700 text-white font-bold flex items-center justify-center shadow-xs">' . $p . '</span>';
                    } else {
                        echo '<a href="' . $buildUrl($p) . '" class="w-8 h-8 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold flex items-center justify-center transition-all">' . $p . '</a>';
                    }
                }

                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span class="px-1 text-slate-400">...</span>';
                    }
                    echo '<a href="' . $buildUrl($totalPages) . '" class="w-8 h-8 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold flex items-center justify-center transition-all">' . $totalPages . '</a>';
                }
            ?>

            <!-- Next Button -->
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= $buildUrl($currentPage + 1) ?>" class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold transition-all flex items-center gap-1">
                    Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </a>
            <?php else: ?>
                <span class="px-2.5 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-slate-300 font-semibold cursor-not-allowed flex items-center gap-1">
                    Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
