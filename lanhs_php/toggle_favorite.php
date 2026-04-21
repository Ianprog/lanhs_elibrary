<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
// toggle_favorite.php — AJAX endpoint
header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'POST only']); exit; }

$bookId = (int)($_POST['book_id'] ?? 0);
$userId = currentUser()['id'];

$exists = DB::val("SELECT COUNT(*) FROM favorites WHERE user_id=? AND book_id=?", [$userId,$bookId]);
if ($exists) {
    DB::query("DELETE FROM favorites WHERE user_id=? AND book_id=?", [$userId,$bookId]);
    echo json_encode(['action'=>'removed']);
} else {
    DB::query("INSERT INTO favorites (user_id, book_id) VALUES (?,?)", [$userId,$bookId]);
    echo json_encode(['action'=>'added']);
}
