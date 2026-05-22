'use strict';
/**
 * src/middleware/cors.js
 * ──────────────────────
 * Konfigurasi CORS yang aman dan fleksibel.
 * Origin yang diizinkan dibaca dari env CORS_ORIGIN (comma-separated).
 */

const corsLib = require('cors');

// Parse CORS_ORIGIN env, fallback ke wildcard di development
const allowedOrigins = process.env.CORS_ORIGIN
  ? process.env.CORS_ORIGIN.split(',').map(o => o.trim())
  : ['*'];

const isDev = process.env.NODE_ENV !== 'production';

const corsOptions = {
  origin(origin, callback) {
    // Izinkan request tanpa Origin (e.g. curl, Postman, server-to-server)
    if (!origin) return callback(null, true);

    if (allowedOrigins.includes('*') || allowedOrigins.includes(origin)) {
      return callback(null, true);
    }

    // Di production, tolak origin yang tidak terdaftar
    const msg = `CORS: Origin "${origin}" tidak diizinkan.`;
    if (isDev) {
      console.warn(`⚠️   ${msg}`);
      return callback(null, true); // longgar di dev
    }
    return callback(new Error(msg));
  },
  methods             : ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
  allowedHeaders      : ['Content-Type', 'Authorization', 'X-Requested-With'],
  exposedHeaders      : ['X-Total-Count'],
  credentials         : true,
  optionsSuccessStatus: 200, // Safari compat
};

module.exports = corsLib(corsOptions);
