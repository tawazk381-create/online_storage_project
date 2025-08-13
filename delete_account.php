<?php
//File: delete_account.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

// Double safeguard
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

requireAuth();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Delete Account</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
  <h2 class="text-danger">Are you sure you want to delete your account?</h2>
  <p>This action is irreversible. All your files will be permanently deleted.</p>

  <form action="includes/delete_user.php" method="POST">
    <button type="submit" class="btn btn-danger">Yes, Delete My Account</button>
    <a href="profile.php" class="btn btn-secondary">Cancel</a>
  </form>
</body>
</html>

