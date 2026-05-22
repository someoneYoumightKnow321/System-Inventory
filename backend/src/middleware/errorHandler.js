'use strict';
/**
 * src/middleware/errorHandler.js
 * ──────────────────────────────
 * Global error handler untuk Express.
 * Letakkan PALING AKHIR setelah semua route di app.js.
 *
 * Menangkap error dari:
 *  - next(err) yang dipanggil di route/controller
 *  - Error CORS
 *  - JSON parse error dari express.json()
 */

const errorHandler = (err, req, res, _next) => {
  const isDev     = process.env.NODE_ENV !== 'production';
  const status    = err.status || err.statusCode || 500;
  const isJsonErr = err.type === 'entity.parse.failed';

  if (isJsonErr) {
    return res.status(400).json({
      status : 'error',
      message: 'Body JSON tidak valid. Pastikan format JSON sudah benar.',
    });
  }

  console.error(`❌  [${req.method}] ${req.originalUrl}  →  ${status}: ${err.message}`);

  return res.status(status).json({
    status : 'error',
    message: err.message || 'Terjadi kesalahan pada server.',
    ...(isDev && { stack: err.stack }),
  });
};

/**
 * Handler untuk rute yang tidak ditemukan (404).
 */
const notFound = (req, res) => {
  res.status(404).json({
    status : 'error',
    message: `Endpoint [${req.method}] ${req.originalUrl} tidak ditemukan.`,
  });
};

module.exports = { errorHandler, notFound };
