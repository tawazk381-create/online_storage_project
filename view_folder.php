<?php
// view_folder.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
requireAuth();

$type = $_GET['type'] ?? '';
if ($type === '') {
    header('Location: dashboard.php');
    exit;
}

// fetch files for this user and type
$stmt = $pdo->prepare("SELECT id, filename, size, uploaded_at FROM files WHERE user_id = ? AND file_type = ? ORDER BY uploaded_at DESC");
$stmt->execute([$_SESSION['user_id'], $type]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Folder: <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">Shona Cloud</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container my-4">
  <?php if ($success): ?><div class="alert alert-success"><?= sanitize($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

  <h4>Folder: <?= sanitize($type) ?></h4>

  <form id="batchForm" method="post">
    <div class="mb-3">
      <button type="button" id="downloadBtn" class="btn btn-primary btn-sm">Download Selected</button>
      <button type="button" id="shareBtn" class="btn btn-outline-secondary btn-sm">Share Selected</button>
      <button type="button" id="deleteBtn" class="btn btn-danger btn-sm">Delete Selected</button>
      <a href="dashboard.php" class="btn btn-secondary btn-sm">Back</a>
    </div>

    <table class="table table-sm table-hover">
      <thead>
        <tr>
          <th style="width:38px"><input type="checkbox" id="selectAll"></th>
          <th>Filename</th>
          <th style="width:140px">Size</th>
          <th style="width:160px">Uploaded</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($files)): ?>
          <tr><td colspan="4" class="text-center">No files in this folder.</td></tr>
        <?php else: ?>
          <?php foreach ($files as $f): ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= (int)$f['id'] ?>" class="file-checkbox"></td>
              <td><?= sanitize($f['filename']) ?></td>
              <td><?= isset($f['size']) ? number_format((int)$f['size']/1024, 2) . ' KB' : '—' ?></td>
              <td><?= sanitize(date('M j, Y H:i', strtotime($f['uploaded_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </form>
</div>

<script>
function serializeSelected() {
  const checked = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(i => i.value);
  return checked;
}

document.getElementById('selectAll').addEventListener('change', function(){
  const v = this.checked;
  document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = v);
});

document.getElementById('downloadBtn').addEventListener('click', function(){
  const ids = serializeSelected();
  if (!ids.length) { alert('Select files first'); return; }
  // create a form and submit to includes/download_multiple.php
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'includes/download_multiple.php';
  ids.forEach(id => {
    const i = document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value = id; form.appendChild(i);
  });
  document.body.appendChild(form);
  form.submit();
});

document.getElementById('deleteBtn').addEventListener('click', function(){
  const ids = serializeSelected();
  if (!ids.length) { alert('Select files first'); return; }
  if (!confirm('Delete selected files? This cannot be undone.')) return;
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'includes/delete_multiple.php';
  ids.forEach(id => {
    const i = document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value = id; form.appendChild(i);
  });
  document.body.appendChild(form);
  form.submit();
});

document.getElementById('shareBtn').addEventListener('click', function(){
  const ids = serializeSelected();
  if (!ids.length) { alert('Select files first'); return; }
  if (!confirm('Create a share link for the selected files?')) return;
  const data = new FormData();
  ids.forEach(id => data.append('ids[]', id));
  fetch('includes/share_multiple.php', { method: 'POST', body: data, credentials: 'same-origin' })
    .then(r => r.json())
    .then(json => {
      if (json.error) { alert('Error: ' + json.error); return; }
      const url = json.url;
      // try write to clipboard then show
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => alert('Share link copied to clipboard: ' + url)).catch(()=>prompt('Share link (copy):', url));
      } else {
        prompt('Share link (copy):', url);
      }
    })
    .catch(err => {
      console.error(err);
      alert('Server error creating share link.');
    });
});
</script>
</body>
</html>
