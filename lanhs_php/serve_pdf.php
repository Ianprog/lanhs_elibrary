<?php
// serve_pdf.php — streams PDF from database
// No X-Frame-Options, no CSP, SameSite=Lax session
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die('Invalid book ID.');
}

$book = DB::row(
    "SELECT id, title, has_pdf, pdf_name, pdf_size, pdf_data FROM books WHERE id = ?",
    [$id]
);

if (!$book) {
    http_response_code(404);
    die('<html><body style="font-family:sans-serif;text-align:center;padding:60px;">
    <h2 style="color:#d62828;">Book Not Found</h2>
    <p>This book does not exist.</p>
    <a href="home.php">Back to Library</a>
    </body></html>');
}

if (empty($book['has_pdf']) || empty($book['pdf_data'])) {
    http_response_code(404);
    die('<html><body style="font-family:sans-serif;text-align:center;padding:60px;">
    <h2 style="color:#d62828;">No PDF Uploaded</h2>
    <p>This book does not have a PDF file yet.</p>
    <p>An admin or teacher can upload it via <strong>Manage Books</strong>.</p>
    <a href="home.php">Back to Library</a>
    </body></html>');
}

// Log (non-fatal)
try {
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    logActivity($uid, 'read_book', (string)$book['title']);
} catch (Exception $e) {}

$filename = !empty($book['pdf_name']) ? $book['pdf_name'] : 'book.pdf';
$pdfData  = $book['pdf_data'];
$size     = strlen($pdfData);

// Clear any output buffering
while (ob_get_level()) ob_end_clean();

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($filename) . '"');
header('Content-Length: ' . $size);
header('Cache-Control: private, max-age=0');
header('Accept-Ranges: bytes');
// Allow embedding in frames from same origin
header('X-Frame-Options: SAMEORIGIN');

echo $pdfData;
exit;
