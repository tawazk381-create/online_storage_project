<?php
// File: includes/delete_user.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
requireAuth();

$user_id = $_SESSION['user_id'];

// Delete files from DB and storage
$stmt = $pdo->prepare("SELECT path FROM files WHERE user_id = ?");
$stmt->execute([$user_id]);
$files = $stmt->fetchAll();

foreach ($files as $file) {
    if (file_exists($file['path'])) {
        unlink($file['path']);
    }
}

// Remove all subfolders and storage directory
$userDir = __DIR__ . '/../storage/' . $user_id;
if (is_dir($userDir)) {
    $it = new RecursiveDirectoryIterator($userDir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($userDir);
}

// Delete DB records
$pdo->prepare("DELETE FROM files WHERE user_id = ?")->execute([$user_id]);
$pdo->prepare("DELETE FROM shares WHERE file_id IN (SELECT id FROM files WHERE user_id = ?)")->execute([$user_id]);
$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);

session_destroy();
header("Location: ../index.php");
exit;
?>
