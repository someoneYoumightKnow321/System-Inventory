<?php
// locations.php - Halaman Manajemen Lokasi Rak Multi-Cabang (Fitur 1)
require_once 'auth.php';
protect_page();
$user = get_current_user_session();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Lokasi Rak Gudang - Sistem Inventaris</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
tailwind.config = {
    theme: { extend: {
        fontFamily: { sans: ['"Plus Jakarta Sans"','sans-serif'], mono: ['"JetBrains Mono"','monospace'] },
        colors: { brand: { 50:'#f0fdfa',100:'#ccfbf1',500:'#0d9488',600:'#0f766e',accent:'#0891b2' } }
    }}
}
window.CurrentUser = { role: "<?php echo htmlspecialchars($user['role']); ?>" };
</script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen antialiased">

<!-- NAV -->
<header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-slate-100 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
        <a href="index.php" class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-brand-500 to-brand-accent flex items-center justify-center text-white">
                <i data-lucide="package" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-sm font-bold text-slate-800">Office Inventory</p>
                <p class="text-[10px] text-slate-400 uppercase tracking-wider leading-none">Sistem Administrasi</p>
            </div>
        </a>
        <nav class="hidden md:flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
            <a href="index.php"     class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-white transition-all flex items-center gap-1.5"><i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>Inventaris</a>
            <a href="locations.php" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white text-brand-600 shadow-sm flex items-center gap-1.5"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i>Lokasi Rak</a>
            <a href="scanner.php"   class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-white transition-all flex items-center gap-1.5"><i data-lucide="scan-line" class="w-3.5 h-3.5"></i>Scanner</a>
            <a href="dashboard.php" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-white transition-all flex items-center gap-1.5"><i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i>Analitik</a>
        </nav>
        <div class="flex items-center gap-2">
            <span id="sse-live-dot" class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" title="SSE Aktif"></span>
            <a href="auth.php?action=logout" class="text-xs text-slate-400 hover:text-rose-500 transition-colors flex items-center gap-1">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
            </a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Lokasi Rak Multi-Cabang</h2>
            <p class="text-xs text-slate-400 mt-0.5">Hierarki: Cabang → Zona → Baris Rak → Level</p>
        </div>
        <div class="flex gap-2">
            <!-- Cari Barang untuk location tree -->
            <div class="relative">
                <input id="search-barang" type="text" placeholder="Cari barang (ID / nama)..."
                    class="bg-white border border-slate-200 rounded-xl py-2 pl-3 pr-24 text-sm text-slate-700 placeholder-slate-400 focus:border-brand-500 outline-none transition-all w-72">
                <button onclick="loadLocationTree()" class="absolute right-1.5 top-1/2 -translate-y-1/2 px-2.5 py-1 bg-brand-500 text-white text-[10px] font-bold rounded-lg hover:bg-brand-600 transition-all">
                    Lacak
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- DAFTAR CABANG GUDANG -->
        <div class="lg:col-span-1 space-y-4">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider px-1">Cabang Gudang</h3>
            <div id="cabang-list" class="space-y-3">
                <div class="bg-white rounded-2xl border border-slate-100 p-4 flex items-center gap-3">
                    <div class="w-6 h-6 rounded-full border-2 border-brand-500/30 border-t-brand-500 animate-spin shrink-0"></div>
                    <p class="text-xs text-slate-400">Memuat cabang...</p>
                </div>
            </div>
        </div>

        <!-- LOCATION TREE + LIVE FEED -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Location Tree Panel -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                        <i data-lucide="git-branch" class="w-4 h-4 text-brand-500"></i>
                        Pohon Lokasi Barang
                    </h3>
                    <span id="tree-barang-name" class="text-[10px] font-mono bg-slate-100 text-slate-500 px-2 py-1 rounded-lg">Pilih barang untuk dilacak</span>
                </div>
                <div id="location-tree" class="p-4 min-h-40">
                    <div class="flex flex-col items-center justify-center py-10 text-center gap-3 text-slate-400">
                        <i data-lucide="git-branch" class="w-10 h-10 text-slate-200"></i>
                        <p class="text-xs">Masukkan ID/nama barang dan tekan <strong>"Lacak"</strong> untuk melihat posisi rak.</p>
                    </div>
                </div>
            </div>

            <!-- Live Mutasi Feed (SSE) -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                        <i data-lucide="activity" class="w-4 h-4 text-emerald-500"></i>
                        Mutasi Real-Time
                    </h3>
                    <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>Live SSE
                    </span>
                </div>
                <div id="sse-feed" class="divide-y divide-slate-50 max-h-56 overflow-y-auto">
                    <div class="p-4 text-xs text-slate-400 text-center">Menghubungkan ke server...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- FORM MUTASI MANUAL -->
    <?php if ($user['role'] === 'admin' || $user['role'] === 'karyawan'): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
        <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
            <i data-lucide="arrow-left-right" class="w-4 h-4 text-brand-500"></i>
            Catat Mutasi Barang
        </h3>
        <form id="mutasi-form" class="space-y-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">ID Barang</label>
                    <input type="text" id="m-barang-id" placeholder="INV-001" required
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-sm font-mono text-slate-700 focus:border-brand-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Tipe Mutasi</label>
                    <select id="m-tipe" required class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-sm text-slate-700 focus:border-brand-500 outline-none transition-all cursor-pointer">
                        <option value="masuk">📦 Masuk (Restock)</option>
                        <option value="keluar">📤 Keluar (Checkout)</option>
                        <option value="pindah">↔️ Pindah Lokasi</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Jumlah Unit</label>
                    <input type="number" id="m-jumlah" min="1" placeholder="1" required
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-sm font-semibold text-slate-700 focus:border-brand-500 outline-none transition-all">
                </div>
                <div class="flex items-end">
                    <button type="submit"
                        class="w-full py-2 bg-gradient-to-r from-brand-500 to-brand-accent text-white text-xs font-bold rounded-xl hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-1.5">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>Simpan Mutasi
                    </button>
                </div>
            </div>

            <!-- Dynamic location fields container -->
            <div id="mutasi-lokasi-container" class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-100 pt-4 hidden">
                <div id="container-lokasi-asal" class="hidden">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Ambil Dari (Gudang & Rak Asal)</label>
                    <select id="m-lokasi-asal" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-xs text-slate-700 focus:border-brand-500 outline-none transition-all cursor-pointer">
                        <option value="">-- Pilih Lokasi Asal --</option>
                    </select>
                </div>
                <div id="container-lokasi-tujuan" class="hidden">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Tempatkan Di (Gudang & Rak Tujuan)</label>
                    <select id="m-lokasi-tujuan" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-xs text-slate-700 focus:border-brand-500 outline-none transition-all cursor-pointer">
                        <option value="">-- Pilih Lokasi Tujuan --</option>
                    </select>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

</main>

<div id="toastContainer" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none"></div>

<script src="assets/js/api.config.js"></script>
<script src="assets/js/app.js" defer></script>
<script>
// ---- LOCATIONS PAGE LOGIC ----
let sseFeed = null;

document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    loadCabangList();
    connectLocationsSSE();
    document.getElementById('mutasi-form')?.addEventListener('submit', submitMutasi);
    document.getElementById('m-tipe')?.addEventListener('change', updateMutationFields);
    document.getElementById('m-barang-id')?.addEventListener('blur', updateMutationFields);
    document.getElementById('m-barang-id')?.addEventListener('input', () => {
        const val = document.getElementById('m-barang-id').value.trim();
        if (val.length >= 7) updateMutationFields();
    });
    
    const searchBar = document.getElementById('search-barang');
    if (searchBar) {
        searchBar.addEventListener('keydown', e => {
            if (e.key === 'Enter') loadLocationTree();
        });
    }

    // Baca query parameters untuk prefill form mutasi
    const urlParams = new URLSearchParams(window.location.search);
    const paramBarangId = urlParams.get('barang_id');
    const paramAction = urlParams.get('action');
    
    if (paramBarangId) {
        const inputBarang = document.getElementById('m-barang-id');
        if (inputBarang) {
            inputBarang.value = paramBarangId.toUpperCase();
        }
        
        if (paramAction === 'checkout') {
            const selectTipe = document.getElementById('m-tipe');
            if (selectTipe) {
                selectTipe.value = 'keluar';
            }
        }
        
        // Jalankan update form lokasi
        updateMutationFields();
    }
});

// Load daftar cabang
async function loadCabangList() {
    try {
        const res  = await fetch(API_CONFIG.CABANG);
        const json = await res.json();
        const el   = document.getElementById('cabang-list');

        if (!json.data || json.data.length === 0) {
            el.innerHTML = '<p class="text-xs text-slate-400 p-4">Belum ada cabang terdaftar.</p>';
            return;
        }

        el.innerHTML = json.data.map(c => `
            <div class="bg-white rounded-2xl border border-slate-100 p-4 hover:border-brand-200 hover:shadow-sm transition-all cursor-pointer" onclick="filterByCabang(${c.id})">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[10px] font-mono font-bold text-brand-500">${c.kode}</p>
                        <h4 class="text-sm font-bold text-slate-800 mt-0.5 leading-tight">${c.nama}</h4>
                        <p class="text-[10px] text-slate-400 mt-1 truncate max-w-[180px]">${c.alamat || '–'}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-lg font-black text-slate-800">${c.jumlah_rak}</p>
                        <p class="text-[10px] text-slate-400">rak aktif</p>
                        <p class="text-xs font-bold text-brand-500 mt-1">${c.total_unit} unit</p>
                    </div>
                </div>
            </div>
        `).join('');
    } catch (e) {
        console.error('Load cabang error:', e);
    }
}

// Load location tree untuk sebuah barang
async function loadLocationTree() {
    const q = document.getElementById('search-barang').value.trim();
    if (!q) return;

    const tree = document.getElementById('location-tree');
    tree.innerHTML = `<div class="flex items-center gap-3 py-6 justify-center"><div class="w-5 h-5 rounded-full border-2 border-brand-500/30 border-t-brand-500 animate-spin"></div><span class="text-xs text-slate-400">Mencari lokasi...</span></div>`;

    try {
        const res  = await fetch(API_CONFIG.treeUrl(q));
        const json = await res.json();

        if (!json.data || json.data.length === 0) {
            tree.innerHTML = `<div class="py-8 text-center"><p class="text-xs text-slate-400">Barang <strong class="font-mono">${q}</strong> tidak memiliki lokasi rak terdaftar.</p></div>`;
            return;
        }

        document.getElementById('tree-barang-name').textContent = q;

        tree.innerHTML = json.data.map(cabang => `
            <div class="mb-4 last:mb-0">
                <!-- Cabang -->
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-6 h-6 rounded-lg bg-slate-800 text-white flex items-center justify-center shrink-0">
                        <i data-lucide="building-2" class="w-3 h-3"></i>
                    </div>
                    <span class="text-xs font-bold text-slate-800">${cabang.cabang_nama}</span>
                    <span class="font-mono text-[10px] text-slate-400">(${cabang.cabang_kode})</span>
                    <span class="ml-auto text-xs font-bold text-brand-500">${cabang.total_unit} unit</span>
                </div>
                <!-- Zona -->
                ${Object.values(cabang.zona).map(zona => `
                <div class="ml-4 mb-2">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-px h-4 bg-slate-200 shrink-0"></span>
                        <i data-lucide="folder" class="w-3.5 h-3.5 text-amber-500 shrink-0"></i>
                        <span class="text-xs font-semibold text-slate-600">${zona.label}</span>
                    </div>
                    <!-- Baris -->
                    ${Object.values(zona.baris).map(baris => `
                    <div class="ml-6 mb-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-px h-3 bg-slate-200 shrink-0"></span>
                            <i data-lucide="align-justify" class="w-3 h-3 text-slate-400 shrink-0"></i>
                            <span class="text-[11px] font-semibold text-slate-500">${baris.label}</span>
                        </div>
                        <!-- Level -->
                        <div class="ml-5 flex flex-wrap gap-2">
                            ${baris.level.map(lv => `
                            <div class="flex items-center gap-1.5 bg-brand-50 border border-brand-100 rounded-lg px-2.5 py-1">
                                <i data-lucide="box" class="w-3 h-3 text-brand-500 shrink-0"></i>
                                <span class="font-mono text-[10px] font-bold text-brand-600">${lv.kode_lengkap.split('-').slice(-1)[0]}</span>
                                <span class="text-[10px] text-brand-500 font-semibold">${lv.jumlah} unit</span>
                            </div>`).join('')}
                        </div>
                    </div>`).join('')}
                </div>`).join('')}
            </div>
        `).join('');

        lucide.createIcons();
    } catch (e) {
        tree.innerHTML = `<p class="text-xs text-rose-500 p-4">Gagal memuat: ${e.message}</p>`;
    }
}

// SSE Live Feed untuk mutations dengan Initial Load data lama
async function connectLocationsSSE() {
    if (!window.EventSource) return;
    const feed = document.getElementById('sse-feed');

    // --- LANGKAH A: Ambil riwayat mutasi lama dari database ---
    try {
        const res = await fetch(API_CONFIG.riwayatUrl(5));
        const json = await res.json();
        
        if (json.status === 'success' && json.data.length > 0) {
            feed.innerHTML = ''; // Bersihkan tulisan "Menghubungkan..."
            
            json.data.forEach(d => {
                const tipeColors = { masuk:'text-emerald-600 bg-emerald-50', keluar:'text-rose-600 bg-rose-50', pindah:'text-violet-600 bg-violet-50' };
                const cls = tipeColors[d.tipe] || 'text-slate-600 bg-slate-50';
                
                const item = document.createElement('div');
                item.className = 'flex items-center gap-3 p-3';
                item.innerHTML = `
                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${cls} shrink-0">${d.tipe}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-700 truncate">${d.barang_nama || d.barang_id}</p>
                        <p class="text-[10px] text-slate-400">${d.jumlah} unit · ${d.user_nama || 'System'}</p>
                    </div>
                    <span class="text-[10px] text-slate-400 shrink-0">Arsip</span>`;
                feed.appendChild(item); // Gunakan appendChild agar yang paling baru tetap di atas berdasarkan query DESC
            });
        }
    } catch (err) {
        console.error("Gagal memuat riwayat awal:", err);
    }

    // --- LANGKAH B: Lanjutkan koneksi SSE untuk mutasi real-time mendatang ---
    const es = new EventSource(API_CONFIG.SSE);
    
    es.addEventListener('connected', () => {
        // Jika feed masih kosong (belum ada riwayat lama), tampilkan status standby
        if (feed.children.length === 0 || feed.querySelector('.text-center')) {
            feed.innerHTML = '<div class="p-3 text-[11px] text-center text-emerald-500 font-semibold">✓ Terhubung – menunggu aktivitas...</div>';
        }
        document.getElementById('sse-live-dot')?.classList.replace('bg-rose-500','bg-emerald-500');
    });

    es.addEventListener('mutasi_baru', e => {
        const d = JSON.parse(e.data);
        const tipeColors = { masuk:'text-emerald-600 bg-emerald-50', keluar:'text-rose-600 bg-rose-50', pindah:'text-violet-600 bg-violet-50' };
        const cls = tipeColors[d.tipe] || 'text-slate-600 bg-slate-50';

        const item = document.createElement('div');
        item.className = 'flex items-center gap-3 p-3 animate-fade-in';
        item.innerHTML = `
            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${cls} shrink-0">${d.tipe}</span>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-slate-700 truncate">${d.barang_nama}</p>
                <p class="text-[10px] text-slate-400">${d.jumlah} unit · ${d.user_nama}</p>
            </div>
            <span class="text-[10px] text-emerald-500 font-bold shrink-0">Baru</span>`;

        // Hapus placeholder teks standby jika ada
        const placeholder = feed.querySelector('.text-center');
        if (placeholder) placeholder.remove();

        feed.prepend(item); // Taruh item baru di paling atas list
        
        // Batasi maksimal 8 item di layar agar tidak memanjang kebawah
        const items = feed.querySelectorAll('.flex.items-center.gap-3');
        if (items.length > 8) items[items.length - 1].remove();

        lucide.createIcons();
    });

    es.addEventListener('error', () => {
        document.getElementById('sse-live-dot')?.classList.replace('bg-emerald-500','bg-rose-500');
    });
}

// Update form input lokasi secara dinamis berdasarkan tipe mutasi dan ID barang
async function updateMutationFields() {
    const barangId = document.getElementById('m-barang-id').value.trim().toUpperCase();
    const tipe = document.getElementById('m-tipe').value;

    const container = document.getElementById('mutasi-lokasi-container');
    const containerAsal = document.getElementById('container-lokasi-asal');
    const containerTujuan = document.getElementById('container-lokasi-tujuan');
    const selectAsal = document.getElementById('m-lokasi-asal');
    const selectTujuan = document.getElementById('m-lokasi-tujuan');

    if (!barangId) {
        container?.classList.add('hidden');
        return;
    }

    container?.classList.remove('hidden');

    if (tipe === 'masuk') {
        containerAsal?.classList.add('hidden');
        containerTujuan?.classList.remove('hidden');
        selectAsal?.removeAttribute('required');
        selectTujuan?.setAttribute('required', 'required');
    } else if (tipe === 'keluar') {
        containerAsal?.classList.remove('hidden');
        containerTujuan?.classList.add('hidden');
        selectAsal?.setAttribute('required', 'required');
        selectTujuan?.removeAttribute('required');
    } else if (tipe === 'pindah') {
        containerAsal?.classList.remove('hidden');
        containerTujuan?.classList.remove('hidden');
        selectAsal?.setAttribute('required', 'required');
        selectTujuan?.setAttribute('required', 'required');
    }

    // Load lokasi asal (rak yang berisi barang ini) jika tipe keluar atau pindah
    if ((tipe === 'keluar' || tipe === 'pindah') && selectAsal) {
        try {
            selectAsal.innerHTML = '<option value="">⏳ Memuat lokasi asal...</option>';
            const res = await fetch(API_CONFIG.rakUrl(null, barangId));
            const json = await res.json();
            if (json.status === 'success' && json.data.length > 0) {
                selectAsal.innerHTML = '<option value="">-- Pilih Lokasi Asal --</option>' + 
                    json.data.map(r => `<option value="${r.id}">${r.kode_lengkap} (Stok: ${r.terisi} unit)</option>`).join('');
            } else {
                selectAsal.innerHTML = '<option value="">⚠️ Barang tidak ditemukan di rak manapun</option>';
            }
        } catch (err) {
            selectAsal.innerHTML = '<option value="">❌ Gagal memuat lokasi asal</option>';
        }
    }

    // Load semua lokasi tujuan aktif jika tipe masuk atau pindah
    if ((tipe === 'masuk' || tipe === 'pindah') && selectTujuan) {
        try {
            selectTujuan.innerHTML = '<option value="">⏳ Memuat lokasi tujuan...</option>';
            const res = await fetch(API_CONFIG.rakUrl());
            const json = await res.json();
            if (json.status === 'success' && json.data.length > 0) {
                selectTujuan.innerHTML = '<option value="">-- Pilih Lokasi Tujuan --</option>' + 
                    json.data.map(r => `<option value="${r.id}">${r.kode_lengkap} (Terisi: ${r.terisi}/${r.kapasitas} unit)</option>`).join('');
            } else {
                selectTujuan.innerHTML = '<option value="">⚠️ Tidak ada lokasi rak aktif</option>';
            }
        } catch (err) {
            selectTujuan.innerHTML = '<option value="">❌ Gagal memuat lokasi tujuan</option>';
        }
    }
}

// Submit mutasi manual
async function submitMutasi(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="animate-spin inline-block w-3 h-3 border-2 border-white/30 border-t-white rounded-full"></span> Menyimpan...'; }

    const tipe = document.getElementById('m-tipe').value;
    const asalVal = document.getElementById('m-lokasi-asal')?.value;
    const tujuVal = document.getElementById('m-lokasi-tujuan')?.value;

    const payload = {
        barang_id:   document.getElementById('m-barang-id').value.trim().toUpperCase(),
        tipe:        tipe,
        jumlah:      parseInt(document.getElementById('m-jumlah').value),
        lokasi_asal: (tipe === 'keluar' || tipe === 'pindah') && asalVal ? parseInt(asalVal) : null,
        lokasi_tuju: (tipe === 'masuk' || tipe === 'pindah') && tujuVal ? parseInt(tujuVal) : null,
        catatan:     'Input manual via halaman Lokasi',
        user_id:     1
    };

    try {
        const res  = await fetch(API_CONFIG.MUTASI, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify(payload)
        });
        const json = await res.json();
        if (res.ok && json.status === 'success') {
            showToast('success', 'Mutasi Dicatat', `${payload.tipe} ${payload.jumlah} unit untuk ${payload.barang_id}`);
            e.target.reset();
            // Sembunyikan kembali container lokasi
            document.getElementById('mutasi-lokasi-container')?.classList.add('hidden');
        } else {
            showToast('error', 'Gagal', json.message || json.error || 'Terjadi kesalahan.');
        }
    } catch (err) {
        showToast('error', 'Koneksi Gagal', err.message);
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="check-circle" class="w-3.5 h-3.5"></i>Simpan Mutasi'; lucide.createIcons(); }
    }
}

function filterByCabang(id) { /* bisa dikembangkan */ }
</script>
</body>
</html>
