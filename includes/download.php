<?php
// File: includes/download.php
require_once __DIR__ . '/config.php';
requireAuth();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

$id = (int)$_GET['id'];

// Fetch the file and ensure it belongs to the logged-in user
$stmt = $pdo->prepare("SELECT filename, path FROM files WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$path = $file['path'];
$filename = $file['filename'];

// Check that file exists on disk
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    echo 'File not found on server.';
    exit;
}

// Clear output buffers (important for large files)
if (ob_get_level()) {
    ob_end_clean();
}

// Determine mime type (best-effort)
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

// Send headers
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($path));

// If you have large files and the server supports X-Sendfile / X-Accel-Redirect, prefer that approach.
// For now, simple streaming:
readfile($path);
exit;
