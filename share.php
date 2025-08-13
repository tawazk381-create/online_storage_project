<?php
// File: share.php
// Public download via token
if (isset($_GET['token'])) {
  require 'includes/config.php';
  $stmt = $pdo->prepare("
    SELECT f.filename,f.path 
    FROM shares s 
    JOIN files f ON s.file_id=f.id 
    WHERE s.token=?
  ");
  $stmt->execute([$_GET['token']]);
  $file = $stmt->fetch();
  if ($file) {
    header('Content-Disposition: attachment; filename="'.basename($file['filename']).'"');
    readfile($file['path']);
    exit;
  }
}
http_response_code(404);
echo 'File not found.';
?>
