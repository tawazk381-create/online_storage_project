<?php
// File: includes/upload.php
require_once __DIR__ . '/config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['name'])) {
    $userDir = __DIR__ . '/../storage/' . $_SESSION['user_id'];
    if (!is_dir($userDir)) mkdir($userDir, 0755, true);

    $filename = basename($_FILES['file']['name']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // File type categories
    $categories = [
        'images'    => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'],
        'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'],
        'videos'    => ['mp4', 'avi', 'mov', 'mkv', 'flv', 'wmv'],
        'audio'     => ['mp3', 'wav', 'aac', 'ogg', 'flac']
    ];

    $folder = 'others';
    foreach ($categories as $cat => $exts) {
        if (in_array($ext, $exts)) {
            $folder = $cat;
            break;
        }
    }

    $typeDir = $userDir . '/' . $folder;
    if (!is_dir($typeDir)) mkdir($typeDir, 0755, true);

    $target = $typeDir . '/' . $filename;

    if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
        $stmt = $pdo->prepare("
            INSERT INTO files (user_id, filename, path, file_type, uploaded_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $filename, $target, $folder]);
    }
}

header('Location: ../dashboard.php');
exit;
?>
