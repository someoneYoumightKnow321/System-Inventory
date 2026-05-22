<?php
// config.php - Konfigurasi Koneksi Database MySQL dan Tabel Sistem (v2.0)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_inventaris');

// --- Konfigurasi Email (Fitur 2) ---
// Ganti dengan data SMTP Anda
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'email.gudang.anda@gmail.com'); // Ganti
define('MAIL_PASSWORD', 'your_app_password');           // Ganti dengan App Password Gmail
define('MAIL_FROM',     'email.gudang.anda@gmail.com');
define('MAIL_FROM_NAME','Sistem Inventaris Gudang');
define('LOGISTIK_EMAIL','tim.logistik@perusahaan.com'); // Penerima notifikasi

// Membuat koneksi ke server MySQL
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(["error" => "Koneksi ke MySQL gagal: " . $conn->connect_error]);
    exit();
}

// Membuat database secara otomatis jika belum ada
$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
$conn->select_db(DB_NAME);
$conn->set_charset('utf8mb4');

// ============================================================
// MIGRASI TABEL - Berjalan otomatis, idempotent (aman diulang)
// ============================================================

// --- Tabel Dasar (sudah ada) ---
$conn->query("CREATE TABLE IF NOT EXISTS barang (
    id VARCHAR(20) PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    dipakai INT NOT NULL DEFAULT 0,
    minimum_stock INT NOT NULL DEFAULT 5,
    email_notif_sent TINYINT(1) DEFAULT 0,
    last_notif_at TIMESTAMP NULL,
    qr_code_data VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Tambah kolom baru jika upgrade dari versi lama (safe ALTER)
$alter_queries = [
    "ALTER TABLE barang ADD COLUMN minimum_stock INT NOT NULL DEFAULT 5",
    "ALTER TABLE barang ADD COLUMN email_notif_sent TINYINT(1) DEFAULT 0",
    "ALTER TABLE barang ADD COLUMN last_notif_at TIMESTAMP NULL",
    "ALTER TABLE barang ADD COLUMN qr_code_data VARCHAR(255) NULL",
];
foreach ($alter_queries as $q) {
   // @$conn->query($q); // @ untuk suppress error jika kolom sudah ada
}

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('admin', 'karyawan') NOT NULL DEFAULT 'karyawan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// --- FITUR 1: Tabel Hierarki Lokasi Gudang ---
$conn->query("CREATE TABLE IF NOT EXISTS cabang_gudang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(20) UNIQUE NOT NULL,
    nama VARCHAR(100) NOT NULL,
    alamat TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS lokasi_rak (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cabang_id INT NOT NULL,
    zona VARCHAR(10) NOT NULL,
    baris VARCHAR(10) NOT NULL,
    level_rak VARCHAR(10) NOT NULL,
    kode_lengkap VARCHAR(60) UNIQUE NOT NULL,
    kapasitas INT DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (cabang_id) REFERENCES cabang_gudang(id) ON DELETE CASCADE,
    INDEX idx_cabang_rak (cabang_id),
    INDEX idx_kode_rak (kode_lengkap)
)");

$conn->query("CREATE TABLE IF NOT EXISTS barang_lokasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barang_id VARCHAR(20) NOT NULL,
    lokasi_id INT NOT NULL,
    jumlah INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE,
    FOREIGN KEY (lokasi_id) REFERENCES lokasi_rak(id) ON DELETE CASCADE,
    UNIQUE KEY uq_barang_lokasi (barang_id, lokasi_id),
    INDEX idx_bl_barang (barang_id),
    INDEX idx_bl_lokasi (lokasi_id)
)");

// --- Tabel Mutasi Stok (penting untuk Fitur 1 real-time & Fitur 4 analitik) ---
$conn->query("CREATE TABLE IF NOT EXISTS riwayat_mutasi (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    barang_id VARCHAR(20) NOT NULL,
    user_id INT NOT NULL,
    tipe ENUM('masuk','keluar','pindah') NOT NULL,
    jumlah INT NOT NULL,
    lokasi_asal_id INT NULL,
    lokasi_tujuan_id INT NULL,
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rm_barang (barang_id),
    INDEX idx_rm_created (created_at),
    INDEX idx_rm_tipe_created (tipe, created_at)
)");

// --- FITUR 2: Log Notifikasi Email ---
$conn->query("CREATE TABLE IF NOT EXISTS notifikasi_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barang_id VARCHAR(20) NOT NULL,
    tipe VARCHAR(50) DEFAULT 'low_stock',
    email_target VARCHAR(255),
    status ENUM('sent','failed') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_barang (barang_id),
    INDEX idx_notif_created (created_at)
)");

// --- Views Analitik Teroptimasi (Fitur 4) ---
$conn->query("CREATE OR REPLACE VIEW v_mutasi_harian AS
    SELECT 
        DATE(created_at) AS tanggal,
        tipe,
        COUNT(*) AS jumlah_transaksi,
        SUM(jumlah) AS total_unit
    FROM riwayat_mutasi
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    GROUP BY DATE(created_at), tipe
");

$conn->query("CREATE OR REPLACE VIEW v_top_barang AS
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
    LIMIT 10
");

// ============================================================
// SEED DATA DEFAULT
// ============================================================

// Seed Users
$check_users = $conn->query("SELECT COUNT(*) as total FROM users");
$row = $check_users->fetch_assoc();
if ($row['total'] == 0) {
    $admin_pass    = password_hash('admin123',    PASSWORD_DEFAULT);
    $karyawan_pass = password_hash('karyawan123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password, nama_lengkap, role) VALUES 
        ('admin',    '$admin_pass',    'Admin Inventaris', 'admin'),
        ('karyawan', '$karyawan_pass', 'Karyawan Staff',   'karyawan')");
}

// Seed Cabang Gudang
$check_cabang = $conn->query("SELECT COUNT(*) as total FROM cabang_gudang");
$row = $check_cabang->fetch_assoc();
if ($row['total'] == 0) {
    $conn->query("INSERT INTO cabang_gudang (kode, nama, alamat) VALUES
        ('GDG-JKT-01', 'Gudang Jakarta Pusat',  'Jl. Kemayoran No.1, Jakarta Pusat'),
        ('GDG-BDG-01', 'Gudang Bandung Utara',  'Jl. Pasteur No.55, Bandung'),
        ('GDG-SBY-01', 'Gudang Surabaya Timur', 'Jl. Rungkut No.10, Surabaya')");
    
    // Seed beberapa rak untuk setiap cabang
    $cabang_ids = [];
    $res = $conn->query("SELECT id, kode FROM cabang_gudang");
    while ($c = $res->fetch_assoc()) {
        $cabang_ids[] = $c;
    }
    
    foreach ($cabang_ids as $cabang) {
        foreach (['A','B'] as $zona) {
            foreach (['01','02'] as $baris) {
                foreach (['L1','L2','L3'] as $level) {
                    $kode = $cabang['kode'].'-'.$zona.'-'.$baris.'-'.$level;
                    $stmt = $conn->prepare("INSERT IGNORE INTO lokasi_rak (cabang_id, zona, baris, level_rak, kode_lengkap) VALUES (?,?,?,?,?)");
                    $stmt->bind_param("issss", $cabang['id'], $zona, $baris, $level, $kode);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}
?>
