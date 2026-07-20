<?php
/**
 * Backend router for PHP built-in server (php -S).
 *
 * For static files (avatars, images, etc.) served from the backend, we must
 * send Cross-Origin-Resource-Policy: cross-origin so that the frontend — which
 * runs under COEP (Cross-Origin-Embedder-Policy: require-corp) — is allowed to
 * load them.
 *
 * All API requests fall through to index.php as before.
 */

// ── CORP header for cross-origin isolation compatibility ─────────────────────
header('Cross-Origin-Resource-Policy: cross-origin');
// Also allow CORS for the API (mirrored from index.php for preflight on static paths)
header('Access-Control-Allow-Origin: *');

$uri      = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$docRoot  = __DIR__;
$filePath = $docRoot . $uri;

// ── Serve real static files (avatars, images) directly ───────────────────────
if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
    ];

    if (isset($mimeTypes[$ext])) {
        // Static asset — serve directly with CORP header already set above
        header('Content-Type: ' . $mimeTypes[$ext]);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

// ── All other requests go to index.php (the API front controller) ────────────
require_once __DIR__ . '/index.php';
