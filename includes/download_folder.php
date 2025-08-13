<?php
// File: includes/download_folder.php
require_once __DIR__ . '/config.php';
requireAuth();

$type = $_GET['type'] ?? '';
if (!$type) {
    http_response_code(400);
    echo 'Missing folder type.';
    exit;
}

// Fetch files for user & type
$stmt = $pdo->prepare("SELECT filename, path FROM files WHERE user_id = ? AND file_type = ? ORDER BY uploaded_at DESC");
$stmt->execute([$_SESSION['user_id'], $type]);
$files = $stmt->fetchAll();

if (empty($files)) {
    http_response_code(404);
    echo 'No files to download in this folder.';
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo 'Server missing Zip extension.';
    exit;
}

// Create a temp zip file
$tmpFile = tempnam(sys_get_temp_dir(), 'zip_');
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'Could not create zip file.';
    exit;
}

// Add files to zip
foreach ($files as $f) {
    $path = $f['path'];
    $displayName = $f['filename'];

    // Only add real files
    if (is_file($path) && is_readable($path)) {
        // Ensure unique names in zip (if duplicates, prefix with id or timestamp)
        $zip->addFile($path, $displayName);
    }
}

$zip->close();

// Stream the zip as attachment
if (filesize($tmpFile) === 0) {
    // Cleanup and error
    @unlink($tmpFile);
    http_response_code(500);
    echo 'Zip creation failed (empty zip).';
    exit;
}

// Clean any output buffers
if (ob_get_level()) {
    ob_end_clean();
}

$filenameSafe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $type);
$downloadName = $filenameSafe . '_' . date('Ymd_His') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Pragma: public');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');
readfile($tmpFile);

// remove temp file
@unlink($tmpFile);
exit;
