<?php
// File: share.php
require_once __DIR__ . '/includes/config.php';

$token = $_GET['token'] ?? '';
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (!$token) {
    http_response_code(400);
    echo '<!doctype html><html><body><h2>Invalid link</h2><p>Missing token.</p></body></html>';
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT f.id AS file_id, f.filename, f.path, f.file_type, f.size, f.uploaded_at, s.expires_at
        FROM shares s
        JOIN files f ON s.file_id = f.id
        WHERE s.token = ? AND (s.expires_at IS NULL OR s.expires_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $file = $stmt->fetch();
} catch (Throwable $e) {
    error_log('share.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo '<!doctype html><html><body><h2>Server error</h2><p>Try again later.</p></body></html>';
    exit;
}

if (!$file) {
    http_response_code(404);
    echo '<!doctype html><html><body><h2>Not found</h2><p>Link invalid or expired.</p></body></html>';
    exit;
}

$ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
$imgExts = ['jpg','jpeg','png','gif','webp','svg','bmp'];
$canPreview = in_array($ext, $imgExts) && is_file($file['path']);
$previewHtml = $canPreview ? '<div class="mb-3 text-center"><img src="public_download.php?token=' . rawurlencode($token) . '&preview=1" alt="' . e($file['filename']) . '" style="max-height:360px;max-width:100%;border-radius:6px;"></div>' : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Shared - <?= e($file['filename']) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#f5f6f7}</style>
</head>
<body class="d-flex align-items-center justify-content-center vh-100">
  <div class="container" style="max-width:760px;">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title"><?= e($file['filename']) ?></h5>
        <p class="text-muted small">
          Type: <?= e(ucfirst($file['file_type'])) ?> &middot;
          Size: <?= isset($file['size']) ? number_format((int)$file['size']/1024, 2) . ' KB' : '—' ?> &middot;
          Uploaded: <?= e(date('M j, Y', strtotime($file['uploaded_at']))) ?>
        </p>

        <?php if (!empty($file['expires_at'])): ?>
          <p class="text-muted small">Expires: <?= e($file['expires_at']) ?></p>
        <?php endif; ?>

        <?= $previewHtml ?>

        <div class="d-flex gap-2">
          <a class="btn btn-primary" href="public_download.php?token=<?= rawurlencode($token) ?>">Download</a>
          <button class="btn btn-outline-secondary" id="copyBtn">Copy Link</button>
        </div>

        <p class="mt-3 small text-muted">Anyone with this link can download the file. To stop sharing, remove the share token or wait for expiry.</p>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('copyBtn').addEventListener('click', function () {
      const url = location.href;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
          alert('Link copied to clipboard');
        }, function() {
          prompt('Copy this link:', url);
        });
      } else {
        prompt('Copy this link:', url);
      }
    });
  </script>
</body>
</html>
