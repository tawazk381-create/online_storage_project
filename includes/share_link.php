<?php
// File: includes/share_link.php
require_once __DIR__ . '/config.php';
requireAuth();

if (isset($_GET['id'])) {
  $id    = (int)$_GET['id'];
  $token = bin2hex(random_bytes(16));
  $stmt  = $pdo->prepare("INSERT INTO shares (file_id, token, created_at) VALUES (?, ?, NOW())");
  $stmt->execute([$id, $token]);
  header('Content-Type: application/json');
  echo json_encode([
    'url' => "https://{$_SERVER['HTTP_HOST']}/share.php?token=$token"
  ]);
}
?>
