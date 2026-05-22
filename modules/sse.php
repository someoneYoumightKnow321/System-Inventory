<?php
// modules/sse.php
// Server-Sent Events: Real-time push notifikasi mutasi stok ke browser
// Usage: new EventSource('modules/sse.php')

// 1. Ambil dependensi auth terlebih dahulu untuk membaca session
require_once '../auth.php';
require_once '../config.php';

// Pastikan session dimulai (jika belum otomatis dimulai di auth.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_user = get_current_user_session();

if (!$current_user) {
    // SSE Headers wajib untuk pengiriman error
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    
    // Kirim event error lalu tutup koneksi
    echo "event: error\ndata: " . json_encode(["message" => "Unauthorized"]) . "\n\n";
    flush();
    exit();
}

// =========================================================================
// KUNCI PERBAIKAN: Lepaskan lock session agar navigasi halaman lain lancar
// =========================================================================
session_write_close(); 
// Setelah baris ini, $_SESSION masih bisa dibaca, tetapi file session di server 
// sudah tidak dikunci lagi. Halaman lain seperti index.php kini bisa diakses instan.

// SSE Headers wajib setelah lolos enkripsi/auth
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Untuk Nginx/Apache agar tidak di-buffer

// Tutup output buffer agar data langsung terkirim secara real-time
if (ob_get_level()) ob_end_clean();

// Kirim heartbeat awal agar koneksi tidak langsung timeout
echo "event: connected\ndata: " . json_encode([
    "message" => "SSE terhubung",
    "user"    => $current_user['username'],
    "time"    => date('H:i:s')
]) . "\n\n";
flush();

// Lacak ID mutasi terakhir yang sudah dikirim
$last_id = isset($_GET['lastEventId']) ? (int)$_GET['lastEventId'] : 0;

// Jika tidak ada last ID, ambil ID terbesar saat ini sebagai starting point
if ($last_id === 0) {
    $res = $conn->query("SELECT COALESCE(MAX(id), 0) as max_id FROM riwayat_mutasi");
    $row = $res->fetch_assoc();
    $last_id = (int)$row['max_id'];
}

$max_iterations = 120; // Maksimal ~4 menit (120 iter × 2 detik)
$iteration      = 0;

while ($iteration < $max_iterations) {
    // Cek apakah klien masih terhubung (jika pindah halaman atau tab ditutup, loop berhenti)
    if (connection_aborted()) break;

    // Query mutasi baru sejak last_id
    $stmt = $conn->prepare("
        SELECT rm.id, rm.barang_id, b.nama AS barang_nama, rm.tipe, rm.jumlah,
               rm.catatan, rm.created_at, u.nama_lengkap AS user_nama,
               cg_asal.nama AS lokasi_asal_nama,
               cg_tuju.nama AS lokasi_tuju_nama
        FROM riwayat_mutasi rm
        JOIN barang b ON b.id = rm.barang_id
        JOIN users u ON u.id = rm.user_id
        LEFT JOIN lokasi_rak lr_a ON lr_a.id = rm.lokasi_asal_id
        LEFT JOIN cabang_gudang cg_asal ON cg_asal.id = lr_a.cabang_id
        LEFT JOIN lokasi_rak lr_t ON lr_t.id = rm.lokasi_tujuan_id
        LEFT JOIN cabang_gudang cg_tuju ON cg_tuju.id = lr_t.cabang_id
        WHERE rm.id > ?
        ORDER BY rm.id ASC
        LIMIT 20
    ");
    $stmt->bind_param("i", $last_id);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($results as $event) {
        $last_id = (int)$event['id'];
        // Format standar SSE: id: <id>\nevent: mutasi\ndata: <json>\n\n
        echo "id: {$last_id}\n";
        echo "event: mutasi\n";
        echo "data: " . json_encode($event, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }

    // Kirim heartbeat setiap 10 iterasi (~20 detik) agar koneksi HTTP tetap hidup
    if ($iteration % 10 === 0) {
        echo ": heartbeat " . date('H:i:s') . "\n\n";
        flush();
    }

    $iteration++;
    sleep(2); // Polling interval ke database setiap 2 detik
}

// Koneksi berakhir (mencapai batas iterasi), minta client untuk otomatis reconnect
echo "event: reconnect\ndata: {\"message\":\"Session refresh\"}\n\n";
flush();
?>