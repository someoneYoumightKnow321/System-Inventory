<?php
// api.php - RESTful API untuk Operasi CRUD Inventaris Kantor (Dengan Pembatasan Role)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Mengaktifkan sesi & otorisasi
require_once 'auth.php';
require_once 'config.php';

// Proteksi API: Pastikan user sudah login
$current_user = get_current_user_session();
if (!$current_user) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized. Harap login terlebih dahulu."]);
    exit();
}

$user_role = $current_user['role']; // 'admin' atau 'karyawan'
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Siapa saja yang sudah login (Admin & Karyawan) boleh melakukan GET
        $query = "SELECT * FROM barang ORDER BY created_at DESC";
        $result = $conn->query($query);
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    "id" => $row['id'],
                    "name" => $row['nama'],
                    "category" => $row['kategori'],
                    "stock" => (int)$row['stok'],
                    "dipakai" => (int)$row['dipakai']
                ];
            }
        }
        
        // Seeding data contoh jika kosong
        if (count($data) === 0) {
            $samples = [
                ['INV-001', 'MacBook Pro M3 Max 16"', 'Elektronik', 12, 8],
                ['INV-002', 'Kursi Kerja Ergonomis Herman Miller', 'Furnitur', 25, 20],
                ['INV-003', 'Monitor Dell UltraSharp 27"', 'Elektronik', 15, 10],
                ['INV-004', 'Keychron K2 Mechanical Keyboard', 'Elektronik', 4, 6],
                ['INV-005', 'Meja Kantor Kayu Jati Minimalis', 'Furnitur', 8, 8],
                ['INV-006', 'Papan Tulis Kaca Magnetic (Glassboard)', 'Furnitur', 2, 3],
                ['INV-007', 'Kertas A4 Sinar Dunia 80gsm', 'ATK', 45, 0],
                ['INV-008', 'Pulpen Gel Pilot G2 (Box of 12)', 'ATK', 0, 12]
            ];
            
            $stmt = $conn->prepare("INSERT INTO barang (id, nama, kategori, stok, dipakai) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                foreach ($samples as $s) {
                    $stmt->bind_param("sssii", $s[0], $s[1], $s[2], $s[3], $s[4]);
                    $stmt->execute();
                }
                $stmt->close();
            }
            
            $result = $conn->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        "id" => $row['id'],
                        "name" => $row['nama'],
                        "category" => $row['kategori'],
                        "stock" => (int)$row['stok'],
                        "dipakai" => (int)$row['dipakai']
                    ];
                }
            }
        }
        
        echo json_encode($data);
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || !isset($input['stock']) || !isset($input['dipakai'])) {
            http_response_code(400);
            echo json_encode(["error" => "Data input tidak lengkap."]);
            break;
        }
        
        $id = trim($input['id']);
        $stock = (int)$input['stock'];
        $dipakai = (int)$input['dipakai'];
        
        // Memeriksa keberadaan barang
        $check_stmt = $conn->prepare("SELECT nama, kategori FROM barang WHERE id = ?");
        $check_stmt->bind_param("s", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $item_exists = ($check_result->num_rows > 0);
        $existing_item = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($user_role === 'karyawan') {
            // PEMBATASAN KARYAWAN:
            // 1. Tidak boleh menambah barang baru
            if (!$item_exists) {
                http_response_code(403);
                echo json_encode(["error" => "Akses Ditolak: Karyawan tidak diizinkan menambahkan barang baru."]);
                break;
            }
            
            // 2. Hanya boleh update Stok & Dipakai (Nama dan Kategori tetap menggunakan data yang sudah ada di database)
            $update_stmt = $conn->prepare("UPDATE barang SET stok = ?, dipakai = ? WHERE id = ?");
            $update_stmt->bind_param("iis", $stock, $dipakai, $id);
            if ($update_stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Jumlah stok dan dipakai berhasil diperbarui oleh karyawan."]);
            } else {
                http_response_code(550);
                echo json_encode(["error" => "Gagal memperbarui stok: " . $conn->error]);
            }
            $update_stmt->close();
            
        } else {
            // ROLE: ADMIN (Dapat melakukan segalanya)
            if (!isset($input['name']) || !isset($input['category'])) {
                http_response_code(400);
                echo json_encode(["error" => "Nama dan Kategori wajib diisi oleh Admin."]);
                break;
            }
            
            $name = trim($input['name']);
            $category = trim($input['category']);
            
            if (empty($name) || empty($category)) {
                http_response_code(400);
                echo json_encode(["error" => "Nama dan Kategori tidak boleh kosong."]);
                break;
            }
            
            if ($item_exists) {
                // Admin: Update semua kolom
                $update_stmt = $conn->prepare("UPDATE barang SET nama = ?, kategori = ?, stok = ?, dipakai = ? WHERE id = ?");
                $update_stmt->bind_param("ssiis", $name, $category, $stock, $dipakai, $id);
                if ($update_stmt->execute()) {
                    echo json_encode(["status" => "success", "message" => "Barang berhasil diperbarui oleh Admin."]);
                } else {
                    http_response_code(500);
                    echo json_encode(["error" => "Gagal memperbarui barang: " . $conn->error]);
                }
                $update_stmt->close();
            } else {
                // Admin: Tambah barang baru
                $insert_stmt = $conn->prepare("INSERT INTO barang (id, nama, kategori, stok, dipakai) VALUES (?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("sssii", $id, $name, $category, $stock, $dipakai);
                if ($insert_stmt->execute()) {
                    echo json_encode(["status" => "success", "message" => "Barang baru berhasil ditambahkan oleh Admin."]);
                } else {
                    http_response_code(500);
                    echo json_encode(["error" => "Gagal menambahkan barang: " . $conn->error]);
                }
                $insert_stmt->close();
            }
        }
        break;
        
    case 'DELETE':
        // PEMBATASAN KARYAWAN: Tidak boleh melakukan aksi HAPUS
        if ($user_role !== 'admin') {
            http_response_code(403);
            echo json_encode(["error" => "Akses Ditolak: Hanya Admin yang diizinkan untuk menghapus barang."]);
            exit();
        }
        
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Parameter ID tidak ditemukan."]);
            break;
        }
        
        $id = trim($_GET['id']);
        
        $delete_stmt = $conn->prepare("DELETE FROM barang WHERE id = ?");
        $delete_stmt->bind_param("s", $id);
        
        if ($delete_stmt->execute()) {
            if ($delete_stmt->affected_rows > 0) {
                echo json_encode(["status" => "success", "message" => "Barang berhasil dihapus oleh Admin."]);
            } else {
                http_response_code(404);
                echo json_encode(["error" => "Barang tidak ditemukan."]);
            }
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Gagal menghapus barang: " . $conn->error]);
        }
        
        $delete_stmt->close();
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["error" => "Metode HTTP tidak didukung."]);
        break;
}

$conn->close();
?>
