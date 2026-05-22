'use strict';
/**
 * src/app.js
 * ──────────
 * Entry point aplikasi Express.js.
 *
 * Urutan middleware PENTING:
 *  1. .env load
 *  2. CORS  (sebelum semua route)
 *  3. express.json / express.urlencoded
 *  4. Logger (dev only)
 *  5. Routes
 *  6. 404 handler
 *  7. Global error handler  ← HARUS paling bawah
 */

require('dotenv').config(); // load .env sebelum apapun

const express            = require('express');
const { testConnection } = require('./config/database');
const corsMiddleware     = require('./middleware/cors');
const { errorHandler, notFound } = require('./middleware/errorHandler');
const locationRoutes     = require('./routes/locationRoutes');

// ── Inisialisasi app ──────────────────────────────────────────
const app  = express();
const PORT = Number(process.env.PORT) || 3000;
const ENV  = process.env.NODE_ENV || 'development';

// ═══════════════════════════════════════════════════════════════
//  GLOBAL MIDDLEWARE
// ═══════════════════════════════════════════════════════════════

// 1. CORS — harus PERTAMA agar Preflight (OPTIONS) sudah ditangani
app.use(corsMiddleware);

// 2. Parse JSON body (proteksi: limit 10mb)
app.use(express.json({ limit: '10mb' }));

// 3. Parse URL-encoded form body
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// 4. Sederhana request logger (development)
if (ENV === 'development') {
  app.use((req, _res, next) => {
    console.log(`→  [${new Date().toISOString()}]  ${req.method.padEnd(7)} ${req.originalUrl}`);
    next();
  });
}

// ═══════════════════════════════════════════════════════════════
//  ROOT — Direktori endpoint (berguna saat buka di browser)
// ═══════════════════════════════════════════════════════════════
app.get('/', (_req, res) => {
  res.json({
    service    : '🚀 Inventaris Gudang API',
    version    : '2.0.0',
    status     : 'running',
    timestamp  : new Date().toISOString(),
    endpoints  : {
      health              : { method: 'GET',  path: '/health' },
      cabang              : { method: 'GET',  path: '/api/locations/cabang' },
      location_tree       : { method: 'GET',  path: '/api/locations/tree?barang_id=INV-001' },
      mutasi              : { method: 'POST', path: '/api/locations/mutasi' },
      riwayat_terakhir    : { method: 'GET',  path: '/api/locations/riwayat-terakhir?limit=10' },
      sse_stream          : { method: 'GET',  path: '/api/locations/sse' },
    },
    docs: 'Buka endpoint di atas untuk mengakses API. Gunakan Postman atau frontend untuk POST.',
  });
});

// ═══════════════════════════════════════════════════════════════
//  HEALTH CHECK
// ═══════════════════════════════════════════════════════════════
app.get('/health', (_req, res) => {
  res.json({
    status   : 'ok',
    service  : 'Inventaris Gudang API',
    version  : '2.0.0',
    timestamp: new Date().toISOString(),
    env      : ENV,
  });
});

// ═══════════════════════════════════════════════════════════════
//  API ROUTES
// ═══════════════════════════════════════════════════════════════
app.use('/api/locations', locationRoutes);

// (Tempat menambahkan router lain di masa depan)
// app.use('/api/barang',   barangRoutes);
// app.use('/api/users',    userRoutes);

// ═══════════════════════════════════════════════════════════════
//  ERROR HANDLERS  (harus di bawah semua route)
// ═══════════════════════════════════════════════════════════════
app.use(notFound);
app.use(errorHandler);

// ═══════════════════════════════════════════════════════════════
//  START SERVER
// ═══════════════════════════════════════════════════════════════
async function startServer() {
  // Tes koneksi DB terlebih dahulu sebelum listen
  await testConnection();

  const server = app.listen(PORT, () => {
    console.log('');
    console.log('╔══════════════════════════════════════════════╗');
    console.log('║  🚀  Inventaris Gudang API  v2.0.0           ║');
    console.log(`║  📡  http://localhost:${PORT}                     ║`);
    console.log(`║  🌍  Mode: ${ENV.padEnd(33)}║`);
    console.log('╚══════════════════════════════════════════════╝');
    console.log('');
    console.log('  Endpoint tersedia:');
    console.log(`  GET  http://localhost:${PORT}/health`);
    console.log(`  GET  http://localhost:${PORT}/api/locations/cabang`);
    console.log(`  GET  http://localhost:${PORT}/api/locations/tree?barang_id=INV-001`);
    console.log(`  POST http://localhost:${PORT}/api/locations/mutasi`);
    console.log(`  GET  http://localhost:${PORT}/api/locations/riwayat-terakhir`);
    console.log(`  GET  http://localhost:${PORT}/api/locations/sse`);
    console.log('');
  });

  // ── Graceful shutdown ──────────────────────────────────────
  const shutdown = async (signal) => {
    console.log(`\n⚡  ${signal} diterima. Menutup server...`);
    server.close(() => {
      console.log('✅  Server ditutup dengan bersih.');
      process.exit(0);
    });
    // Paksa keluar jika tidak selesai dalam 10 detik
    setTimeout(() => process.exit(1), 10_000);
  };

  process.on('SIGTERM', () => shutdown('SIGTERM'));
  process.on('SIGINT',  () => shutdown('SIGINT'));

  // Tangkap unhandled rejection agar server tidak crash diam-diam
  process.on('unhandledRejection', (reason) => {
    console.error('🔥  Unhandled Promise Rejection:', reason);
  });
}

startServer();
