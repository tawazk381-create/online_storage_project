<?php
// public_download.php
require_once __DIR__ . '/includes/config.php';

$token = $_GET['token'] ?? '';
$preview = isset($_GET['preview']) && $_GET['preview'] == '1';

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

// determine MIME
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

// detect image extensions for preview
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$imageExts = ['jpg','jpeg','png','gif','webp','svg','bmp'];
$isImage = in_array($ext, $imageExts);

// clear output buffer
if (ob_get_level()) ob_end_clean();

if ($preview && $isImage) {
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=3600');
    readfile($path);
    exit;
}

// stream as attachment
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
