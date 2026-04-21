<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
// ============================================================
//  announcements.php — Section-targeted announcements
// ============================================================
requireLogin();
$u = currentUser();

$sections = [
    'Grade 7 - Sampaguita','Grade 7 - Ilang-ilang',
    'Grade 8 - Rosal',     'Grade 8 - Orchid',
    'Grade 9 - Pandan',    'Grade 9 - Yakal',
    'Grade 10 - Narra',
];

// ── Post announcement ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_post_ann'])) {
    requireRole('admin','teacher');
    verifyCsrf();

    $title      = trim($_POST['title']          ?? '');
    $body       = trim($_POST['body']           ?? '');
    $targetType = $_POST['target_type']         ?? 'all';
    $targetSec  = trim($_POST['target_section'] ?? '');

    if (!$title || !$body) {
        flash('error', 'Title and message are required.');
        redirect('announcements.php');
    }
    if ($targetType === 'section' && !in_array($targetSec, $sections, true)) {
        flash('error', 'Please select a valid section.');
        redirect('announcements.php');
    }
    if ($targetType !== 'section') { $targetSec = null; $targetType = 'all'; }

    DB::query(
        "INSERT INTO announcements (title, body, author_id, target_type, target_section)
         VALUES (?,?,?,?,?)",
        [$title, $body, $u['id'], $targetType, $targetSec]
    );
    logActivity($u['id'], 'post_announcement',
        $title . ($targetSec ? " → section: $targetSec" : ' → all students'));
    redirect('announcements.php', 'success',
        'Announcement posted' . ($targetSec ? " to section: $targetSec" : ' to all students') . '.');
}

// ── Delete ────────────────────────────────────────────────────
if (isset($_GET['delete']) && isRole('admin')) {
    DB::query("DELETE FROM announcements WHERE id=?", [(int)$_GET['delete']]);
    redirect('announcements.php', 'success', 'Announcement deleted.');
}

// ── Fetch announcements visible to current user ───────────────
if ($u['role'] === 'student') {
    // Students see: (a) all-broadcast announcements
    //               (b) announcements targeted at their section
    $userSection = DB::val("SELECT section FROM users WHERE id=?", [$u['id']]);
    $anns = DB::rows(
        "SELECT a.*, u.full_name AS author_name, u.role AS author_role
         FROM announcements a
         LEFT JOIN users u ON a.author_id = u.id
         WHERE a.target_type = 'all'
            OR (a.target_type = 'section' AND a.target_section = ?)
         ORDER BY a.created_at DESC",
        [$userSection ?: '']
    );
} else {
    // Teachers and admins see everything
    $anns = DB::rows(
        "SELECT a.*, u.full_name AS author_name, u.role AS author_role
         FROM announcements a
         LEFT JOIN users u ON a.author_id = u.id
         ORDER BY a.created_at DESC"
    );
}

renderHead('Announcements');
renderSidebar('announcements');
renderPageHeader('Announcements', 'School and library notices', false, count($anns) . ' posts');
?>
<div class="page-content">
<?php renderFlash(); ?>

<?php if (isRole('admin','teacher')): ?>
<!-- ── Post Form ─────────────────────────────────────────────── -->
<div class="card">
  <div class="card-title">Post New Announcement</div>
  <form method="POST" id="ann-form">
    <input type="hidden" name="_post_ann" value="1">
    <?= csrfField() ?>

    <div class="form-grid">
      <div class="form-group">
        <label>Title *</label>
        <input type="text" name="title" required placeholder="Announcement title"
               value="<?= sanitize($_POST['title'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Send To</label>
        <select name="target_type" id="target-type" onchange="toggleSectionPicker()">
          <option value="all">All Students (Broadcast)</option>
          <option value="section">Specific Section Only</option>
        </select>
      </div>
    </div>

    <!-- Section picker — shown only when "Specific Section" is chosen -->
    <div class="form-grid" id="section-picker" style="display:none;">
      <div class="form-group">
        <label>Target Section</label>
        <select name="target_section">
          <option value="">— Choose a section —</option>
          <?php foreach ($sections as $s): ?>
            <option <?= ($_POST['target_section'] ?? '') === $s ? 'selected' : '' ?>><?= sanitize($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="justify-content:flex-end;">
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:11px;color:#92400e;margin-top:auto;">
          Only students whose profile section matches the selected section will see this announcement.
        </div>
      </div>
    </div>

    <div class="form-grid full">
      <div class="form-group">
        <label>Message *</label>
        <textarea name="body" required rows="4"
                  placeholder="Type your announcement here…"><?= sanitize($_POST['body'] ?? '') ?></textarea>
      </div>
    </div>

    <button class="btn btn-primary" type="submit">Post Announcement</button>
  </form>
</div>
<?php endif; ?>

<!-- ── Announcement list ──────────────────────────────────────── -->
<?php if (empty($anns)): ?>
  <div style="text-align:center;padding:56px;color:#aaa;">
    <?= icon('announce','nav-icon') ?>
    <p style="margin-top:14px;font-size:13px;">No announcements yet.</p>
  </div>
<?php else: ?>
  <?php foreach ($anns as $a): ?>
  <div class="ann-card <?= $a['target_type']==='section' ? 'ann-section' : '' ?>">
    <div class="ann-meta">
      <span class="badge badge-<?= sanitize($a['author_role'] ?? 'admin') ?>">
        <?= sanitize($a['author_role'] ?? 'admin') ?>
      </span>
      <span><?= sanitize($a['author_name'] ?? 'Admin') ?></span>
      <span><?= timeAgo($a['created_at']) ?></span>
      <!-- Target badge -->
      <?php if ($a['target_type'] === 'section'): ?>
        <span class="section-target-badge">
          <?= icon('notes','target-icon') ?>
          <?= sanitize($a['target_section']) ?> only
        </span>
      <?php else: ?>
        <span class="broadcast-badge">All Students</span>
      <?php endif; ?>
      <?php if (isRole('admin')): ?>
        <a href="announcements.php?delete=<?= (int)$a['id'] ?>"
           class="remove-link" style="margin-left:auto;"
           onclick="return confirm('Delete this announcement?')">Delete</a>
      <?php endif; ?>
    </div>
    <div class="ann-title"><?= sanitize($a['title']) ?></div>
    <div class="ann-body"><?= nl2br(sanitize($a['body'])) ?></div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<style>
.ann-section { border-left-color: var(--orange); }
.section-target-badge {
  display:inline-flex;align-items:center;gap:4px;
  background:#fff3e0;color:#e65100;
  font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;
}
.target-icon{width:10px;height:10px;stroke:#e65100;}
.broadcast-badge {
  background:var(--red-soft);color:var(--red);
  font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;
}
</style>

<script>
function toggleSectionPicker() {
  const type   = document.getElementById('target-type').value;
  const picker = document.getElementById('section-picker');
  picker.style.display = type === 'section' ? 'grid' : 'none';
}
</script>
<?php renderFooter(); ?>
