'use strict';
/**
 * src/config/database.js
 * ──────────────────────
 * Konfigurasi connection pool MySQL2 dengan promise API.
 * Menggunakan pool agar koneksi direcycle dan tidak bocor.
 */

const mysql = require('mysql2/promise');
require('dotenv').config();

const poolConfig = {
  host              : process.env.DB_HOST    || 'localhost',
  port              : Number(process.env.DB_PORT) || 3306,
  user              : process.env.DB_USER    || 'root',
  password          : process.env.DB_PASS    || '',
  database          : process.env.DB_NAME    || 'db_inventaris',
  charset           : 'utf8mb4',
  timezone          : '+07:00',
  // ── Pool settings ──────────────────────────────────────────
  waitForConnections: true,
  connectionLimit   : Number(process.env.DB_POOL_MAX)     || 10,
  queueLimit        : 0,
  idleTimeout       : Number(process.env.DB_POOL_IDLE)    || 10000,
  // ── Auto-reconnect ─────────────────────────────────────────
  enableKeepAlive   : true,
  keepAliveInitialDelay: 0,
};

const pool = mysql.createPool(poolConfig);

/**
 * Tes koneksi saat startup.
 * Lempar error agar proses Node.js langsung berhenti jika DB tidak bisa dihubungi.
 */
async function testConnection() {
  let conn;
  try {
    conn = await pool.getConnection();
    const [rows] = await conn.query('SELECT VERSION() AS ver');
    console.log(`✅  MySQL terhubung  →  ${rows[0].ver}  (db: ${poolConfig.database})`);
  } catch (err) {
    console.error('❌  Gagal koneksi ke MySQL:', err.message);
    process.exit(1);
  } finally {
    if (conn) conn.release();
  }
}

module.exports = { pool, testConnection };
