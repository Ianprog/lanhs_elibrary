<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
// ============================================================
//  users.php — Admin user management (tabbed by role)
// ============================================================
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
$u = currentUser();

// ── Actions ───────────────────────────────────────────────────
if (isset($_GET['approve'])) {
    DB::query("UPDATE users SET status='approved' WHERE id=?", [(int)$_GET['approve']]);
    logActivity($u['id'], 'approve_user', 'Approved user #' . (int)$_GET['approve']);
    redirect('users.php', 'success', 'User approved.');
}
if (isset($_GET['reject'])) {
    DB::query("UPDATE users SET status='rejected' WHERE id=?", [(int)$_GET['reject']]);
    logActivity($u['id'], 'reject_user', 'Rejected user #' . (int)$_GET['reject']);
    redirect('users.php', 'success', 'User rejected.');
}
if (isset($_GET['suspend'])) {
    $uid = (int)$_GET['suspend'];
    DB::query("UPDATE users SET status='pending' WHERE id=? AND role!='admin'", [$uid]);
    redirect('users.php', 'success', 'User suspended.');
}
if (isset($_GET['delete_user'])) {
    $uid  = (int)$_GET['delete_user'];
    $name = DB::val("SELECT full_name FROM users WHERE id=? AND role!='admin'", [$uid]);
    if ($name) {
        DB::query("DELETE FROM users WHERE id=?", [$uid]);
        logActivity($u['id'], 'delete_user', "Deleted user: $name");
        redirect('users.php', 'success', "User \"$name\" deleted.");
    }
}

// ── Add user ──────────────────────────────────────────────────
$addErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_add_user'])) {
    verifyCsrf();
    $name    = trim($_POST['full_name'] ?? '');
    $email   = strtolower(trim($_POST['email'] ?? ''));
    $role    = $_POST['role']     ?? 'student';
    $pass    = $_POST['password'] ?? '';
    $grade   = trim($_POST['grade']   ?? '');
    $section = trim($_POST['section'] ?? '');
    $status  = $_POST['status'] ?? 'approved';

    if (!$name || !$email || !$pass) $addErrors[] = 'Name, email and password are required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $addErrors[] = 'Invalid email address.';
    elseif (strlen($pass) < 6) $addErrors[] = 'Password must be at least 6 characters.';
    elseif (!in_array($role, ['admin','teacher','student'], true)) $addErrors[] = 'Invalid role.';
    elseif (DB::val("SELECT COUNT(*) FROM users WHERE email=?", [$email])) $addErrors[] = 'Email already registered.';

    if (empty($addErrors)) {
        DB::query(
            "INSERT INTO users (full_name,email,password,role,status,grade,section) VALUES (?,?,?,?,?,?,?)",
            [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $status, $grade, $section]
        );
        logActivity($u['id'], 'add_user', "$role: $name ($email)");
        redirect('users.php?tab=' . $role, 'success', "User \"$name\" created.");
    }
}

// ── Sort & filter ─────────────────────────────────────────────
$tab    = in_array($_GET['tab'] ?? '', ['student','teacher','admin']) ? $_GET['tab'] : 'student';
$sort   = in_array($_GET['sort'] ?? '', ['full_name','email','status','grade','section','created_at'])
            ? $_GET['sort'] : 'full_name';
$dir    = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$search = trim($_GET['q'] ?? '');

$where  = "WHERE role = ?";
$params = [$tab];
if ($search) {
    $where   .= " AND (full_name LIKE ? OR email LIKE ? OR section LIKE ?)";
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$users = DB::rows("SELECT * FROM users $where ORDER BY $sort $dir", $params);

// Count per role for badges
$counts = [
    'student' => DB::val("SELECT COUNT(*) FROM users WHERE role='student'"),
    'teacher' => DB::val("SELECT COUNT(*) FROM users WHERE role='teacher'"),
    'admin'   => DB::val("SELECT COUNT(*) FROM users WHERE role='admin'"),
    'pending' => DB::val("SELECT COUNT(*) FROM users WHERE status='pending'"),
];

$roleColors = [
    'admin'   => ['bg'=>'rgba(214,40,40,0.12)',  'text'=>'#d62828'],
    'teacher' => ['bg'=>'rgba(247,127,0,0.12)',  'text'=>'#e65100'],
    'student' => ['bg'=>'rgba(40,167,69,0.12)',  'text'=>'#1b5e20'],
];

renderHead('User Management');
renderSidebar('users');
renderPageHeader('User Management', 'Manage students, teachers, and administrators',
    false, $counts['pending'] . ' pending');
?>
<div class="page-content">
<?php renderFlash(); ?>
<?php foreach ($addErrors as $e): ?>
  <div class="alert alert-error"><?= sanitize($e) ?></div>
<?php endforeach; ?>

<!-- ── Summary cards ─────────────────────────────────────────── -->
<div class="stats-grid" style="margin-bottom:20px;">
  <div class="stat-card"><div class="stat-label">Students</div>
    <div class="stat-number" style="color:#1b5e20;"><?= $counts['student'] ?></div></div>
  <div class="stat-card" style="border-left-color:#e65100;"><div class="stat-label">Teachers</div>
    <div class="stat-number" style="color:#e65100;"><?= $counts['teacher'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Admins</div>
    <div class="stat-number"><?= $counts['admin'] ?></div></div>
  <div class="stat-card" style="border-left-color:#f59e0b;"><div class="stat-label">Pending Approval</div>
    <div class="stat-number" style="color:#f59e0b;"><?= $counts['pending'] ?></div></div>
</div>

<!-- ── Tabs ──────────────────────────────────────────────────── -->
<div class="user-tabs">
  <?php foreach(['student'=>'Students','teacher'=>'Teachers','admin'=>'Admins'] as $r=>$l): ?>
  <a href="users.php?tab=<?= $r ?>&sort=<?= $sort ?>&dir=<?= $dir ?>"
     class="user-tab <?= $tab===$r?'active':'' ?>">
    <?= $l ?>
    <span class="tab-count"><?= $counts[$r] ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ── Search + Add user ─────────────────────────────────────── -->
<div class="card">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:6px;flex:1;min-width:220px;">
      <input type="hidden" name="tab" value="<?= $tab ?>">
      <input type="text" name="q" value="<?= sanitize($search) ?>"
             placeholder="Search name, email or section…"
             style="flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--radius);
                    font-size:12px;outline:none;font-family:'Sora',sans-serif;">
      <button class="btn btn-secondary btn-sm" type="submit">Search</button>
      <?php if ($search): ?>
        <a href="users.php?tab=<?= $tab ?>" class="btn btn-secondary btn-sm">Clear</a>
      <?php endif; ?>
    </form>
    <button class="btn btn-primary btn-sm" onclick="toggleAddForm()">+ Add User</button>
  </div>

  <!-- Add user form (hidden by default) -->
  <div id="add-user-form" style="display:none;border-top:1px solid var(--border);padding-top:16px;margin-bottom:16px;">
    <div style="font-size:13px;font-weight:700;color:var(--red);margin-bottom:14px;">Add New User</div>
    <form method="POST">
      <input type="hidden" name="_add_user" value="1">
      <?= csrfField() ?>
      <div class="form-grid">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="full_name" required placeholder="Full name"
                 value="<?= sanitize($_POST['full_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" required placeholder="email@lanhs.edu"
                 value="<?= sanitize($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Role</label>
          <select name="role">
            <option value="student" <?= ($tab==='student')?'selected':'' ?>>Student</option>
            <option value="teacher" <?= ($tab==='teacher')?'selected':'' ?>>Teacher</option>
            <option value="admin"   <?= ($tab==='admin')  ?'selected':'' ?>>Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" required placeholder="Min. 6 characters">
        </div>
        <div class="form-group">
          <label>Grade <span style="color:#aaa;font-size:10px;">(students)</span></label>
          <select name="grade">
            <option value="">— None —</option>
            <?php foreach(['Grade 7','Grade 8','Grade 9','Grade 10'] as $g): ?>
              <option><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Section <span style="color:#aaa;font-size:10px;">(students)</span></label>
          <select name="section">
            <option value="">— None —</option>
            <?php foreach(['Sampaguita','Ilang-ilang','Rosal','Orchid','Pandan','Yakal','Narra'] as $s): ?>
              <option><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Account Status</label>
          <select name="status">
            <option value="approved">Active (Approved)</option>
            <option value="pending">Pending Approval</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:8px;margin-top:4px;">
        <button class="btn btn-primary" type="submit">Create User</button>
        <button class="btn btn-secondary" type="button" onclick="toggleAddForm()">Cancel</button>
      </div>
    </form>
  </div>

  <!-- ── Sort bar ────────────────────────────────────────────── -->
  <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap;">
    <span style="font-size:11px;color:#aaa;font-weight:600;">Sort by:</span>
    <?php
    $sortOptions = ['full_name'=>'Name','email'=>'Email','status'=>'Status','created_at'=>'Joined'];
    if ($tab === 'student') $sortOptions['grade'] = 'Grade';
    if ($tab === 'student') $sortOptions['section'] = 'Section';
    foreach($sortOptions as $k=>$v):
      $newDir = ($sort===$k && $dir==='asc') ? 'desc' : 'asc';
    ?>
    <a href="users.php?tab=<?= $tab ?>&sort=<?= $k ?>&dir=<?= $newDir ?>&q=<?= urlencode($search) ?>"
       style="font-size:11px;font-weight:600;
              color:<?= $sort===$k?'var(--red)':'#888' ?>;
              border-bottom:<?= $sort===$k?'2px solid var(--red)':'2px solid transparent' ?>;
              padding-bottom:1px;text-decoration:none;">
      <?= $v ?><?= $sort===$k?($dir==='asc'?' ↑':' ↓'):'' ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- ── Users table ─────────────────────────────────────────── -->
  <?php if (empty($users)): ?>
    <p style="text-align:center;color:#aaa;padding:24px 0;font-size:13px;">
      No <?= $tab ?>s found<?= $search ? ' matching "' . sanitize($search) . '"' : '' ?>.
    </p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Email</th>
          <?php if ($tab === 'student'): ?><th>Grade</th><th>Section</th><?php endif; ?>
          <th>Status</th>
          <th>Last Login</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $uu):
          $rc  = $roleColors[$uu['role']] ?? $roleColors['student'];
          $ini = getInitials($uu['full_name']);
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:9px;">
              <div style="width:30px;height:30px;border-radius:50%;background:<?= $rc['bg'] ?>;
                          color:<?= $rc['text'] ?>;display:flex;align-items:center;justify-content:center;
                          font-size:10px;font-weight:700;flex-shrink:0;">
                <?= sanitize($ini) ?>
              </div>
              <strong style="font-size:12px;"><?= sanitize($uu['full_name']) ?></strong>
            </div>
          </td>
          <td style="font-size:11px;color:#666;"><?= sanitize($uu['email']) ?></td>
          <?php if ($tab === 'student'): ?>
            <td style="font-size:11px;"><?= sanitize($uu['grade'] ?? '—') ?></td>
            <td style="font-size:11px;"><?= sanitize($uu['section'] ?? '—') ?></td>
          <?php endif; ?>
          <td><span class="badge badge-<?= $uu['status'] ?>"><?= $uu['status'] ?></span></td>
          <td style="font-size:11px;color:#aaa;">
            <?= $uu['last_login'] ? timeAgo($uu['last_login']) : 'Never' ?>
          </td>
          <td style="font-size:11px;color:#aaa;">
            <?= date('M j, Y', strtotime($uu['created_at'])) ?>
          </td>
          <td>
            <div style="display:flex;gap:4px;flex-wrap:wrap;">
              <?php if ($uu['status'] === 'pending'): ?>
                <a href="users.php?approve=<?= $uu['id'] ?>"
                   class="btn btn-success btn-sm">Approve</a>
                <a href="users.php?reject=<?= $uu['id'] ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Reject this request?')">Reject</a>
              <?php elseif ($uu['role'] !== 'admin' && $uu['status'] === 'approved'): ?>
                <a href="users.php?suspend=<?= $uu['id'] ?>"
                   class="btn btn-secondary btn-sm"
                   onclick="return confirm('Suspend this user?')">Suspend</a>
              <?php endif; ?>
              <?php if ($uu['id'] !== $u['id'] && $uu['role'] !== 'admin'): ?>
                <a href="users.php?delete_user=<?= $uu['id'] ?>&tab=<?= $tab ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Permanently delete <?= addslashes(sanitize($uu['full_name'])) ?>?')">
                  Delete
                </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:10px;font-size:11px;color:#aaa;text-align:right;">
    Showing <?= count($users) ?> <?= $tab ?>(s)
    <?= $search ? ' matching "' . sanitize($search) . '"' : '' ?>
  </div>
  <?php endif; ?>
</div>
</div>

<style>
.user-tabs{display:flex;gap:0;margin-bottom:18px;border-bottom:2px solid var(--border);}
.user-tab{
  padding:10px 20px;font-size:13px;font-weight:600;color:var(--gray);
  text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;
  transition:color .15s,border-color .15s;display:flex;align-items:center;gap:7px;
}
.user-tab:hover{color:var(--red);}
.user-tab.active{color:var(--red);border-bottom-color:var(--red);}
.tab-count{background:var(--red-soft);color:var(--red);border-radius:20px;
  padding:1px 8px;font-size:10px;font-weight:700;}
</style>

<script>
function toggleAddForm() {
  const f = document.getElementById('add-user-form');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>
<?php renderFooter(); ?>
