<?php
// File: dashboard.php
require_once __DIR__ . '/includes/config.php';
requireAuth();

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

// Determine display name: prefer session-stored full name, otherwise fetch from DB
$displayName = '';
if (!empty($_SESSION['user_full_name'])) {
    $displayName = sanitize($_SESSION['user_full_name']);
} elseif (!empty($_SESSION['user_id'])) {
    // fallback DB query (in case session value not set)
    $stmtName = $pdo->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $stmtName->execute([$_SESSION['user_id']]);
    $rowName = $stmtName->fetch();
    $displayName = $rowName && !empty($rowName['full_name']) ? sanitize($rowName['full_name']) : 'User';
} else {
    $displayName = 'User';
}

// Search handling
$q = trim($_GET['q'] ?? '');
$searchResults = [];
$searchCount = 0;
if ($q !== '') {
    // Limit results to avoid huge payloads; adjust as needed
    $limit = 500;
    $like = '%' . $q . '%';

    // NOTE: LIMIT cannot reliably be bound as a parameter in many MySQL/MariaDB drivers,
    // so we inject an integer-casted value into the SQL string (safe because of intval()).
    $sql = "
        SELECT id, filename, file_type, uploaded_at
        FROM files
        WHERE user_id = ? AND filename LIKE ?
        ORDER BY uploaded_at DESC
        LIMIT " . intval($limit);

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id'], $like]);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $searchCount = count($searchResults);
}

// If no search, get folder types and counts for the logged-in user
$fileTypes = [];
if ($q === '') {
    $stmt = $pdo->prepare("SELECT file_type, COUNT(*) AS cnt FROM files WHERE user_id = ? GROUP BY file_type ORDER BY file_type");
    $stmt->execute([$_SESSION['user_id']]);
    $fileTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Optional message from actions (delete, etc.)
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard – Shona Cloud</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .folder-card {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: background 0.2s ease;
            min-height: 170px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .folder-card:hover { background: #e9ecef; }
        .folder-icon { font-size: 44px; color: #ffc107; }
        .folder-meta { margin-top: 8px; color: #6c757d; font-size: 0.9rem; }
        .file-card .card-subtitle { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        @media (max-width: 576px) { .folder-card { min-height: 160px; } }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Shona Cloud</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto">
                <!-- left side if needed -->
            </ul>

            <!-- Search form in navbar -->
            <form class="d-flex me-3" method="get" action="dashboard.php" role="search" onsubmit="return true;">
                <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Search files..." aria-label="Search files" value="<?= sanitize($q) ?>">
                <?php if ($q !== ''): ?>
                    <a href="dashboard.php" class="btn btn-sm btn-outline-light me-2">Clear</a>
                <?php endif; ?>
                <button class="btn btn-sm btn-light" type="submit">Search</button>
            </form>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <?php if ($success): ?>
        <div class="alert alert-success"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?= sanitize($msg) ?></div>
    <?php endif; ?>

    <div class="mb-4 d-flex flex-column flex-md-row align-items-start gap-3">
        <div>
            <h2 class="text-secondary mb-1">Welcome, <?= $displayName ?>!</h2>
            <p class="text-muted mb-0">Your file library is organised into folders by type.</p>
        </div>

        <div class="ms-auto d-flex gap-2">
            <a href="profile.php" class="btn btn-outline-secondary btn-sm align-self-start">Edit Profile</a>
            <a href="delete_account.php" class="btn btn-outline-danger btn-sm align-self-start"
               onclick="return confirm('Are you sure you want to delete your account? All your files will be lost.');">Unsubscribe</a>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Upload a File</h5>
            <form action="includes/upload.php" method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                <input class="form-control" type="file" name="file" required>
                <button class="btn btn-success" type="submit">Upload</button>
            </form>
        </div>
    </div>

    <?php if ($q !== ''): ?>
        <div class="mb-3">
            <h4>Search results for "<?= sanitize($q) ?>" <small class="text-muted">(<?= $searchCount ?> found)</small></h4>
            <p class="text-muted mb-0">Showing up to <?= intval($limit) ?> most recent matches. Try a more specific phrase if you get too many results.</p>
        </div>

        <?php if (empty($searchResults)): ?>
            <div class="alert alert-info">No files matched your search.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($searchResults as $f): ?>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card h-100 shadow-sm file-card">
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-subtitle mb-2 text-truncate"><?= sanitize($f['filename']) ?></h6>
                                <p class="card-text text-muted small mb-3">
                                    Type: <?= sanitize($f['file_type']) ?><br>
                                    Uploaded: <?= date('M j, Y', strtotime($f['uploaded_at'])) ?>
                                </p>
                                <div class="mt-auto d-grid gap-2">
                                    <a href="includes/download.php?id=<?= (int)$f['id'] ?>" class="btn btn-outline-primary btn-sm">Download</a>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="share(<?= (int)$f['id'] ?>)">Share</button>
                                    <a href="includes/delete.php?id=<?= (int)$f['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete <?= sanitize($f['filename']) ?>?');">Delete</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Folder overview when no search -->
        <div class="row g-4">
            <?php if (empty($fileTypes)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">No files yet. Upload something to get started!</div>
                </div>
            <?php else: ?>
                <?php foreach ($fileTypes as $row):
                    $type = $row['file_type'];
                    $count = (int)$row['cnt'];
                    $typeEsc = urlencode($type);
                ?>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="folder-card shadow-sm d-flex flex-column">
                            <div>
                                <div class="folder-icon">📁</div>
                                <h5 class="mt-2 mb-0 text-truncate"><?= ucfirst(sanitize($type)) ?></h5>
                                <div class="folder-meta"><?= $count ?> file<?= $count !== 1 ? 's' : '' ?></div>
                            </div>

                            <div class="mt-3">
                                <div class="d-grid gap-2">
                                    <a href="view_folder.php?type=<?= $typeEsc ?>" class="btn btn-primary btn-sm">Open</a>
                                    <a href="includes/download_folder.php?type=<?= $typeEsc ?>" class="btn btn-outline-primary btn-sm" <?= $count === 0 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Download</a>

                                    <form action="includes/delete_folder.php" method="post" onsubmit="return confirmDeleteFolder('<?= sanitize($type) ?>');" class="m-0">
                                        <input type="hidden" name="type" value="<?= sanitize($type) ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" <?= $count === 0 ? 'disabled' : '' ?>>Delete Folder</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
function confirmDeleteFolder(folder) {
    return confirm('Delete ALL files in folder "' + folder + '"? This action cannot be undone.');
}
</script>
</body>
</html>
