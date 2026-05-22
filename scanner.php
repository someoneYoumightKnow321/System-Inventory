<?php
// scanner.php - Halaman Scanner QR/Barcode (Fitur 3)
require_once 'auth.php';
protect_page();
$user = get_current_user_session();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Scanner QR/Barcode - Sistem Inventaris</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
tailwind.config = {
    theme: { extend: {
        fontFamily: { sans: ['"Plus Jakarta Sans"','sans-serif'], mono: ['"JetBrains Mono"','monospace'] },
        colors: {
            brand: { 50:'#f0fdfa',100:'#ccfbf1',500:'#0d9488',600:'#0f766e',accent:'#0891b2' },
        }
    }}
}
window.CurrentUser = { role: "<?php echo htmlspecialchars($user['role']); ?>" };
</script>
<link rel="stylesheet" href="assets/css/style.css">
<style>
#qr-reader { border: none !important; }
#qr-reader video { border-radius: 16px !important; }
#qr-reader__scan_region { border-radius: 12px; overflow: hidden; }
#qr-reader__dashboard_section_csr button {
    background: #0d9488 !important;
    color: white !important;
    border-radius: 10px !important;
    padding: 8px 20px !important;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    border: none !important;
    cursor: pointer !important;
}
#qr-reader__dashboard_section_fsr span { display: none !important; }
</style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen antialiased">

<!-- NAV -->
<header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-slate-100 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="index.php" class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-brand-500 to-brand-accent flex items-center justify-center text-white">
                    <i data-lucide="package" class="w-5 h-5"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-800">Office Inventory</p>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider leading-none">Sistem Administrasi</p>
                </div>
            </a>
        </div>
        <nav class="hidden md:flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
            <a href="index.php"     class="nav-tab px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-white transition-all flex items-center gap-1.5"><i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>Inventaris</a>
            <a href="locations.php" class="nav-tab px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-white transition-all flex items-center gap-1.5"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i>Lokasi Rak</a>
            <a href="scanner.php"   class="nav-tab px-3 py-1.5 rounded-lg text-xs font-semibold bg-white text-brand-600 shadow-sm flex items-center gap-1.5"><i data-lucide="scan-line" class="w-3.5 h-3.5"></i>Scanner</a>
            <a href="dashboard.php" class="nav-tab px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-white transition-all flex items-center gap-1.5"><i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i>Analitik</a>
        </nav>
        <a href="auth.php?action=logout" class="text-xs text-slate-400 hover:text-rose-500 transition-colors flex items-center gap-1">
            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
        </a>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-6">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-800">QR &amp; Barcode Scanner</h2>
            <p class="text-xs text-slate-400 mt-0.5">Scan via kamera untuk check-in/out barang instan</p>
        </div>
        <span id="scanner-status-badge" class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Kamera Tidak Aktif</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- KAMERA PANEL -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                    <i data-lucide="camera" class="w-4 h-4 text-brand-500"></i>Kamera Scanner
                </h3>
                <div class="flex gap-2">
                    <button id="btn-start-scan" onclick="startScanner()"
                        class="px-3 py-1.5 bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5">
                        <i data-lucide="play" class="w-3.5 h-3.5"></i>Mulai Scan
                    </button>
                    <button id="btn-stop-scan" onclick="stopScanner()"
                        class="hidden px-3 py-1.5 bg-rose-500 hover:bg-rose-600 text-white text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5">
                        <i data-lucide="square" class="w-3.5 h-3.5"></i>Stop
                    </button>
                </div>
            </div>

            <!-- QR Reader Area -->
            <div class="relative bg-slate-900 min-h-64">
                <div id="qr-reader" class="w-full"></div>
                <div id="scan-flash" class="hidden absolute inset-0 bg-emerald-400/30 rounded pointer-events-none z-10"></div>
                <div id="camera-placeholder" class="absolute inset-0 flex flex-col items-center justify-center text-slate-500 gap-3">
                    <div class="w-20 h-20 rounded-2xl border-2 border-dashed border-slate-600 flex items-center justify-center">
                        <i data-lucide="scan" class="w-10 h-10 text-slate-600"></i>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">Tekan "Mulai Scan" untuk mengaktifkan kamera</p>
                </div>
            </div>

            <!-- Manual Input Fallback -->
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Atau Masukkan ID/Kode Manual</p>
                <div class="flex gap-2">
                    <input id="manual-scan-input" type="text" placeholder="Contoh: INV-001 atau kode barcode..."
                        class="flex-1 bg-white border border-slate-200 rounded-xl py-2 px-3 text-sm text-slate-700 placeholder-slate-400 focus:border-brand-500 outline-none transition-all font-mono"
                        onkeydown="if(event.key==='Enter') handleManualSearch()">
                    <button onclick="handleManualSearch()"
                        class="px-3 py-2 bg-brand-500 text-white text-xs font-semibold rounded-xl hover:bg-brand-600 transition-all flex items-center gap-1">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i>Cari
                    </button>
                </div>
            </div>
        </div>

        <!-- RESULT PANEL -->
        <div class="space-y-4">
            <div id="scan-default-state" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-8 flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-2xl bg-brand-50 flex items-center justify-center mb-4">
                    <i data-lucide="scan-line" class="w-8 h-8 text-brand-500"></i>
                </div>
                <h4 class="text-sm font-bold text-slate-700">Siap untuk Scan</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-xs">Arahkan kamera ke QR code atau barcode barang. Hasil akan muncul di sini secara otomatis.</p>
            </div>

            <div id="scan-result-panel" class="hidden bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <!-- Diisi oleh scanner.js -->
            </div>

            <div class="bg-brand-50 border border-brand-100/50 rounded-2xl p-4 space-y-2">
                <h4 class="text-xs font-bold text-brand-700 flex items-center gap-1.5">
                    <i data-lucide="info" class="w-3.5 h-3.5"></i>Format yang Didukung
                </h4>
                <div class="grid grid-cols-2 gap-1.5">
                    <?php
                    $formats = ['QR Code','Code 128','Code 39','EAN-13','EAN-8','Data Matrix'];
                    foreach($formats as $f):
                    ?>
                    <div class="flex items-center gap-1.5 text-[11px] text-brand-700 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shrink-0"></span><?= $f ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-[10px] text-brand-600 pt-1 border-t border-brand-100">
                    💡 Tip: Nilai QR code = ID barang (contoh: <span class="font-mono font-bold">INV-001</span>)
                </p>
            </div>
        </div>
    </div>
</main>

<!-- ═══════════════════════════════════════════════════════
     MODAL CHECK-OUT: Chained Dropdown Lokasi Asal
     ═══════════════════════════════════════════════════════ -->
<div id="checkout-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeCheckoutModal()"></div>

    <!-- Dialog -->
    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden animate-fade-in">

        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-cyan-50 to-teal-50">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-cyan-100 flex items-center justify-center">
                    <i data-lucide="package-minus" class="w-4 h-4 text-cyan-600"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-800">Check-Out Barang</p>
                    <p id="co-barang-label" class="text-[11px] text-slate-400 font-mono mt-0.5">—</p>
                </div>
            </div>
            <button onclick="closeCheckoutModal()" class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-all">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="p-5 space-y-4">

            <!-- Jumlah Unit -->
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                    Jumlah Unit
                </label>
                <input id="co-jumlah" type="number" min="1" placeholder="1"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3.5 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-transparent transition-all">
            </div>

            <!-- ── Pemilihan Lokasi Asal (Chained Dropdown) ── -->
            <div class="space-y-3 rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="map-pin" class="w-3 h-3 text-teal-500"></i>
                    Pilih Lokasi Asal Pengambilan
                </p>

                <!-- Dropdown 1: Cabang Gudang -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Cabang Gudang</label>
                    <div class="relative">
                        <select id="co-select-cabang"
                            class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-transparent transition-all appearance-none cursor-pointer">
                            <option value="">⏳ Memuat data gudang...</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                    </div>
                </div>

                <!-- Dropdown 2: Zona -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Zona</label>
                    <div class="relative">
                        <select id="co-select-zona" disabled
                            class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-transparent transition-all appearance-none disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                            <option value="">— Pilih Gudang dulu —</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                    </div>
                </div>

                <!-- Dropdown 3: Baris & Level Rak -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Baris &amp; Level Rak</label>
                    <div class="relative">
                        <select id="co-select-rak" disabled
                            class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-transparent transition-all appearance-none disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                            <option value="">— Pilih Zona dulu —</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                    </div>
                </div>

                <!-- Info stok rak terpilih -->
                <div id="co-stok-info" class="hidden text-[11px] text-teal-700 bg-teal-50 border border-teal-100 rounded-lg px-3 py-2 flex items-center gap-2">
                    <i data-lucide="info" class="w-3.5 h-3.5 shrink-0"></i>
                    <span id="co-stok-info-text"></span>
                </div>
            </div>

            <!-- Catatan -->
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Catatan (Opsional)</label>
                <input id="co-catatan" type="text" placeholder="Misal: Dipinjam Dept. Marketing"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-transparent transition-all">
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="flex items-center justify-end gap-2.5 px-5 py-4 border-t border-slate-100 bg-slate-50/50">
            <button onclick="closeCheckoutModal()"
                class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition-all">
                Batal
            </button>
            <button id="co-submit-btn" onclick="submitCheckout()"
                class="px-5 py-2 bg-gradient-to-r from-brand-500 to-brand-accent text-white text-xs font-bold rounded-xl hover:opacity-90 active:scale-95 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <i data-lucide="package-minus" class="w-3.5 h-3.5"></i>Konfirmasi Check-Out
            </button>
        </div>
    </div>
</div>

<div id="toastContainer" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none"></div>

<!-- Scripts -->
<script src="assets/js/api.config.js"></script>
<script src="assets/js/app.js" defer></script>
<script src="assets/js/scanner.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    initScanner();
    document.getElementById('btn-start-scan').addEventListener('click', () => {
        document.getElementById('camera-placeholder').style.display = 'none';
        document.getElementById('scan-default-state').classList.add('hidden');
    });
});
</script>
</body>
</html>

