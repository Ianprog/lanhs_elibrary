<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();
$u = currentUser();

$books = DB::rows(
    "SELECT b.id,b.title,b.author,b.subject,b.has_pdf,b.has_cover,f.id AS fav_id
     FROM favorites f JOIN books b ON f.book_id=b.id
     WHERE f.user_id=? ORDER BY f.created_at DESC",
    [$u['id']]
);
$subjectColors=['Mathematics'=>'#dbeafe','Science'=>'#dcfce7','Literature'=>'#fef9c3','History'=>'#fce7f3','Technology'=>'#e0e7ff','Filipino'=>'#f3e8ff'];
$subjectIcons=['Mathematics'=>'dashboard','Science'=>'shield','Literature'=>'read','History'=>'notes','Technology'=>'manage','Filipino'=>'book'];

renderHead('My Favorites');
renderSidebar('favorites');
renderPageHeader('My Favorites','Books you have saved',false,count($books).' saved');
?>
<div class="page-content">
<?php renderFlash(); ?>
<?php if (empty($books)): ?>
<div class="books-grid">
  <div class="empty-state">
    <div style="margin-bottom:12px;"><?= icon('heart-outline','nav-icon') ?></div>
    <h3>No favorites yet</h3>
    <p>Go to the <a href="home.php" style="color:var(--red);font-weight:600;">Library</a> and click the heart on books you love.</p>
  </div>
</div>
<?php else: ?>
<div class="books-grid">
  <?php foreach ($books as $b): $bg=$subjectColors[$b['subject']]??'#f0f4f8'; $ico=$subjectIcons[$b['subject']]??'book'; ?>
  <div class="book-card">
    <div class="book-cover" style="background:<?=$bg?>;cursor:pointer;padding:0;overflow:hidden;"
         onclick="openReader(<?=(int)$b['id']?>,<?=json_encode($b['title'])?>,<?=$b['has_pdf']?'true':'false'?>)">
      <?php if ($b['has_cover']): ?>
        <img src="serve_cover.php?id=<?=(int)$b['id']?>" alt="<?=sanitize($b['title'])?>"
             style="width:100%;height:100%;object-fit:cover;display:block;">
      <?php else: ?><?= icon($ico,'book-cover-svg') ?><?php endif; ?>
      <span class="book-cover-badge"><?= sanitize($b['subject']??'') ?></span>
      <?php if ($b['has_pdf']): ?><span class="book-pdf-tag">PDF</span><?php endif; ?>
    </div>
    <div class="book-body">
      <div class="book-title"><?= sanitize($b['title']) ?></div>
      <div class="book-author">by <?= sanitize($b['author']??'Unknown') ?></div>
      <div class="book-actions">
        <button class="btn-read" onclick="openReader(<?=(int)$b['id']?>,<?=json_encode($b['title'])?>,<?=$b['has_pdf']?'true':'false'?>)">Read</button>
        <button class="btn-fav saved" id="fav-<?=$b['id']?>" onclick="toggleFavorite(<?=(int)$b['id']?>,this)" title="Remove from favorites">
          <?= icon('heart','nav-icon') ?>
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<div class="reader-overlay" id="reader-overlay">
  <div class="reader-top">
    <h3 id="r-title"></h3>
    <div class="reader-controls">
      <button class="r-btn" id="r-zoom-out" onclick="zoomOut()">A−</button>
      <span class="zoom-val" id="r-zoom">100%</span>
      <button class="r-btn" id="r-zoom-in"  onclick="zoomIn()">A+</button>
      <button class="r-btn" id="r-tts-btn"  onclick="toggleTTS()">Read Aloud</button>
      <button class="r-btn"                  onclick="closeReader()">✕ Close</button>
    </div>
  </div>
  <div class="reader-tts-bar" id="reader-tts-bar">
    <span style="font-size:11px;color:rgba(255,255,255,.7);">Reading aloud…</span>
    <div class="tts-track"><div class="tts-fill" id="tts-fill"></div></div>
    <button class="r-btn" style="padding:5px 12px;font-size:11px;" onclick="stopTTS()">Stop</button>
  </div>
  <div class="reader-body" id="reader-body"></div>
  <div class="reader-hint">ESC — close &nbsp;·&nbsp; +/− — zoom &nbsp;·&nbsp; Read Aloud — TTS</div>
</div>
<?php renderFooter(); ?>
