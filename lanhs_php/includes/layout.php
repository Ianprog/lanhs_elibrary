<?php

// ── Compatibility helper (PHP 7.2+) ──────────────────────────
if (!function_exists('getInitials')) {
    function getInitials(string $name): string {
        $parts    = array_slice(explode(' ', trim($name)), 0, 2);
        $initials = '';
        foreach ($parts as $part) {
            if ($part !== '') $initials .= strtoupper($part[0]);
        }
        return $initials;
    }
}

// ============================================================
//  includes/layout.php — Shared sidebar layout (SVG icons)
// ============================================================

function renderHead(string $title = 'LANHS eLibrary'): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<!-- Security headers via meta (Apache .htaccess handles the real ones) -->
<meta http-equiv="X-Content-Type-Options" content="nosniff">
<meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
<title><?= sanitize($title) ?> — LANHS eLibrary</title>
<link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<?php } ?>

<?php
// ── SVG icon helper ───────────────────────────────────────────
function icon(string $name, string $cls = ''): string {
    $file = __DIR__ . '/../assets/img/icon-' . $name . '.svg';
    if (!file_exists($file)) return '';
    $svg = file_get_contents($file);
    if ($cls) $svg = str_replace('<svg ', '<svg class="' . htmlspecialchars($cls) . '" ', $svg);
    return $svg;
}

// ── Nav definitions ───────────────────────────────────────────
function renderSidebar(string $active = ''): void {
    $u    = currentUser();
    $role = $u['role'];

    $nav = [
        'student' => [
            ['section' => 'Explore', 'items' => [
                ['page' => 'home',          'icon' => 'library',   'label' => 'Library'],
                ['page' => 'announcements', 'icon' => 'announce',  'label' => 'Announcements'],
                ['page' => 'notes',         'icon' => 'notes',     'label' => 'Section Notes'],
                ['page' => 'favorites',     'icon' => 'favorites', 'label' => 'My Favorites'],
            ]],
            ['section' => 'Account', 'items' => [
                ['page' => 'profile', 'icon' => 'profile', 'label' => 'My Profile'],
            ]],
        ],
        'teacher' => [
            ['section' => 'Explore', 'items' => [
                ['page' => 'home',          'icon' => 'library',   'label' => 'Library'],
                ['page' => 'announcements', 'icon' => 'announce',  'label' => 'Announcements'],
                ['page' => 'notes',         'icon' => 'notes',     'label' => 'Section Notes'],
                ['page' => 'notepad',       'icon' => 'notepad',   'label' => 'My Notepad'],
                ['page' => 'favorites',     'icon' => 'favorites', 'label' => 'My Favorites'],
            ]],
            ['section' => 'Manage', 'items' => [
                ['page' => 'manage_books', 'icon' => 'manage', 'label' => 'Add Books'],
            ]],
            ['section' => 'Account', 'items' => [
                ['page' => 'profile', 'icon' => 'profile', 'label' => 'My Profile'],
            ]],
        ],
        'admin' => [
            ['section' => 'Overview', 'items' => [
                ['page' => 'dashboard',     'icon' => 'dashboard', 'label' => 'Dashboard'],
                ['page' => 'home',          'icon' => 'library',   'label' => 'Library'],
                ['page' => 'announcements', 'icon' => 'announce',  'label' => 'Announcements'],
            ]],
            ['section' => 'Manage', 'items' => [
                ['page' => 'manage_books', 'icon' => 'manage',  'label' => 'Manage Books'],
                ['page' => 'users',         'icon' => 'profile', 'label' => 'User Management'],
                ['page' => 'notes',        'icon' => 'notes',   'label' => 'Section Notes'],
            ]],
            ['section' => 'Account', 'items' => [
                ['page' => 'profile', 'icon' => 'profile', 'label' => 'My Profile'],
            ]],
        ],
    ];

    $roleColors = [
        'admin'   => ['bg' => 'rgba(214,40,40,0.2)',  'text' => '#d62828'],
        'teacher' => ['bg' => 'rgba(247,127,0,0.2)',  'text' => '#e65100'],
        'student' => ['bg' => 'rgba(40,167,69,0.2)',  'text' => '#1b5e20'],
    ];
    $rc = $roleColors[$role] ?? $roleColors['student'];
?>
<div class="app-layout">
<aside class="sidebar">
  <div class="sb-header">
    <div class="sb-logo">
      <div class="sb-logo-icon">
        <img src="assets/img/logo.svg" alt="LANHS" width="22" height="22">
      </div>
      <div>
        <strong class="sb-logo-title">LANHS eLibrary</strong>
        <span class="sb-logo-sub"><?= ucfirst($role) ?> Portal</span>
      </div>
    </div>
  </div>

  <nav class="sb-nav">
    <?php foreach ($nav[$role] ?? [] as $group): ?>
      <div class="sb-section-label"><?= sanitize($group['section']) ?></div>
      <?php foreach ($group['items'] as $item): ?>
        <a href="<?= sanitize($item['page']) ?>.php"
           class="sb-item <?= $active === $item['page'] ? 'active' : '' ?>">
          <span class="sb-item-icon"><?= icon($item['icon'], 'nav-icon') ?></span>
          <?= sanitize($item['label']) ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-avatar" style="background:<?= $rc['bg'] ?>;color:<?= $rc['text'] ?>">
        <?= sanitize($u['initials']) ?>
      </div>
      <div class="sb-user-info">
        <strong><?= sanitize($u['name']) ?></strong>
        <span><?= sanitize($role) ?></span>
      </div>
      <a href="logout.php" class="sb-logout" title="Sign out">
        <?= icon('logout', 'nav-icon') ?>
      </a>
    </div>
  </div>
</aside>
<div class="app-main">
<?php } ?>

<?php
function renderPageHeader(string $title, string $subtitle = '', bool $showSearch = false, string $badge = ''): void { ?>
<div class="page-header">
  <div class="page-header-left">
    <h1><?= sanitize($title) ?></h1>
    <?php if ($subtitle): ?><p><?= sanitize($subtitle) ?></p><?php endif; ?>
  </div>
  <?php if ($showSearch): ?>
  <form method="GET" class="header-search">
    <span class="search-icon"><?= icon('search', 'nav-icon') ?></span>
    <input type="text" name="search" placeholder="Search books, authors…"
           value="<?= sanitize($_GET['search'] ?? '') ?>">
    <select name="subject" onchange="this.form.submit()">
      <option value="">All Subjects</option>
      <?php foreach (['Mathematics','Science','Literature','History','Technology','Filipino'] as $s): ?>
        <option <?= ($_GET['subject'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Search</button>
  </form>
  <?php endif; ?>
  <?php if ($badge): ?>
    <span class="header-badge"><?= sanitize($badge) ?></span>
  <?php endif; ?>
</div>
<?php } ?>

<?php
function renderFlash(): void {
    $success = getFlash('success');
    $error   = getFlash('error');
    if ($success): ?>
      <div class="alert alert-success"><?= sanitize($success) ?></div>
    <?php endif;
    if ($error): ?>
      <div class="alert alert-error"><?= sanitize($error) ?></div>
    <?php endif;
} ?>

<?php
function renderFooter(): void { ?>
</div><!-- .app-main -->
</div><!-- .app-layout -->
<script src="assets/js/app.js"></script>
</body>
</html>
<?php } ?>
