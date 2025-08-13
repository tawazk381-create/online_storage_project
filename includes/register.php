<?php
// File: includes/register.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // collect & validate
    $fullName = trim($_POST['full_name']);
    $email    = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone    = trim($_POST['phone']);
    $country  = trim($_POST['country']);
    $password = $_POST['password'];

    if (!$fullName || !$email || !$phone || !$country || strlen($password) < 6) {
        $_SESSION['error'] = 'Please fill in all fields (password ≥ 6 chars).';
        header('Location: ../signup.php');
        exit;
    }

    // check for existing email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Email already registered.';
        header('Location: ../signup.php');
        exit;
    }

    // hash & insert
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
      INSERT INTO users (full_name, email, phone, country, password_hash)
      VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$fullName, $email, $phone, $country, $hash]);

    // redirect to login
    header('Location: ../login.php');
    exit;
}
?>
