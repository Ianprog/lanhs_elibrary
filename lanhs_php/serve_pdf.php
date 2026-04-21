<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
// ============================================================
//  serve_pdf.php — Stream PDF BLOB from database to browser
// ============================================================
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('Invalid book ID.'); }

// Fetch only PDF columns — never pull cover_data here
$book = DB::row(
    "SELECT id, title, has_pdf, pdf_name, pdf_size, pdf_data FROM books WHERE id = ?",
    [$id]
);

if (!$book) {
    http_response_code(404);
    showError('Book Not Found', 'This book does not exist in the database.');
}
if (!$book['has_pdf'] || empty($book['pdf_data'])) {
    http_response_code(404);
    showError('No PDF Available',
        'This book does not have a PDF uploaded yet.<br>
         An admin or teacher can add one via <strong>Manage Books</strong>.');
}

// Log read activity (non-fatal)
try { logActivity($_SESSION['user_id'], 'read_book', $book['title']); }
catch (Exception $e) { /* ignore */ }

$filename = $book['pdf_name'] ?: 'book.pdf';
$size     = strlen($book['pdf_data']);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . $size);
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
header('X-Frame-Options: SAMEORIGIN');

if (ob_get_level()) ob_end_clean();
echo $book['pdf_data'];
exit;

function showError(string $title, string $msg): void {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
      body{font-family:Sora,sans-serif;background:#f8f9fa;display:flex;align-items:center;
           justify-content:center;min-height:100vh;margin:0;}
      .box{background:#fff;border-radius:14px;padding:40px 48px;text-align:center;
           max-width:400px;box-shadow:0 4px 24px rgba(0,0,0,.1);}
      h2{color:#d62828;font-size:18px;margin-bottom:10px;}
      p{color:#555;font-size:13px;line-height:1.6;margin-bottom:20px;}
      a{background:#d62828;color:#fff;padding:10px 24px;border-radius:8px;
        text-decoration:none;font-size:13px;font-weight:600;}
    </style></head><body>
    <div class="box">
      <h2>' . htmlspecialchars($title) . '</h2>
      <p>' . $msg . '</p>
      <a href="home.php">Back to Library</a>
    </div></body></html>';
    exit;
}
