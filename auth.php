<?php
// PENTING: Jangan tambahkan session_start() lagi di file lain yang meng-include auth.php
// karena akan menyebabkan pesan error "session_start(): Ignoring session_start() because a session is already active"
session_start();

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

function checkAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: index.php");
        exit();
    }
}

function checkStaff() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
        header("Location: index.php");
        exit();
    }
}

function checkOwner() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
        header("Location: index.php");
        exit();
    }
}

function checkKeuangan() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'keuangan') {
        header("Location: index.php");
        exit();
    }
}

function checkRole($allowed_roles) {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: index.php");
        exit();
    }
}
?> 