<?php
/**
 * Frontend router for PHP built-in server (php -S).
 *
 * Serves all static frontend files with correct MIME types.
 *
 * Usage: php -S 0.0.0.0:8081 router.php
 */

$uri      = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$docRoot  = __DIR__;
$filePath = $docRoot . $uri;

// Directory → serve its index.html
if (is_dir($filePath)) {
    $filePath = rtrim($filePath, '/') . '/index.html';
}

// ── MIME type map ─────────────────────────────────────────────────────────────
$mimeTypes = [
    'html'  => 'text/html; charset=UTF-8',
    'htm'   => 'text/html; charset=UTF-8',
    'css'   => 'text/css; charset=UTF-8',
    'js'    => 'application/javascript; charset=UTF-8',
    'mjs'   => 'application/javascript; charset=UTF-8',
    'json'  => 'application/json; charset=UTF-8',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'svg'   => 'image/svg+xml',
    'ico'   => 'image/x-icon',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'txt'   => 'text/plain; charset=UTF-8',
    'wasm'  => 'application/wasm',
];

// ── Serve the file ────────────────────────────────────────────────────────────
if (file_exists($filePath) && is_file($filePath)) {
    $ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// ── 404 fallback ──────────────────────────────────────────────────────────────
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
echo '<h1>404 Not Found</h1>';
