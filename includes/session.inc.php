<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// وظائف إدارة رسائل Flash
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// وظائف التحقق من تسجيل الدخول
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function isCustomer() {
    return getUserRole() === 'customer';
}

function isOwner() {
    return getUserRole() === 'owner';
}

function isManager() {
    return getUserRole() === 'manager';
}

// وظائف التحقق من الصلاحيات
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /pages/login.php');
        exit;
    }
}

// function requireRole($role) {
//     requireLogin();
//     if (getUserRole() !== $role) {
//         header('Location: /pages/unauthorized.php');
//         exit;
//     }
// }

function getUserData() {
    return $_SESSION['user_data'] ?? null;
}

function setUserData($data) {
    $_SESSION['user_data'] = $data;
}

function clearSession() {
    session_unset();
    session_destroy();
} 