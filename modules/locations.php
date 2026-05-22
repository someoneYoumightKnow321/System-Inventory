<?php
// modules/locations.php
// API: Manajemen Cabang Gudang & Lokasi Rak (Fitur 1)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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

$user_role = $current_user['role'];
$method    = $_SERVER['REQUEST_METHOD'];
$action    = $_GET['action'] ?? 'list_cabang';

switch ($action) {
    // --- GET: Ambil riwayat mutasi terakhir untuk initial load ---
    case 'riwayat_terakhir':
        // Sesuaikan nama tabel dan kolom dengan database Anda
        // Di sini kita JOIN ke tabel barang/user untuk mendapatkan nama aslinya
        $result = $conn->query("
            SELECT rm.*, b.nama AS barang_nama, u.username AS user_nama 
            FROM riwayat_mutasi rm
            LEFT JOIN barang b ON b.id = rm.barang_id
            LEFT JOIN users u ON u.id = rm.user_id
            ORDER BY rm.created_at DESC 
            LIMIT 5
        ");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $data]);
        break;
    // --- GET: Daftar semua cabang ---
    case 'list_cabang':
        $result = $conn->query("
            SELECT cg.*, 
                COUNT(DISTINCT lr.id) AS jumlah_rak,
                COALESCE(SUM(bl.jumlah), 0) AS total_unit
            FROM cabang_gudang cg
            LEFT JOIN lokasi_rak lr ON lr.cabang_id = cg.id
            LEFT JOIN barang_lokasi bl ON bl.lokasi_id = lr.id
            WHERE cg.is_active = 1
            GROUP BY cg.id
            ORDER BY cg.kode
        ");
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        echo json_encode(["status" => "success", "data" => $data]);
        break;

    // --- GET: Pohon lokasi (location tree) untuk 1 barang ---
    case 'location_tree':
        $barang_id = $_GET['barang_id'] ?? '';
        if (empty($barang_id)) {
            http_response_code(400);
            echo json_encode(["error" => "barang_id wajib diisi."]);
            break;
        }

        $stmt = $conn->prepare("
            SELECT 
                cg.id AS cabang_id, cg.kode AS cabang_kode, cg.nama AS cabang_nama,
                lr.id AS rak_id, lr.zona, lr.baris, lr.level_rak, lr.kode_lengkap,
                bl.jumlah
            FROM barang_lokasi bl
            JOIN lokasi_rak lr ON lr.id = bl.lokasi_id
            JOIN cabang_gudang cg ON cg.id = lr.cabang_id
            WHERE bl.barang_id = ? AND bl.jumlah > 0
            ORDER BY cg.kode, lr.zona, lr.baris, lr.level_rak
        ");
        $stmt->bind_param("s", $barang_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Bangun hierarki tree
        $tree = [];
        foreach ($rows as $r) {
            $ck = $r['cabang_kode'];
            if (!isset($tree[$ck])) {
                $tree[$ck] = [
                    'cabang_id'   => $r['cabang_id'],
                    'cabang_kode' => $r['cabang_kode'],
                    'cabang_nama' => $r['cabang_nama'],
                    'total_unit'  => 0,
                    'zona'        => []
                ];
            }
            $tree[$ck]['total_unit'] += (int)$r['jumlah'];

            $zk = $r['zona'];
            if (!isset($tree[$ck]['zona'][$zk])) {
                $tree[$ck]['zona'][$zk] = ['label' => "Zona $zk", 'baris' => []];
            }

            $bk = $r['baris'];
            if (!isset($tree[$ck]['zona'][$zk]['baris'][$bk])) {
                $tree[$ck]['zona'][$zk]['baris'][$bk] = ['label' => "Baris $bk", 'level' => []];
            }

            $tree[$ck]['zona'][$zk]['baris'][$bk]['level'][] = [
                'rak_id'       => $r['rak_id'],
                'level'        => $r['level_rak'],
                'kode_lengkap' => $r['kode_lengkap'],
                'jumlah'       => (int)$r['jumlah']
            ];
        }

        echo json_encode(["status" => "success", "data" => array_values($tree)]);
        break;

    // --- GET: Daftar rak per cabang (untuk dropdown) ---
    // Param opsional: ?cabang_id=1  → filter per cabang
    // Param opsional: ?barang_id=INV-001 → hanya rak yang memiliki stok barang tersebut > 0
    // Response juga menyertakan stok_rak, zona, baris, level_rak untuk chained dropdown
    case 'list_rak':
        $cabang_id = (int)($_GET['cabang_id'] ?? 0);
        $barang_id_filter = trim($_GET['barang_id'] ?? '');

        if (!empty($barang_id_filter)) {
            // Mode: filter hanya rak yg memiliki barang ini dengan stok > 0
            $query = "
                SELECT
                    lr.id, lr.cabang_id, lr.zona, lr.baris, lr.level_rak, lr.kode_lengkap,
                    lr.kapasitas, lr.is_active,
                    cg.id   AS cabang_gudang_id,
                    cg.kode AS cabang_kode,
                    cg.nama AS cabang_nama,
                    bl.jumlah AS stok_rak
                FROM lokasi_rak lr
                JOIN barang_lokasi bl ON bl.lokasi_id = lr.id
                    AND bl.barang_id = ?
                    AND bl.jumlah > 0
                JOIN cabang_gudang cg ON cg.id = lr.cabang_id AND cg.is_active = 1
                WHERE lr.is_active = 1
            ";
            $params      = [$barang_id_filter];
            $param_types = 's';

            if ($cabang_id > 0) {
                $query .= " AND lr.cabang_id = ?";
                $params[]    = $cabang_id;
                $param_types .= 'i';
            }

            $query .= " ORDER BY cg.kode, lr.zona, lr.baris, lr.level_rak";

            $stmt = $conn->prepare($query);
            $stmt->bind_param($param_types, ...$params);

        } else {
            // Mode: semua rak aktif + agregasi total terisi
            $query = "
                SELECT
                    lr.id, lr.cabang_id, lr.zona, lr.baris, lr.level_rak, lr.kode_lengkap,
                    lr.kapasitas, lr.is_active,
                    cg.id   AS cabang_gudang_id,
                    cg.kode AS cabang_kode,
                    cg.nama AS cabang_nama,
                    COALESCE(SUM(bl.jumlah), 0) AS stok_rak
                FROM lokasi_rak lr
                LEFT JOIN barang_lokasi bl ON bl.lokasi_id = lr.id
                JOIN cabang_gudang cg ON cg.id = lr.cabang_id AND cg.is_active = 1
                WHERE lr.is_active = 1
            ";
            $params      = [];
            $param_types = '';

            if ($cabang_id > 0) {
                $query .= " AND lr.cabang_id = ?";
                $params[]    = $cabang_id;
                $param_types .= 'i';
            }

            $query .= " GROUP BY lr.id ORDER BY cg.kode, lr.zona, lr.baris, lr.level_rak";

            $stmt = $conn->prepare($query);
            if ($params) {
                $stmt->bind_param($param_types, ...$params);
            }
        }

        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Bangun struktur hierarki untuk chained dropdown:
        // { cabang_id => { cabang_nama, zona => { rak_list[] } } }
        $hierarchy = [];
        $flat      = [];

        foreach ($rows as $r) {
            $flat[] = $r; // tetap sertakan data flat untuk penggunaan sederhana

            $cid  = (int)$r['cabang_id'];
            $zona = $r['zona'];

            if (!isset($hierarchy[$cid])) {
                $hierarchy[$cid] = [
                    'cabang_id'   => $cid,
                    'cabang_kode' => $r['cabang_kode'],
                    'cabang_nama' => $r['cabang_nama'],
                    'zona'        => [],
                ];
            }

            if (!isset($hierarchy[$cid]['zona'][$zona])) {
                $hierarchy[$cid]['zona'][$zona] = [
                    'label' => "Zona $zona",
                    'rak'   => [],
                ];
            }

            $hierarchy[$cid]['zona'][$zona]['rak'][] = [
                'rak_id'       => (int)$r['id'],
                'baris'        => $r['baris'],
                'level_rak'    => $r['level_rak'],
                'kode_lengkap' => $r['kode_lengkap'],
                'stok_rak'     => (int)$r['stok_rak'],
                'kapasitas'    => (int)$r['kapasitas'],
            ];
        }

        // Konversi zona dari associative ke indexed array
        $hierarchy_indexed = [];
        foreach ($hierarchy as $cabang) {
            $zonaArr = [];
            foreach ($cabang['zona'] as $zonaKey => $zonaData) {
                $zonaArr[] = array_merge(['zona_key' => $zonaKey], $zonaData);
            }
            $cabang['zona'] = $zonaArr;
            $hierarchy_indexed[] = $cabang;
        }

        echo json_encode([
            "status"    => "success",
            "data"      => $flat,           // flat list untuk penggunaan sederhana
            "hierarchy" => $hierarchy_indexed, // tree terstruktur untuk chained dropdown
        ]);
        break;

    // --- POST: Catat mutasi & update lokasi barang ---
    case 'mutasi':
        if ($method !== 'POST') {
            http_response_code(405); echo json_encode(["error" => "Method not allowed"]); break;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $barang_id   = trim($input['barang_id']   ?? '');
        $tipe        = trim($input['tipe']         ?? ''); // masuk|keluar|pindah
        $jumlah      = (int)($input['jumlah']      ?? 0);
        $lokasi_asal = (int)($input['lokasi_asal'] ?? 0);  // ID lokasi_rak
        $lokasi_tuju = (int)($input['lokasi_tuju'] ?? 0);
        $catatan     = trim($input['catatan']       ?? '');
        $user_id     = (int)$current_user['id'];

        // Validasi
        if (empty($barang_id) || !in_array($tipe, ['masuk','keluar','pindah']) || $jumlah <= 0) {
            http_response_code(400);
            echo json_encode(["error" => "Data tidak valid: barang_id, tipe (masuk/keluar/pindah), dan jumlah wajib diisi."]);
            break;
        }

$conn->begin_transaction();
        try {
            // 1. Catat riwayat mutasi
            $stmt = $conn->prepare("
                INSERT INTO riwayat_mutasi (barang_id, user_id, tipe, jumlah, lokasi_asal_id, lokasi_tujuan_id, catatan)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            // FIX ERROR: Pastikan hasil kondisi ditampung ke dalam variabel asli
            $final_asal = ($lokasi_asal > 0) ? $lokasi_asal : null;
            $final_tuju = ($lokasi_tuju > 0) ? $lokasi_tuju : null;

            // Sekarang panggil bind_param hanya menggunakan variabel murni
            $stmt->bind_param(
                "sisiiss",
                $barang_id, 
                $user_id, 
                $tipe, 
                $jumlah,
                $final_asal,
                $final_tuju,
                $catatan
            );
            
            $stmt->execute();
            $stmt->close();

            // 2. Update stok di tabel barang
            if ($tipe === 'masuk') {
                $s = $conn->prepare("UPDATE barang SET stok = stok + ? WHERE id = ?");
                $s->bind_param("is", $jumlah, $barang_id);
                $s->execute(); $s->close();

                // Update barang_lokasi (upsert)
                if ($lokasi_tuju > 0) {
                    $s = $conn->prepare("
                        INSERT INTO barang_lokasi (barang_id, lokasi_id, jumlah)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE jumlah = jumlah + VALUES(jumlah)
                    ");
                    $s->bind_param("sii", $barang_id, $lokasi_tuju, $jumlah);
                    $s->execute(); $s->close();
                }
            } elseif ($tipe === 'keluar') {
                $s = $conn->prepare("UPDATE barang SET stok = GREATEST(0, stok - ?) WHERE id = ?");
                $s->bind_param("is", $jumlah, $barang_id);
                $s->execute(); $s->close();

                if ($lokasi_asal > 0) {
                    $s = $conn->prepare("
                        UPDATE barang_lokasi SET jumlah = GREATEST(0, jumlah - ?)
                        WHERE barang_id = ? AND lokasi_id = ?
                    ");
                    $s->bind_param("isi", $jumlah, $barang_id, $lokasi_asal);
                    $s->execute(); $s->close();
                }
            } elseif ($tipe === 'pindah') {
                if ($lokasi_asal <= 0 || $lokasi_tuju <= 0) {
                    throw new Exception("Pindah butuh lokasi_asal dan lokasi_tuju.");
                }
                // Kurangi dari asal
                $s = $conn->prepare("
                    UPDATE barang_lokasi SET jumlah = GREATEST(0, jumlah - ?)
                    WHERE barang_id = ? AND lokasi_id = ?
                ");
                $s->bind_param("isi", $jumlah, $barang_id, $lokasi_asal);
                $s->execute(); $s->close();

                // Tambah ke tujuan
                $s = $conn->prepare("
                    INSERT INTO barang_lokasi (barang_id, lokasi_id, jumlah) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE jumlah = jumlah + VALUES(jumlah)
                ");
                $s->bind_param("sii", $barang_id, $lokasi_tuju, $jumlah);
                $s->execute(); $s->close();
            }

            $conn->commit();

            // 3. Cek stok menipis & kirim notifikasi (Fitur 2 hook)
            require_once 'notify.php';
            check_and_notify_low_stock($conn, $barang_id);

            echo json_encode(["status" => "success", "message" => "Mutasi berhasil dicatat."]);

        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Action '$action' tidak ditemukan."]);
        break;
}

$conn->close();
?>
