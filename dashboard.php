<?php
// dashboard.php - Tab: Analitik & Scanner (Fitur 3 & 4)
require_once 'auth.php';
protect_page();
$user = get_current_user_session();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard Analitik - Sistem Inventaris</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
tailwind.config = {
    theme: { extend: {
        fontFamily: { sans: ['"Plus Jakarta Sans"','sans-serif'], mono: ['"JetBrains Mono"','monospace'] },
        colors: {
            brand: { 50:'#f0fdfa',100:'#ccfbf1',500:'#0d9488',600:'#0f766e',accent:'#0891b2' },
            charcoal: { 800:'#1e293b',900:'#0f172a' }
        }
    }}
}
window.CurrentUser = {
    username: "<?php echo htmlspecialchars($user['username']); ?>",
    nama_lengkap: "<?php echo htmlspecialchars($user['nama_lengkap']); ?>",
    role: "<?php echo htmlspecialchars($user['role']); ?>"
};
</script>
<link rel="stylesheet" href="assets/css/style.css">
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

        <!-- TAB NAVIGATION -->
        <nav class="hidden md:flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
            <a href="index.php" class="nav-tab px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-white hover:text-slate-800 transition-all flex items-center gap-1.5">
                <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>Inventaris
            </a>
            <a href="locations.php" class="nav-tab px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-white hover:text-slate-800 transition-all flex items-center gap-1.5">
                <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>Lokasi Rak
            </a>
            <a href="scanner.php" class="nav-tab px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-white hover:text-slate-800 transition-all flex items-center gap-1.5">
                <i data-lucide="scan-line" class="w-3.5 h-3.5"></i>Scanner
            </a>
            <a href="dashboard.php" class="nav-tab px-3 py-1.5 rounded-lg text-xs font-semibold bg-white text-brand-600 shadow-sm transition-all flex items-center gap-1.5">
                <i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i>Analitik
            </a>
        </nav>

        <div class="flex items-center gap-2">
            <span id="sse-status" class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">🟢 Live</span>
            <a href="auth.php?action=logout" class="text-xs text-slate-400 hover:text-rose-500 transition-colors flex items-center gap-1">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
            </a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Dashboard Analitik</h2>
            <p class="text-xs text-slate-400 mt-0.5">Data tren penjualan & arus logistik secara real-time</p>
        </div>
        <!-- Range Filter -->
        <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
            <button id="range-daily"  onclick="setAnalyticsRange('daily')"  class="range-tab px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 transition-all">Harian</button>
            <button id="range-weekly" onclick="setAnalyticsRange('weekly')" class="range-tab px-3 py-1.5 rounded-lg text-xs font-semibold bg-brand-500 text-white shadow-sm">Mingguan</button>
            <button id="range-monthly" onclick="setAnalyticsRange('monthly')" class="range-tab px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 transition-all">Bulanan</button>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <section class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <?php
        $cards = [
            ['an-total-jenis',  'Jenis Barang',     'archive',          'brand-500',  '-'],
            ['an-total-unit',   'Total Unit',        'layers',           'cyan-500',   '-'],
            ['an-stok-menipis', 'Stok Kritis',      'alert-triangle',   'amber-500',  '-'],
            ['an-stok-habis',   'Stok Habis',        'package-x',        'rose-500',   '-'],
            ['an-tx-hari-ini',  'Transaksi Hari Ini','activity',         'violet-500', '-'],
        ];
        $bg = ['bg-brand-50','bg-cyan-50','bg-amber-50','bg-rose-50','bg-violet-50'];
        $tc = ['text-brand-500','text-cyan-500','text-amber-500','text-rose-500','text-violet-500'];
        foreach ($cards as $i => $c):
        ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-start justify-between mb-3">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-tight"><?= $c[1] ?></p>
                <div class="w-8 h-8 rounded-lg <?= $bg[$i] ?> <?= $tc[$i] ?> flex items-center justify-center shrink-0">
                    <i data-lucide="<?= $c[2] ?>" class="w-4 h-4"></i>
                </div>
            </div>
            <p id="<?= $c[0] ?>" class="text-2xl font-black text-slate-800">–</p>
        </div>
        <?php endforeach; ?>
    </section>

    <!-- FLOW MASUK VS KELUAR (BADGE) -->
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-4 text-white">
            <p class="text-xs font-semibold text-emerald-100 uppercase tracking-wider">Total Masuk (Periode)</p>
            <p id="an-flow-masuk" class="text-3xl font-black mt-1">– unit</p>
            <div class="flex items-center gap-1 mt-2 text-emerald-200 text-xs"><i data-lucide="arrow-down-circle" class="w-3.5 h-3.5"></i>Barang diterima</div>
        </div>
        <div class="bg-gradient-to-br from-cyan-500 to-blue-600 rounded-2xl p-4 text-white">
            <p class="text-xs font-semibold text-cyan-100 uppercase tracking-wider">Total Keluar (Periode)</p>
            <p id="an-flow-keluar" class="text-3xl font-black mt-1">– unit</p>
            <div class="flex items-center gap-1 mt-2 text-cyan-200 text-xs"><i data-lucide="arrow-up-circle" class="w-3.5 h-3.5"></i>Barang dikeluarkan</div>
        </div>
    </div>

    <!-- CHARTS ROW 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <!-- Flow Chart: 3/5 width -->
        <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Arus Logistik</h3>
                    <p class="text-[11px] text-slate-400">Barang masuk vs keluar per hari</p>
                </div>
                <i data-lucide="trending-up" class="w-4 h-4 text-brand-500"></i>
            </div>
            <div class="h-52">
                <canvas id="chart-flow"></canvas>
            </div>
        </div>

        <!-- Category Doughnut: 2/5 width -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Distribusi Stok</h3>
                    <p class="text-[11px] text-slate-400">Per kategori barang</p>
                </div>
                <i data-lucide="pie-chart" class="w-4 h-4 text-brand-500"></i>
            </div>
            <div class="h-52">
                <canvas id="chart-category"></canvas>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 2 -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <!-- Top Products: 3/5 -->
        <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Top 5 Barang Terlaris</h3>
                    <p class="text-[11px] text-slate-400">Berdasarkan total unit keluar</p>
                </div>
                <i data-lucide="award" class="w-4 h-4 text-amber-500"></i>
            </div>
            <div class="h-52 relative">
                <canvas id="chart-top-products"></canvas>
                <div id="chart-top-empty" class="hidden absolute inset-0 flex items-center justify-center">
                    <p class="text-xs text-slate-400">Belum ada data mutasi keluar.</p>
                </div>
            </div>
        </div>

        <!-- Recent Activity: 2/5 -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 p-5 shadow-sm flex flex-col">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Aktivitas Terbaru</h3>
                    <p class="text-[11px] text-slate-400">Real-time via SSE</p>
                </div>
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            </div>
            <div id="recent-activity-list" class="flex-1 overflow-y-auto divide-y divide-slate-50 max-h-52">
                <div class="py-6 text-center text-xs text-slate-400">Memuat aktivitas...</div>
            </div>
        </div>
    </div>

</main>

<!-- TOAST CONTAINER -->
<div id="toastContainer" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none"></div>

<script src="assets/js/analytics.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    initAnalyticsDashboard();
});
</script>
</body>
</html>
