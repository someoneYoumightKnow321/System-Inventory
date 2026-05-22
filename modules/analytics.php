<?php
// modules/analytics.php
// Fitur 4: API Data Dashboard Analitik (Query Teroptimasi)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once '../auth.php';
require_once '../config.php';

$current_user = get_current_user_session();
if (!$current_user) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized."]);
    exit();
}

$action   = $_GET['action']   ?? 'summary';
$range    = $_GET['range']    ?? 'weekly';   // daily|weekly|monthly
$limit_dt = match($range) {
    'daily'   => 'INTERVAL 1 DAY',
    'monthly' => 'INTERVAL 30 DAY',
    default   => 'INTERVAL 7 DAY',  // weekly
};

switch ($action) {

    // --------------------------------------------------------
    // Ringkasan Kartu Metrik Utama Dashboard
    // --------------------------------------------------------
    case 'summary':
        // Total barang terdaftar
        $r1 = $conn->query("SELECT COUNT(*) AS total_jenis, COALESCE(SUM(stok),0) AS total_unit FROM barang");
        $s1 = $r1->fetch_assoc();

        // Barang stok menipis (stok <= minimum_stock)
        $r2 = $conn->query("SELECT COUNT(*) AS jumlah FROM barang WHERE stok <= minimum_stock AND stok > 0");
        $s2 = $r2->fetch_assoc();

        // Barang habis
        $r3 = $conn->query("SELECT COUNT(*) AS jumlah FROM barang WHERE stok = 0");
        $s3 = $r3->fetch_assoc();

        // Transaksi hari ini
        $r4 = $conn->query("SELECT COUNT(*) AS jumlah FROM riwayat_mutasi WHERE DATE(created_at) = CURDATE()");
        $s4 = $r4->fetch_assoc();

        // Total masuk & keluar minggu ini
        $r5 = $conn->query("
            SELECT tipe, SUM(jumlah) AS total
            FROM riwayat_mutasi
            WHERE created_at >= DATE_SUB(NOW(), {$limit_dt})
            GROUP BY tipe
        ");
        $flow = ['masuk' => 0, 'keluar' => 0, 'pindah' => 0];
        while ($row = $r5->fetch_assoc()) {
            $flow[$row['tipe']] = (int)$row['total'];
        }

        echo json_encode([
            "status" => "success",
            "data"   => [
                "total_jenis_barang" => (int)$s1['total_jenis'],
                "total_unit"         => (int)$s1['total_unit'],
                "stok_menipis"       => (int)$s2['jumlah'],
                "stok_habis"         => (int)$s3['jumlah'],
                "transaksi_hari_ini" => (int)$s4['jumlah'],
                "flow"               => $flow,
                "range"              => $range
            ]
        ]);
        break;

    // --------------------------------------------------------
    // Top 5 Barang Paling Banyak Keluar (Selling Products)
    // --------------------------------------------------------
    case 'top_products':
        $top_limit = (int)($_GET['top'] ?? 5);
        $top_limit = min(max($top_limit, 3), 20); // Clamp 3-20

        $stmt = $conn->prepare("
            SELECT 
                rm.barang_id,
                b.nama,
                b.kategori,
                SUM(rm.jumlah) AS total_keluar,
                COUNT(rm.id)   AS total_transaksi
            FROM riwayat_mutasi rm
            JOIN barang b ON b.id = rm.barang_id
            WHERE rm.tipe = 'keluar'
              AND rm.created_at >= DATE_SUB(NOW(), ?)
            GROUP BY rm.barang_id, b.nama, b.kategori
            ORDER BY total_keluar DESC
            LIMIT ?
        ");

        // Konversi INTERVAL ke DATE_SUB-compatible format
        $date_threshold = match($range) {
            'daily'   => date('Y-m-d H:i:s', strtotime('-1 day')),
            'monthly' => date('Y-m-d H:i:s', strtotime('-30 days')),
            default   => date('Y-m-d H:i:s', strtotime('-7 days')),
        };

        $stmt = $conn->prepare("
            SELECT 
                rm.barang_id,
                b.nama,
                b.kategori,
                SUM(rm.jumlah) AS total_keluar,
                COUNT(rm.id)   AS total_transaksi
            FROM riwayat_mutasi rm
            JOIN barang b ON b.id = rm.barang_id
            WHERE rm.tipe = 'keluar'
              AND rm.created_at >= ?
            GROUP BY rm.barang_id, b.nama, b.kategori
            ORDER BY total_keluar DESC
            LIMIT ?
        ");
        $stmt->bind_param("si", $date_threshold, $top_limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Format untuk Chart.js
        $labels = array_column($rows, 'nama');
        $values = array_map(fn($r) => (int)$r['total_keluar'], $rows);

        echo json_encode([
            "status" => "success",
            "data"   => [
                "rows"   => $rows,
                "chart"  => ["labels" => $labels, "values" => $values],
                "range"  => $range
            ]
        ]);
        break;

    // --------------------------------------------------------
    // Arus Logistik: Barang Masuk vs Keluar per Hari
    // --------------------------------------------------------
    case 'flow_chart':
        $date_threshold = match($range) {
            'daily'   => date('Y-m-d', strtotime('-1 day')),
            'monthly' => date('Y-m-d', strtotime('-30 days')),
            default   => date('Y-m-d', strtotime('-7 days')),
        };

        // Query teragregasi — gunakan INDEX idx_rm_tipe_created
        $stmt = $conn->prepare("
            SELECT 
                DATE(created_at) AS tanggal,
                SUM(CASE WHEN tipe = 'masuk'  THEN jumlah ELSE 0 END) AS total_masuk,
                SUM(CASE WHEN tipe = 'keluar' THEN jumlah ELSE 0 END) AS total_keluar,
                SUM(CASE WHEN tipe = 'pindah' THEN jumlah ELSE 0 END) AS total_pindah
            FROM riwayat_mutasi
            WHERE DATE(created_at) >= ?
            GROUP BY DATE(created_at)
            ORDER BY tanggal ASC
        ");
        $stmt->bind_param("s", $date_threshold);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Isi tanggal kosong dengan 0 (agar grafik tidak putus)
        $date_map = [];
        foreach ($rows as $r) {
            $date_map[$r['tanggal']] = $r;
        }

        $start     = new DateTime($date_threshold);
        $end       = new DateTime('today');
        $interval  = new DateInterval('P1D');
        $period    = new DatePeriod($start, $interval, $end->modify('+1 day'));

        $labels       = [];
        $data_masuk   = [];
        $data_keluar  = [];

        foreach ($period as $dt) {
            $tgl = $dt->format('Y-m-d');
            $labels[]      = $dt->format('d/m');
            $data_masuk[]  = (int)($date_map[$tgl]['total_masuk']  ?? 0);
            $data_keluar[] = (int)($date_map[$tgl]['total_keluar'] ?? 0);
        }

        echo json_encode([
            "status" => "success",
            "data"   => [
                "labels"       => $labels,
                "data_masuk"   => $data_masuk,
                "data_keluar"  => $data_keluar,
                "range"        => $range
            ]
        ]);
        break;

    // --------------------------------------------------------
    // Distribusi Stok per Kategori (untuk Pie/Doughnut Chart)
    // --------------------------------------------------------
    case 'category_dist':
        $result = $conn->query("
            SELECT kategori, COUNT(*) AS jumlah_jenis, SUM(stok) AS total_unit
            FROM barang
            GROUP BY kategori
            ORDER BY total_unit DESC
        ");
        $rows   = $result->fetch_all(MYSQLI_ASSOC);
        $labels = array_column($rows, 'kategori');
        $values = array_map(fn($r) => (int)$r['total_unit'], $rows);

        echo json_encode([
            "status" => "success",
            "data"   => ["rows" => $rows, "chart" => ["labels" => $labels, "values" => $values]]
        ]);
        break;

    // --------------------------------------------------------
    // Riwayat Mutasi Terbaru (Live Feed)
    // --------------------------------------------------------
    case 'recent_activity':
        $page_size = (int)($_GET['limit'] ?? 10);
        $stmt      = $conn->prepare("
            SELECT rm.id, rm.tipe, rm.jumlah, rm.catatan, rm.created_at,
                   b.nama AS barang_nama, b.kategori,
                   u.nama_lengkap AS user_nama
            FROM riwayat_mutasi rm
            JOIN barang b ON b.id = rm.barang_id
            JOIN users  u ON u.id = rm.user_id
            ORDER BY rm.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $page_size);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(["status" => "success", "data" => $rows]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Action tidak dikenal: '$action'"]);
        break;
}

$conn->close();
?>
