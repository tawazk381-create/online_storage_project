<?php
// includes/share_multiple.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

function appendLog($path, $line) {
    @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$dbLog = STORAGE_PATH . '/db_errors.log';

// Accept only POST
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

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo json_encode(['error' => 'Server missing Zip support (ZipArchive).']);
    exit;
}

// Fetch files that belong to this user
try {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, filename, path FROM files WHERE id IN ($in) AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $params = array_merge($ids, [$_SESSION['user_id']]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $msg = 'share_multiple DB error: ' . $e->getMessage();
    appendLog($dbLog, date('c') . ' - ' . $msg . ' - trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Server error preparing share.']);
    exit;
}

if (empty($rows)) {
    http_response_code(404);
    echo json_encode(['error' => 'No matching files found.']);
    exit;
}

// Build user directories
$userDir = STORAGE_PATH . '/' . $_SESSION['user_id'];
$sharedDir = $userDir . '/shared';

// Ensure base directories exist (best-effort)
if (!is_dir($userDir)) {
    @mkdir($userDir, 0755, true);
}
if (!is_dir($sharedDir)) {
    @mkdir($sharedDir, 0755, true);
}

// prepare zip file name
$zipName = 'shared_' . time() . '_' . bin2hex(random_bytes(6)) . '.zip';
$zipFull = $sharedDir . '/' . $zipName;

// helper to try create zip at $destPath
function try_create_zip(array $files, string $destPath, string &$errOut = null): bool {
    $zip = new ZipArchive();
    $ok = ($zip->open($destPath, ZipArchive::OVERWRITE) === true);
    if (!$ok) {
        $errOut = 'ZipArchive::open returned false for ' . $destPath;
        return false;
    }
    foreach ($files as $r) {
        $path = $r['path'];
        $display = $r['filename'];
        if (is_file($path) && is_readable($path)) {
            // ensure unique names inside zip
            $uniqueName = $display;
            $i = 1;
            while ($zip->locateName($uniqueName) !== false) {
                $ext = pathinfo($display, PATHINFO_EXTENSION);
                $base = pathinfo($display, PATHINFO_FILENAME);
                $uniqueName = $base . "_$i" . ($ext ? '.' . $ext : '');
                $i++;
            }
            if (!$zip->addFile($path, $uniqueName)) {
                // continue but note error
                // ZipArchive::addFile sometimes fails silently; we'll log after closing
            }
        }
    }
    $zip->close();
    // verify file exists and non-zero
    if (!is_file($destPath) || filesize($destPath) === 0) {
        $errOut = 'Zip created but file missing or empty at ' . $destPath;
        return false;
    }
    return true;
}

// 1) Try creating zip in user's shared folder
$err = null;
$created = false;
$creationAttemptPaths = [];

try {
    $creationAttemptPaths[] = $zipFull;
    if (is_dir($sharedDir) && is_writable($sharedDir)) {
        $created = try_create_zip($rows, $zipFull, $err);
        if (!$created) {
            appendLog($dbLog, date('c') . " - share_multiple: primary zip creation failed at $zipFull; err: $err");
        }
    } else {
        appendLog($dbLog, date('c') . " - share_multiple: sharedDir missing or not writable ($sharedDir)");
    }

    // 2) Fallback: create zip in system temp directory and then move it to sharedDir
    if (!$created) {
        $tmp = tempnam(sys_get_temp_dir(), 'sharezip_');
        if ($tmp === false) {
            appendLog($dbLog, date('c') . " - share_multiple: tempnam failed in sys_get_temp_dir()");
        } else {
            // tempnam creates an empty file; ZipArchive can open it
            $creationAttemptPaths[] = $tmp;
            $created = try_create_zip($rows, $tmp, $err);
            if ($created) {
                // attempt to move it to sharedDir (preferred), otherwise leave it in tmp and use that path
                if (is_dir($sharedDir) && is_writable($sharedDir)) {
                    $dest = $sharedDir . '/' . $zipName;
                    // attempt rename (move)
                    if (!@rename($tmp, $dest)) {
                        // try copy
                        if (!@copy($tmp, $dest)) {
                            // moving failed; we'll keep $tmp as the final zip
                            appendLog($dbLog, date('c') . " - share_multiple: move/copy from tmp to sharedDir failed; tmp=$tmp dest=$dest");
                            // leave $created path as $tmp
                            $zipFull = $tmp;
                        } else {
                            // copy succeeded, unlink tmp
                            @unlink($tmp);
                            $zipFull = $dest;
                        }
                    } else {
                        // rename succeeded
                        $zipFull = $dest;
                    }
                } else {
                    // sharedDir not writable - keep $tmp as zipFull
                    appendLog($dbLog, date('c') . " - share_multiple: sharedDir not writable; using tmp zip at $tmp");
                    $zipFull = $tmp;
                }
            } else {
                appendLog($dbLog, date('c') . " - share_multiple: fallback zip creation in tmp failed; err: $err");
                // cleanup tmp
                if (is_file($tmp)) @unlink($tmp);
            }
        }
    }
} catch (Throwable $e) {
    appendLog($dbLog, date('c') . ' - share_multiple exception: ' . $e->getMessage() . ' trace:' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Server error creating zip.']);
    exit;
}

// If still not created
if (empty($zipFull) || !is_file($zipFull) || filesize($zipFull) === 0) {
    $attempts = implode(', ', $creationAttemptPaths);
    appendLog($dbLog, date('c') . " - share_multiple failed to create zip. Attempts: $attempts ; last_err: " . ($err ?? 'none'));
    http_response_code(500);
    echo json_encode(['error' => 'Could not create zip file on server. Check storage permissions.']);
    exit;
}

// Insert a new files row for this zip so we can reuse existing share logic
try {
    $size = filesize($zipFull);
    // Use file_name in DB as the zip file name (visible to user)
    $stmt = $pdo->prepare("INSERT INTO files (user_id, filename, path, file_type, size, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], basename($zipFull), $zipFull, 'zip', $size]);
    $newFileId = (int)$pdo->lastInsertId();

    // create token (7 days)
    $token = bin2hex(random_bytes(24));
    $expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 3600));
    $stmt2 = $pdo->prepare("INSERT INTO shares (file_id, token, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
    $stmt2->execute([$newFileId, $token, $expiresAt]);

} catch (Throwable $e) {
    // If DB insert fails, attempt to remove the zip to avoid orphan files (best-effort)
    appendLog($dbLog, date('c') . ' - share_multiple insert error: ' . $e->getMessage());
    if (is_file($zipFull)) {
        // Only unlink created zips inside userDir/shared or tmp if we created them; be conservative
        @unlink($zipFull);
    }
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
