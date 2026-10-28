<?php
// includes/auth.php

// Configure secure sessions before starting
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        $currentRole = $_SESSION['role'];
        
        // Handle absolute vs relative redirect safely for XAMPP
        // We'll rely on relative paths since requireRole is called from inside subdirectories
        header("Location: ../$currentRole/dashboard.php");
        exit;
    }
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function isStaff() {
    return isLoggedIn() && $_SESSION['role'] === 'staff';
}

function isUser() {
    return isLoggedIn() && $_SESSION['role'] === 'user';
}

function currentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}
?>
