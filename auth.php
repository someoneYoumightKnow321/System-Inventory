<?php
// auth.php - Helper Autentikasi dan Otorisasi Sesi Pengguna
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Memastikan user sudah login. Jika belum, dialihkan ke login.php.
 */
function protect_page() {
    if (!isset($_SESSION['user'])) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Memeriksa apakah user yang sedang login adalah Admin.
 */
function is_admin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

/**
 * Memeriksa apakah user yang sedang login adalah Karyawan.
 */
function is_karyawan() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'karyawan';
}

/**
 * Mengambil detail user dari session.
 */
function get_current_user_session() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

// Menangani Aksi Logout jika dipanggil secara langsung lewat URL (?action=logout)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
