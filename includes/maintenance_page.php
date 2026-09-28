<?php
/**
 * Halaman Mode Maintenance
 * Sengaja dibuat mandiri (tanpa koneksi database / layout lain) supaya tetap
 * bisa tampil meski ada masalah lain pada sistem selama masa maintenance.
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - <?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistem') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/public/assets/img/favicon.svg">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            color: #0f172a;
            padding: 16px;
        }
        .card {
            background: #ffffff;
            border-radius: 20px;
            max-width: 440px;
            width: 100%;
            padding: 36px 32px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
        }
        .icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: #1d4ed8;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
        }
        h1 {
            font-size: 19px;
            font-weight: 800;
            margin: 0 0 10px;
            color: #0f172a;
        }
        p {
            font-size: 13.5px;
            line-height: 1.6;
            color: #475569;
            margin: 0;
        }
        .badge {
            display: inline-block;
            margin-top: 20px;
            padding: 5px 12px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#128295;</div>
        <h1>Sedang Dalam Maintenance</h1>
        <p><?= nl2br(htmlspecialchars(defined('MAINTENANCE_MESSAGE') ? MAINTENANCE_MESSAGE : 'Sistem sedang dalam perbaikan. Silakan coba lagi nanti.')) ?></p>
        <span class="badge">Mohon Maaf Atas Ketidaknyamanannya</span>
    </div>
</body>
</html>
