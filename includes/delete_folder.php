<?php
// File: includes/delete_folder.php
require_once __DIR__ . '/config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

$type = $_POST['type'] ?? '';
if (!$type) {
    http_response_code(400);
    echo 'Missing folder type.';
    exit;
}

// Fetch files for this user & type
$stmt = $pdo->prepare("SELECT id, filename, path FROM files WHERE user_id = ? AND file_type = ?");
$stmt->execute([$_SESSION['user_id'], $type]);
$files = $stmt->fetchAll();

if (empty($files)) {
    // Nothing to delete — redirect back
    header('Location: ../dashboard.php?msg=' . urlencode('nothing_to_delete'));
    exit;
}

$pdo->beginTransaction();
$deletedFiles = [];
$failedUnlinks = [];

foreach ($files as $f) {
    $path = $f['path'];
    $id = (int)$f['id'];

    // Attempt to unlink file if exists
    if (is_file($path)) {
        if (@unlink($path)) {
            $deletedFiles[] = $id;
        } else {
            // mark as failed unlink but continue
            $failedUnlinks[] = $f['filename'];
        }
    } else {
        // File missing on disk — still delete DB record
        $deletedFiles[] = $id;
    }
}

// Delete DB records for collected ids
if (!empty($deletedFiles)) {
    $in  = str_repeat('?,', count($deletedFiles) - 1) . '?';
    $sql = "DELETE FROM files WHERE id IN ($in) AND user_id = ?";
    $params = array_merge($deletedFiles, [$_SESSION['user_id']]);
    $stmtDel = $pdo->prepare($sql);
    $stmtDel->execute($params);
}

$pdo->commit();

// Build redirect message
if (!empty($failedUnlinks)) {
    $msg = 'Deleted records, but some files could not be removed from disk: ' . implode(', ', array_slice($failedUnlinks, 0, 5));
    // Note: For long lists, only show first 5 filenames
    header('Location: ../dashboard.php?msg=' . urlencode($msg));
    exit;
}

// Success
header('Location: ../dashboard.php?msg=' . urlencode('folder_deleted'));
exit;
