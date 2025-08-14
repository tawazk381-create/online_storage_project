<?php
// File: view_folder.php
require_once __DIR__ . '/includes/config.php';
requireAuth();

$type = $_GET['type'] ?? '';
if (!$type) {
    header('Location: dashboard.php');
    exit;
}

// Get files of this type for the logged-in user
$stmt = $pdo->prepare("SELECT * FROM files WHERE user_id = ? AND file_type = ? ORDER BY uploaded_at DESC");
$stmt->execute([$_SESSION['user_id'], $type]);
$files = $stmt->fetchAll();

// Count for display
$count = count($files);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= ucfirst(sanitize($type)) ?> – Shona Cloud</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .card-body .btn { min-width: 100%; }
        .file-card { min-height: 160px; display:flex; flex-direction:column; justify-content:space-between; }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Shona Cloud</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <div class="d-flex align-items-center mb-3">
        <h2 class="me-3 mb-0"><?= ucfirst(sanitize($type)) ?> (<?= $count ?>)</h2>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">⬅ Back to Folders</a>
    </div>

    <div class="row g-4">
        <?php if (empty($files)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">No files in this folder yet.</div>
            </div>
        <?php else: ?>
            <?php foreach ($files as $f): ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm file-card">
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-subtitle mb-2 text-truncate"><?= sanitize($f['filename']) ?></h6>
                            <p class="card-text text-muted small mb-3">
                                Uploaded: <?= date('M j, Y', strtotime($f['uploaded_at'])) ?>
                                <br>
                                Size: <?= isset($f['size']) ? number_format((int)$f['size'] / 1024, 2) . ' KB' : '—' ?>
                            </p>

                            <div class="mt-auto d-grid gap-2">
                                <!-- Per-file Download -->
                                <a href="includes/download.php?id=<?= (int)$f['id'] ?>"
                                   class="btn btn-outline-primary btn-sm">Download</a>

                                <!-- Per-file Share -->
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        onclick="share(<?= (int)$f['id'] ?>)">Share</button>

                                <!-- Per-file Delete -->
                                <a href="includes/delete.php?id=<?= (int)$f['id'] ?>"
                                   class="btn btn-outline-danger btn-sm"
                                   onclick="return confirm('Delete <?= sanitize($f['filename']) ?>?');">Delete</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- bootstrap js (optional for other UI) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- include the real share() implementation -->
<script src="assets/js/app.js"></script>
</body>
</html>
