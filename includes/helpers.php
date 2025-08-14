<?php
// File: includes/helpers.php

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
