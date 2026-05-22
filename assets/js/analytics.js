// assets/js/analytics.js
// Fitur 4: Dashboard Analitik dengan Chart.js v4

// ============================================================
// STATE
// ============================================================
let charts      = {};
let activeRange = 'weekly';
let sseSource   = null;

// ============================================================
// INISIALISASI DASHBOARD ANALITIK
// ============================================================
function initAnalyticsDashboard() {
    loadSummaryCards();
    loadTopProductsChart();
    loadFlowChart();
    loadCategoryChart();
    loadRecentActivity();
    connectSSE();
}

// ============================================================
// KARTU METRIK RINGKASAN
// ============================================================
async function loadSummaryCards() {
    try {
        const res  = await fetch(`modules/analytics.php?action=summary&range=${activeRange}`);
        const json = await res.json();
        if (!json.data) return;

        const d = json.data;
        setEl('an-total-jenis',   d.total_jenis_barang);
        setEl('an-total-unit',    d.total_unit + ' unit');
        setEl('an-stok-menipis',  d.stok_menipis);
        setEl('an-stok-habis',    d.stok_habis);
        setEl('an-tx-hari-ini',   d.transaksi_hari_ini);
        setEl('an-flow-masuk',    d.flow.masuk  + ' unit');
        setEl('an-flow-keluar',   d.flow.keluar + ' unit');

    } catch (e) {
        console.error('Summary error:', e);
    }
}

// ============================================================
// GRAFIK TOP 5 PRODUK (Bar Chart Horizontal)
// ============================================================
async function loadTopProductsChart() {
    const el = document.getElementById('chart-top-products');
    if (!el) return;

    try {
        const res  = await fetch(`modules/analytics.php?action=top_products&range=${activeRange}&top=5`);
        const json = await res.json();
        if (!json.data) return;

        const { labels, values } = json.data.chart;

        if (charts['top-products']) charts['top-products'].destroy();

        charts['top-products'] = new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Unit Keluar',
                    data: values,
                    backgroundColor: [
                        'rgba(13,148,136,0.85)',
                        'rgba(8,145,178,0.80)',
                        'rgba(20,184,166,0.75)',
                        'rgba(6,182,212,0.70)',
                        'rgba(45,212,191,0.65)',
                    ],
                    borderColor: [
                        '#0d9488','#0891b2','#14b8a6','#06b6d4','#2dd4bf'
                    ],
                    borderWidth: 1.5,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                indexAxis: 'y', // Horizontal bar
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.raw} unit keluar`
                        },
                        backgroundColor: '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: '#f1f5f9',
                        padding: 10,
                        cornerRadius: 8,
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" }
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            color: '#475569',
                            font: { size: 11, weight: '600', family: "'Plus Jakarta Sans', sans-serif" },
                            callback: function(value, index) {
                                const label = this.getLabelForValue(index);
                                return label.length > 20 ? label.substring(0, 20) + '…' : label;
                            }
                        }
                    }
                },
                animation: {
                    duration: 600,
                    easing: 'easeOutQuart'
                }
            }
        });

        // Empty state
        const empty = document.getElementById('chart-top-empty');
        if (empty) empty.classList.toggle('hidden', values.length > 0);

    } catch (e) {
        console.error('Top products chart error:', e);
    }
}

// ============================================================
// GRAFIK ARUS LOGISTIK (Line Chart - Masuk vs Keluar)
// ============================================================
async function loadFlowChart() {
    const el = document.getElementById('chart-flow');
    if (!el) return;

    try {
        const res  = await fetch(`modules/analytics.php?action=flow_chart&range=${activeRange}`);
        const json = await res.json();
        if (!json.data) return;

        const { labels, data_masuk, data_keluar } = json.data;

        if (charts['flow']) charts['flow'].destroy();

        charts['flow'] = new Chart(el, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Barang Masuk',
                        data: data_masuk,
                        borderColor: '#0d9488',
                        backgroundColor: 'rgba(13,148,136,0.08)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#0d9488',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    },
                    {
                        label: 'Barang Keluar',
                        data: data_keluar,
                        borderColor: '#0891b2',
                        backgroundColor: 'rgba(8,145,178,0.06)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#0891b2',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderDash: [0],
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            color: '#475569',
                            font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" },
                            padding: 20,
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: '#f1f5f9',
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: ${ctx.raw} unit`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 10, family: "'Plus Jakarta Sans', sans-serif" }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" },
                            stepSize: 1,
                        }
                    }
                },
                animation: { duration: 800, easing: 'easeOutQuart' }
            }
        });

    } catch (e) {
        console.error('Flow chart error:', e);
    }
}

// ============================================================
// GRAFIK DISTRIBUSI KATEGORI (Doughnut Chart)
// ============================================================
async function loadCategoryChart() {
    const el = document.getElementById('chart-category');
    if (!el) return;

    try {
        const res  = await fetch('modules/analytics.php?action=category_dist');
        const json = await res.json();
        if (!json.data) return;

        const { labels, values } = json.data.chart;

        const COLORS = [
            '#0d9488','#0891b2','#8b5cf6','#f59e0b','#ef4444',
            '#10b981','#3b82f6','#ec4899','#14b8a6','#f97316',
        ];

        if (charts['category']) charts['category'].destroy();

        charts['category'] = new Chart(el, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: COLORS.slice(0, labels.length),
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            color: '#475569',
                            font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" },
                            padding: 16,
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: '#f1f5f9',
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.raw} unit`
                        }
                    }
                },
                animation: { duration: 700, easing: 'easeOutBack' }
            }
        });

    } catch (e) {
        console.error('Category chart error:', e);
    }
}

// ============================================================
// RIWAYAT AKTIVITAS TERBARU (Live Feed)
// ============================================================
async function loadRecentActivity() {
    const container = document.getElementById('recent-activity-list');
    if (!container) return;

    try {
        const res  = await fetch('modules/analytics.php?action=recent_activity&limit=8');
        const json = await res.json();
        if (!json.data || json.data.length === 0) {
            container.innerHTML = `<div class="py-10 text-center text-xs text-slate-400">Belum ada aktivitas mutasi tercatat.</div>`;
            return;
        }

        const tipeMap = {
            masuk:  { icon: 'arrow-down-circle', cls: 'text-emerald-500 bg-emerald-50', label: 'Masuk' },
            keluar: { icon: 'arrow-up-circle',   cls: 'text-cyan-500 bg-cyan-50',       label: 'Keluar' },
            pindah: { icon: 'move-right',         cls: 'text-violet-500 bg-violet-50',   label: 'Pindah' },
        };

        container.innerHTML = json.data.map(item => {
            const t   = tipeMap[item.tipe] || tipeMap.masuk;
            const ago = timeAgo(item.created_at);
            return `
            <div class="flex items-center gap-3 py-3 border-b border-slate-50 last:border-0">
                <div class="w-8 h-8 rounded-lg ${t.cls} flex items-center justify-center shrink-0">
                    <i data-lucide="${t.icon}" class="w-4 h-4"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-slate-800 truncate">${escHtml(item.barang_nama)}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">
                        <span class="font-medium text-slate-600">${t.label}</span> ${item.jumlah} unit
                        · ${escHtml(item.user_nama)}
                    </p>
                </div>
                <span class="text-[10px] text-slate-400 shrink-0">${ago}</span>
            </div>`;
        }).join('');

        lucide.createIcons();

    } catch (e) {
        console.error('Recent activity error:', e);
    }
}

// ============================================================
// SSE: Real-time Update saat Ada Mutasi Baru
// ============================================================
function connectSSE() {
    if (!window.EventSource) return; // Browser tidak support

    if (sseSource) sseSource.close();

    sseSource = new EventSource('modules/sse.php');

    sseSource.addEventListener('mutasi', (e) => {
        const data = JSON.parse(e.data);

        // Tambah notifikasi live feed ke activity
        prependActivityItem(data);

        // Update charts secara debounce
        debounce(() => {
            loadSummaryCards();
            loadFlowChart();
            loadTopProductsChart();
        }, 1500)();

        // Toast notifikasi kecil
        const tipeLabel = { masuk: '⬇️ Masuk', keluar: '⬆️ Keluar', pindah: '↔️ Pindah' };
        showToast('info',
            `${tipeLabel[data.tipe] || 'Mutasi'}: ${data.barang_nama}`,
            `${data.jumlah} unit · ${data.user_nama}`
        );
    });

    sseSource.addEventListener('error', () => {
        // SSE reconnect otomatis setelah ~3 detik (browser built-in)
        setEl('sse-status', '🔴 Terputus');
    });

    sseSource.addEventListener('connected', () => {
        setEl('sse-status', '🟢 Live');
    });

    sseSource.addEventListener('reconnect', () => {
        sseSource.close();
        setTimeout(connectSSE, 3000);
    });
}

function prependActivityItem(data) {
    const container = document.getElementById('recent-activity-list');
    if (!container) return;

    const tipeMap = {
        masuk:  { icon: 'arrow-down-circle', cls: 'text-emerald-500 bg-emerald-50', label: 'Masuk' },
        keluar: { icon: 'arrow-up-circle',   cls: 'text-cyan-500 bg-cyan-50',       label: 'Keluar' },
        pindah: { icon: 'move-right',         cls: 'text-violet-500 bg-violet-50',   label: 'Pindah' },
    };
    const t = tipeMap[data.tipe] || tipeMap.masuk;

    const el = document.createElement('div');
    el.className = 'flex items-center gap-3 py-3 border-b border-slate-50 last:border-0 animate-fade-in bg-brand-50/30';
    el.innerHTML = `
        <div class="w-8 h-8 rounded-lg ${t.cls} flex items-center justify-center shrink-0">
            <i data-lucide="${t.icon}" class="w-4 h-4"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-slate-800 truncate">${escHtml(data.barang_nama)}</p>
            <p class="text-[10px] text-slate-400 mt-0.5">
                <span class="font-medium text-slate-600">${t.label}</span> ${data.jumlah} unit · ${escHtml(data.user_nama || '')}
            </p>
        </div>
        <span class="text-[10px] text-slate-400 shrink-0">Baru saja</span>`;

    container.prepend(el);
    lucide.createIcons();

    // Hapus item terlama jika lebih dari 10
    const items = container.querySelectorAll('.flex');
    if (items.length > 10) items[items.length - 1].remove();

    // Hilangkan highlight setelah 3 detik
    setTimeout(() => el.classList.remove('bg-brand-50/30'), 3000);
}

// ============================================================
// RANGE FILTER (Daily / Weekly / Monthly)
// ============================================================
function setAnalyticsRange(range) {
    activeRange = range;

    document.querySelectorAll('.range-tab').forEach(btn => {
        btn.classList.remove('bg-brand-500', 'text-white', 'shadow-sm');
        btn.classList.add('text-slate-500', 'hover:text-slate-800');
    });

    const activeBtn = document.getElementById(`range-${range}`);
    if (activeBtn) {
        activeBtn.classList.add('bg-brand-500', 'text-white', 'shadow-sm');
        activeBtn.classList.remove('text-slate-500', 'hover:text-slate-800');
    }

    // Reload semua charts
    loadSummaryCards();
    loadTopProductsChart();
    loadFlowChart();
}

// ============================================================
// HELPERS
// ============================================================
function setEl(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}

function escHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(text)));
    return d.innerHTML;
}

function timeAgo(dateStr) {
    const diff = Math.floor((new Date() - new Date(dateStr)) / 1000);
    if (diff < 60)    return `${diff}d lalu`;
    if (diff < 3600)  return `${Math.floor(diff/60)}m lalu`;
    if (diff < 86400) return `${Math.floor(diff/3600)}j lalu`;
    return `${Math.floor(diff/86400)} hari lalu`;
}

function debounce(fn, delay) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}
