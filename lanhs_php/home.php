<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();

$u       = currentUser();
$search  = trim(isset($_GET['search'])  ? $_GET['search']  : '');
$subject = trim(isset($_GET['subject']) ? $_GET['subject'] : '');

$sql    = "SELECT id,title,author,description,subject,has_pdf,has_cover
           FROM books b WHERE 1=1";
$favSub = "(SELECT COUNT(*) FROM favorites WHERE book_id=b.id AND user_id=" . (int)$u['id'] . ") AS is_fav";
$sql    = "SELECT id,title,author,description,subject,has_pdf,has_cover, $favSub FROM books b WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql   .= " AND (title LIKE ? OR author LIKE ? OR description LIKE ?)";
    $like   = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}
if ($subject !== '') {
    $sql   .= " AND subject = ?";
    $params[] = $subject;
}
$sql .= " ORDER BY created_at DESC";
$books = DB::rows($sql, $params);

$subjectColors = [
    'Mathematics'=>'#dbeafe','Science'=>'#dcfce7',
    'Literature' =>'#fef9c3','History'=>'#fce7f3',
    'Technology' =>'#e0e7ff','Filipino'=>'#f3e8ff',
];
$subjectIcons = [
    'Mathematics'=>'dashboard','Science'=>'shield',
    'Literature' =>'read',     'History'=>'notes',
    'Technology' =>'manage',   'Filipino'=>'book',
];

renderHead('Library');
renderSidebar('home');
renderPageHeader('Library', 'Browse and read available books', true, count($books) . ' books');
?>
<div class="page-content">
<?php renderFlash(); ?>

<?php if (empty($books)): ?>
<div class="books-grid">
  <div class="empty-state">
    <?= icon('book','nav-icon') ?>
    <h3 style="margin-top:10px;">No books found</h3>
    <p><?= $search ? 'No results for "' . sanitize($search) . '".' : 'The catalog is empty. Admin can add books via Manage Books.' ?></p>
  </div>
</div>
<?php else: ?>
<div class="books-grid">
  <?php foreach ($books as $b):
    $bg  = isset($subjectColors[$b['subject']]) ? $subjectColors[$b['subject']] : '#f0f4f8';
    $ico = isset($subjectIcons[$b['subject']])  ? $subjectIcons[$b['subject']]  : 'book';
    $isFav = !empty($b['is_fav']);
  ?>
  <div class="book-card">
    <div class="book-cover"
         style="background:<?= $bg ?>;cursor:pointer;padding:0;overflow:hidden;position:relative;"
         onclick="openReader(<?= (int)$b['id'] ?>, <?= json_encode($b['title']) ?>, <?= $b['has_pdf'] ? 'true' : 'false' ?>)">
      <?php if ($b['has_cover']): ?>
        <img src="serve_cover.php?id=<?= (int)$b['id'] ?>"
             alt="<?= sanitize($b['title']) ?>"
             style="width:100%;height:100%;object-fit:cover;display:block;">
      <?php else: ?>
        <?= icon($ico, 'book-cover-svg') ?>
      <?php endif; ?>
      <span class="book-cover-badge"><?= sanitize($b['subject'] ?? '') ?></span>
      <?php if ($b['has_pdf']): ?>
        <span class="book-pdf-tag">PDF</span>
      <?php endif; ?>
    </div>
    <div class="book-body">
      <div class="book-title"><?= sanitize($b['title']) ?></div>
      <div class="book-author">by <?= sanitize($b['author'] ?? 'Unknown') ?></div>
      <div class="book-actions">
        <button class="btn-read"
                onclick="openReader(<?= (int)$b['id'] ?>, <?= json_encode($b['title']) ?>, <?= $b['has_pdf'] ? 'true' : 'false' ?>)">
          Read
        </button>
        <button class="btn-fav <?= $isFav ? 'saved' : '' ?>"
                id="fav-<?= $b['id'] ?>"
                onclick="toggleFavorite(<?= (int)$b['id'] ?>, this)"
                title="<?= $isFav ? 'Remove from favorites' : 'Add to favorites' ?>">
          <?= icon($isFav ? 'heart' : 'heart-outline', 'nav-icon') ?>
        </button>
        <?php if (isRole('teacher','admin')): ?>
          <a href="manage_books.php?edit=<?= (int)$b['id'] ?>" class="btn-del-sm" title="Edit">
            <?= icon('notepad','nav-icon') ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- ════════════════ READER OVERLAY ══════════════════════════ -->
<div class="reader-overlay" id="reader-overlay">
  <div class="reader-top">
    <h3 id="r-title"></h3>
    <div class="reader-controls">
      <button class="r-btn" id="r-zoom-out" onclick="zoomOut()" title="Zoom out (-)">A−</button>
      <span class="zoom-val" id="r-zoom">100%</span>
      <button class="r-btn" id="r-zoom-in"  onclick="zoomIn()"  title="Zoom in (+)">A+</button>
      <button class="r-btn" id="r-tts-btn"  onclick="toggleTTS()">Read Aloud</button>
      <button class="r-btn"                  onclick="closeReader()" title="Close (Esc)">✕ Close</button>
    </div>
  </div>
  <div class="reader-tts-bar" id="reader-tts-bar">
    <span style="font-size:11px;color:rgba(255,255,255,.7);white-space:nowrap;">Reading aloud…</span>
    <div class="tts-track"><div class="tts-fill" id="tts-fill"></div></div>
    <button class="r-btn" style="padding:5px 12px;font-size:11px;" onclick="stopTTS()">Stop</button>
  </div>
  <div class="reader-body" id="reader-body"></div>
  <div class="reader-hint">
    ESC — close &nbsp;·&nbsp; +/− — zoom text &nbsp;·&nbsp; Read Aloud — text-to-speech
  </div>
</div>
<?php renderFooter(); ?>
