'use strict';
/**
 * src/routes/locationRoutes.js
 * ─────────────────────────────
 * Router Express untuk semua endpoint /api/locations/*
 *
 * Daftar rute:
 *   GET  /api/locations/cabang            → daftar cabang + agregasi
 *   GET  /api/locations/tree              → location tree per barang
 *   GET  /api/locations/sse               → Server-Sent Events stream
 *   GET  /api/locations/riwayat-terakhir  → riwayat mutasi terbaru
 *   POST /api/locations/mutasi            → catat mutasi barang
 */

const { Router } = require('express');
const ctrl       = require('../controllers/locationController');

const router = Router();

// ── GET: Daftar cabang aktif + agregasi rak & unit ─────────────
router.get('/cabang', ctrl.getCabang);

// ── GET: Location tree hierarki per barang ─────────────────────
// Contoh: GET /api/locations/tree?barang_id=INV-001
router.get('/tree', ctrl.getLocationTree);

// ── GET: SSE stream — HARUS sebelum route generik lainnya ──────
// Browser connect sekali lalu menerima push event terus-menerus
router.get('/sse', ctrl.getSseStream);

// ── GET: 10 riwayat mutasi terbaru ─────────────────────────────
// Opsional: GET /api/locations/riwayat-terakhir?limit=5&barang_id=INV-001
router.get('/riwayat-terakhir', ctrl.getRiwayatTerakhir);

// ── GET: Daftar rak aktif ──────────────────────────────────────
// Contoh: GET /api/locations/rak?cabang_id=1&barang_id=INV-001
router.get('/rak', ctrl.getRak);

// ── POST: Catat mutasi (masuk / keluar / pindah) ───────────────
router.post('/mutasi', ctrl.postMutasi);

module.exports = router;
