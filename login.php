<?php
//File: login.php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log In – Shona Cloud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex vh-100 align-items-center justify-content-center bg-light">

    <div class="card p-4 shadow-sm w-100" style="max-width: 400px;">
        <h3 class="mb-3 text-center">Log In</h3>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <form action="includes/login.php" method="post">
            <div class="mb-3">
                <label class="form-label">Email address</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Log In</button>
        </form>

        <p class="mt-3 text-center">
            Don’t have an account? <a href="signup.php">Sign up</a>
        </p>
    </div>

</body>
</html>
