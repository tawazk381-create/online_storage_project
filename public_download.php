<?php
// File: public_download.php
// Usage: public_download.php?token=... [&preview=1]
// Place in web root (not in includes/)

require_once __DIR__ . '/includes/config.php';

$token = $_GET['token'] ?? '';
$preview = isset($_GET['preview']) && $_GET['preview'] === '1';

if (!$token) {
    http_response_code(400);
    echo 'Missing token.';
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT f.filename, f.path
        FROM shares s
        JOIN files f ON s.file_id = f.id
        WHERE s.token = ? AND (s.expires_at IS NULL OR s.expires_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $file = $stmt->fetch();
} catch (Throwable $e) {
    error_log('public_download DB error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Server error.';
    exit;
}

if (!$file) {
    http_response_code(404);
    echo 'File not found or link expired.';
    exit;
}

$path = $file['path'];
$filename = $file['filename'];

if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    echo 'File missing on server.';
    exit;
}

// Determine mime
$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $m = finfo_file($finfo, $path);
        if ($m) $mime = $m;
        finfo_close($finfo);
    }
} elseif (function_exists('mime_content_type')) {
    $m = mime_content_type($path);
    if ($m) $mime = $m;
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$imageExts = ['jpg','jpeg','png','gif','webp','svg','bmp'];
$isImage = in_array($ext, $imageExts);

// clear output buffers
if (ob_get_level()) ob_end_clean();

// Option: Use X-Accel-Redirect (nginx) or X-Sendfile (apache) if available.
// Configure environment toggles in .env if desired:
$useXAccel = getenv('USE_X_ACCEL') === '1';
$nginxInternalPrefix = getenv('NGINX_INTERNAL_PREFIX') ?: '/protected_files'; // map to STORAGE_PATH

if ($preview && $isImage) {
    // Serve inline for preview (no attachment)
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=3600');
    readfile($path);
    exit;
}

// Try X-Accel-Redirect when enabled
if ($useXAccel && !empty($nginxInternalPrefix)) {
    // compute path relative to STORAGE_PATH
    $rel = str_replace(STORAGE_PATH, '', $path);
    $rel = ltrim($rel, '/');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('X-Accel-Redirect: ' . rtrim($nginxInternalPrefix, '/') . '/' . $rel);
    exit;
}

// Apache mod_xsendfile
if (function_exists('apache_get_modules') && in_array('mod_xsendfile', apache_get_modules())) {
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('X-Sendfile: ' . $path);
    exit;
}

// Fallback: PHP streaming
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
