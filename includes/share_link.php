<?php
// File: includes/share_link.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

function json_error(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

// ensure logged in
if (empty($_SESSION['user_id'])) {
    json_error('Not authenticated', 403);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    json_error('Missing or invalid id', 400);
}
$fileId = (int)$_GET['id'];

// verify ownership
try {
    $stmt = $pdo->prepare("SELECT id, filename FROM files WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$fileId, $_SESSION['user_id']]);
    $file = $stmt->fetch();
} catch (Throwable $e) {
    error_log('share_link DB error: ' . $e->getMessage());
    json_error('Server error', 500);
}

if (!$file) {
    json_error('File not found or not owned by you', 403);
}

// Determine base URL (prefer APP_URL)
$baseUrl = '';
if (!empty(APP_URL) && filter_var(APP_URL, FILTER_VALIDATE_URL)) {
    $baseUrl = rtrim(APP_URL, '/');
} else {
    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? null);
    if ($host) {
        $baseUrl = $scheme . '://' . rtrim($host, '/');
    }
}
if (empty($baseUrl)) {
    json_error('Server misconfiguration: no base URL. Set APP_URL in environment.', 500);
}

// Reuse existing non-expired share if present
try {
    $stmt = $pdo->prepare("
        SELECT token, expires_at
        FROM shares
        WHERE file_id = ? AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$fileId]);
    $existing = $stmt->fetch();
} catch (Throwable $e) {
    error_log('share_link DB error (lookup): ' . $e->getMessage());
    json_error('Server error', 500);
}

if ($existing && !empty($existing['token'])) {
    $token = $existing['token'];
    $expires = $existing['expires_at'];
    $url = $baseUrl . '/share.php?token=' . rawurlencode($token);
    echo json_encode(['url' => $url, 'expires_at' => $expires, 'reused' => true]);
    exit;
}

// create token (7 days)
try {
    $token = bin2hex(random_bytes(24));
} catch (Exception $e) {
    json_error('Could not generate token', 500);
}
$expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 3600));

try {
    $stmt = $pdo->prepare("INSERT INTO shares (file_id, token, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
    $stmt->execute([$fileId, $token, $expiresAt]);
} catch (Throwable $e) {
    error_log('share_link DB error (insert): ' . $e->getMessage());
    json_error('Server error', 500);
}

$url = $baseUrl . '/share.php?token=' . rawurlencode($token);
echo json_encode(['url' => $url, 'expires_at' => $expiresAt, 'reused' => false]);
exit;
