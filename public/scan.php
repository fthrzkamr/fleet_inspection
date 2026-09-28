<?php
/**
 * Halaman Scan QR Code Lapangan (Mobile Responsive & High Compatibility) - Enterprise UI
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pdo = get_db();
$user = current_user();

// Ambil daftar kendaraan aktif untuk quick test (dibatasi ke cabang sendiri
// untuk role petugas/pic yang sudah di-set cabang-nya)
$scopeCabang = (in_array($user['role'], ['petugas', 'pic']) && !empty($user['cabang'])) ? $user['cabang'] : null;
$stmt = $pdo->prepare("SELECT id, asset_id, no_polisi, merk, model, cabang FROM kendaraan WHERE status = 'active'"
    . ($scopeCabang ? " AND cabang = :cb" : "") . " ORDER BY id ASC LIMIT 8");
$stmt->execute($scopeCabang ? [':cb' => $scopeCabang] : []);
$quickVehicles = $stmt->fetchAll();

$pageTitle = 'Pindai QR Armada - ' . APP_NAME;
require_once __DIR__ . '/../includes/layout_header.php';
require_once __DIR__ . '/../includes/layout_navbar.php';
?>

<!-- Include html5-qrcode library (local copy, no CDN dependency) -->
<script src="<?= BASE_URL ?>/public/assets/js/html5-qrcode.min.js" type="text/javascript"></script>

<div class="max-w-md mx-auto space-y-4">
    <!-- Header Title -->
    <div class="text-center">
        <h1 class="text-lg sm:text-xl font-bold text-slate-900 flex items-center justify-center gap-2">
            <i class="fa-solid fa-qrcode text-emerald-600"></i> Pindai Stiker QR Kendaraan
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">Arahkan kamera ke stiker QR unit armada untuk memulai atau melanjutkan inspeksi.</p>
    </div>

    <!-- Scanner Box Container -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm relative space-y-3">
        <!-- Live Camera Viewport -->
        <div id="reader-wrapper" class="relative rounded-xl overflow-hidden bg-slate-950 min-h-[260px] max-w-[340px] mx-auto border-2 border-slate-700 shadow-inner flex flex-col items-center justify-center">
            <div id="reader" class="w-full h-full"></div>
            
            <!-- Target Framing Guide Overlay -->
            <div id="scanner-guide" class="absolute inset-0 pointer-events-none flex flex-col items-center justify-center p-4">
                <div class="w-48 h-48 border-2 border-dashed border-emerald-400 rounded-xl relative shadow-xs">
                    <div class="absolute -top-1 -left-1 w-5 h-5 border-t-4 border-l-4 border-emerald-400"></div>
                    <div class="absolute -top-1 -right-1 w-5 h-5 border-t-4 border-r-4 border-emerald-400"></div>
                    <div class="absolute -bottom-1 -left-1 w-5 h-5 border-b-4 border-l-4 border-emerald-400"></div>
                    <div class="absolute -bottom-1 -right-1 w-5 h-5 border-b-4 border-r-4 border-emerald-400"></div>
                </div>
                <div class="mt-3 bg-black/80 text-white px-3 py-1 rounded-full text-[11px] font-semibold tracking-wide">
                    Posisikan QR di dalam kotak
                </div>
            </div>
        </div>

        <!-- Scan Status Feedback -->
        <div id="scan-status" class="text-center text-xs font-semibold text-slate-600 min-h-[24px]">
            <i class="fa-solid fa-circle-notch fa-spin text-blue-600 mr-1"></i> Mengaktifkan sensor kamera...
        </div>

        <!-- Controls: Camera Toggle & File Upload -->
        <div class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-100">
            <button id="btn-toggle-camera" type="button" class="btn-secondary text-xs py-2 px-2 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-camera-rotate text-blue-700"></i> Ganti Kamera
            </button>
            <label class="btn-secondary text-xs py-2 px-2 flex items-center justify-center gap-1.5 cursor-pointer text-center">
                <i class="fa-solid fa-image text-emerald-600"></i> Scan File / Foto
                <input type="file" id="qr-file-input" accept="image/*" class="hidden">
            </label>
        </div>
    </div>

    <!-- Manual Input & Quick Picker Fallback -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2 flex items-center gap-1.5">
            <i class="fa-solid fa-keyboard text-blue-700"></i> Atau Masukkan Asset ID Manual:
        </h3>
        <form action="<?= BASE_URL ?>/public/inspeksi_mulai.php" method="GET" class="flex gap-2">
            <input type="text" name="asset_id" required placeholder="Contoh: FLEET-001" 
                   class="flex-1 px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 uppercase focus:border-blue-600 outline-none font-mono">
            <button type="submit" class="btn-primary text-xs py-2 px-3.5">
                Buka Cek <i class="fa-solid fa-arrow-right ml-1"></i>
            </button>
        </form>

        <!-- Quick Demo Vehicle Pills -->
        <div class="mt-4 pt-3 border-t border-slate-100">
            <div class="text-[11px] font-semibold text-slate-500 mb-2">Pilih Cepat Unit Armada:</div>
            <div class="grid grid-cols-2 gap-2">
                <?php foreach ($quickVehicles as $qv): ?>
                    <a href="<?= BASE_URL ?>/public/inspeksi_mulai.php?asset_id=<?= urlencode($qv['asset_id']) ?>" 
                       class="p-2.5 bg-slate-50 hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-lg transition-all flex flex-col text-left">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-bold text-blue-700"><?= htmlspecialchars($qv['asset_id']) ?></span>
                            <span class="text-[10px] text-slate-400"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
                        </div>
                        <div class="text-xs font-bold text-slate-900 truncate mt-0.5"><?= htmlspecialchars($qv['no_polisi']) ?></div>
                        <div class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($qv['merk']) ?> <?= htmlspecialchars($qv['model']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
let html5QrCode = null;
let currentFacingMode = "environment";
let isRedirecting = false;
let availableCameras = [];
let currentCameraIndex = 0;

// Handle Success Decode
function onScanSuccess(decodedText, decodedResult) {
    if (isRedirecting) return;
    isRedirecting = true;

    // Audio Feedback Beep
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, audioCtx.currentTime);
        osc.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.15);
    } catch(e) {}

    // Visual Feedback
    const statusEl = document.getElementById('scan-status');
    statusEl.innerHTML = `
        <span class="text-emerald-700 font-bold text-xs"><i class="fa-solid fa-circle-check text-sm"></i> QR Terdeteksi: ${decodedText} &rarr; Membuka...</span>
    `;

    // Stop scanning cleanly and redirect immediately
    try {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().catch(() => {});
        }
    } catch(e) {}

    setTimeout(() => {
        redirectToInspection(decodedText);
    }, 250);
}

// Robust Payload Parser & Redirector
function redirectToInspection(qrPayload) {
    let cleanPayload = (qrPayload || '').trim();
    
    // Check if contains asset_id query param
    if (cleanPayload.includes('asset_id=')) {
        const match = cleanPayload.match(/asset_id=([^&]+)/);
        if (match) {
            window.location.href = '<?= BASE_URL ?>/public/inspeksi_mulai.php?asset_id=' + encodeURIComponent(match[1]);
            return;
        }
    }

    // Check if contains id query param (e.g. ?id=4)
    if (cleanPayload.includes('id=')) {
        const matchId = cleanPayload.match(/[?&]id=(\d+)/);
        if (matchId) {
            window.location.href = '<?= BASE_URL ?>/public/inspeksi_mulai.php?id=' + encodeURIComponent(matchId[1]);
            return;
        }
    }

    // Check if matches FLEET-XXX pattern anywhere in the string
    const matchFleet = cleanPayload.match(/(FLEET-\d+)/i);
    if (matchFleet) {
        window.location.href = '<?= BASE_URL ?>/public/inspeksi_mulai.php?asset_id=' + encodeURIComponent(matchFleet[1].toUpperCase());
        return;
    }

    // Default: pass raw payload as asset_id
    window.location.href = '<?= BASE_URL ?>/public/inspeksi_mulai.php?asset_id=' + encodeURIComponent(cleanPayload);
}

function startScanner() {
    const statusEl = document.getElementById('scan-status');

    if (typeof Html5Qrcode === 'undefined') {
        handleCameraError(new Error('Library html5-qrcode gagal dimuat'));
        return;
    }

    statusEl.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin text-blue-700 mr-1"></i> Mengaktifkan sensor kamera...`;

    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("reader", {
            formatsToSupport: [ Html5QrcodeSupportedFormats.QR_CODE ],
            verbose: false,
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            }
        });
    }

    const config = {
        fps: 20,
        aspectRatio: 1.0,
        experimentalFeatures: {
            useBarCodeDetectorIfSupported: true
        }
    };

    // If cameras list available, use deviceId; else use facingMode
    if (availableCameras.length > 0) {
        const cameraId = availableCameras[currentCameraIndex].id;
        html5QrCode.start(
            cameraId,
            config,
            onScanSuccess,
            (errorMessage) => {}
        ).then(() => {
            statusEl.innerHTML = '<span class="text-emerald-700 font-semibold"><i class="fa-solid fa-video mr-1"></i> Kamera Aktif & Siap Memindai</span>';
        }).catch(err => {
            handleCameraError(err);
        });
    } else {
        html5QrCode.start(
            { facingMode: currentFacingMode },
            config,
            onScanSuccess,
            (errorMessage) => {}
        ).then(() => {
            statusEl.innerHTML = '<span class="text-emerald-700 font-semibold"><i class="fa-solid fa-video mr-1"></i> Kamera Aktif & Siap Memindai</span>';
        }).catch(err => {
            // Fallback try user facing mode
            html5QrCode.start(
                { facingMode: "user" },
                config,
                onScanSuccess,
                (errorMessage) => {}
            ).then(() => {
                statusEl.innerHTML = '<span class="text-emerald-700 font-semibold"><i class="fa-solid fa-video mr-1"></i> Kamera Depan Aktif</span>';
            }).catch(e2 => {
                handleCameraError(e2);
            });
        });
    }
}

function handleCameraError(err) {
    console.warn("Camera init error:", err);
    document.getElementById('scan-status').innerHTML = `
        <span class="text-amber-700 text-[11px]"><i class="fa-solid fa-triangle-exclamation"></i> Kamera tidak aktif atau belum diizinkan. Gunakan tombol <b>Scan File/Foto</b> atau input manual di bawah.</span>
    `;
}

document.addEventListener('DOMContentLoaded', () => {
    // Check available devices
    if (typeof Html5Qrcode === 'undefined') {
        handleCameraError(new Error('Library html5-qrcode gagal dimuat'));
    } else {
        try {
            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length) {
                    availableCameras = devices;
                    // Prefer back camera if found
                    const backIndex = devices.findIndex(d => d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('environment') || d.label.toLowerCase().includes('belakang'));
                    if (backIndex !== -1) {
                        currentCameraIndex = backIndex;
                    }
                }
                startScanner();
            }).catch(() => {
                startScanner();
            });
        } catch (e) {
            handleCameraError(e);
        }
    }

    // Toggle Camera Button
    document.getElementById('btn-toggle-camera').addEventListener('click', () => {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                if (availableCameras.length > 1) {
                    currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
                } else {
                    currentFacingMode = (currentFacingMode === "environment") ? "user" : "environment";
                }
                startScanner();
            }).catch(() => {
                startScanner();
            });
        } else {
            startScanner();
        }
    });

    // File Input Scanner Handler
    const fileInput = document.getElementById('qr-file-input');
    fileInput.addEventListener('change', e => {
        if (!e.target.files || e.target.files.length === 0) return;
        const file = e.target.files[0];
        
        const statusEl = document.getElementById('scan-status');
        statusEl.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin text-blue-700 mr-1"></i> Membaca gambar QR...`;

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
        }

        html5QrCode.scanFile(file, true)
            .then(decodedText => {
                onScanSuccess(decodedText);
            })
            .catch(err => {
                statusEl.innerHTML = `
                    <span class="text-rose-600 font-semibold text-xs"><i class="fa-solid fa-circle-xmark mr-1"></i> Kode QR tidak terbaca pada file ini. Silakan coba foto yang lebih jelas.</span>
                `;
            });
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/layout_footer.php';
?>
