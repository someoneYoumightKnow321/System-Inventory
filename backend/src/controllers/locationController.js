'use strict';
/**
 * src/controllers/locationController.js
 * ──────────────────────────────────────
 * Controller utama untuk semua fitur Lokasi Rak Multi-Cabang.
 *
 * Endpoint yang di-handle:
 *   GET  /api/locations/cabang          → daftar cabang + agregasi
 *   GET  /api/locations/tree            → location tree per barang
 *   POST /api/locations/mutasi          → catat mutasi (transaction)
 *   GET  /api/locations/riwayat-terakhir→ 10 riwayat terbaru
 *   GET  /api/locations/sse             → Server-Sent Events stream
 *
 * Pola: async/await + try-catch + prepared statements (mysql2 ?)
 */

const { pool }  = require('../config/database');
const sseService = require('../services/sseService');
const crypto    = require('crypto');

// ═══════════════════════════════════════════════════════════════
//  HELPER
// ═══════════════════════════════════════════════════════════════

/**
 * Kirim respons JSON standar.
 * @param {import('express').Response} res
 * @param {number} status  HTTP status code
 * @param {object} body    Objek yang akan di-serialize
 */
const sendJSON = (res, status, body) => res.status(status).json(body);

/**
 * Bungkus response error agar konsisten.
 */
const sendError = (res, status, message, detail = null) =>
  sendJSON(res, status, {
    status : 'error',
    message,
    ...(detail && process.env.NODE_ENV !== 'production' && { detail: String(detail) }),
  });

// ═══════════════════════════════════════════════════════════════
//  A. GET /api/locations/cabang
// ═══════════════════════════════════════════════════════════════
/**
 * Kembalikan semua cabang aktif beserta:
 *  - jumlah_rak   : COUNT(DISTINCT lokasi_rak.id)
 *  - total_unit   : SUM(barang_lokasi.jumlah)
 */
const getCabang = async (req, res) => {
  try {
    const sql = `
      SELECT
        cg.id,
        cg.kode,
        cg.nama,
        cg.alamat,
        cg.is_active,
        COUNT(DISTINCT lr.id)            AS jumlah_rak,
        COALESCE(SUM(bl.jumlah), 0)      AS total_unit
      FROM cabang_gudang cg
      LEFT JOIN lokasi_rak    lr ON lr.cabang_id = cg.id  AND lr.is_active = 1
      LEFT JOIN barang_lokasi bl ON bl.lokasi_id = lr.id
      WHERE cg.is_active = 1
      GROUP BY cg.id, cg.kode, cg.nama, cg.alamat, cg.is_active
      ORDER BY cg.kode ASC
    `;

    const [rows] = await pool.query(sql);

    return sendJSON(res, 200, {
      status : 'success',
      total  : rows.length,
      data   : rows.map(r => ({
        ...r,
        jumlah_rak : Number(r.jumlah_rak),
        total_unit : Number(r.total_unit),
      })),
    });
  } catch (err) {
    console.error('[getCabang] Error:', err.message);
    return sendError(res, 500, 'Gagal mengambil data cabang.', err.message);
  }
};

// ═══════════════════════════════════════════════════════════════
//  B. GET /api/locations/tree?barang_id=KODE_BARANG
// ═══════════════════════════════════════════════════════════════
/**
 * Format data FLAT dari MySQL menjadi TREE hierarki JSON:
 *
 * [
 *   {
 *     cabang_id, cabang_kode, cabang_nama, total_unit,
 *     zona: {
 *       "A": { label:"Zona A", baris: {
 *         "02": { label:"Baris 02", level: [{ rak_id, level, kode_lengkap, jumlah }] }
 *       }}
 *     }
 *   }
 * ]
 */
const getLocationTree = async (req, res) => {
  const { barang_id } = req.query;

  if (!barang_id || !barang_id.toString().trim()) {
    return sendError(res, 400, 'Query parameter barang_id wajib diisi.');
  }

  try {
    // Prepared statement — aman dari SQL Injection
    const sql = `
      SELECT
        cg.id          AS cabang_id,
        cg.kode        AS cabang_kode,
        cg.nama        AS cabang_nama,
        lr.id          AS rak_id,
        lr.zona,
        lr.baris,
        lr.level_rak,
        lr.kode_lengkap,
        bl.jumlah
      FROM barang_lokasi bl
      JOIN lokasi_rak    lr ON lr.id        = bl.lokasi_id
      JOIN cabang_gudang cg ON cg.id        = lr.cabang_id
      WHERE bl.barang_id = ?
        AND bl.jumlah    > 0
        AND lr.is_active = 1
        AND cg.is_active = 1
      ORDER BY cg.kode, lr.zona, lr.baris, lr.level_rak
    `;

    const [rows] = await pool.query(sql, [barang_id.toString().trim()]);

    // ── Bangun struktur TREE dari data flat ──────────────────
    /** @type {Map<string, object>} key = cabang_kode */
    const treeMap = new Map();

    for (const r of rows) {
      // ── Level Cabang ──────────────────────────────────────
      if (!treeMap.has(r.cabang_kode)) {
        treeMap.set(r.cabang_kode, {
          cabang_id  : r.cabang_id,
          cabang_kode: r.cabang_kode,
          cabang_nama: r.cabang_nama,
          total_unit : 0,
          zona       : {},           // { [zonaKey]: { label, baris: { [barisKey]: { label, level[] } } } }
        });
      }

      const cabangNode = treeMap.get(r.cabang_kode);
      cabangNode.total_unit += Number(r.jumlah);

      // ── Level Zona ────────────────────────────────────────
      const zk = r.zona;
      if (!cabangNode.zona[zk]) {
        cabangNode.zona[zk] = { label: `Zona ${zk}`, baris: {} };
      }

      // ── Level Baris ───────────────────────────────────────
      const bk = r.baris;
      if (!cabangNode.zona[zk].baris[bk]) {
        cabangNode.zona[zk].baris[bk] = { label: `Baris ${bk}`, level: [] };
      }

      // ── Level Rak (leaf node) ─────────────────────────────
      cabangNode.zona[zk].baris[bk].level.push({
        rak_id      : r.rak_id,
        level       : r.level_rak,
        kode_lengkap: r.kode_lengkap,
        jumlah      : Number(r.jumlah),
      });
    }

    return sendJSON(res, 200, {
      status   : 'success',
      barang_id,
      total_cabang: treeMap.size,
      data     : [...treeMap.values()],
    });
  } catch (err) {
    console.error('[getLocationTree] Error:', err.message);
    return sendError(res, 500, 'Gagal membangun location tree.', err.message);
  }
};

// ═══════════════════════════════════════════════════════════════
//  C. POST /api/locations/mutasi
// ═══════════════════════════════════════════════════════════════
/**
 * Body JSON:
 *  { barang_id, tipe, jumlah, lokasi_asal, lokasi_tuju, catatan, user_id }
 *
 * tipe: 'masuk' | 'keluar' | 'pindah'
 *
 * Langkah transaksi:
 *  1. INSERT riwayat_mutasi
 *  2. UPDATE stok di tabel barang  (masuk / keluar saja)
 *  3. UPSERT barang_lokasi         (semua tipe)
 *
 * Jika sukses → commit + broadcast SSE.
 * Jika gagal  → rollback + return 400/500.
 */
const postMutasi = async (req, res) => {
  // ── 1. Parsing & Validasi Input ────────────────────────────
  const {
    barang_id,
    tipe,
    jumlah: rawJumlah,
    lokasi_asal: rawAsal = null,
    lokasi_tuju: rawTuju = null,
    catatan = '',
    user_id = 1, // fallback jika belum ada auth middleware
  } = req.body;

  const jumlah      = Number(rawJumlah);
  const lokasi_asal = rawAsal ? Number(rawAsal) : null;
  const lokasi_tuju = rawTuju ? Number(rawTuju) : null;
  const VALID_TIPE  = ['masuk', 'keluar', 'pindah'];

  // Validasi wajib
  if (!barang_id || !barang_id.toString().trim()) {
    return sendError(res, 400, 'Field barang_id wajib diisi.');
  }
  if (!VALID_TIPE.includes(tipe)) {
    return sendError(res, 400, `Tipe mutasi tidak valid. Gunakan: ${VALID_TIPE.join(' | ')}`);
  }
  if (!Number.isInteger(jumlah) || jumlah <= 0) {
    return sendError(res, 400, 'Jumlah harus berupa bilangan bulat positif.');
  }
  if (tipe === 'pindah' && (!lokasi_asal || !lokasi_tuju)) {
    return sendError(res, 400, 'Tipe pindah membutuhkan lokasi_asal dan lokasi_tuju.');
  }
  if (tipe === 'keluar' && !lokasi_asal) {
    return sendError(res, 400, 'Tipe keluar membutuhkan lokasi_asal.');
  }
  if (tipe === 'masuk' && !lokasi_tuju) {
    return sendError(res, 400, 'Tipe masuk membutuhkan lokasi_tuju.');
  }

  // ── 2. Ambil koneksi & mulai transaksi ─────────────────────
  const conn = await pool.getConnection();

  try {
    await conn.beginTransaction();

    // ── Langkah 1: INSERT riwayat_mutasi ──────────────────────
    const [insertResult] = await conn.query(
      `INSERT INTO riwayat_mutasi
         (barang_id, user_id, tipe, jumlah, lokasi_asal_id, lokasi_tujuan_id, catatan)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        barang_id.toString().trim(),
        Number(user_id),
        tipe,
        jumlah,
        lokasi_asal,   // NULL-safe — mysql2 kirim NULL jika value null
        lokasi_tuju,
        catatan.toString().trim(),
      ]
    );
    const mutasiId = insertResult.insertId;

    // ── Langkah 2: UPDATE stok global di tabel barang ─────────
    if (tipe === 'masuk') {
      await conn.query(
        'UPDATE barang SET stok = stok + ? WHERE id = ?',
        [jumlah, barang_id]
      );
    } else if (tipe === 'keluar') {
      // Cek dulu stok mencukupi
      const [[barangRow]] = await conn.query(
        'SELECT stok FROM barang WHERE id = ? FOR UPDATE',  // row-level lock
        [barang_id]
      );
      if (!barangRow) {
        throw new Error(`Barang dengan ID "${barang_id}" tidak ditemukan.`);
      }
      if (barangRow.stok < jumlah) {
        throw new Error(
          `Stok tidak mencukupi. Tersedia: ${barangRow.stok}, dibutuhkan: ${jumlah}.`
        );
      }
      await conn.query(
        'UPDATE barang SET stok = stok - ? WHERE id = ?',
        [jumlah, barang_id]
      );
    }
    // 'pindah' → stok global tidak berubah (hanya posisi rak yang berubah)

    // ── Langkah 3: UPSERT barang_lokasi ───────────────────────
    if (tipe === 'masuk') {
      // Tambah ke rak tujuan
      await conn.query(
        `INSERT INTO barang_lokasi (barang_id, lokasi_id, jumlah)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE jumlah = jumlah + VALUES(jumlah)`,
        [barang_id, lokasi_tuju, jumlah]
      );

    } else if (tipe === 'keluar') {
      // Cek stok di rak asal
      const [[rakRow]] = await conn.query(
        'SELECT jumlah FROM barang_lokasi WHERE barang_id = ? AND lokasi_id = ? FOR UPDATE',
        [barang_id, lokasi_asal]
      );
      const jumlahDiRak = rakRow ? Number(rakRow.jumlah) : 0;
      if (jumlahDiRak < jumlah) {
        throw new Error(
          `Stok di rak tidak mencukupi. Di rak: ${jumlahDiRak}, dibutuhkan: ${jumlah}.`
        );
      }
      await conn.query(
        `UPDATE barang_lokasi
         SET jumlah = jumlah - ?
         WHERE barang_id = ? AND lokasi_id = ?`,
        [jumlah, barang_id, lokasi_asal]
      );

    } else if (tipe === 'pindah') {
      // Cek stok di rak asal
      const [[rakRow]] = await conn.query(
        'SELECT jumlah FROM barang_lokasi WHERE barang_id = ? AND lokasi_id = ? FOR UPDATE',
        [barang_id, lokasi_asal]
      );
      const jumlahDiRak = rakRow ? Number(rakRow.jumlah) : 0;
      if (jumlahDiRak < jumlah) {
        throw new Error(
          `Stok di rak asal tidak mencukupi untuk dipindahkan. Di rak: ${jumlahDiRak}, dibutuhkan: ${jumlah}.`
        );
      }
      // Kurangi dari rak asal
      await conn.query(
        `UPDATE barang_lokasi
         SET jumlah = jumlah - ?
         WHERE barang_id = ? AND lokasi_id = ?`,
        [jumlah, barang_id, lokasi_asal]
      );
      // Tambah ke rak tujuan (upsert)
      await conn.query(
        `INSERT INTO barang_lokasi (barang_id, lokasi_id, jumlah)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE jumlah = jumlah + VALUES(jumlah)`,
        [barang_id, lokasi_tuju, jumlah]
      );
    }

    // ── Commit ────────────────────────────────────────────────
    await conn.commit();

    // ── SSE Broadcast ─────────────────────────────────────────
    const eventPayload = {
      mutasi_id   : mutasiId,
      barang_id   : barang_id.toString().trim(),
      tipe,
      jumlah,
      lokasi_asal,
      lokasi_tuju,
      catatan     : catatan.toString().trim(),
      timestamp   : new Date().toISOString(),
    };
    sseService.broadcast('mutasi_baru', eventPayload);

    return sendJSON(res, 201, {
      status    : 'success',
      message   : 'Mutasi berhasil dicatat.',
      mutasi_id : mutasiId,
      data      : eventPayload,
    });

  } catch (err) {
    // ── Rollback jika ada error ────────────────────────────────
    await conn.rollback();
    console.error('[postMutasi] Rollback:', err.message);

    const isValidationError =
      err.message.includes('tidak ditemukan') ||
      err.message.includes('tidak mencukupi') ||
      err.message.includes('wajib');

    return sendError(
      res,
      isValidationError ? 422 : 500,
      err.message || 'Gagal mencatat mutasi.',
      err.message
    );
  } finally {
    conn.release(); // kembalikan koneksi ke pool
  }
};

// ═══════════════════════════════════════════════════════════════
//  D. GET /api/locations/riwayat-terakhir
// ═══════════════════════════════════════════════════════════════
/**
 * Query param opsional:
 *   ?limit=10        (default 10, max 100)
 *   ?barang_id=...   (filter per barang)
 */
const getRiwayatTerakhir = async (req, res) => {
  const limit    = Math.min(Math.max(Number(req.query.limit) || 10, 1), 100);
  const barangId = req.query.barang_id || null;

  try {
    let sql = `
      SELECT
        rm.id,
        rm.barang_id,
        b.nama                     AS barang_nama,
        rm.user_id,
        u.username                 AS user_nama,
        u.nama_lengkap             AS user_lengkap,
        rm.tipe,
        rm.jumlah,
        rm.catatan,
        rm.created_at,
        -- Lokasi Asal
        lr_asal.kode_lengkap       AS lokasi_asal_kode,
        cg_asal.nama               AS lokasi_asal_cabang,
        -- Lokasi Tujuan
        lr_tuju.kode_lengkap       AS lokasi_tuju_kode,
        cg_tuju.nama               AS lokasi_tuju_cabang
      FROM riwayat_mutasi rm
      LEFT JOIN barang         b        ON b.id          = rm.barang_id
      LEFT JOIN users          u        ON u.id          = rm.user_id
      LEFT JOIN lokasi_rak     lr_asal  ON lr_asal.id    = rm.lokasi_asal_id
      LEFT JOIN cabang_gudang  cg_asal  ON cg_asal.id    = lr_asal.cabang_id
      LEFT JOIN lokasi_rak     lr_tuju  ON lr_tuju.id    = rm.lokasi_tujuan_id
      LEFT JOIN cabang_gudang  cg_tuju  ON cg_tuju.id    = lr_tuju.cabang_id
    `;

    const params = [];
    if (barangId) {
      sql += ' WHERE rm.barang_id = ?';
      params.push(barangId.toString().trim());
    }

    sql += ' ORDER BY rm.created_at DESC LIMIT ?';
    params.push(limit);

    const [rows] = await pool.query(sql, params);

    return sendJSON(res, 200, {
      status : 'success',
      limit,
      total  : rows.length,
      data   : rows,
    });
  } catch (err) {
    console.error('[getRiwayatTerakhir] Error:', err.message);
    return sendError(res, 500, 'Gagal mengambil riwayat mutasi.', err.message);
  }
};

// ═══════════════════════════════════════════════════════════════
//  E. GET /api/locations/sse
// ═══════════════════════════════════════════════════════════════
/**
 * Endpoint SSE — browser terhubung dan menunggu event.
 * Setiap kali POST /mutasi berhasil, semua client menerima
 * event "mutasi_baru" secara real-time tanpa perlu polling.
 */
const getSseStream = (req, res) => {
  const clientId = crypto.randomUUID();

  // Simpan client & set header SSE
  sseService.addClient(clientId, res);

  // Cleanup otomatis saat client disconnect (tutup tab/jaringan putus)
  req.on('close', () => {
    sseService.removeClient(clientId);
  });
};

// ═══════════════════════════════════════════════════════════════
//  F. GET /api/locations/rak
// ═══════════════════════════════════════════════════════════════
/**
 * Mengambil daftar rak aktif.
 * Query parameters:
 *   ?cabang_id=123 (opsional, filter per cabang)
 *   ?barang_id=INV-001 (opsional, jika diset hanya ambil rak yang memiliki barang tersebut > 0)
 */
const getRak = async (req, res) => {
  const cabang_id = req.query.cabang_id ? Number(req.query.cabang_id) : null;
  const barang_id = req.query.barang_id || null;

  try {
    let sql;
    const params = [];

    if (barang_id) {
      sql = `
        SELECT lr.id, lr.cabang_id, lr.zona, lr.baris, lr.level_rak, lr.kode_lengkap, lr.kapasitas, lr.is_active,
               COALESCE(bl.jumlah, 0) AS terisi
        FROM lokasi_rak lr
        INNER JOIN barang_lokasi bl ON bl.lokasi_id = lr.id AND bl.barang_id = ? AND bl.jumlah > 0
        WHERE lr.is_active = 1
      `;
      params.push(barang_id.toString().trim());
    } else {
      sql = `
        SELECT lr.id, lr.cabang_id, lr.zona, lr.baris, lr.level_rak, lr.kode_lengkap, lr.kapasitas, lr.is_active,
               COALESCE(SUM(bl.jumlah), 0) AS terisi
        FROM lokasi_rak lr
        LEFT JOIN barang_lokasi bl ON bl.lokasi_id = lr.id
        WHERE lr.is_active = 1
      `;
    }

    if (cabang_id) {
      sql += ' AND lr.cabang_id = ? ';
      params.push(cabang_id);
    }

    if (!barang_id) {
      sql += ' GROUP BY lr.id ';
    }

    sql += ' ORDER BY lr.zona, lr.baris, lr.level_rak';

    const [rows] = await pool.query(sql, params);

    return sendJSON(res, 200, {
      status : 'success',
      total  : rows.length,
      data   : rows,
    });
  } catch (err) {
    console.error('[getRak] Error:', err.message);
    return sendError(res, 500, 'Gagal mengambil data lokasi rak.', err.message);
  }
};

// ═══════════════════════════════════════════════════════════════
//  EXPORTS
// ═══════════════════════════════════════════════════════════════
module.exports = {
  getCabang,
  getLocationTree,
  postMutasi,
  getRiwayatTerakhir,
  getSseStream,
  getRak,
};
