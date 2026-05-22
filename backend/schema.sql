-- ============================================================
-- schema.sql
-- Sistem Inventaris Gudang — Node.js Edition
-- ============================================================
-- Jalankan script ini SATU KALI untuk setup database awal.
-- Semua statement bersifat idempotent (aman diulang).
-- ============================================================

-- Buat & pilih database
CREATE DATABASE IF NOT EXISTS db_inventaris
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE db_inventaris;

-- ============================================================
--  TABEL INTI
-- ============================================================

-- Tabel pengguna sistem
CREATE TABLE IF NOT EXISTS users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(50)  NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  role         ENUM('admin','karyawan') NOT NULL DEFAULT 'karyawan',
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel barang
CREATE TABLE IF NOT EXISTS barang (
  id              VARCHAR(20)  PRIMARY KEY,
  nama            VARCHAR(150) NOT NULL,
  kategori        VARCHAR(50)  NOT NULL,
  stok            INT          NOT NULL DEFAULT 0,
  dipakai         INT          NOT NULL DEFAULT 0,
  minimum_stock   INT          NOT NULL DEFAULT 5,
  email_notif_sent TINYINT(1)  DEFAULT 0,
  last_notif_at   TIMESTAMP    NULL,
  qr_code_data    VARCHAR(255) NULL,
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_barang_kategori (kategori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TABEL LOKASI RAK MULTI-CABANG
-- ============================================================

-- Cabang gudang
CREATE TABLE IF NOT EXISTS cabang_gudang (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  kode       VARCHAR(20)  NOT NULL UNIQUE,
  nama       VARCHAR(100) NOT NULL,
  alamat     TEXT,
  is_active  TINYINT(1)   DEFAULT 1,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cabang_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lokasi rak (zona → baris → level)
CREATE TABLE IF NOT EXISTS lokasi_rak (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  cabang_id    INT          NOT NULL,
  zona         VARCHAR(10)  NOT NULL,
  baris        VARCHAR(10)  NOT NULL,
  level_rak    VARCHAR(10)  NOT NULL,
  kode_lengkap VARCHAR(60)  NOT NULL UNIQUE,
  kapasitas    INT          DEFAULT 100,
  is_active    TINYINT(1)   DEFAULT 1,
  FOREIGN KEY (cabang_id) REFERENCES cabang_gudang(id) ON DELETE CASCADE,
  INDEX idx_rak_cabang      (cabang_id),
  INDEX idx_rak_kode        (kode_lengkap),
  INDEX idx_rak_zona_baris  (cabang_id, zona, baris, level_rak)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Posisi barang di rak (N:M antara barang ↔ lokasi_rak)
-- Primary Key komposit (barang_id, lokasi_id) dijamin oleh UNIQUE KEY
CREATE TABLE IF NOT EXISTS barang_lokasi (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  barang_id  VARCHAR(20) NOT NULL,
  lokasi_id  INT         NOT NULL,
  jumlah     INT         NOT NULL DEFAULT 0,
  updated_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (barang_id) REFERENCES barang(id)    ON DELETE CASCADE,
  FOREIGN KEY (lokasi_id) REFERENCES lokasi_rak(id) ON DELETE CASCADE,
  UNIQUE KEY  uq_barang_lokasi (barang_id, lokasi_id),
  INDEX idx_bl_barang  (barang_id),
  INDEX idx_bl_lokasi  (lokasi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TABEL RIWAYAT MUTASI
-- ============================================================

CREATE TABLE IF NOT EXISTS riwayat_mutasi (
  id               BIGINT AUTO_INCREMENT PRIMARY KEY,
  barang_id        VARCHAR(20)                    NOT NULL,
  user_id          INT                            NOT NULL,
  tipe             ENUM('masuk','keluar','pindah') NOT NULL,
  jumlah           INT                            NOT NULL,
  lokasi_asal_id   INT                            NULL,
  lokasi_tujuan_id INT                            NULL,
  catatan          TEXT,
  created_at       TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rm_barang       (barang_id),
  INDEX idx_rm_user         (user_id),
  INDEX idx_rm_created      (created_at),
  INDEX idx_rm_tipe_created (tipe, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  LOG NOTIFIKASI EMAIL
-- ============================================================

CREATE TABLE IF NOT EXISTS notifikasi_log (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  barang_id    VARCHAR(20)  NOT NULL,
  tipe         VARCHAR(50)  DEFAULT 'low_stock',
  email_target VARCHAR(255),
  status       ENUM('sent','failed') NOT NULL,
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_barang  (barang_id),
  INDEX idx_notif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  VIEWS ANALITIK
-- ============================================================

-- Ringkasan mutasi harian (90 hari terakhir)
CREATE OR REPLACE VIEW v_mutasi_harian AS
  SELECT
    DATE(created_at)  AS tanggal,
    tipe,
    COUNT(*)          AS jumlah_transaksi,
    SUM(jumlah)       AS total_unit
  FROM riwayat_mutasi
  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
  GROUP BY DATE(created_at), tipe;

-- Top 10 barang paling banyak keluar
CREATE OR REPLACE VIEW v_top_barang AS
  SELECT
    rm.barang_id,
    b.nama,
    b.kategori,
    SUM(rm.jumlah) AS total_keluar
  FROM riwayat_mutasi rm
  JOIN barang b ON b.id = rm.barang_id
  WHERE rm.tipe = 'keluar'
  GROUP BY rm.barang_id, b.nama, b.kategori
  ORDER BY total_keluar DESC
  LIMIT 10;

-- ============================================================
--  SEED DATA DEFAULT
-- ============================================================

-- Users (password: admin123 / karyawan123 — hash bcrypt rounds=10)
INSERT IGNORE INTO users (username, password, nama_lengkap, role) VALUES
  ('admin',    '$2b$10$GH0wHzKt1Qz9y/Ib0MqBiOqfXgHJKs0FVNhkXSo.5P5dJLKKLMWcy', 'Admin Inventaris', 'admin'),
  ('karyawan', '$2b$10$o0mAe/VJDlpD5a8VxBCJBuovhGHUbf1HlvA/Wq5KfhQ7RV0MXFKPK', 'Karyawan Staff',   'karyawan');

-- Barang sample
INSERT IGNORE INTO barang (id, nama, kategori, stok, dipakai, minimum_stock) VALUES
  ('INV-001', 'MacBook Pro M3 Max 16"',            'Elektronik', 12, 8,  3),
  ('INV-002', 'Kursi Kerja Ergonomis Herman Miller','Furnitur',   25, 20, 5),
  ('INV-003', 'Monitor Dell UltraSharp 27"',        'Elektronik', 15, 10, 3),
  ('INV-004', 'Keychron K2 Mechanical Keyboard',    'Elektronik',  4,  6, 5),
  ('INV-005', 'Meja Kantor Kayu Jati Minimalis',    'Furnitur',    8,  8, 2),
  ('INV-006', 'Papan Tulis Kaca Magnetic',          'Furnitur',    2,  3, 2),
  ('INV-007', 'Kertas A4 Sinar Dunia 80gsm',        'ATK',        45,  0,10),
  ('INV-008', 'Pulpen Gel Pilot G2 (Box of 12)',    'ATK',         0, 12,10);

-- Cabang gudang
INSERT IGNORE INTO cabang_gudang (kode, nama, alamat) VALUES
  ('GDG-JKT-01', 'Gudang Jakarta Pusat',  'Jl. Kemayoran No.1, Jakarta Pusat'),
  ('GDG-BDG-01', 'Gudang Bandung Utara',  'Jl. Pasteur No.55, Bandung'),
  ('GDG-SBY-01', 'Gudang Surabaya Timur', 'Jl. Rungkut No.10, Surabaya');

-- Rak untuk setiap cabang (zona A-B × baris 01-02 × level L1-L3 = 12 rak/cabang, 36 total)
INSERT IGNORE INTO lokasi_rak (cabang_id, zona, baris, level_rak, kode_lengkap)
SELECT c.id, z.zona, b.baris, l.level_rak,
       CONCAT(c.kode, '-', z.zona, '-', b.baris, '-', l.level_rak)
FROM cabang_gudang c
CROSS JOIN (SELECT 'A' AS zona UNION SELECT 'B') z
CROSS JOIN (SELECT '01' AS baris UNION SELECT '02') b
CROSS JOIN (SELECT 'L1' AS level_rak UNION SELECT 'L2' UNION SELECT 'L3') l;

-- ============================================================
--  SEED POSISI BARANG DI RAK (barang_lokasi)
--  Query menggunakan kode_lengkap sebagai referensi agar
--  tidak bergantung pada nilai ID auto_increment
-- ============================================================

-- INV-001 | MacBook Pro M3 Max 16" | 12 unit → 3 cabang
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-001', id, 5 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-01-L1';
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-001', id, 4 FROM lokasi_rak WHERE kode_lengkap = 'GDG-BDG-01-A-02-L3';
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-001', id, 3 FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-B-01-L2';

-- INV-002 | Kursi Ergonomis Herman Miller | 25 unit → Jakarta & Surabaya
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-002', id, 10 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-B-01-L1';
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-002', id,  8 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-B-02-L1';
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-002', id,  7 FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-A-01-L1';

-- INV-003 | Monitor Dell UltraSharp 27" | 15 unit → Jakarta & Bandung
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-003', id,  7 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-01-L2';
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-003', id,  8 FROM lokasi_rak WHERE kode_lengkap = 'GDG-BDG-01-B-01-L1';

-- INV-004 | Keychron K2 Keyboard | 4 unit → Jakarta saja (stok terbatas)
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-004', id,  4 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-02-L1';

-- INV-005 | Meja Kantor Kayu Jati | 8 unit → Surabaya (gudang besar)
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-005', id,  5 FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-B-01-L1';
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-005', id,  3 FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-B-02-L1';

-- INV-006 | Papan Tulis Kaca Magnetic | 2 unit → Bandung (stok kritis)
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-006', id,  2 FROM lokasi_rak WHERE kode_lengkap = 'GDG-BDG-01-A-01-L2';

-- INV-007 | Kertas A4 80gsm | 45 unit → semua cabang (consumable)
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-007', id, 20 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-02-L2';
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-007', id, 15 FROM lokasi_rak WHERE kode_lengkap = 'GDG-BDG-01-B-02-L2';
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-007', id, 10 FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-A-02-L1';

-- INV-008 | Pulpen Gel Pilot G2 | 0 unit → stok habis, tidak ada posisi rak

-- ============================================================
--  SEED RIWAYAT MUTASI (histori realistis)
-- ============================================================
INSERT IGNORE INTO riwayat_mutasi
  (barang_id, user_id, tipe, jumlah, lokasi_asal_id, lokasi_tujuan_id, catatan, created_at)
VALUES
  ('INV-001', 1, 'masuk',  5, NULL,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-01-L1'),
    'Penerimaan dari supplier Apple Indonesia', DATE_SUB(NOW(), INTERVAL 7 DAY)),

  ('INV-001', 1, 'masuk',  4, NULL,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-BDG-01-A-02-L3'),
    'Transfer ke cabang Bandung', DATE_SUB(NOW(), INTERVAL 5 DAY)),

  ('INV-002', 1, 'masuk',  7, NULL,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-A-01-L1'),
    'Penerimaan furnitur batch pertama', DATE_SUB(NOW(), INTERVAL 6 DAY)),

  ('INV-003', 2, 'keluar', 3,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-01-L2'),
    NULL, 'Distribusi ke ruang rapat lt.3', DATE_SUB(NOW(), INTERVAL 3 DAY)),

  ('INV-004', 1, 'pindah', 2,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-01-L3'),
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-02-L1'),
    'Reorganisasi rak zona A', DATE_SUB(NOW(), INTERVAL 2 DAY)),

  ('INV-007', 1, 'masuk', 20, NULL,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-02-L2'),
    'Restock kertas A4 — pesanan bulanan', DATE_SUB(NOW(), INTERVAL 1 DAY)),

  ('INV-008', 2, 'keluar', 12,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-B-02-L3'),
    NULL, 'Distribusi ke semua departemen', DATE_SUB(NOW(), INTERVAL 4 HOUR)),

  ('INV-007', 2, 'masuk', 10, NULL,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-A-02-L1'),
    'Kiriman dari distributor Surabaya', DATE_SUB(NOW(), INTERVAL 1 HOUR));

