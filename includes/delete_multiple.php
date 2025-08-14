<?php
// includes/delete_multiple.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

$ids = $_POST['ids'] ?? [];
if (!is_array($ids) || empty($ids)) {
    $_SESSION['error'] = 'No files selected.';
    header('Location: ../dashboard.php');
    exit;
}

$idsFiltered = array_values(array_filter(array_map('intval', $ids), function($v){ return $v > 0; }));
if (empty($idsFiltered)) {
    $_SESSION['error'] = 'No valid files selected.';
    header('Location: ../dashboard.php');
    exit;
}

try {
    // select files owned by user
    $in = implode(',', array_fill(0, count($idsFiltered), '?'));
    $sql = "SELECT id, path, filename FROM files WHERE id IN ($in) AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $params = array_merge($idsFiltered, [$_SESSION['user_id']]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        $_SESSION['error'] = 'No matching files found to delete.';
        header('Location: ../dashboard.php');
        exit;
    }

    $pdo->beginTransaction();

    $deletedIds = [];
    $failedUnlinks = [];

    foreach ($rows as $r) {
        $path = $r['path'];
        $id = (int)$r['id'];
        if (is_file($path)) {
            if (@unlink($path)) {
                $deletedIds[] = $id;
            } else {
                $failedUnlinks[] = $r['filename'];
                // still delete DB entry? We'll skip deleting DB record when unlink fails,
                // but you may decide to delete DB record anyway. Here we try to delete DB only for deleted files.
            }
        } else {
            // if file missing on disk, still delete DB record
            $deletedIds[] = $id;
        }
    }

    if (!empty($deletedIds)) {
        $in2 = implode(',', array_fill(0, count($deletedIds), '?'));
        $sqlDel = "DELETE FROM files WHERE id IN ($in2) AND user_id = ?";
        $paramsDel = array_merge($deletedIds, [$_SESSION['user_id']]);
        $stmtDel = $pdo->prepare($sqlDel);
        $stmtDel->execute($paramsDel);
    }

    $pdo->commit();

    if (!empty($failedUnlinks)) {
        $_SESSION['error'] = 'Deleted records, but some files could not be removed from disk: ' . implode(', ', array_slice($failedUnlinks, 0, 5));
    } else {
        $_SESSION['success'] = 'Selected files deleted successfully.';
    }
    header('Location: ../dashboard.php');
    exit;

} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    @file_put_contents(STORAGE_PATH . '/db_errors.log', date('c') . ' - delete_multiple error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    $_SESSION['error'] = 'Server error deleting files.';
    if (strtolower(APP_ENV) !== 'production') {
        $_SESSION['error'] .= ' ' . $e->getMessage();
    }
    header('Location: ../dashboard.php');
    exit;
}
