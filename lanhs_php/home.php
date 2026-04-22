<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();

$u       = currentUser();
$search  = trim(isset($_GET['search'])  ? $_GET['search']  : '');
$subject = trim(isset($_GET['subject']) ? $_GET['subject'] : '');

$sql    = "SELECT b.id,b.title,b.author,b.subject,b.has_pdf,b.has_cover,
                  (SELECT COUNT(*) FROM favorites f WHERE f.book_id=b.id AND f.user_id=?) AS is_fav
           FROM books b WHERE 1=1";
$params = [(int)$u['id']];
if ($search !== '') {
    $sql     .= " AND (b.title LIKE ? OR b.author LIKE ?)";
    $params[] = '%'.$search.'%'; $params[] = '%'.$search.'%';
}
if ($subject !== '') { $sql .= " AND b.subject=?"; $params[] = $subject; }
$sql .= " ORDER BY b.created_at DESC";
$books = DB::rows($sql, $params);

$colors = ['Mathematics'=>'#dbeafe','Science'=>'#dcfce7','Literature'=>'#fef9c3',
           'History'=>'#fce7f3','Technology'=>'#e0e7ff','Filipino'=>'#f3e8ff'];

renderHead('Library');
renderSidebar('home');
renderPageHeader('Library','Browse and read available books',true,count($books).' books');
?>
<div class="page-content">
<?php renderFlash(); ?>

<?php if (empty($books)): ?>
  <div style="text-align:center;padding:60px;color:#aaa;">
    <div style="font-size:42px;margin-bottom:14px;">📚</div>
    <h3 style="font-size:16px;">No books found</h3>
    <p style="margin-top:8px;font-size:13px;">
      <?= $search ? 'No results for "'.sanitize($search).'".'
                  : 'The catalog is empty. Add books via Manage Books.' ?>
    </p>
  </div>
<?php else: ?>
  <div class="books-grid">
    <?php foreach ($books as $b):
      $bg    = isset($colors[$b['subject']]) ? $colors[$b['subject']] : '#f0f4f8';
      $id    = (int)$b['id'];
      $isFav = !empty($b['is_fav']);
    ?>
    <div class="book-card">
      <a href="viewer.php?id=<?= $id ?>" target="_blank" style="display:block;text-decoration:none;">
        <div class="book-cover"
             style="background:<?= $bg ?>;padding:0;overflow:hidden;position:relative;">
          <?php if ($b['has_cover']): ?>
            <img src="serve_cover.php?id=<?= $id ?>"
                 style="width:100%;height:100%;object-fit:cover;display:block;" alt="">
          <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;
                        justify-content:center;font-size:46px;opacity:.3;">📖</div>
          <?php endif; ?>
          <span class="book-cover-badge"><?= sanitize($b['subject'] ?? '') ?></span>
          <?php if ($b['has_pdf']): ?>
            <span class="book-pdf-tag">PDF</span>
          <?php endif; ?>
          <!-- Hover overlay -->
          <div style="position:absolute;inset:0;background:rgba(214,40,40,0);
                      display:flex;align-items:center;justify-content:center;
                      transition:background .2s;opacity:0;"
               onmouseover="this.style.background='rgba(214,40,40,0.7)';this.style.opacity='1';"
               onmouseout="this.style.background='rgba(214,40,40,0)';this.style.opacity='0';">
            <span style="color:#fff;font-size:13px;font-weight:700;font-family:Sora,sans-serif;
                         background:rgba(0,0,0,.3);padding:8px 16px;border-radius:20px;">
              📖 Read Now
            </span>
          </div>
        </div>
      </a>
      <div class="book-body">
        <div class="book-title"><?= sanitize($b['title']) ?></div>
        <div class="book-author">by <?= sanitize($b['author'] ?? 'Unknown') ?></div>
        <div class="book-actions">
          <a href="viewer.php?id=<?= $id ?>" target="_blank" class="btn-read"
             style="text-align:center;display:flex;align-items:center;justify-content:center;
                    text-decoration:none;">
            Read
          </a>
          <button class="btn-fav <?= $isFav?'saved':'' ?>"
                  id="fav<?= $id ?>"
                  onclick="doFav(<?= $id ?>)">
            <?= $isFav ? '♥' : '♡' ?>
          </button>
          <?php if (isRole('teacher','admin')): ?>
            <a href="manage_books.php?edit=<?= $id ?>" class="btn-del-sm" title="Edit book">✏</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<script>
function doFav(id) {
  var fd = new FormData();
  fd.append('book_id', id);
  fetch('toggle_favorite.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      var btn = document.getElementById('fav'+id);
      if (!btn) return;
      if (d.action==='added') { btn.className='btn-fav saved'; btn.innerHTML='♥'; }
      else                    { btn.className='btn-fav';       btn.innerHTML='♡'; }
    });
}
</script>
<?php renderFooter(); ?>
