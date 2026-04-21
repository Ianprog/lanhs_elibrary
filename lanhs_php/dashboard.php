<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
// dashboard.php
// PHP 7.2 compatible initials helper
if (!function_exists('getInitials')) {
    function getInitials(string $name): string {
        $parts = array_slice(explode(' ', trim($name)), 0, 2);
        $init  = '';
        foreach ($parts as $p) { if ($p !== '') $init .= strtoupper($p[0]); }
        return $init;
    }
}

requireRole('admin');

$stats = [
    'users'      => DB::val("SELECT COUNT(*) FROM users WHERE status='approved'"),
    'students'   => DB::val("SELECT COUNT(*) FROM users WHERE role='student' AND status='approved'"),
    'teachers'   => DB::val("SELECT COUNT(*) FROM users WHERE role='teacher' AND status='approved'"),
    'books'      => DB::val("SELECT COUNT(*) FROM books"),
    'pending'    => DB::val("SELECT COUNT(*) FROM users WHERE status='pending'"),
    'ann'        => DB::val("SELECT COUNT(*) FROM announcements"),
];

// Handle approve / reject / suspend
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    DB::query("UPDATE users SET status='approved' WHERE id=?", [$id]);
    logActivity(currentUser()['id'], 'approve_user', "Approved user #$id");
    redirect('dashboard.php', 'success', 'User approved.');
}
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    DB::query("UPDATE users SET status='rejected' WHERE id=?", [$id]);
    logActivity(currentUser()['id'], 'reject_user', "Rejected user #$id");
    redirect('dashboard.php', 'success', 'User rejected.');
}
if (isset($_GET['suspend'])) {
    $id = (int)$_GET['suspend'];
    DB::query("UPDATE users SET status='pending' WHERE id=? AND role!='admin'", [$id]);
    redirect('dashboard.php', 'success', 'User suspended.');
}

$pending    = DB::rows("SELECT * FROM users WHERE status='pending' ORDER BY created_at DESC");
$allUsers   = DB::rows("SELECT * FROM users ORDER BY created_at DESC");
$activities = DB::rows("SELECT a.*, u.full_name FROM activity_log a LEFT JOIN users u ON a.user_id=u.id ORDER BY a.created_at DESC LIMIT 10");

$statColors = ['admin'=>['bg'=>'rgba(214,40,40,0.15)','text'=>'#d62828'],'teacher'=>['bg'=>'rgba(247,127,0,0.15)','text'=>'#e65100'],'student'=>['bg'=>'rgba(40,167,69,0.15)','text'=>'#1b5e20']];

renderHead('Dashboard');
renderSidebar('dashboard');
renderPageHeader('Dashboard', 'System overview and monitoring', false, $stats['pending'] . ' pending');
?>
<div class="page-content">
  <?php renderFlash(); ?>

  <!-- Stats -->
  <div class="stats-grid">
    <?php
    $sc = [
      ['Total Users',    $stats['users'],   '#d62828', 'Active accounts'],
      ['Students',       $stats['students'], '#1976d2', 'Enrolled'],
      ['Teachers',       $stats['teachers'], '#f77f00', 'Active staff'],
      ['Books',          $stats['books'],    '#28a745', 'In catalog'],
      ['Pending Signups',$stats['pending'],  '#9c27b0', 'Awaiting approval'],
      ['Announcements',  $stats['ann'],      '#17a2b8', 'Posted'],
    ];
    foreach ($sc as [$label,$num,$color,$sub]): ?>
    <div class="stat-card" style="border-left-color:<?= $color ?>">
      <div class="stat-label"><?= $label ?></div>
      <div class="stat-number" style="color:<?= $color ?>"><?= $num ?></div>
      <div class="stat-sub"><?= $sub ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="dash-row">
    <!-- Pending -->
    <div class="card">
      <div class="card-title">⏳ Pending Sign-ups</div>
      <?php if (empty($pending)): ?>
        <p style="font-size:12px;color:#aaa;text-align:center;padding:16px 0;">No pending requests.</p>
      <?php else: foreach ($pending as $pu):
        $rc = $statColors[$pu['role']] ?? $statColors['student'];
        $ini = getInitials($pu['full_name']);
      ?>
      <div class="pending-card">
        <div class="pending-mini-avatar" style="background:<?= $rc['bg'] ?>;color:<?= $rc['text'] ?>"><?= $ini ?></div>
        <div class="pending-info">
          <strong><?= sanitize($pu['full_name']) ?></strong>
          <span><?= sanitize($pu['email']) ?> · <span class="badge badge-<?= $pu['role'] ?>"><?= $pu['role'] ?></span></span>
        </div>
        <div style="display:flex;gap:5px;">
          <a href="dashboard.php?approve=<?= $pu['id'] ?>" class="btn btn-success btn-sm">✓ Approve</a>
          <a href="dashboard.php?reject=<?= $pu['id'] ?>"  class="btn btn-danger  btn-sm"
             onclick="return confirm('Reject this request?')">✕</a>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Activity -->
    <div class="card">
      <div class="card-title">📋 Activity Log</div>
      <?php if (empty($activities)): ?>
        <p style="font-size:12px;color:#aaa;text-align:center;padding:16px 0;">No activity yet.</p>
      <?php else: foreach ($activities as $act): ?>
      <div class="activity-item">
        <div class="activity-dot"></div>
        <div class="activity-text"><strong><?= sanitize($act['full_name'] ?? 'System') ?>:</strong> <?= sanitize($act['action']) ?></div>
        <div class="activity-time"><?= timeAgo($act['created_at']) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Users quick link -->
  <div class="card">
    <div class="card-title" style="display:flex;align-items:center;justify-content:space-between;">
      <span>User Management</span>
      <a href="users.php" class="btn btn-primary btn-sm">View All Users</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
      <a href="users.php?tab=student" style="text-decoration:none;">
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px;text-align:center;">
          <div style="font-size:22px;font-weight:700;color:#1b5e20;"><?php echo DB::val("SELECT COUNT(*) FROM users WHERE role='student'"); ?></div>
          <div style="font-size:11px;color:#6b7280;margin-top:3px;">Students</div>
        </div>
      </a>
      <a href="users.php?tab=teacher" style="text-decoration:none;">
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:16px;text-align:center;">
          <div style="font-size:22px;font-weight:700;color:#e65100;"><?php echo DB::val("SELECT COUNT(*) FROM users WHERE role='teacher'"); ?></div>
          <div style="font-size:11px;color:#6b7280;margin-top:3px;">Teachers</div>
        </div>
      </a>
      <a href="users.php?tab=admin" style="text-decoration:none;">
        <div style="background:#fff5f5;border:1px solid #fde8e8;border-radius:10px;padding:16px;text-align:center;">
          <div style="font-size:22px;font-weight:700;color:var(--red);"><?php echo DB::val("SELECT COUNT(*) FROM users WHERE role='admin'"); ?></div>
          <div style="font-size:11px;color:#6b7280;margin-top:3px;">Admins</div>
        </div>
      </a>
    </div>
  </div>
</div>
<?php renderFooter(); ?>
