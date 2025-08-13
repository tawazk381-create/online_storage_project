<?php
// File: includes/update_profile.php
require_once __DIR__ . '/config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name']);
    $phone    = trim($_POST['phone']);
    $country  = trim($_POST['country']);
    $password = $_POST['password'];

    // Validate required fields
    if (!$fullName || !$phone || !$country) {
        $_SESSION['error'] = 'Please fill all required fields.';
        header('Location: ../profile.php');
        exit;
    }

    // Validate password length if provided
    if ($password && strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters.';
        header('Location: ../profile.php');
        exit;
    }

    // Perform update
    if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users
            SET full_name = ?, phone = ?, country = ?, password_hash = ?
            WHERE id = ?
        ");
        $stmt->execute([$fullName, $phone, $country, $hash, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE users
            SET full_name = ?, phone = ?, country = ?
            WHERE id = ?
        ");
        $stmt->execute([$fullName, $phone, $country, $_SESSION['user_id']]);
    }

    // Redirect back to dashboard with success message
    $_SESSION['success'] = 'Profile updated successfully.';
    header('Location: ../dashboard.php');
    exit;
}
?>
