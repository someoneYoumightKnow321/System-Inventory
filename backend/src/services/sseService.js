'use strict';
/**
 * src/services/sseService.js
 * ──────────────────────────
 * Manajemen client Server-Sent Events (SSE).
 *
 * Pattern:  Map<clientId, Response>
 * - addClient()     → simpan koneksi SSE baru
 * - removeClient()  → hapus saat client disconnect
 * - broadcast()     → kirim event ke SEMUA client yang aktif
 * - sendToClient()  → kirim event ke satu client spesifik
 */

const { EventEmitter } = require('events');

class SSEService extends EventEmitter {
  constructor() {
    super();
    /** @type {Map<string, import('express').Response>} */
    this.clients = new Map();
    this._heartbeatMs = Number(process.env.SSE_HEARTBEAT_MS) || 30_000;
    this._startHeartbeat();
  }

  // ── Koneksi masuk ────────────────────────────────────────────
  /**
   * Inisialisasi response header SSE dan simpan client.
   * @param {string} clientId  - ID unik per koneksi (e.g. crypto.randomUUID)
   * @param {import('express').Response} res
   * @param {number} [retryMs]
   */
  addClient(clientId, res, retryMs) {
    const retry = retryMs ?? Number(process.env.SSE_RETRY_MS) ?? 3000;

    res.setHeader('Content-Type', 'text/event-stream');
    res.setHeader('Cache-Control', 'no-cache');
    res.setHeader('Connection', 'keep-alive');
    res.setHeader('X-Accel-Buffering', 'no'); // untuk NGINX agar tidak buffer SSE
    res.flushHeaders();

    // Instruksi reconnect ke browser
    res.write(`retry: ${retry}\n\n`);

    // Event "welcome" pertama kali connect
    this._writeEvent(res, 'connected', {
      message : 'Terhubung ke SSE Inventaris Gudang',
      clientId,
      timestamp: new Date().toISOString(),
    });

    this.clients.set(clientId, res);
    console.log(`📡  SSE client terhubung  [${clientId}]  — total: ${this.clients.size}`);

    this.emit('client:connect', { clientId, total: this.clients.size });
  }

  // ── Koneksi putus ─────────────────────────────────────────────
  removeClient(clientId) {
    this.clients.delete(clientId);
    console.log(`📴  SSE client putus      [${clientId}]  — sisa: ${this.clients.size}`);
    this.emit('client:disconnect', { clientId, total: this.clients.size });
  }

  // ── Siaran ke semua client ────────────────────────────────────
  /**
   * @param {string} eventName  - Nama event SSE (e.g. 'mutasi_baru')
   * @param {object} payload    - Data yang di-serialize ke JSON
   */
  broadcast(eventName, payload) {
    if (this.clients.size === 0) return;

    let delivered = 0;
    for (const [id, res] of this.clients) {
      try {
        this._writeEvent(res, eventName, payload);
        delivered++;
      } catch {
        // Jika write gagal, client kemungkinan sudah disconnect
        this.removeClient(id);
      }
    }
    console.log(`📢  Broadcast [${eventName}]  →  ${delivered}/${this.clients.size} client`);
  }

  // ── Kirim ke satu client ──────────────────────────────────────
  sendToClient(clientId, eventName, payload) {
    const res = this.clients.get(clientId);
    if (!res) return false;
    try {
      this._writeEvent(res, eventName, payload);
      return true;
    } catch {
      this.removeClient(clientId);
      return false;
    }
  }

  // ── Helper penulisan frame SSE ────────────────────────────────
  /**
   * Format SSE:
   *   event: <name>
   *   data: <json>
   *   id: <timestamp>
   *   (blank line)
   */
  _writeEvent(res, eventName, data) {
    const json = JSON.stringify(data);
    res.write(`event: ${eventName}\n`);
    res.write(`id: ${Date.now()}\n`);
    res.write(`data: ${json}\n\n`);
  }

  // ── Heartbeat (mencegah proxy timeout) ───────────────────────
  _startHeartbeat() {
    setInterval(() => {
      for (const [id, res] of this.clients) {
        try {
          // Comment SSE — tidak memicu event handler di browser
          res.write(`: heartbeat ${new Date().toISOString()}\n\n`);
        } catch {
          this.removeClient(id);
        }
      }
    }, this._heartbeatMs);
  }

  get clientCount() {
    return this.clients.size;
  }
}

// Singleton — satu instance dipakai di seluruh aplikasi
const sseService = new SSEService();

module.exports = sseService;
