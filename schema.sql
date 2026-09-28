-- Skema Database Fleet Inspection
-- Database: fleet_inspection_db

CREATE DATABASE IF NOT EXISTS fleet_inspection_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fleet_inspection_db;

-- 0. Tabel Master Cabang
CREATE TABLE IF NOT EXISTS cabang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_cabang VARCHAR(20) UNIQUE NOT NULL,    -- misal 'JKT-PST', 'SBY', 'BDG'
  nama_cabang VARCHAR(100) NOT NULL,          -- misal 'Jakarta Pusat (Pusat)'
  alamat TEXT NULL,
  penanggung_jawab VARCHAR(100) NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1. Tabel Kendaraan
CREATE TABLE IF NOT EXISTS kendaraan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id VARCHAR(20) UNIQUE NOT NULL,      -- misal 'FLEET-001', auto-generate
  no_polisi VARCHAR(20) NOT NULL,
  merk VARCHAR(50) NOT NULL,
  model VARCHAR(50) NOT NULL,
  jenis_kendaraan ENUM('Motor','Mobil') NOT NULL DEFAULT 'Mobil',
  vin VARCHAR(50) NULL,                      -- no rangka
  cabang VARCHAR(50) NOT NULL,
  status ENUM('active','peremajaan','inactive') DEFAULT 'active',
  status_inactive_reason ENUM('dijual','lainnya') NULL,
  tgl_stnk_expired DATE NULL,                -- STNK, perpanjangan tahunan
  tgl_kir_expired DATE NULL,
  tgl_plat_expired DATE NULL,                -- Plat Nomor / TNKB, perpanjangan 5 tahunan
  qr_code_path VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel Pengguna (Users)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','petugas','pic') NOT NULL,
  cabang VARCHAR(50) NULL,
  no_wa VARCHAR(20) NULL,                     -- untuk role pic, tujuan alert WA
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel Sesi Inspeksi
CREATE TABLE IF NOT EXISTS inspeksi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kendaraan_id INT NOT NULL,
  petugas_id INT NOT NULL,
  tanggal_mulai DATETIME NOT NULL,
  tanggal_selesai DATETIME NULL,
  page_progress TINYINT DEFAULT 0,            -- 0-4, untuk lanjutkan sesi terputus
  odometer INT NULL,
  bbm_level VARCHAR(20) NULL,
  hasil_akhir ENUM('ok','minor','mayor') NULL,
  catatan_umum TEXT NULL,
  FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id) ON DELETE CASCADE,
  FOREIGN KEY (petugas_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel Detail Item Checklist Inspeksi
CREATE TABLE IF NOT EXISTS inspeksi_detail (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inspeksi_id INT NOT NULL,
  kategori ENUM('eksterior','interior_kelistrikan','mesin','kaki_kaki') NOT NULL,
  item_nama VARCHAR(100) NOT NULL,
  kondisi ENUM('ok','rusak','perlu_perhatian') NOT NULL,
  foto_path VARCHAR(255) NULL,
  catatan VARCHAR(255) NULL,
  FOREIGN KEY (inspeksi_id) REFERENCES inspeksi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabel Riwayat Tindak Lanjut (Follow-up)
CREATE TABLE IF NOT EXISTS riwayat_tindak_lanjut (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inspeksi_id INT NOT NULL,
  kendaraan_id INT NOT NULL,
  jenis ENUM('mayor_unsafe','minor_service') NOT NULL,
  status_notifikasi ENUM('terkirim','gagal','pending') DEFAULT 'pending',
  keterangan TEXT NULL,
  tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inspeksi_id) REFERENCES inspeksi(id) ON DELETE CASCADE,
  FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabel Log Perubahan / Refresh Armada
CREATE TABLE IF NOT EXISTS kendaraan_log_perubahan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kendaraan_id INT NOT NULL,
  jenis ENUM('perubahan_identitas','refresh_armada') NOT NULL,
  data_lama JSON NULL,
  data_baru JSON NULL,
  asset_id_baru VARCHAR(20) NULL,             -- diisi kalau refresh_armada
  oleh_user_id INT NULL,
  tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabel Riwayat Servis / Perawatan Kendaraan
CREATE TABLE IF NOT EXISTS riwayat_servis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kendaraan_id INT NULL,                      -- NULL = entri umum, tidak terkait 1 kendaraan spesifik (mis. beli sparepart untuk beberapa unit sekaligus)
  proyek VARCHAR(100) NULL,                   -- misal 'TBT'
  tanggal_servis DATE NOT NULL,
  km_servis INT NULL,                         -- odometer kendaraan saat servis
  nominal DECIMAL(12,0) NULL,                 -- biaya servis (Rupiah)
  jenis_servis VARCHAR(150) NOT NULL,         -- misal 'Ganti Oli', 'Servis Rem', 'Tune Up'
  bengkel VARCHAR(150) NULL,                  -- nama bengkel / vendor yang mengerjakan
  catatan_bengkel TEXT NULL,                  -- catatan tambahan dari bengkel
  keterangan TEXT NULL,
  belum_diganti VARCHAR(255) NULL,            -- item yang belum diganti / masih perlu ditindaklanjuti
  oleh_user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id) ON DELETE CASCADE,
  FOREIGN KEY (oleh_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indeks Tambahan untuk Performa Query
CREATE INDEX IF NOT EXISTS idx_kendaraan_asset_id ON kendaraan(asset_id);
CREATE INDEX IF NOT EXISTS idx_kendaraan_status ON kendaraan(status);
CREATE INDEX IF NOT EXISTS idx_inspeksi_kendaraan ON inspeksi(kendaraan_id);
CREATE INDEX IF NOT EXISTS idx_inspeksi_petugas ON inspeksi(petugas_id);
CREATE INDEX IF NOT EXISTS idx_inspeksi_tanggal ON inspeksi(tanggal_mulai);
CREATE INDEX IF NOT EXISTS idx_riwayat_servis_kendaraan ON riwayat_servis(kendaraan_id);
CREATE INDEX IF NOT EXISTS idx_riwayat_servis_tanggal ON riwayat_servis(tanggal_servis);
