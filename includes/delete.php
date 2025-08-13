<?php
// File: includes/delete.php
require_once __DIR__ . '/config.php';
requireAuth();

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Fetch file path
    $stmt = $pdo->prepare("SELECT path FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    if ($f = $stmt->fetch()) {
        if (file_exists($f['path'])) {
            unlink($f['path']);
        }
        $pdo->prepare("DELETE FROM files WHERE id = ?")->execute([$id]);
    }
}
header('Location: ../dashboard.php');
exit;
?>
