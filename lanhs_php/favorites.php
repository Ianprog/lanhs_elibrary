<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();
$u = currentUser();

$books = DB::rows(
    "SELECT b.id,b.title,b.author,b.subject,b.has_pdf,b.has_cover
     FROM favorites f JOIN books b ON f.book_id=b.id
     WHERE f.user_id=? ORDER BY f.created_at DESC",
    [(int)$u['id']]
);
$colors=['Mathematics'=>'#dbeafe','Science'=>'#dcfce7','Literature'=>'#fef9c3',
         'History'=>'#fce7f3','Technology'=>'#e0e7ff','Filipino'=>'#f3e8ff'];

renderHead('My Favorites'); renderSidebar('favorites');
renderPageHeader('My Favorites','Books you have saved',false,count($books).' saved');
?>
<div class="page-content">
<?php renderFlash(); ?>
<?php if (empty($books)): ?>
  <div style="text-align:center;padding:60px;color:#aaa;">
    <div style="font-size:42px;margin-bottom:14px;">🤍</div>
    <h3>No favorites yet</h3>
    <p style="margin-top:8px;font-size:13px;">Go to <a href="home.php" style="color:#d62828;font-weight:600;">Library</a> and click ♡ on books.</p>
  </div>
<?php else: ?>
  <div class="books-grid">
    <?php foreach ($books as $b):
      $bg=$colors[$b['subject']] ?? '#f0f4f8'; $id=(int)$b['id'];
    ?>
    <div class="book-card">
      <a href="viewer.php?id=<?= $id ?>" target="_blank" style="display:block;text-decoration:none;">
        <div class="book-cover" style="background:<?= $bg ?>;padding:0;overflow:hidden;position:relative;">
          <?php if ($b['has_cover']): ?>
            <img src="serve_cover.php?id=<?= $id ?>" style="width:100%;height:100%;object-fit:cover;display:block;" alt="">
          <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:46px;opacity:.3;">📖</div>
          <?php endif; ?>
          <span class="book-cover-badge"><?= sanitize($b['subject']??'') ?></span>
          <?php if ($b['has_pdf']): ?><span class="book-pdf-tag">PDF</span><?php endif; ?>
        </div>
      </a>
      <div class="book-body">
        <div class="book-title"><?= sanitize($b['title']) ?></div>
        <div class="book-author">by <?= sanitize($b['author']??'Unknown') ?></div>
        <div class="book-actions">
          <a href="viewer.php?id=<?= $id ?>" target="_blank" class="btn-read"
             style="text-align:center;display:flex;align-items:center;justify-content:center;text-decoration:none;">Read</a>
          <button class="btn-fav saved" id="fav<?= $id ?>" onclick="doFav(<?= $id ?>)">♥</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
<script>
function doFav(id){var fd=new FormData();fd.append('book_id',id);fetch('toggle_favorite.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){var b=document.getElementById('fav'+id);if(!b)return;if(d.action==='added'){b.className='btn-fav saved';b.innerHTML='♥';}else{b.className='btn-fav';b.innerHTML='♡';}});}
</script>
<?php renderFooter(); ?>
