<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
// logout.php
startSession();
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout', 'Signed out');
    sessionClear();
}
header('Location: login.php');
exit;
