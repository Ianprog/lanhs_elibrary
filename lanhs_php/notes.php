<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();
$u = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_post_note'])) {
    verifyCsrf();
    requireRole('admin','teacher');
    $section = trim($_POST['section'] ?? '');
    $title   = trim($_POST['title']   ?? '');
    $body    = trim($_POST['body']    ?? '');
    if ($section && $title && $body) {
        DB::query("INSERT INTO section_notes (section, title, body, author_id) VALUES (?,?,?,?)", [$section,$title,$body,$u['id']]);
        logActivity($u['id'], 'add_note', "$section: $title");
        redirect('notes.php', 'success', 'Note saved!');
    }
}
if (isset($_GET['delete']) && isRole('admin','teacher')) {
    DB::query("DELETE FROM section_notes WHERE id=?", [(int)$_GET['delete']]);
    redirect('notes.php', 'success', 'Note deleted.');
}

$notes = DB::rows("SELECT n.*, u.full_name as author_name FROM section_notes n LEFT JOIN users u ON n.author_id=u.id ORDER BY n.created_at DESC");

$sections = ['Grade 7 - Sampaguita','Grade 7 - Ilang-ilang','Grade 8 - Rosal','Grade 8 - Orchid','Grade 9 - Pandan','Grade 9 - Yakal','Grade 10 - Narra'];

renderHead('Section Notes');
renderSidebar('notes');
renderPageHeader('Section Notes', 'Notes posted by teachers', false, count($notes) . ' notes');
?>
<div class="page-content">
  <?php renderFlash(); ?>

  <?php if (isRole('admin','teacher')): ?>
  <div class="card">
    <div class="card-title">📝 Add Section Note</div>
    <form method="POST">
      <input type="hidden" name="_post_note" value="1">
      <?= csrfField() ?>
      <div class="form-grid">
        <div class="form-group">
          <label>Section</label>
          <select name="section" required>
            <?php foreach ($sections as $s): ?>
              <option><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Title</label>
          <input type="text" name="title" required placeholder="Note title">
        </div>
      </div>
      <div class="form-grid full">
        <div class="form-group">
          <label>Content</label>
          <textarea name="body" required placeholder="Note content…"></textarea>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Save Note</button>
    </form>
  </div>
  <?php endif; ?>

  <?php if (empty($notes)): ?>
    <div style="text-align:center;padding:48px;color:#aaa;">
      <div style="font-size:38px;margin-bottom:12px;opacity:.3;">📝</div>
      <p style="font-size:13px;">No section notes yet.</p>
    </div>
  <?php else: foreach ($notes as $n): ?>
    <div class="note-card">
      <div class="note-header">
        <div class="note-title"><?= sanitize($n['title']) ?></div>
        <div style="display:flex;align-items:center;gap:8px;">
          <span class="note-tag">📍 <?= sanitize($n['section']) ?></span>
          <?php if (isRole('admin','teacher')): ?>
            <a href="notes.php?delete=<?= $n['id'] ?>" style="color:#dc3545;font-size:11px;"
               onclick="return confirm('Delete this note?')">✕</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="note-body"><?= nl2br(sanitize($n['body'])) ?></div>
      <div class="note-meta">by <?= sanitize($n['author_name'] ?? 'Teacher') ?> · <?= timeAgo($n['created_at']) ?></div>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php renderFooter(); ?>
