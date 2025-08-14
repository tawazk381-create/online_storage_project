<?php
// File: includes/upload.php
// Improved upload handler with extra checks and detailed logging for DB insert failures.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// Ensure session available
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function friendlyUploadError($code) {
    switch ($code) {
        case UPLOAD_ERR_OK: return 'No error.';
        case UPLOAD_ERR_INI_SIZE: return 'The uploaded file exceeds upload_max_filesize in php.ini.';
        case UPLOAD_ERR_FORM_SIZE: return 'The uploaded file exceeds the form MAX_FILE_SIZE directive.';
        case UPLOAD_ERR_PARTIAL: return 'The uploaded file was only partially uploaded.';
        case UPLOAD_ERR_NO_FILE: return 'No file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR: return 'Missing a temporary folder on server.';
        case UPLOAD_ERR_CANT_WRITE: return 'Failed to write file to disk.';
        case UPLOAD_ERR_EXTENSION: return 'A PHP extension stopped the file upload.';
        default: return 'Unknown upload error code: ' . $code;
    }
}

function bytesFromIniSize(string $val): int {
    $val = trim($val);
    if ($val === '') return 0;
    $last = strtolower($val[strlen($val)-1]);
    $num = (int)$val;
    switch ($last) {
        case 'g': return $num * 1024 * 1024 * 1024;
        case 'm': return $num * 1024 * 1024;
        case 'k': return $num * 1024;
        default: return (int)$val;
    }
}

$logFile = STORAGE_PATH . '/upload_errors.log';
$dbLogFile = STORAGE_PATH . '/db_errors.log';

// Helper to append log lines (best-effort)
function appendLog($path, $line) {
    @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Basic guard: must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

// Logged-in requirement
if (empty($_SESSION['user_id'])) {
    $_SESSION['error'] = 'You must be logged in to upload.';
    header('Location: ../login.php');
    exit;
}

// check that PDO is available
if (!isset($pdo) || $pdo === null) {
    $msg = 'Database connection not available. See ' . $dbLogFile;
    appendLog($logFile, date('c') . " - ERROR - upload attempted but \$pdo is null - user:{$_SESSION['user_id']}");
    appendLog($dbLogFile, date('c') . " - ERROR - upload attempted but \$pdo is null - user:{$_SESSION['user_id']}");
    // In dev, show DB error message if present
    if (!empty($GLOBALS['DB_ERROR_MESSAGE']) && strtolower(APP_ENV) !== 'production') {
        $_SESSION['error'] = 'DB error: ' . $GLOBALS['DB_ERROR_MESSAGE'];
    } else {
        $_SESSION['error'] = $msg;
    }
    header('Location: ../dashboard.php');
    exit;
}

// If no file input
if (!isset($_FILES['file'])) {
    $_SESSION['error'] = 'No file data was sent.';
    appendLog($logFile, date('c') . " - ERROR - no \$_FILES['file'] present - user:{$_SESSION['user_id']}");
    header('Location: ../dashboard.php');
    exit;
}

$file = $_FILES['file'];

// Validate $_FILES structure
if (!isset($file['error']) || is_array($file['error'])) {
    $msg = 'Invalid upload parameters.';
    $_SESSION['error'] = $msg;
    appendLog($logFile, date('c') . " - ERROR - invalid upload params - user:{$_SESSION['user_id']} - " . print_r($file, true));
    header('Location: ../dashboard.php');
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $friendly = friendlyUploadError($file['error']);
    $_SESSION['error'] = 'Upload failed: ' . $friendly;
    $debug = [
        'time' => date('c'),
        'user_id' => $_SESSION['user_id'],
        'orig_name' => $file['name'] ?? '',
        'tmp_name' => $file['tmp_name'] ?? '',
        'error' => $file['error'],
        'php_upload_max' => ini_get('upload_max_filesize'),
        'php_post_max' => ini_get('post_max_size'),
    ];
    appendLog($logFile, json_encode($debug));
    header('Location: ../dashboard.php');
    exit;
}

// Size checks
$uploadMax = bytesFromIniSize(ini_get('upload_max_filesize'));
$postMax   = bytesFromIniSize(ini_get('post_max_size'));
$fileSize  = isset($file['size']) ? (int)$file['size'] : 0;

if ($fileSize <= 0) {
    $_SESSION['error'] = 'Uploaded file appears empty.';
    appendLog($logFile, date('c') . " - ERROR - zero-size file - user:{$_SESSION['user_id']} - " . print_r($file, true));
    header('Location: ../dashboard.php');
    exit;
}

if ($fileSize > $uploadMax || $fileSize > $postMax) {
    $_SESSION['error'] = 'File too large. Server limit: upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size');
    appendLog($logFile, date('c') . " - ERROR - file too large - user:{$_SESSION['user_id']} - size:$fileSize - upload_max:$uploadMax - post_max:$postMax");
    header('Location: ../dashboard.php');
    exit;
}

// Ensure STORAGE_PATH exists and is writable
if (!is_dir(STORAGE_PATH)) {
    if (!@mkdir(STORAGE_PATH, 0755, true) && !is_dir(STORAGE_PATH)) {
        $_SESSION['error'] = 'Server storage folder missing and could not be created.';
        appendLog($logFile, date('c') . " - ERROR - cannot create STORAGE_PATH='" . STORAGE_PATH . "' - user:{$_SESSION['user_id']}");
        header('Location: ../dashboard.php');
        exit;
    }
}

if (!is_writable(STORAGE_PATH)) {
    $_SESSION['error'] = 'Server storage folder is not writable by PHP.';
    appendLog($logFile, date('c') . " - ERROR - STORAGE_PATH not writable: " . STORAGE_PATH . " - user:{$_SESSION['user_id']}");
    header('Location: ../dashboard.php');
    exit;
}

// Prepare per-user folder
$userDir = STORAGE_PATH . '/' . $_SESSION['user_id'];
if (!is_dir($userDir)) {
    if (!@mkdir($userDir, 0755, true) && !is_dir($userDir)) {
        $_SESSION['error'] = 'Unable to create user storage directory.';
        appendLog($logFile, date('c') . " - ERROR - could not create userDir: $userDir - user:{$_SESSION['user_id']}");
        header('Location: ../dashboard.php');
        exit;
    }
}

// sanitize filename for disk but keep original name for DB display
$originalName = basename($file['name']);
$safeFilename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $originalName);
$ext = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));

// categories
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
if (!is_dir($typeDir)) {
    if (!@mkdir($typeDir, 0755, true) && !is_dir($typeDir)) {
        $_SESSION['error'] = 'Unable to create subfolder for your files.';
        appendLog($logFile, date('c') . " - ERROR - could not create typeDir: $typeDir - user:{$_SESSION['user_id']}");
        header('Location: ../dashboard.php');
        exit;
    }
}

$target = $typeDir . '/' . $safeFilename;

// Avoid overwrite
if (file_exists($target)) {
    $base = pathinfo($safeFilename, PATHINFO_FILENAME);
    $target = $typeDir . '/' . $base . '_' . time() . ( $ext ? '.' . $ext : '' );
}

// Attempt move
$moved = @move_uploaded_file($file['tmp_name'], $target);
if (!$moved) {
    $err = error_get_last();
    $_SESSION['error'] = 'Server failed to store uploaded file.';
    $log = [
        'time' => date('c'),
        'user_id' => $_SESSION['user_id'],
        'orig_name' => $originalName,
        'tmp_name' => $file['tmp_name'],
        'target' => $target,
        'error_get_last' => $err,
        'php_upload_max' => ini_get('upload_max_filesize'),
        'php_post_max' => ini_get('post_max_size'),
        'free_disk_space' => @disk_free_space(STORAGE_PATH),
    ];
    appendLog($logFile, json_encode($log));
    header('Location: ../dashboard.php');
    exit;
}

// Before DB insert: check that files table exists and has expected columns
try {
    $cols = [];
    $descStmt = $pdo->query("DESCRIBE files");
    $desc = $descStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($desc as $col) {
        $cols[] = $col['Field'];
    }
    $required = ['user_id', 'filename', 'path', 'file_type', 'size', 'uploaded_at'];
    $missing = array_diff($required, $cols);
    if (!empty($missing)) {
        // rollback file
        @unlink($target);
        $msg = 'Missing expected column(s) in files table: ' . implode(', ', $missing);
        appendLog($dbLogFile, date('c') . " - ERROR - " . $msg . " - user:{$_SESSION['user_id']}");
        $_SESSION['error'] = 'Server misconfiguration: files table schema mismatch. Contact admin.';
        if (strtolower(APP_ENV) !== 'production') {
            $_SESSION['error'] .= ' (' . $msg . ')';
        }
        header('Location: ../dashboard.php');
        exit;
    }
} catch (Throwable $e) {
    // likely the table does not exist or DB error
    @unlink($target);
    $errMsg = 'DB error while checking files table: ' . $e->getMessage();
    appendLog($dbLogFile, date('c') . " - ERROR - " . $errMsg . " - user:{$_SESSION['user_id']} - trace:" . $e->getTraceAsString());
    $_SESSION['error'] = 'Server misconfiguration: files table missing or inaccessible.';
    if (strtolower(APP_ENV) !== 'production') {
        $_SESSION['error'] .= ' ('.$e->getMessage().')';
    }
    header('Location: ../dashboard.php');
    exit;
}

// All good — insert into DB
try {
    $stmt = $pdo->prepare("
        INSERT INTO files (user_id, filename, path, file_type, size, uploaded_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $size = filesize($target);
    $stmt->execute([$_SESSION['user_id'], $originalName, $target, $folder, $size]);
} catch (Throwable $e) {
    // rollback file if DB insert fails
    @unlink($target);

    $errDetails = [
        'time' => date('c'),
        'user_id' => $_SESSION['user_id'],
        'orig_name' => $originalName,
        'target' => $target,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
    appendLog($logFile, json_encode($errDetails));
    appendLog($dbLogFile, json_encode($errDetails));

    // Friendly message to user
    $_SESSION['error'] = 'Server error saving file metadata.';
    if (strtolower(APP_ENV) !== 'production') {
        // reveal DB error in development to speed debugging
        $_SESSION['error'] .= ' DB: ' . $e->getMessage();
    }

    header('Location: ../dashboard.php');
    exit;
}

// Success
$_SESSION['success'] = 'File uploaded successfully.';
header('Location: ../dashboard.php');
exit;
