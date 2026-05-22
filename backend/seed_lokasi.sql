-- ============================================================
-- seed_lokasi.sql
-- Seed data posisi barang di rak untuk database yang SUDAH ADA.
-- Jalankan file ini jika tabel dan rak sudah terbentuk via schema.sql
-- tapi tabel barang_lokasi masih kosong / ingin direset.
--
-- Cara jalankan:
--   mysql -u root db_inventaris < seed_lokasi.sql
--   atau copy-paste ke phpMyAdmin
-- ============================================================

USE db_inventaris;

-- Bersihkan data lama agar aman dijalankan ulang
DELETE FROM barang_lokasi;

-- ============================================================
--  PEMETAAN POSISI BARANG DI RAK
--  Format query: cari lokasi_rak berdasarkan kode_lengkap (aman
--  meski ID auto_increment berbeda-beda di tiap mesin)
-- ============================================================

-- ──────────────────────────────────────────────────────────────
--  INV-001 | MacBook Pro M3 Max 16" | Stok: 12 unit
--  Tersebar di 3 cabang (elektronik premium → distribusi merata)
-- ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-001', id, 5 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-01-L1';

INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-001', id, 4 FROM lokasi_rak WHERE kode_lengkap = 'GDG-BDG-01-A-02-L3';

INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-001', id, 3 FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-B-01-L2';

-- ──────────────────────────────────────────────────────────────
--  INV-002 | Kursi Kerja Ergonomis Herman Miller | Stok: 25 unit
--  Furnitur besar → butuh banyak rak, dominan di Jakarta & Surabaya
-- ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-002', id, 10 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-B-01-L1';

INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-002', id,  8 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-B-02-L1';

INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-002', id,  7 FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-A-01-L1';

-- ──────────────────────────────────────────────────────────────
--  INV-003 | Monitor Dell UltraSharp 27" | Stok: 15 unit
--  Elektronik medium → 2 cabang
-- ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-003', id,  7 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-01-L2';

INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-003', id,  8 FROM lokasi_rak WHERE kode_lengkap = 'GDG-BDG-01-B-01-L1';

-- ──────────────────────────────────────────────────────────────
--  INV-004 | Keychron K2 Mechanical Keyboard | Stok: 4 unit
--  Stok sedikit → satu rak di Jakarta
-- ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-004', id,  4 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-02-L1';

-- ──────────────────────────────────────────────────────────────
--  INV-005 | Meja Kantor Kayu Jati Minimalis | Stok: 8 unit
--  Furnitur besar → di Surabaya (kapasitas gudang besar)
-- ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-005', id,  5 FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-B-01-L1';

INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-005', id,  3 FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-B-02-L1';

-- ──────────────────────────────────────────────────────────────
--  INV-006 | Papan Tulis Kaca Magnetic | Stok: 2 unit
--  Stok sangat sedikit (sudah hampir habis) → satu rak Bandung
-- ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-006', id,  2 FROM lokasi_rak WHERE kode_lengkap = 'GDG-BDG-01-A-01-L2';

-- ──────────────────────────────────────────────────────────────
--  INV-007 | Kertas A4 Sinar Dunia 80gsm | Stok: 45 unit
--  ATK consumable → tersebar di semua cabang
-- ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-007', id, 20 FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-02-L2';

INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-007', id, 15 FROM lokasi_rak WHERE kode_lengkap = 'GDG-BDG-01-B-02-L2';

INSERT IGNORE INTO barang_lokasi (barang_id, lokasi_id, jumlah)
SELECT 'INV-007', id, 10 FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-A-02-L1';

-- ──────────────────────────────────────────────────────────────
--  INV-008 | Pulpen Gel Pilot G2 | Stok: 0 unit
--  Stok habis → tidak ada posisi rak (sudah keluar semua)
-- ──────────────────────────────────────────────────────────────
-- (sengaja tidak di-insert — tidak ada barang di rak = normal)

-- ──────────────────────────────────────────────────────────────
--  SEED RIWAYAT MUTASI — data histori realistis
-- ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO riwayat_mutasi 
  (barang_id, user_id, tipe, jumlah, lokasi_asal_id, lokasi_tujuan_id, catatan, created_at)
VALUES
  -- Penerimaan awal INV-001 ke Jakarta
  ('INV-001', 1, 'masuk', 5,
    NULL,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-01-L1'),
    'Penerimaan dari supplier Apple Indonesia',
    DATE_SUB(NOW(), INTERVAL 7 DAY)),

  -- Penerimaan awal INV-001 ke Bandung
  ('INV-001', 1, 'masuk', 4,
    NULL,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-BDG-01-A-02-L3'),
    'Transfer dari gudang pusat ke cabang Bandung',
    DATE_SUB(NOW(), INTERVAL 5 DAY)),

  -- Pengiriman kursi ke cabang Surabaya
  ('INV-002', 1, 'masuk', 7,
    NULL,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-A-01-L1'),
    'Penerimaan furnitur batch pertama',
    DATE_SUB(NOW(), INTERVAL 6 DAY)),

  -- Monitor keluar untuk kantor Jakarta
  ('INV-003', 2, 'keluar', 3,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-01-L2'),
    NULL,
    'Distribusi ke ruang rapat lt.3',
    DATE_SUB(NOW(), INTERVAL 3 DAY)),

  -- Pemindahan keyboard antar rak
  ('INV-004', 1, 'pindah', 2,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-01-L3'),
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-02-L1'),
    'Reorganisasi rak zona A baris 01',
    DATE_SUB(NOW(), INTERVAL 2 DAY)),

  -- Kertas masuk baru ke semua cabang
  ('INV-007', 1, 'masuk', 20,
    NULL,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-A-02-L2'),
    'Restock kertas A4 — pesanan bulanan',
    DATE_SUB(NOW(), INTERVAL 1 DAY)),

  -- Pulpen keluar semua (stok habis)
  ('INV-008', 2, 'keluar', 12,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-JKT-01-B-02-L3'),
    NULL,
    'Distribusi ke semua departemen',
    DATE_SUB(NOW(), INTERVAL 4 HOUR)),

  -- Mutasi terbaru hari ini
  ('INV-007', 2, 'masuk', 10,
    NULL,
    (SELECT id FROM lokasi_rak WHERE kode_lengkap = 'GDG-SBY-01-A-02-L1'),
    'Kiriman dari distributor Surabaya',
    DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- ============================================================
--  VERIFIKASI — jalankan query ini untuk cek hasilnya
-- ============================================================
-- SELECT b.id, b.nama, b.stok AS stok_global,
--        SUM(bl.jumlah) AS stok_di_rak,
--        COUNT(bl.id)   AS jumlah_lokasi
-- FROM barang b
-- LEFT JOIN barang_lokasi bl ON bl.barang_id = b.id
-- GROUP BY b.id, b.nama, b.stok
-- ORDER BY b.id;
