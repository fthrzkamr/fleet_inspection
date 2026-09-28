# FleetGuard Digital

Aplikasi web manajemen & inspeksi kendaraan operasional (fleet) berbasis PHP native — dibangun untuk mengelola data armada, jadwal pemeriksaan fisik kendaraan lapangan, riwayat servis, serta pengingat masa berlaku dokumen kendaraan (STNK, KIR, Plat/TNKB) secara terpusat lintas cabang.

## Fitur Utama

- **Kelola Armada** — registrasi kendaraan, penerbitan QR code unik per unit, riwayat perubahan identitas/refresh armada
- **Scan & Cek Lapangan** — inspeksi fisik kendaraan via scan QR, checklist 16 item (eksterior, interior/kelistrikan, mesin, kaki-kaki), dengan foto bukti
- **Riwayat Servis** — pencatatan histori servis/perawatan per kendaraan (termasuk entri umum tanpa kendaraan spesifik), import/export CSV
- **Dashboard** — rekap kondisi armada, grafik tren inspeksi, reminder dokumen jatuh tempo (STNK/KIR/Plat), dan reminder servis berkala berdasarkan KM
- **Master Cabang & Kelola User** — manajemen data cabang dan akses pengguna berbasis peran (Admin, PIC, Petugas) dengan data yang otomatis tersaring per cabang
- **Mode Maintenance** — mekanisme kunci akses seluruh sistem berbasis file konfigurasi, tanpa perlu database

## Teknologi

PHP native (kompatibel PHP 7.4+), MySQL/MariaDB, Tailwind CSS (CDN), vanilla JavaScript — tanpa framework backend.

## Setup Lokal

1. Clone repository ini ke folder `htdocs` XAMPP (atau web server lokal lainnya)
2. Buat database MySQL, lalu import `schema.sql`
3. Salin `config.local.example.php` menjadi `config.local.php`, sesuaikan kredensial database
4. Buka `public/login.php` di browser
