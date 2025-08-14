<?php
// includes/share_multiple.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = $_POST['ids'] ?? [];
if (!is_array($raw) || empty($raw)) {
    http_response_code(400);
    echo json_encode(['error' => 'No files selected']);
    exit;
}

$ids = array_values(array_filter(array_map('intval', $raw), function($v){ return $v > 0; }));
if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid file ids provided']);
    exit;
}

try {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, filename, path FROM files WHERE id IN ($in) AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $params = array_merge($ids, [$_SESSION['user_id']]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    @file_put_contents(STORAGE_PATH . '/db_errors.log', date('c') . ' - share_multiple DB error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Server error preparing share.']);
    exit;
}

if (empty($rows)) {
    http_response_code(404);
    echo json_encode(['error' => 'No matching files found.']);
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo json_encode(['error' => 'Server missing Zip support.']);
    exit;
}

$userDir = STORAGE_PATH . '/' . $_SESSION['user_id'];
if (!is_dir($userDir)) @mkdir($userDir, 0755, true);

// create zip file inside user's folder
$zipName = 'shared_' . time() . '_' . bin2hex(random_bytes(6)) . '.zip';
$zipPath = $userDir . '/shared/' ;
if (!is_dir($zipPath)) @mkdir($zipPath, 0755, true);
$zipFull = $zipPath . $zipName;

$zip = new ZipArchive();
if ($zip->open($zipFull, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not create zip file on server.']);
    exit;
}

foreach ($rows as $r) {
    $path = $r['path'];
    $display = $r['filename'];
    if (is_file($path) && is_readable($path)) {
        // ensure unique names inside zip
        $uniqueName = $display;
        $i = 1;
        while ($zip->locateName($uniqueName) !== false) {
            $uniqueName = pathinfo($display, PATHINFO_FILENAME) . "_$i" . ($ext = pathinfo($display, PATHINFO_EXTENSION) ? '.' . pathinfo($display, PATHINFO_EXTENSION) : '');
            $i++;
        }
        $zip->addFile($path, $uniqueName);
    }
}

$zip->close();

if (!is_file($zipFull) || filesize($zipFull) === 0) {
    @unlink($zipFull);
    http_response_code(500);
    echo json_encode(['error' => 'Zip creation failed.']);
    exit;
}

// Insert a new files row for this zip so we can reuse existing share logic
try {
    $size = filesize($zipFull);
    $stmt = $pdo->prepare("INSERT INTO files (user_id, filename, path, file_type, size, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $zipName, $zipFull, 'zip', $size]);
    $newFileId = (int)$pdo->lastInsertId();

    // create token (7 days)
    $token = bin2hex(random_bytes(24));
    $expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 3600));
    $stmt2 = $pdo->prepare("INSERT INTO shares (file_id, token, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
    $stmt2->execute([$newFileId, $token, $expiresAt]);

} catch (Throwable $e) {
    @unlink($zipFull);
    @file_put_contents(STORAGE_PATH . '/db_errors.log', date('c') . ' - share_multiple insert error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Server error creating share.']);
    exit;
}

// Build base URL
$baseUrl = '';
if (!empty(APP_URL) && filter_var(APP_URL, FILTER_VALIDATE_URL)) {
    $baseUrl = rtrim(APP_URL, '/');
} else {
    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? null);
    if ($host) $baseUrl = $scheme . '://' . rtrim($host, '/');
}
if (empty($baseUrl)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server misconfiguration: no base URL.']);
    exit;
}

$url = $baseUrl . '/share.php?token=' . rawurlencode($token);
echo json_encode(['url' => $url, 'expires_at' => $expiresAt]);
exit;
