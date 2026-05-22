<?php
// modules/scan.php
// Fitur 3: API Endpoint untuk Scan QR/Barcode
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'lookup';

switch ($action) {

    // --------------------------------------------------------
    // GET ?action=lookup&q=<scan_result>
    // Lookup barang berdasarkan hasil scan (ID atau qr_code_data)
    // --------------------------------------------------------
    case 'lookup':
        $q = trim($_GET['q'] ?? '');

        if (empty($q)) {
            http_response_code(400);
            echo json_encode(["error" => "Parameter 'q' (hasil scan) wajib diisi."]);
            break;
        }

        // Sanitasi: strip tag & escape
        $q = htmlspecialchars(strip_tags($q), ENT_QUOTES, 'UTF-8');

        // Cari di dua kolom: id barang (exact match) ATAU qr_code_data (exact/like)
        $stmt = $conn->prepare("
            SELECT 
                b.id, b.nama, b.kategori, b.stok, b.dipakai,
                b.minimum_stock, b.qr_code_data, b.created_at,
                -- Ambil semua lokasi barang ini
                GROUP_CONCAT(
                    CONCAT(lr.kode_lengkap, ':', bl.jumlah)
                    ORDER BY lr.kode_lengkap
                    SEPARATOR '|'
                ) AS lokasi_raw
            FROM barang b
            LEFT JOIN barang_lokasi bl ON bl.barang_id = b.id
            LEFT JOIN lokasi_rak lr ON lr.id = bl.lokasi_id AND bl.jumlah > 0
            WHERE b.id = ? OR b.qr_code_data = ?
            GROUP BY b.id
            LIMIT 1
        ");
        $stmt->bind_param("ss", $q, $q);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item) {
            http_response_code(404);
            echo json_encode([
                "error"    => "Barang tidak ditemukan.",
                "scanned"  => $q,
                "hint"     => "Pastikan QR/barcode terdaftar di sistem."
            ]);
            break;
        }

        // Parse lokasi_raw → array [{kode, jumlah}]
        $lokasi = [];
        if (!empty($item['lokasi_raw'])) {
            foreach (explode('|', $item['lokasi_raw']) as $loc) {
                [$kode, $jml] = explode(':', $loc, 2);
                $lokasi[] = ['kode' => $kode, 'jumlah' => (int)$jml];
            }
        }
        unset($item['lokasi_raw']);
        $item['lokasi']      = $lokasi;
        $item['stok']        = (int)$item['stok'];
        $item['dipakai']     = (int)$item['dipakai'];
        $item['minimum_stock'] = (int)$item['minimum_stock'];
        $item['is_low_stock']  = $item['stok'] <= $item['minimum_stock'];
        $item['status']        = $item['stok'] <= 0 ? 'habis' : ($item['is_low_stock'] ? 'kritis' : 'tersedia');

        echo json_encode(["status" => "success", "data" => $item]);
        break;

    // --------------------------------------------------------
    // POST ?action=checkin
    // Check-in barang cepat (barang masuk via scan)
    // --------------------------------------------------------
    case 'checkin':
        if ($method !== 'POST') {
            http_response_code(405); echo json_encode(["error" => "Gunakan POST."]); break;
        }
        $input     = json_decode(file_get_contents('php://input'), true);
        $barang_id = trim($input['barang_id'] ?? '');
        $jumlah    = (int)($input['jumlah']   ?? 1);
        $lokasi_id = (int)($input['lokasi_id'] ?? 0);
        $catatan   = trim($input['catatan']   ?? 'Check-in via scan QR');
        $user_id   = (int)$current_user['id'];

        if (empty($barang_id) || $jumlah <= 0) {
            http_response_code(400);
            echo json_encode(["error" => "barang_id dan jumlah (> 0) wajib diisi."]);
            break;
        }

        $conn->begin_transaction();
        try {
            // Update stok
            $stmt = $conn->prepare("UPDATE barang SET stok = stok + ? WHERE id = ?");
            $stmt->bind_param("is", $jumlah, $barang_id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) throw new Exception("Barang '$barang_id' tidak ditemukan.");
            $stmt->close();

            // Update lokasi jika ada
            if ($lokasi_id > 0) {
                $stmt = $conn->prepare("
                    INSERT INTO barang_lokasi (barang_id, lokasi_id, jumlah) VALUES (?,?,?)
                    ON DUPLICATE KEY UPDATE jumlah = jumlah + VALUES(jumlah)
                ");
                $stmt->bind_param("sii", $barang_id, $lokasi_id, $jumlah);
                $stmt->execute(); $stmt->close();
            }

            // Catat mutasi
            $stmt = $conn->prepare("
                INSERT INTO riwayat_mutasi (barang_id, user_id, tipe, jumlah, lokasi_tujuan_id, catatan)
                VALUES (?, ?, 'masuk', ?, ?, ?)
            ");
            $lok = $lokasi_id ?: null;
            $stmt->bind_param("siiss", $barang_id, $user_id, $jumlah, $lok, $catatan);
            $stmt->execute(); $stmt->close();

            $conn->commit();

            // Ambil stok terkini
            $res   = $conn->query("SELECT stok FROM barang WHERE id = '$barang_id'");
            $stok  = (int)$res->fetch_assoc()['stok'];

            echo json_encode([
                "status"       => "success",
                "message"      => "Check-in $jumlah unit berhasil.",
                "barang_id"    => $barang_id,
                "stok_terkini" => $stok
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // --------------------------------------------------------
    // POST ?action=checkout
    // Check-out barang cepat (barang keluar via scan)
    // --------------------------------------------------------
    case 'checkout':
        if ($method !== 'POST') {
            http_response_code(405); echo json_encode(["error" => "Gunakan POST."]); break;
        }
        $input     = json_decode(file_get_contents('php://input'), true);
        $barang_id = trim($input['barang_id'] ?? '');
        $jumlah    = (int)($input['jumlah']   ?? 1);
        $lokasi_id = (int)($input['lokasi_id'] ?? 0);
        $catatan   = trim($input['catatan']   ?? 'Check-out via scan QR');
        $user_id   = (int)$current_user['id'];

        if (empty($barang_id) || $jumlah <= 0) {
            http_response_code(400);
            echo json_encode(["error" => "barang_id dan jumlah (> 0) wajib diisi."]);
            break;
        }

        // Cek stok cukup
        $stmt = $conn->prepare("SELECT stok FROM barang WHERE id = ?");
        $stmt->bind_param("s", $barang_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            http_response_code(404);
            echo json_encode(["error" => "Barang tidak ditemukan."]);
            break;
        }

        if ((int)$row['stok'] < $jumlah) {
            http_response_code(422);
            echo json_encode([
                "error"    => "Stok tidak mencukupi.",
                "stok"     => (int)$row['stok'],
                "diminta"  => $jumlah
            ]);
            break;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE barang SET stok = stok - ?, dipakai = dipakai + ? WHERE id = ?");
            $stmt->bind_param("iis", $jumlah, $jumlah, $barang_id);
            $stmt->execute(); $stmt->close();

            if ($lokasi_id > 0) {
                $stmt = $conn->prepare("
                    UPDATE barang_lokasi SET jumlah = GREATEST(0, jumlah - ?)
                    WHERE barang_id = ? AND lokasi_id = ?
                ");
                $stmt->bind_param("isi", $jumlah, $barang_id, $lokasi_id);
                $stmt->execute(); $stmt->close();
            }

            $stmt = $conn->prepare("
                INSERT INTO riwayat_mutasi (barang_id, user_id, tipe, jumlah, lokasi_asal_id, catatan)
                VALUES (?, ?, 'keluar', ?, ?, ?)
            ");
            $lok = $lokasi_id ?: null;
            $stmt->bind_param("siiss", $barang_id, $user_id, $jumlah, $lok, $catatan);
            $stmt->execute(); $stmt->close();

            $conn->commit();

            // Cek & kirim notifikasi low stock jika perlu
            require_once 'notify.php';
            check_and_notify_low_stock($conn, $barang_id);

            $stok_baru = (int)$row['stok'] - $jumlah;
            echo json_encode([
                "status"       => "success",
                "message"      => "Check-out $jumlah unit berhasil.",
                "barang_id"    => $barang_id,
                "stok_terkini" => $stok_baru
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Action tidak dikenal."]);
        break;
}

$conn->close();
?>
