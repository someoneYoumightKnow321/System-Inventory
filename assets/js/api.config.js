// assets/js/api.config.js
// ─────────────────────────────────────────────────────────────
// File konfigurasi sentral untuk URL backend.
// Ubah BASE_URL di sini jika port atau host Node.js berubah.
// Semua file JS frontend mengimpor dari sini — tidak perlu
// mencari-cari URL hardcoded di banyak tempat.
// ─────────────────────────────────────────────────────────────

const API_CONFIG = {
  // ── Base URL server Node.js Express ──────────────────────
  // Ganti nilai ini jika Node.js berjalan di port/host berbeda
  BASE_URL: 'http://localhost:3000',

  // ── Endpoint lengkap ─────────────────────────────────────
  get CABANG()          { return `${this.BASE_URL}/api/locations/cabang`; },
  get TREE()            { return `${this.BASE_URL}/api/locations/tree`; },
  get MUTASI()          { return `${this.BASE_URL}/api/locations/mutasi`; },
  get RIWAYAT()         { return `${this.BASE_URL}/api/locations/riwayat-terakhir`; },
  get RAK()             { return `${this.BASE_URL}/api/locations/rak`; },
  get SSE()             { return `${this.BASE_URL}/api/locations/sse`; },
  get HEALTH()          { return `${this.BASE_URL}/health`; },

  // ── Helper: URL tree dengan query param ──────────────────
  treeUrl(barangId)     { return `${this.TREE}?barang_id=${encodeURIComponent(barangId)}`; },
  rakUrl(cabangId = null, barangId = null) {
    let url = `${this.RAK}?`;
    if (cabangId) url += `cabang_id=${encodeURIComponent(cabangId)}&`;
    if (barangId) url += `barang_id=${encodeURIComponent(barangId)}`;
    return url;
  },
  riwayatUrl(limit = 10, barangId = null) {
    let url = `${this.RIWAYAT}?limit=${limit}`;
    if (barangId) url += `&barang_id=${encodeURIComponent(barangId)}`;
    return url;
  },
};

// Expose global agar bisa dipakai di semua script inline maupun file eksternal
window.API_CONFIG = API_CONFIG;
