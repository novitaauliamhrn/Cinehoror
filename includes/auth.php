<?php
// ============================================================
// INCLUDES/AUTH.PHP — Fungsi Login, Logout, Guard
// ============================================================

function requireLogin() {
    if (empty($_SESSION['user'])) {
        header('Location: ../index.php');
        exit;
    }
}

function requireAdmin() {
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        header('Location: ../index.php');
        exit;
    }
}

function authLogin($username, $password) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        $_SESSION['user'] = $user;
        return true;
    }
    return false;
}

function authLogout() {
    session_destroy();
    header('Location: index.php');
    exit;
}

function isLoggedIn()  { return !empty($_SESSION['user']); }
function isAdmin()     { return !empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'; }
function currentUser() { return $_SESSION['user'] ?? null; }
?>