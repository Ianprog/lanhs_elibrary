<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
// serve_cover.php — streams book cover image from DB
requireLogin();

$id   = (int)($_GET['id'] ?? 0);
$book = DB::row("SELECT has_cover, cover_mime, cover_data FROM books WHERE id = ?", [$id]);

if (!$book || !$book['has_cover'] || empty($book['cover_data'])) {
    // Return a plain SVG placeholder
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=86400');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="280" viewBox="0 0 200 280">
        <rect width="200" height="280" fill="#f0f4f8"/>
        <rect x="20" y="20" width="160" height="240" rx="6" fill="#dbeafe"/>
        <rect x="40" y="60" width="120" height="8" rx="4" fill="#93c5fd"/>
        <rect x="40" y="80" width="90"  height="6" rx="3" fill="#bfdbfe"/>
        <rect x="40" y="96" width="110" height="6" rx="3" fill="#bfdbfe"/>
        <rect x="40" y="140" width="120" height="70" rx="6" fill="#dbeafe"/>
        <text x="100" y="235" text-anchor="middle" font-family="sans-serif" font-size="11" fill="#6b7280">No Cover</text>
    </svg>';
    exit;
}

header('Content-Type: '  . ($book['cover_mime'] ?: 'image/jpeg'));
header('Content-Length: ' . strlen($book['cover_data']));
header('Cache-Control: private, max-age=3600');
if (ob_get_level()) ob_end_clean();
echo $book['cover_data'];
exit;
