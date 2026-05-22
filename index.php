<?php
// index.php - Dashboard Sistem Inventaris Kantor (Dilengkapi Sesi & Hak Akses)
require_once 'auth.php';

// Memproteksi halaman: jika belum login, alihkan ke login.php
protect_page();

// Mengambil info user dari sesi login
$user = get_current_user_session();
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaris - Sistem Inventaris Kantor</title>
    
    <!-- Meta SEO -->
    <meta name="description" content="Sistem Inventaris Kantor minimalis, bersih, dan modern untuk memantau, menambah, dan mengubah data barang secara cepat dan efisien.">
    <meta name="author" content="Admin Office Inventory">
    
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Chart.js (untuk widget stok mini di dashboard utama) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Tailwind Config for Custom Theme & Fonts -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        charcoal: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            800: '#1e293b',
                            900: '#0f172a',
                        },
                        brand: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            500: '#0d9488', // Teal lembut
                            600: '#0f766e',
                            700: '#115e59',
                            accent: '#0891b2', // Cyan
                        }
                    },
                    boxShadow: {
                        'soft': '0 2px 12px -1px rgba(0, 0, 0, 0.03), 0 4px 30px -2px rgba(0, 0, 0, 0.02)',
                        'premium': '0 10px 30px -5px rgba(0, 0, 0, 0.04), 0 1px 3px 0 rgba(0, 0, 0, 0.01)',
                        'modal': '0 20px 50px -12px rgba(0, 0, 0, 0.08), 0 1px 2px 0 rgba(0, 0, 0, 0.02)',
                        'glow': '0 0 20px 0 rgba(13, 148, 136, 0.15)',
                    }
                }
            }
        }
    </script>

    <!-- Custom CSS (Isolated) -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Inject User Info to JS Global Context -->
    <script>
        window.CurrentUser = {
            username: "<?php echo htmlspecialchars($user['username']); ?>",
            nama_lengkap: "<?php echo htmlspecialchars($user['nama_lengkap']); ?>",
            role: "<?php echo htmlspecialchars($user['role']); ?>"
        };
    </script>
    
    <!-- Custom JS (Isolated & Deferred) -->
    <script src="assets/js/api.config.js"></script>
    <script src="assets/js/app.js" defer></script>
</head>
<body class="bg-charcoal-50 bg-grid-pattern text-charcoal-800 font-sans min-h-screen antialiased flex flex-col selection:bg-brand-100 selection:text-brand-700">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-slate-100 shadow-sm transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">

            <!-- Logo + Nav Tab -->
            <div class="flex items-center gap-4 shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-brand-500 to-brand-accent flex items-center justify-center text-white shadow-soft shadow-brand-500/20">
                        <i data-lucide="package" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h1 class="text-sm font-bold text-charcoal-900 tracking-tight leading-tight">Office Inventory</h1>
                        <p class="text-[10px] text-slate-400 font-medium tracking-wider uppercase leading-none">Sistem Administrasi</p>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <nav class="hidden lg:flex items-center gap-0.5 bg-slate-100 p-1 rounded-xl ml-2">
                    <a href="index.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-semibold bg-white text-brand-600 shadow-sm transition-all">
                        <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>Inventaris
                    </a>
                    <a href="locations.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-slate-500 hover:bg-white hover:text-slate-800 transition-all">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>Lokasi Rak
                    </a>
                    <a href="scanner.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-slate-500 hover:bg-white hover:text-slate-800 transition-all">
                        <i data-lucide="scan-line" class="w-3.5 h-3.5"></i>Scanner
                    </a>
                    <a href="dashboard.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-slate-500 hover:bg-white hover:text-slate-800 transition-all">
                        <i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i>Analitik
                    </a>
                </nav>
            </div>

            <!-- Search Bar (Minimalist & Centered) -->
            <div class="hidden md:flex items-center flex-1 max-w-lg relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 group-focus-within:text-brand-500 transition-colors">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </div>
                <input 
                    type="text" 
                    id="searchBar" 
                    placeholder="Cari berdasarkan nama barang atau kode ID...  [ / ]" 
                    class="w-full bg-slate-50/50 hover:bg-slate-50 border border-slate-200/80 rounded-xl py-2 pl-9 pr-4 text-sm text-slate-700 placeholder-slate-400 transition-all duration-200 focus:bg-white focus:border-brand-500"
                >
                <button 
                    id="clearSearch" 
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-300 hover:text-slate-500 hidden transition-colors"
                    title="Clear search"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Admin Profile Section -->
            <div class="flex items-center gap-3 shrink-0">
                <!-- Dropdown Trigger -->
                <div class="relative">
                    <button id="profileDropdownBtn" class="flex items-center gap-3 p-1.5 rounded-xl hover:bg-slate-50 transition-colors group">
                        <div class="relative">
                            <div class="w-9 h-9 rounded-lg bg-gradient-to-tr from-brand-500 to-brand-accent text-white flex items-center justify-center font-bold text-sm shadow-soft">
                                <?php 
                                    $initials = "";
                                    $names = explode(" ", $user['nama_lengkap']);
                                    foreach ($names as $n) {
                                        $initials .= strtoupper(substr($n, 0, 1));
                                    }
                                    echo htmlspecialchars(substr($initials, 0, 2));
                                ?>
                            </div>
                            <span class="absolute bottom-0 right-0 block h-2.5 w-2.5 rounded-full ring-2 ring-white bg-emerald-500" title="Online"></span>
                        </div>
                        <div class="hidden sm:block text-left pr-1">
                            <h2 class="text-xs font-semibold text-charcoal-900 group-hover:text-brand-500 transition-colors leading-tight"><?php echo htmlspecialchars($user['nama_lengkap']); ?></h2>
                            <p class="text-[10px] text-slate-400 leading-none capitalize"><?php echo htmlspecialchars($user['role']); ?></p>
                        </div>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 hidden sm:block"></i>
                    </button>

                    <!-- Profile Dropdown Card -->
                    <div id="profileDropdown" class="absolute right-0 mt-2 w-56 rounded-xl bg-white border border-slate-100 shadow-modal py-1.5 hidden animate-fade-in z-50">
                        <div class="px-4 py-3 border-b border-slate-50">
                            <p class="text-xs text-slate-400">Masuk sebagai</p>
                            <p class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($user['username']); ?></p>
                        </div>
                        <div class="px-4 py-2 text-xs text-slate-500 bg-slate-50/50 flex items-center gap-1.5 font-semibold">
                            <span class="inline-block w-2 h-2 rounded-full <?php echo $user['role'] === 'admin' ? 'bg-brand-500' : 'bg-cyan-500'; ?>"></span>
                            Role: <span class="capitalize text-slate-800"><?php echo htmlspecialchars($user['role']); ?></span>
                        </div>
                        <div class="border-t border-slate-50 my-1"></div>
                        <a href="auth.php?action=logout" class="flex items-center gap-2 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 transition-colors">
                            <i data-lucide="log-out" class="w-4 h-4"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Search Bar -->
    <div class="md:hidden px-4 pt-4 shrink-0">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <i data-lucide="search" class="w-4 h-4"></i>
            </div>
            <input 
                type="text" 
                id="searchBarMobile" 
                placeholder="Cari barang atau kode ID..." 
                class="w-full bg-white border border-slate-200 rounded-xl py-2.5 pl-9 pr-4 text-sm text-slate-700 placeholder-slate-400 shadow-sm focus:border-brand-500 focus:bg-white transition-all"
            >
        </div>
    </div>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 sm:space-y-8">

        <!-- Role Banner Notification -->
        <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-soft flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-600 flex items-center justify-center shrink-0">
                    <i data-lucide="shield-check" class="w-5 h-5 text-brand-500"></i>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-slate-800">Sistem Autentikasi Aktif</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        Anda masuk sebagai <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($user['nama_lengkap']); ?></span> 
                        dengan role <span class="font-bold text-brand-600 capitalize bg-brand-50 px-1.5 py-0.5 rounded text-[10px]"><?php echo htmlspecialchars($user['role']); ?></span>.
                    </p>
                </div>
            </div>
            <?php if ($user['role'] === 'karyawan'): ?>
                <div class="bg-cyan-50 border border-cyan-100/50 rounded-xl px-3 py-2 text-[10px] text-cyan-800 font-medium flex items-center gap-1.5 self-start sm:self-auto">
                    <i data-lucide="info" class="w-3.5 h-3.5 shrink-0 text-cyan-600"></i>
                    <span>Pembatasan: Anda hanya dapat memperbarui kuantitas Stok dan Dipakai pada barang terdaftar.</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Metrics/Stats Section (3 Card Kecil di Atas) -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            
            <!-- Card 1: Total Barang -->
            <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft hover:shadow-premium hover:-translate-y-0.5 transition-all duration-300 group">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Barang</p>
                        <h3 id="statTotalItems" class="text-3.5xl font-bold text-slate-800 mt-2 leading-none tracking-tight">0</h3>
                        <p class="text-xs text-slate-400 mt-2.5 flex items-center gap-1">
                            <span id="statTotalUnits" class="font-semibold text-slate-600">0 unit</span> total di sistem
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-brand-50 text-brand-500 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                        <i data-lucide="archive" class="w-6 h-6"></i>
                    </div>
                </div>
            </div>

            <!-- Card 2: Barang Keluar/Dipakai -->
            <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft hover:shadow-premium hover:-translate-y-0.5 transition-all duration-300 group">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Barang Keluar / Dipakai</p>
                        <h3 id="statItemsInUse" class="text-3.5xl font-bold text-slate-800 mt-2 leading-none tracking-tight">0</h3>
                        <p class="text-xs text-slate-400 mt-2.5 flex items-center gap-1">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse"></span>
                            Sedang digunakan oleh staf
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                        <i data-lucide="arrow-up-right" class="w-6 h-6"></i>
                    </div>
                </div>
            </div>

            <!-- Card 3: Stok Menipis -->
            <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft hover:shadow-premium hover:-translate-y-0.5 transition-all duration-300 group sm:col-span-2 lg:col-span-1">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Stok Menipis</p>
                        <h3 id="statLowStock" class="text-3.5xl font-bold text-rose-500 mt-2 leading-none tracking-tight">0</h3>
                        <p class="text-xs text-rose-500/80 mt-2.5 flex items-center gap-1 font-medium">
                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                            <span id="lowStockWarningText">Stok barang di bawah &le; 5 unit</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                        <i data-lucide="alert-circle" class="w-6 h-6"></i>
                    </div>
                </div>
            </div>

        </section>

        <!-- Main Section: Action Bar & Inventory Table -->
        <section class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden flex flex-col">
            
            <!-- Action Bar Inside Table Card -->
            <div class="p-5 border-b border-slate-100 bg-white flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                
                <!-- Category Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0 scroll-smooth no-scrollbar">
                    <button onclick="filterCategory('Semua')" id="filterBtnSemua" class="filter-tab px-4 py-2 rounded-xl text-xs font-semibold transition-all duration-200 bg-brand-500 text-white shadow-soft shadow-brand-500/10">
                        Semua
                    </button>
                    <button onclick="filterCategory('Elektronik')" id="filterBtnElektronik" class="filter-tab px-4 py-2 rounded-xl text-xs font-semibold transition-all duration-200 text-slate-500 hover:bg-slate-50 hover:text-slate-800">
                        Elektronik
                    </button>
                    <button onclick="filterCategory('Furnitur')" id="filterBtnFurnitur" class="filter-tab px-4 py-2 rounded-xl text-xs font-semibold transition-all duration-200 text-slate-500 hover:bg-slate-50 hover:text-slate-800">
                        Furnitur
                    </button>
                    <button onclick="filterCategory('ATK')" id="filterBtnATK" class="filter-tab px-4 py-2 rounded-xl text-xs font-semibold transition-all duration-200 text-slate-500 hover:bg-slate-50 hover:text-slate-800">
                        ATK
                    </button>
                </div>

                <!-- Action Buttons: Filter, Export, Add Item -->
                <div class="flex items-center gap-2.5 w-full sm:w-auto shrink-0 justify-end">
                    
                    <!-- Advanced Filter Dropdown -->
                    <div class="relative">
                        <button id="sortBtn" class="flex items-center gap-2 px-3.5 py-2 border border-slate-200 hover:border-slate-300 rounded-xl text-xs font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-50 transition-all">
                            <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
                            <span>Urutkan</span>
                            <i data-lucide="chevron-down" class="w-3 h-3 text-slate-400"></i>
                        </button>
                        
                        <!-- Sort Dropdown Menu -->
                        <div id="sortDropdown" class="absolute right-0 mt-2 w-48 rounded-xl bg-white border border-slate-100 shadow-modal py-1.5 hidden animate-fade-in z-30">
                            <button onclick="applySort('id')" class="w-full text-left px-4 py-2 text-xs text-slate-600 hover:bg-slate-50 hover:text-brand-500 transition-colors flex items-center justify-between">
                                ID Barang <span>&uarr;&darr;</span>
                            </button>
                            <button onclick="applySort('name')" class="w-full text-left px-4 py-2 text-xs text-slate-600 hover:bg-slate-50 hover:text-brand-500 transition-colors flex items-center justify-between">
                                Nama Barang (A-Z) <span>A-Z</span>
                            </button>
                            <button onclick="applySort('stock-high')" class="w-full text-left px-4 py-2 text-xs text-slate-600 hover:bg-slate-50 hover:text-brand-500 transition-colors flex items-center justify-between">
                                Stok Terbanyak <span>&darr;</span>
                            </button>
                            <button onclick="applySort('stock-low')" class="w-full text-left px-4 py-2 text-xs text-slate-600 hover:bg-slate-50 hover:text-brand-500 transition-colors flex items-center justify-between">
                                Stok Tersedikit <span>&uarr;</span>
                            </button>
                        </div>
                    </div>

                    <!-- Export Button -->
                    <button onclick="exportToCSV()" class="flex items-center gap-2 px-3.5 py-2 border border-slate-200 hover:border-slate-300 rounded-xl text-xs font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-50 transition-all" title="Ekspor ke CSV">
                        <i data-lucide="download" class="w-3.5 h-3.5"></i>
                        <span class="hidden sm:inline">Ekspor</span>
                    </button>

                    <!-- Add Item Button (CTA) - Hidden for non-admin -->
                    <?php if ($user['role'] === 'admin'): ?>
                        <button onclick="openAddModal()" class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-brand-500 to-brand-accent text-white rounded-xl text-xs font-semibold hover:opacity-95 shadow-soft shadow-brand-500/10 hover:shadow-md hover:shadow-brand-500/20 active:scale-95 transition-all">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            <span>Tambah Barang</span>
                        </button>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Table Container (Responsive) -->
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider w-24">ID Barang</th>
                            <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nama Barang</th>
                            <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider w-40">Kategori</th>
                            <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider w-32">Jumlah Stok</th>
                            <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider w-32">Status</th>
                            <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider w-24 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody" class="divide-y divide-slate-100">
                        <tr id="tableSkeleton">
                            <td colspan="6" class="py-12 text-center text-slate-400 text-sm">
                                <div class="flex flex-col items-center justify-center gap-3">
                                    <div class="w-8 h-8 rounded-full border-2 border-brand-500/30 border-t-brand-500 animate-spin"></div>
                                    <p class="font-medium text-slate-500">Memuat data inventaris...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State Illustration -->
            <div id="emptyState" class="hidden py-16 px-6 flex flex-col items-center justify-center text-center animate-fade-in">
                <div class="w-20 h-20 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 mb-4 shadow-sm animate-float">
                    <i data-lucide="package-search" class="w-10 h-10"></i>
                </div>
                <h4 class="text-base font-semibold text-slate-800">Tidak ada barang ditemukan</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-sm">Coba ubah kata kunci pencarian Anda atau periksa filter kategori yang sedang aktif.</p>
                <button onclick="resetFilters()" class="mt-4 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-800 text-xs font-semibold rounded-lg transition-colors">
                    Reset Pencarian & Filter
                </button>
            </div>

            <!-- Table Footer -->
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row gap-3 items-center justify-between text-xs text-slate-500 font-medium">
                <div>
                    Menampilkan <span id="showingStartCount" class="text-slate-800">0</span> - <span id="showingEndCount" class="text-slate-800">0</span> dari <span id="totalItemsCount" class="text-slate-800">0</span> entri barang
                </div>
                <div class="flex items-center gap-1">
                    <button class="px-2.5 py-1.5 border border-slate-200 rounded-lg hover:bg-white text-slate-400 hover:text-slate-600 transition-colors disabled:opacity-50" disabled>
                        Sebelumnya
                    </button>
                    <button class="px-3 py-1.5 bg-brand-50 border border-brand-100 rounded-lg text-brand-600 font-semibold">
                        1
                    </button>
                    <button class="px-2.5 py-1.5 border border-slate-200 rounded-lg hover:bg-white text-slate-400 hover:text-slate-600 transition-colors disabled:opacity-50" disabled>
                        Selanjutnya
                    </button>
                </div>
            </div>

        </section>

    </main>

    <!-- Footer Page -->
    <footer class="mt-auto border-t border-slate-100 bg-white py-6 text-center text-xs text-slate-400 shrink-0 font-medium">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row justify-between items-center gap-3">
            <p>&copy; 2026 Office Inventory. Didesain dengan presisi dan minimalisme.</p>
            <div class="flex items-center gap-4">
                <a href="#" class="hover:text-brand-500 transition-colors">Bantuan</a>
                <span class="text-slate-200">|</span>
                <a href="#" class="hover:text-brand-500 transition-colors">Kebijakan Privasi</a>
                <span class="text-slate-200">|</span>
                <p class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Sistem Berjalan Optimal</p>
            </div>
        </div>
    </footer>

    <!-- ADD / EDIT MODAL OVERLAY -->
    <div id="itemModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity duration-300" onclick="closeModal()"></div>
        
        <!-- Modal Wrapper -->
        <div class="flex min-h-screen items-center justify-center p-4">
            
            <!-- Modal Body Card -->
            <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-modal border border-slate-100/50 p-6 animate-fade-in">
                
                <!-- Close Button -->
                <button onclick="closeModal()" class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition-colors" title="Batal & Tutup">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>

                <!-- Header -->
                <div class="flex items-center gap-3 mb-6">
                    <div id="modalIconContainer" class="w-10 h-10 rounded-xl bg-brand-50 text-brand-500 flex items-center justify-center">
                        <i data-lucide="package-plus" id="modalIcon" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 id="modalTitle" class="text-base font-bold text-slate-800 leading-tight">Tambah Barang Baru</h3>
                        <p id="modalSub" class="text-[11px] text-slate-400 mt-0.5">Masukkan data inventaris baru secara lengkap.</p>
                    </div>
                </div>

                <!-- Form -->
                <form id="itemForm" onsubmit="handleFormSubmit(event)" class="space-y-4">
                    
                    <!-- ID Input -->
                    <div>
                        <label for="itemIdInput" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">ID Barang</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="itemIdInput" 
                                required 
                                placeholder="CONTOH: INV-009" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3.5 text-sm font-semibold text-slate-700 placeholder-slate-400 transition-all font-mono"
                            >
                            <button 
                                type="button" 
                                id="generateIdBtn" 
                                onclick="generateNewId()"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 px-2.5 py-1 text-[10px] font-bold text-brand-600 bg-brand-50 hover:bg-brand-100 rounded-md transition-colors uppercase"
                            >
                                Acak ID
                            </button>
                        </div>
                        <p id="idHelpText" class="text-[10px] text-slate-400 mt-1">ID barang harus unik, gunakan kombinasi huruf dan angka.</p>
                    </div>

                    <!-- Name Input -->
                    <div>
                        <label for="itemNameInput" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Nama Barang</label>
                        <input 
                            type="text" 
                            id="itemNameInput" 
                            required 
                            placeholder="Contoh: Laptop Lenovo ThinkPad L14" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3.5 text-sm text-slate-700 placeholder-slate-400 transition-all"
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Category Select -->
                        <div>
                            <label for="itemCategoryInput" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Kategori</label>
                            <select 
                                id="itemCategoryInput" 
                                required
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-sm text-slate-700 transition-all cursor-pointer"
                            >
                                <option value="Elektronik">Elektronik</option>
                                <option value="Furnitur">Furnitur</option>
                                <option value="ATK">ATK</option>
                            </select>
                        </div>

                        <!-- Stock Input -->
                        <div>
                            <label for="itemStockInput" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Jumlah Stok</label>
                            <input 
                                type="number" 
                                id="itemStockInput" 
                                required 
                                min="0" 
                                placeholder="0" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3.5 text-sm text-slate-700 placeholder-slate-400 transition-all font-semibold"
                            >
                        </div>
                    </div>

                    <!-- In Use Input -->
                    <div>
                        <label for="itemInUseInput" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Jumlah Dipakai / Keluar</label>
                        <input 
                            type="number" 
                            id="itemInUseInput" 
                            required 
                            min="0" 
                            placeholder="0" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3.5 text-sm text-slate-700 placeholder-slate-400 transition-all font-semibold"
                        >
                        <p class="text-[10px] text-slate-400 mt-1">Jumlah unit yang sedang digunakan oleh karyawan saat ini.</p>
                    </div>

                    <!-- Initial Storage Location (Only shown when adding new item with stock > 0) -->
                    <div id="initialLocationSection" class="border-t border-slate-100 pt-4 space-y-3">
                        <label for="initialLocationInput" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Lokasi Penyimpanan Awal</label>
                        <select 
                            id="initialLocationInput" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-sm text-slate-700 transition-all cursor-pointer"
                        >
                            <option value="">-- Pilih Lokasi Penyimpanan --</option>
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Gudang, zona, baris, dan level rak tempat menaruh stok awal barang ini.</p>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 mt-6">
                        <button 
                            type="button" 
                            onclick="closeModal()" 
                            class="px-4 py-2 border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold transition-all"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-gradient-to-r from-brand-500 to-brand-accent text-white rounded-xl text-xs font-semibold hover:opacity-95 shadow-soft shadow-brand-500/10 active:scale-95 transition-all"
                        >
                            Simpan Perubahan
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- CONFIRM DELETE DIALOG -->
    <div id="deleteDialog" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeDeleteDialog()"></div>
        
        <!-- Wrapper -->
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="relative w-full max-w-sm bg-white rounded-2xl shadow-modal border border-slate-100 p-6 animate-fade-in text-center">
                
                <div class="mx-auto w-12 h-12 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center mb-4">
                    <i data-lucide="trash-2" class="w-6 h-6 animate-pulse"></i>
                </div>

                <h3 class="text-base font-bold text-slate-800">Hapus Barang?</h3>
                <p class="text-xs text-slate-400 mt-2">
                    Apakah Anda yakin ingin menghapus barang <span id="deleteItemName" class="font-semibold text-slate-700"></span>? Tindakan ini tidak dapat dibatalkan.
                </p>

                <div class="flex items-center justify-center gap-2.5 mt-6 pt-2 border-t border-slate-50">
                    <button 
                        onclick="closeDeleteDialog()" 
                        class="flex-1 px-4 py-2 border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold transition-all"
                    >
                        Batal
                    </button>
                    <button 
                        id="confirmDeleteBtn"
                        class="flex-1 px-4 py-2 bg-rose-500 hover:bg-rose-600 text-white rounded-xl text-xs font-semibold shadow-soft shadow-rose-500/15 transition-all active:scale-95"
                    >
                        Ya, Hapus
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- FLOATING TOAST NOTIFICATION CONTAINER -->
    <div id="toastContainer" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 max-w-md w-full pointer-events-none">
        <!-- Dynamic Toast gets rendered here -->
    </div>

</body>
</html>
