<?php
// File: includes/login.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    if (!$email || !$password) {
        $_SESSION['error'] = 'Please enter both email and password.';
        header('Location: ../login.php');
        exit;
    }

    // Look up user by email (also select full_name)
    $stmt = $pdo->prepare("SELECT id, password_hash, full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // No account with that email
        $_SESSION['error'] = 'No account found for that email. Please sign up first.';
        header('Location: ../login.php');
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        $_SESSION['error'] = 'Incorrect password. Please try again.';
        header('Location: ../login.php');
        exit;
    }

    // Success! Store useful session values
    $_SESSION['user_id'] = $user['id'];
    // store full name for display on dashboard
    $_SESSION['user_full_name'] = $user['full_name'];
    // optional: store email too (used elsewhere)
    $_SESSION['user_email'] = $email;

    header('Location: ../dashboard.php');
    exit;
}
