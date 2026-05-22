<?php
// modules/notify.php
// Fitur 2: Logika Notifikasi Email Stok Menipis
// Bisa di-include dari modul lain ATAU dipanggil langsung via HTTP

// ============================================================
// FUNGSI UTAMA: Cek & kirim notifikasi (Event-driven approach)
// Dipanggil setelah setiap operasi yang mengurangi stok
// ============================================================
function check_and_notify_low_stock($conn, $barang_id = null) {
    // Ambil barang yang stok <= minimum_stock DAN belum dinotif dalam 1 jam terakhir
    $query = "
        SELECT id, nama, stok, minimum_stock, last_notif_at
        FROM barang
        WHERE stok <= minimum_stock
          AND stok >= 0
          AND (last_notif_at IS NULL OR last_notif_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))
    ";

    if ($barang_id !== null) {
        // Mode event-driven: hanya cek 1 barang spesifik
        $stmt = $conn->prepare($query . " AND id = ?");
        $stmt->bind_param("s", $barang_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        // Mode batch (untuk endpoint standalone): cek semua barang
        $result = $conn->query($query);
    }

    $sent_count = 0;
    while ($item = $result->fetch_assoc()) {
        $success = send_low_stock_email($item);
        $status  = $success ? 'sent' : 'failed';

        // Log notifikasi
        $log_stmt = $conn->prepare("
            INSERT INTO notifikasi_log (barang_id, tipe, email_target, status)
            VALUES (?, 'low_stock', ?, ?)
        ");
        $email_target = defined('LOGISTIK_EMAIL') ? LOGISTIK_EMAIL : 'tim.logistik@perusahaan.com';
        $log_stmt->bind_param("sss", $item['id'], $email_target, $status);
        $log_stmt->execute();
        $log_stmt->close();

        // Update timestamp notifikasi terakhir
        if ($success) {
            $upd = $conn->prepare("UPDATE barang SET last_notif_at = NOW() WHERE id = ?");
            $upd->bind_param("s", $item['id']);
            $upd->execute();
            $upd->close();
            $sent_count++;
        }
    }

    return $sent_count;
}

// ============================================================
// FUNGSI PENGIRIMAN EMAIL (menggunakan PHP mail() atau SMTP manual)
// Untuk produksi: ganti dengan PHPMailer
// ============================================================
function send_low_stock_email($item) {
    $to      = defined('LOGISTIK_EMAIL') ? LOGISTIK_EMAIL : 'tim.logistik@perusahaan.com';
    $from    = defined('MAIL_FROM')      ? MAIL_FROM      : 'sistem@inventaris.com';
    $subject = "[ALERT] Stok Menipis: " . $item['nama'];
    $body    = build_email_html($item);

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Sistem Inventaris <{$from}>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Gunakan PHP mail() native (pastikan SMTP dikonfigurasi di php.ini XAMPP)
    // Untuk SMTP eksternal (Gmail), uncomment bagian PHPMailer di bawah
    $result = @mail($to, $subject, $body, $headers);

    // --- OPSI PHPMailer (lebih handal untuk produksi) ---
    // Uncomment jika sudah install PHPMailer via composer
    // require_once '../vendor/autoload.php';
    // use PHPMailer\PHPMailer\PHPMailer;
    // $mail = new PHPMailer(true);
    // try {
    //     $mail->isSMTP();
    //     $mail->Host       = MAIL_HOST;
    //     $mail->SMTPAuth   = true;
    //     $mail->Username   = MAIL_USERNAME;
    //     $mail->Password   = MAIL_PASSWORD;
    //     $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    //     $mail->Port       = MAIL_PORT;
    //     $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    //     $mail->addAddress($to, 'Tim Logistik');
    //     $mail->isHTML(true);
    //     $mail->Subject = $subject;
    //     $mail->Body    = $body;
    //     $mail->send();
    //     $result = true;
    // } catch (Exception $e) { $result = false; }

    return $result;
}

// ============================================================
// TEMPLATE EMAIL HTML
// ============================================================
function build_email_html($item) {
    $percentage = $item['minimum_stock'] > 0
        ? round(($item['stok'] / $item['minimum_stock']) * 100)
        : 0;
    $percentage = min($percentage, 100);

    $bar_color  = $item['stok'] == 0 ? '#ef4444' : '#f97316';
    $badge_text = $item['stok'] == 0 ? 'STOK HABIS' : 'STOK KRITIS';
    $badge_bg   = $item['stok'] == 0 ? '#fef2f2'   : '#fff7ed';
    $badge_col  = $item['stok'] == 0 ? '#dc2626'   : '#ea580c';

    $now = date('d M Y, H:i') . ' WIB';

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Alert Stok Menipis</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 20px;">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
      
      <!-- HEADER -->
      <tr>
        <td style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);padding:32px 36px;">
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td>
                <p style="margin:0;color:#94a3b8;font-size:11px;font-weight:600;letter-spacing:2px;text-transform:uppercase;">Sistem Inventaris Gudang</p>
                <h1 style="margin:8px 0 0;color:#ffffff;font-size:22px;font-weight:700;">⚠️ Peringatan Stok Menipis</h1>
              </td>
              <td align="right">
                <span style="display:inline-block;background:{$badge_bg};color:{$badge_col};font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;padding:6px 12px;border-radius:20px;">
                  {$badge_text}
                </span>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- BODY -->
      <tr>
        <td style="padding:32px 36px;">
          <p style="margin:0 0 24px;color:#475569;font-size:14px;line-height:1.6;">
            Tim Logistik yang terhormat, sistem telah mendeteksi bahwa stok barang berikut telah mencapai batas minimum dan memerlukan tindakan segera.
          </p>

          <!-- INFO CARD -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:24px;">
            <tr><td style="padding:20px 24px;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="padding-bottom:12px;border-bottom:1px solid #e2e8f0;">
                    <p style="margin:0;color:#94a3b8;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Nama Barang</p>
                    <p style="margin:4px 0 0;color:#0f172a;font-size:18px;font-weight:700;">{$item['nama']}</p>
                  </td>
                </tr>
                <tr><td style="padding-top:12px;">
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td width="33%">
                        <p style="margin:0;color:#94a3b8;font-size:10px;font-weight:700;text-transform:uppercase;">ID Barang</p>
                        <p style="margin:4px 0 0;color:#334155;font-size:14px;font-weight:700;font-family:monospace;">{$item['id']}</p>
                      </td>
                      <td width="33%" align="center">
                        <p style="margin:0;color:#94a3b8;font-size:10px;font-weight:700;text-transform:uppercase;">Stok Saat Ini</p>
                        <p style="margin:4px 0 0;color:{$badge_col};font-size:24px;font-weight:800;">{$item['stok']} <span style="font-size:12px;font-weight:500;">unit</span></p>
                      </td>
                      <td width="33%" align="right">
                        <p style="margin:0;color:#94a3b8;font-size:10px;font-weight:700;text-transform:uppercase;">Stok Minimum</p>
                        <p style="margin:4px 0 0;color:#334155;font-size:24px;font-weight:800;">{$item['minimum_stock']} <span style="font-size:12px;font-weight:500;">unit</span></p>
                      </td>
                    </tr>
                  </table>
                </td></tr>
              </table>
            </td></tr>

            <!-- PROGRESS BAR -->
            <tr><td style="padding:0 24px 20px;">
              <p style="margin:0 0 8px;color:#64748b;font-size:11px;font-weight:600;">Tingkat Stok: {$percentage}% dari batas minimum</p>
              <div style="background:#e2e8f0;border-radius:999px;height:8px;overflow:hidden;">
                <div style="background:{$bar_color};height:8px;width:{$percentage}%;border-radius:999px;"></div>
              </div>
            </td></tr>
          </table>

          <!-- CTA BUTTON -->
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center" style="padding:8px 0 24px;">
              <a href="#" style="display:inline-block;background:linear-gradient(135deg,#0d9488,#0891b2);color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:14px 32px;border-radius:10px;letter-spacing:.3px;">
                🔗 Buka Dashboard Inventaris
              </a>
            </td></tr>
          </table>

          <p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.6;border-top:1px solid #e2e8f0;padding-top:20px;">
            Email ini dikirim otomatis oleh sistem pada <strong>{$now}</strong>.<br>
            Notifikasi berikutnya akan dikirim kembali jika stok masih di bawah minimum setelah 1 jam.
          </p>
        </td>
      </tr>

      <!-- FOOTER -->
      <tr>
        <td style="background:#f8fafc;padding:20px 36px;border-top:1px solid #e2e8f0;">
          <p style="margin:0;color:#94a3b8;font-size:11px;text-align:center;">© 2026 Sistem Inventaris Gudang · Pesan ini dikirim secara otomatis, harap tidak membalas.</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ============================================================
// MODE: Dipanggil langsung via HTTP (untuk testing/manual trigger)
// ============================================================
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header("Content-Type: application/json");
    require_once '../auth.php';
    require_once '../config.php';

    $user = get_current_user_session();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Hanya Admin yang dapat memicu notifikasi manual."]);
        exit();
    }

    $sent = check_and_notify_low_stock($conn);
    echo json_encode([
        "status"  => "success",
        "message" => "Pengecekan selesai. $sent email notifikasi terkirim."
    ]);
}
?>
