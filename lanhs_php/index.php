<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
// index.php — entry point
startSession();

if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['user_role'] === 'admin' ? 'dashboard.php' : 'home.php'));
} else {
    header('Location: login.php');
}
exit;
