// assets/js/scanner.js
// Fitur 3: QR/Barcode Scanner via Browser Camera (html5-qrcode v2.3.8)

// ============================================================
// STATE & CONFIG
// ============================================================
let html5QrCode   = null;
let scannerActive = false;
let lastScanned   = null;
const SCAN_COOLDOWN_MS = 2000; // Cegah scan berulang dalam 2 detik

const SCAN_CONFIG = {
    fps: 10,
    qrbox: { width: 250, height: 250 },
    aspectRatio: 1.0,
    supportedScanTypes: [
        Html5QrcodeScanType.SCAN_TYPE_CAMERA,
        Html5QrcodeScanType.SCAN_TYPE_FILE
    ]
};

// ============================================================
// INISIALISASI SCANNER
// ============================================================
function initScanner() {
    const container = document.getElementById('qr-reader');
    if (!container) return;

    html5QrCode = new Html5Qrcode('qr-reader', {
        verbose: false,
        formatsToSupport: [
            Html5QrcodeSupportedFormats.QR_CODE,
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.DATA_MATRIX
        ]
    });
    setScannerStatus('idle');
}

// ============================================================
// START/STOP KAMERA
// ============================================================
async function startScanner() {
    if (!html5QrCode) initScanner();
    if (scannerActive) return;

    try {
        setScannerStatus('starting');
        const devices = await Html5Qrcode.getCameras();
        if (!devices || devices.length === 0) {
            throw new Error('Tidak ada kamera yang terdeteksi.');
        }

        // Pilih kamera belakang jika tersedia (untuk mobile)
        const cameraId = devices.find(d =>
            d.label.toLowerCase().includes('back') ||
            d.label.toLowerCase().includes('belakang') ||
            d.label.toLowerCase().includes('environment')
        )?.id || devices[0].id;

        await html5QrCode.start(
            cameraId,
            SCAN_CONFIG,
            onScanSuccess,
            onScanFailure
        );

        scannerActive = true;
        setScannerStatus('active');

        document.getElementById('btn-start-scan').classList.add('hidden');
        document.getElementById('btn-stop-scan').classList.remove('hidden');

    } catch (err) {
        console.error('Scanner error:', err);
        setScannerStatus('error');
        showScannerError(err.message || 'Gagal mengakses kamera. Pastikan izin kamera diberikan.');
    }
}

async function stopScanner() {
    if (!html5QrCode || !scannerActive) return;
    try {
        await html5QrCode.stop();
        scannerActive = false;
        setScannerStatus('idle');

        document.getElementById('btn-start-scan').classList.remove('hidden');
        document.getElementById('btn-stop-scan').classList.add('hidden');
    } catch (err) {
        console.error('Stop scanner error:', err);
    }
}

// ============================================================
// CALLBACK SCAN SUKSES
// ============================================================
function onScanSuccess(decodedText) {
    const now = Date.now();
    // Cooldown: abaikan jika hasil sama dalam 2 detik
    if (lastScanned && lastScanned.text === decodedText && (now - lastScanned.time) < SCAN_COOLDOWN_MS) return;

    lastScanned = { text: decodedText, time: now };

    // Feedback visual & audio
    triggerScanFeedback();

    // Lookup ke server
    lookupScannedItem(decodedText);
}

function onScanFailure(error) {
    // Abaikan — error ini terjadi setiap frame yang tidak mengandung QR code
}

// ============================================================
// LOOKUP BARANG DARI SERVER
// ============================================================
async function lookupScannedItem(scanResult) {
    setScannerStatus('loading');
    showScannerResult('loading', scanResult);

    try {
        const res  = await fetch(`modules/scan.php?action=lookup&q=${encodeURIComponent(scanResult)}`);
        const json = await res.json();

        if (!res.ok || json.error) {
            showScannerResult('not_found', scanResult, json.error);
            setScannerStatus('active');
            return;
        }

        showScannerResult('found', scanResult, null, json.data);
        setScannerStatus('active');

    } catch (err) {
        showScannerResult('error', scanResult, 'Koneksi ke server gagal.');
        setScannerStatus('error');
    }
}

// ============================================================
// RENDER HASIL SCAN KE UI
// ============================================================
function showScannerResult(state, scanned = '', errMsg = '', item = null) {
    const panel = document.getElementById('scan-result-panel');
    if (!panel) return;

    panel.classList.remove('hidden');

    if (state === 'loading') {
        panel.innerHTML = `
            <div class="flex items-center gap-3 p-4">
                <div class="w-6 h-6 rounded-full border-2 border-brand-500/30 border-t-brand-500 animate-spin shrink-0"></div>
                <div>
                    <p class="text-xs font-semibold text-slate-700">Mencari di database...</p>
                    <p class="text-[11px] text-slate-400 font-mono mt-0.5">${escapeHtml(scanned)}</p>
                </div>
            </div>`;
        return;
    }

    if (state === 'not_found' || state === 'error') {
        panel.innerHTML = `
            <div class="p-4 bg-rose-50 rounded-xl border border-rose-100">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-rose-100 rounded-lg flex items-center justify-center shrink-0">
                        <i data-lucide="scan-line" class="w-4 h-4 text-rose-500"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-rose-700">Barang Tidak Ditemukan</p>
                        <p class="text-[11px] text-rose-500 mt-0.5">${escapeHtml(errMsg || 'Scan ulang atau periksa registrasi barang.')}</p>
                        <p class="font-mono text-[10px] text-rose-400 mt-1">Hasil scan: ${escapeHtml(scanned)}</p>
                    </div>
                </div>
            </div>`;
        lucide.createIcons();
        return;
    }

    if (state === 'found' && item) {
        const statusMap = {
            tersedia: { bg: 'bg-emerald-50', text: 'text-emerald-700', dot: 'bg-emerald-500', label: 'Tersedia' },
            kritis:   { bg: 'bg-amber-50',   text: 'text-amber-700',   dot: 'bg-amber-500',   label: 'Stok Kritis' },
            habis:    { bg: 'bg-rose-50',     text: 'text-rose-700',    dot: 'bg-rose-500',    label: 'Habis' }
        };
        const s = statusMap[item.status] || statusMap['tersedia'];

        const lokasiHtml = item.lokasi && item.lokasi.length > 0
            ? item.lokasi.map(l => `
                <div class="flex items-center justify-between py-1 border-b border-slate-50 last:border-0">
                    <span class="font-mono text-[10px] text-slate-500">${escapeHtml(l.kode)}</span>
                    <span class="text-xs font-bold text-slate-700">${l.jumlah} unit</span>
                </div>`).join('')
            : `<p class="text-xs text-slate-400 italic">Belum ada lokasi rak terdaftar.</p>`;

        panel.innerHTML = `
            <div class="p-4 space-y-3">
                <!-- Header barang -->
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1">
                        <p class="text-[10px] font-mono text-slate-400">${escapeHtml(item.id)}</p>
                        <h4 class="text-sm font-bold text-slate-800 mt-0.5 leading-snug">${escapeHtml(item.nama)}</h4>
                        <span class="inline-block text-[10px] font-semibold uppercase px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 mt-1">${escapeHtml(item.kategori)}</span>
                    </div>
                    <div class="flex flex-col items-end gap-1 shrink-0">
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-1 rounded-full ${s.bg} ${s.text}">
                            <span class="w-1.5 h-1.5 rounded-full ${s.dot}"></span>${s.label}
                        </span>
                        <span class="text-2xl font-black text-slate-800">${item.stok} <span class="text-xs font-medium text-slate-400">unit</span></span>
                    </div>
                </div>

                <!-- Lokasi rak -->
                <div class="bg-slate-50 rounded-xl p-3">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                        <i data-lucide="map-pin" class="w-3 h-3 inline-block mr-1"></i>Lokasi Rak
                    </p>
                    ${lokasiHtml}
                </div>

                <!-- Tombol aksi cepat -->
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button onclick="quickCheckin('${escapeHtml(item.id)}')"
                        class="flex items-center justify-center gap-2 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold text-xs rounded-xl transition-all border border-emerald-100">
                        <i data-lucide="package-plus" class="w-3.5 h-3.5"></i>Check-In
                    </button>
                    <button onclick="quickCheckout('${escapeHtml(item.id)}', ${item.stok})"
                        ${item.stok <= 0 ? 'disabled' : ''}
                        class="flex items-center justify-center gap-2 py-2.5 bg-cyan-50 hover:bg-cyan-100 text-cyan-700 font-semibold text-xs rounded-xl transition-all border border-cyan-100 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="package-minus" class="w-3.5 h-3.5"></i>Check-Out
                    </button>
                </div>
            </div>`;
        lucide.createIcons();
    }
}

// ============================================================
// QUICK CHECK-IN
// ============================================================
async function quickCheckin(barangId) {
    const jumlah = prompt(`Check-In berapa unit untuk ${barangId}?`, '1');
    if (!jumlah || isNaN(jumlah) || parseInt(jumlah) <= 0) return;

    try {
        const res = await fetch('modules/scan.php?action=checkin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ barang_id: barangId, jumlah: parseInt(jumlah), catatan: 'Check-in via QR scan' })
        });
        const json = await res.json();
        if (json.status === 'success') {
            showToast('success', 'Check-In Berhasil', `${jumlah} unit masuk. Stok sekarang: ${json.stok_terkini} unit`);
            lookupScannedItem(barangId);
        } else {
            showToast('error', 'Check-In Gagal', json.error);
        }
    } catch (e) {
        showToast('error', 'Koneksi Gagal', e.message);
    }
}

// ============================================================
// CHECKOUT MODAL — State
// ============================================================
/** @type {{ barangId: string, hierarchy: Array, currentStok: number }|null} */
let _coState = null;

/**
 * Buka modal Check-Out dan load hierarchy lokasi dari backend.
 * Dipanggil dari tombol "Check-Out" di result panel.
 * @param {string} barangId
 * @param {number} currentStok
 */
async function quickCheckout(barangId, currentStok) {
    if (currentStok <= 0) {
        showToast('error', 'Stok Habis', 'Tidak bisa melakukan check-out, stok = 0.');
        return;
    }

    // Reset state
    _coState = { barangId, hierarchy: [], currentStok };

    // Tampilkan modal
    const modal = document.getElementById('checkout-modal');
    if (!modal) { console.error('checkout-modal element tidak ditemukan'); return; }
    modal.classList.remove('hidden');

    // Label header
    const lbl = document.getElementById('co-barang-label');
    if (lbl) lbl.textContent = barangId;

    // Reset field
    const jumlahEl = document.getElementById('co-jumlah');
    if (jumlahEl) { jumlahEl.value = '1'; jumlahEl.max = currentStok; }

    const catatanEl = document.getElementById('co-catatan');
    if (catatanEl) catatanEl.value = '';

    _coResetDropdowns();

    if (typeof lucide !== 'undefined') lucide.createIcons();

    // Fetch hierarchy dari backend (hanya rak yang berisi barang ini)
    const selectCabang = document.getElementById('co-select-cabang');
    if (selectCabang) selectCabang.innerHTML = '<option value="">⏳ Memuat data gudang...</option>';

    try {
        const url = `modules/locations.php?action=list_rak&barang_id=${encodeURIComponent(barangId)}`;
        const res  = await fetch(url);
        const json = await res.json();

        if (!res.ok || json.status !== 'success') {
            throw new Error(json.error || 'Gagal memuat data lokasi.');
        }

        _coState.hierarchy = json.hierarchy || [];

        if (_coState.hierarchy.length === 0) {
            if (selectCabang) selectCabang.innerHTML = '<option value="">⚠️ Barang tidak ada di rak manapun</option>';
            return;
        }

        // Isi dropdown Cabang Gudang
        if (selectCabang) {
            selectCabang.innerHTML = '<option value="">— Pilih Cabang Gudang —</option>' +
                _coState.hierarchy.map(c =>
                    `<option value="${c.cabang_id}">${c.cabang_kode} – ${c.cabang_nama}</option>`
                ).join('');
        }

    } catch (err) {
        if (selectCabang) selectCabang.innerHTML = `<option value="">❌ Error: ${escapeHtml(err.message)}</option>`;
        showToast('error', 'Gagal', err.message);
    }
}

/** Tutup modal checkout */
function closeCheckoutModal() {
    const modal = document.getElementById('checkout-modal');
    if (modal) modal.classList.add('hidden');
    _coState = null;
}

/** Reset zona + rak dropdown ke kondisi disabled */
function _coResetDropdowns() {
    const selZona = document.getElementById('co-select-zona');
    const selRak  = document.getElementById('co-select-rak');
    const info    = document.getElementById('co-stok-info');

    if (selZona) { selZona.innerHTML = '<option value="">— Pilih Gudang dulu —</option>'; selZona.disabled = true; }
    if (selRak)  { selRak.innerHTML  = '<option value="">— Pilih Zona dulu —</option>';   selRak.disabled  = true; }
    if (info)    info.classList.add('hidden');
}

// ── Event Listener: Perubahan Dropdown Cabang ──────────────────
document.addEventListener('change', function (e) {
    if (e.target.id === 'co-select-cabang') _coOnCabangChange(e.target.value);
    if (e.target.id === 'co-select-zona')   _coOnZonaChange(e.target.value);
    if (e.target.id === 'co-select-rak')    _coOnRakChange(e.target.value);
});

function _coOnCabangChange(cabangId) {
    const selZona = document.getElementById('co-select-zona');
    const selRak  = document.getElementById('co-select-rak');
    const info    = document.getElementById('co-stok-info');

    // Reset downstream
    if (selRak)  { selRak.innerHTML = '<option value="">— Pilih Zona dulu —</option>'; selRak.disabled = true; }
    if (info)    info.classList.add('hidden');

    if (!cabangId || !_coState) {
        if (selZona) { selZona.innerHTML = '<option value="">— Pilih Gudang dulu —</option>'; selZona.disabled = true; }
        return;
    }

    const cabangNode = _coState.hierarchy.find(c => String(c.cabang_id) === String(cabangId));
    if (!cabangNode || !cabangNode.zona || cabangNode.zona.length === 0) {
        if (selZona) { selZona.innerHTML = '<option value="">⚠️ Tidak ada zona tersedia</option>'; selZona.disabled = true; }
        return;
    }

    if (selZona) {
        selZona.innerHTML = '<option value="">— Pilih Zona —</option>' +
            cabangNode.zona.map(z =>
                `<option value="${escapeHtml(z.zona_key)}">${escapeHtml(z.label)}</option>`
            ).join('');
        selZona.disabled = false;
    }
}

function _coOnZonaChange(zonaKey) {
    const selCabang = document.getElementById('co-select-cabang');
    const selRak    = document.getElementById('co-select-rak');
    const info      = document.getElementById('co-stok-info');

    if (info) info.classList.add('hidden');

    const cabangId = selCabang ? selCabang.value : '';

    if (!zonaKey || !cabangId || !_coState) {
        if (selRak) { selRak.innerHTML = '<option value="">— Pilih Zona dulu —</option>'; selRak.disabled = true; }
        return;
    }

    const cabangNode = _coState.hierarchy.find(c => String(c.cabang_id) === String(cabangId));
    const zonaNode   = cabangNode && cabangNode.zona
        ? cabangNode.zona.find(z => z.zona_key === zonaKey)
        : null;

    if (!zonaNode || !zonaNode.rak || zonaNode.rak.length === 0) {
        if (selRak) { selRak.innerHTML = '<option value="">⚠️ Tidak ada rak di zona ini</option>'; selRak.disabled = true; }
        return;
    }

    if (selRak) {
        selRak.innerHTML = '<option value="">— Pilih Baris & Level Rak —</option>' +
            zonaNode.rak.map(r =>
                `<option value="${r.rak_id}" data-stok="${r.stok_rak}">` +
                `Baris ${escapeHtml(r.baris)} – Level ${escapeHtml(r.level_rak)} ` +
                `(Sisa ${r.stok_rak} unit)` +
                `</option>`
            ).join('');
        selRak.disabled = false;
    }
}

function _coOnRakChange(rakId) {
    const selRak = document.getElementById('co-select-rak');
    const info   = document.getElementById('co-stok-info');
    const infoTxt = document.getElementById('co-stok-info-text');

    if (!rakId || !selRak) {
        if (info) info.classList.add('hidden');
        return;
    }

    const selected = selRak.options[selRak.selectedIndex];
    const stokRak  = selected ? parseInt(selected.dataset.stok || '0') : 0;

    if (info && infoTxt) {
        infoTxt.textContent = `Rak ini memiliki ${stokRak} unit barang ${_coState?.barangId || ''}.`;
        info.classList.remove('hidden');
    }

    // Update max jumlah checkout sesuai stok di rak
    const jumlahEl = document.getElementById('co-jumlah');
    if (jumlahEl) jumlahEl.max = stokRak;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// ── Submit Checkout ────────────────────────────────────────────
async function submitCheckout() {
    if (!_coState) return;

    const jumlah    = parseInt(document.getElementById('co-jumlah')?.value || '0');
    const rakId     = document.getElementById('co-select-rak')?.value;
    const catatan   = document.getElementById('co-catatan')?.value?.trim() || 'Check-out via QR scan';
    const submitBtn = document.getElementById('co-submit-btn');

    // Validasi
    if (!jumlah || jumlah <= 0) {
        showToast('error', 'Input Kurang', 'Masukkan jumlah unit yang valid.'); return;
    }
    if (!rakId) {
        showToast('error', 'Lokasi Belum Dipilih', 'Pilih cabang, zona, dan rak terlebih dahulu.'); return;
    }

    // Cek stok di rak
    const selRak   = document.getElementById('co-select-rak');
    const stokRak  = selRak ? parseInt(selRak.options[selRak.selectedIndex]?.dataset.stok || '0') : 0;
    if (jumlah > stokRak) {
        showToast('error', 'Stok Rak Tidak Cukup', `Rak hanya memiliki ${stokRak} unit, Anda meminta ${jumlah}.`);
        return;
    }

    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="animate-spin inline-block w-3 h-3 border-2 border-white/30 border-t-white rounded-full"></span> Memproses...'; }

    try {
        // Kirim ke Node.js backend via API_CONFIG (jika tersedia), fallback ke modules/scan.php
        const endpoint = (typeof API_CONFIG !== 'undefined') ? API_CONFIG.MUTASI : 'modules/scan.php?action=checkout';

        const payload = (typeof API_CONFIG !== 'undefined')
            ? {
                barang_id   : _coState.barangId,
                tipe        : 'keluar',
                jumlah,
                lokasi_asal : parseInt(rakId),
                lokasi_tuju : null,
                catatan,
                user_id     : 1,
              }
            : { barang_id: _coState.barangId, jumlah, catatan };

        const res  = await fetch(endpoint, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify(payload),
        });
        const json = await res.json();

        if (res.ok && (json.status === 'success')) {
            showToast('success', 'Check-Out Berhasil',
                `${jumlah} unit ${_coState.barangId} berhasil dikeluarkan dari rak.`);
            closeCheckoutModal();
            lookupScannedItem(_coState?.barangId || '');
        } else {
            showToast('error', 'Check-Out Gagal', json.message || json.error || 'Terjadi kesalahan.');
        }
    } catch (err) {
        showToast('error', 'Koneksi Gagal', err.message);
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i data-lucide="package-minus" class="w-3.5 h-3.5"></i>Konfirmasi Check-Out';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }
}

// ============================================================
// SCAN VIA INPUT MANUAL (fallback)
// ============================================================
function handleManualSearch() {
    const input = document.getElementById('manual-scan-input');
    if (!input || !input.value.trim()) return;
    lookupScannedItem(input.value.trim());
    input.value = '';
}

// ============================================================
// UI HELPERS
// ============================================================
function setScannerStatus(status) {
    const badge = document.getElementById('scanner-status-badge');
    if (!badge) return;

    const map = {
        idle:     { text: 'Kamera Tidak Aktif', cls: 'bg-slate-100 text-slate-500' },
        starting: { text: 'Memulai Kamera...', cls: 'bg-amber-100 text-amber-700' },
        active:   { text: 'Scanner Aktif', cls: 'bg-emerald-100 text-emerald-700' },
        loading:  { text: 'Mencari Data...', cls: 'bg-blue-100 text-blue-700' },
        error:    { text: 'Error Kamera', cls: 'bg-rose-100 text-rose-700' },
    };

    const s = map[status] || map.idle;
    badge.textContent = s.text;
    badge.className   = `px-2.5 py-1 rounded-full text-[10px] font-bold ${s.cls}`;
}

function triggerScanFeedback() {
    // Visual flash
    const overlay = document.getElementById('scan-flash');
    if (overlay) {
        overlay.classList.remove('hidden');
        setTimeout(() => overlay.classList.add('hidden'), 200);
    }

    // Audio beep (Web Audio API)
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 1200;
        osc.type = 'sine';
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.1);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.1);
    } catch (e) { /* Audio tidak tersedia, abaikan */ }
}

function showScannerError(msg) {
    const panel = document.getElementById('scan-result-panel');
    if (panel) {
        panel.classList.remove('hidden');
        panel.innerHTML = `
            <div class="p-4 bg-rose-50 rounded-xl border border-rose-100">
                <p class="text-xs font-bold text-rose-700">⚠️ ${escapeHtml(msg)}</p>
                <p class="text-[11px] text-rose-500 mt-1">Coba gunakan kolom pencarian manual di bawah.</p>
            </div>`;
    }
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(text)));
    return div.innerHTML;
}

