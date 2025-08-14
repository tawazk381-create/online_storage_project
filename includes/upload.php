<?php
// File: includes/upload.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['name'])) {
    if (empty($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }

    $userDir = STORAGE_PATH . '/' . $_SESSION['user_id'];
    if (!is_dir($userDir)) {
        if (!mkdir($userDir, 0755, true) && !is_dir($userDir)) {
            error_log("Unable to create user storage dir: $userDir");
            header('Location: ../dashboard.php');
            exit;
        }
    }

    $filename = basename($_FILES['file']['name']);
    // sanitize filename for safety on disk (but keep original for display)
    $safeFilename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);
    $ext = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));

    $categories = [
        'images'    => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'],
        'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf','txt', 'csv'],
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

    $target = $typeDir . '/' . $safeFilename;

    // Avoid overwrite: append timestamp if file exists
    if (file_exists($target)) {
        $base = pathinfo($safeFilename, PATHINFO_FILENAME);
        $target = $typeDir . '/' . $base . '_' . time() . '.' . $ext;
    }

    if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
        // store the original display filename in DB, but path is absolute target
        $stmt = $pdo->prepare("
            INSERT INTO files (user_id, filename, path, file_type, size, uploaded_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $size = filesize($target);
        $stmt->execute([$_SESSION['user_id'], $filename, $target, $folder, $size]);
    } else {
        error_log('Upload failed for ' . $filename);
    }
}

header('Location: ../dashboard.php');
exit;
