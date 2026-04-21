<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
// PHP 7.2 compatible initials helper
if (!function_exists('getInitials')) {
    function getInitials(string $name): string {
        $parts = array_slice(explode(' ', trim($name)), 0, 2);
        $init  = '';
        foreach ($parts as $p) { if ($p !== '') $init .= strtoupper($p[0]); }
        return $init;
    }
}

requireLogin();
$u = currentUser();

// Load full user data
$user = DB::row("SELECT * FROM users WHERE id=?", [$u['id']]);

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_update_profile'])) {
    verifyCsrf();
    $name     = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email']     ?? '');
    $bio      = trim($_POST['bio']       ?? '');
    $location = trim($_POST['location']  ?? '');
    $grade    = trim($_POST['grade']     ?? '');
    $section  = trim($_POST['section']   ?? '');

    $errors = [];
    if (!$name)  $errors[] = 'Name is required.';
    if (!$email) $errors[] = 'Email is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if ($email !== $user['email'] && DB::val("SELECT COUNT(*) FROM users WHERE email=? AND id!=?", [$email,$u['id']])) {
        $errors[] = 'That email is already taken.';
    }

    if (empty($errors)) {
        DB::query(
            "UPDATE users SET full_name=?, email=?, bio=?, location=?, grade=?, section=? WHERE id=?",
            [$name,$email,$bio,$location,$grade,$section,$u['id']]
        );
        $_SESSION['user_name']  = $name;
        $_SESSION['user_email'] = $email;
        logActivity($u['id'], 'update_profile', 'Profile updated');
        redirect('profile.php', 'success', 'Profile updated successfully!');
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_change_pw'])) {
    verifyCsrf();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (!password_verify($current, $user['password'])) $errors[] = 'Current password is incorrect.';
    if (strlen($new) < 6) $errors[] = 'New password must be at least 6 characters.';
    if ($new !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        DB::query("UPDATE users SET password=? WHERE id=?", [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        logActivity($u['id'], 'change_password', 'Password changed');
        redirect('profile.php', 'success', 'Password changed successfully!');
    }
}

$roleColors = ['admin'=>['bg'=>'rgba(214,40,40,0.15)','text'=>'#d62828'],'teacher'=>['bg'=>'rgba(247,127,0,0.15)','text'=>'#e65100'],'student'=>['bg'=>'rgba(40,167,69,0.15)','text'=>'#1b5e20']];
$rc = $roleColors[$user['role']] ?? $roleColors['student'];
$initials = getInitials($user['full_name']);

renderHead('My Profile');
renderSidebar('profile');
renderPageHeader('My Profile', 'Manage your account information');
?>
<div class="page-content">
  <?php renderFlash(); ?>

  <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= sanitize($e) ?></div><?php endforeach; ?>
  <?php endif; ?>

  <div class="profile-wrap">

    <!-- Profile Info -->
    <div class="card">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px;">
        <div class="profile-avatar" style="background:<?= $rc['bg'] ?>;color:<?= $rc['text'] ?>">
          <?= $initials ?>
        </div>
        <div>
          <div style="font-size:17px;font-weight:700;color:var(--ink);"><?= sanitize($user['full_name']) ?></div>
          <span class="badge badge-<?= $user['role'] ?>" style="margin-top:4px;display:inline-block;"><?= $user['role'] ?></span>
        </div>
      </div>

      <form method="POST">
        <input type="hidden" name="_update_profile" value="1">
      <?= csrfField() ?>
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" required value="<?= sanitize($user['full_name']) ?>">
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required value="<?= sanitize($user['email']) ?>">
          </div>
        </div>
        <?php if ($user['role'] === 'student'): ?>
        <div class="form-grid">
          <div class="form-group">
            <label>Grade Level</label>
            <select name="grade">
              <option value="">— Select —</option>
              <?php foreach (['Grade 7','Grade 8','Grade 9','Grade 10'] as $g): ?>
                <option <?= $user['grade']===$g?'selected':'' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Section</label>
            <input type="text" name="section" placeholder="e.g. Rizal" value="<?= sanitize($user['section'] ?? '') ?>">
          </div>
        </div>
        <?php endif; ?>
        <div class="form-grid full">
          <div class="form-group">
            <label>Bio</label>
            <textarea name="bio" placeholder="Tell us about yourself…"><?= sanitize($user['bio'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label>Location / Purok</label>
          <select name="location">
            <option value="">— Select —</option>
            <?php foreach (['Phase 1','Phase 2A','PHASE 2B','PHASE 3A','PHASE 3B','PROPER','GAWAD KALINGGA','SOUTH SUMMIT'] as $loc): ?>
              <option <?= $user['location']===$loc?'selected':'' ?>><?= $loc ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" type="submit">Save Changes</button>
      </form>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card-title">🔑 Change Password</div>
      <form method="POST">
        <input type="hidden" name="_change_pw" value="1">
      <?= csrfField() ?>
        <div class="form-group" style="margin-bottom:12px;">
          <label>Current Password</label>
          <input type="password" name="current_password" required placeholder="Enter current password">
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" required placeholder="Min. 6 characters">
          </div>
          <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required placeholder="Repeat new password">
          </div>
        </div>
        <button class="btn btn-primary" type="submit">Update Password</button>
      </form>
    </div>

    <!-- Sign out -->
    <a href="logout.php" class="btn btn-secondary" style="width:100%;justify-content:center;margin-top:4px;">⏻ Sign Out</a>
  </div>
</div>
<?php renderFooter(); ?>
