<?php
// includes/download_multiple.php
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

// sanitize numeric ids
$idsFiltered = array_values(array_filter(array_map('intval', $ids), function($v){ return $v > 0; }));
if (empty($idsFiltered)) {
    $_SESSION['error'] = 'No valid files selected.';
    header('Location: ../dashboard.php');
    exit;
}

try {
    // fetch files that belong to this user and the requested ids
    $in = implode(',', array_fill(0, count($idsFiltered), '?'));
    $sql = "SELECT id, filename, path FROM files WHERE id IN ($in) AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $params = array_merge($idsFiltered, [$_SESSION['user_id']]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    @file_put_contents(STORAGE_PATH . '/db_errors.log', date('c') . ' - download_multiple DB error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    $_SESSION['error'] = 'Server error preparing download.';
    header('Location: ../dashboard.php');
    exit;
}

if (empty($rows)) {
    $_SESSION['error'] = 'No matching files found (they may have been removed).';
    header('Location: ../dashboard.php');
    exit;
}

// ensure zip extension
if (!class_exists('ZipArchive')) {
    $_SESSION['error'] = 'Server does not support zip creation.';
    header('Location: ../dashboard.php');
    exit;
}

// create a temp zip
$tmpFile = tempnam(sys_get_temp_dir(), 'zip_');
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmpFile);
    $_SESSION['error'] = 'Could not create zip.';
    header('Location: ../dashboard.php');
    exit;
}

foreach ($rows as $r) {
    $path = $r['path'];
    $display = $r['filename'];
    if (is_file($path) && is_readable($path)) {
        // if duplicate filename, ZipArchive will handle by overwriting - we add a prefix for uniqueness
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

if (filesize($tmpFile) === 0) {
    @unlink($tmpFile);
    $_SESSION['error'] = 'Zip creation failed (empty zip).';
    header('Location: ../dashboard.php');
    exit;
}

// stream zip
if (ob_get_level()) ob_end_clean();

$downloadName = 'files_' . date('Ymd_His') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Pragma: public');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

readfile($tmpFile);
@unlink($tmpFile);
exit;
