<?php
// ============================================================
//  includes/auth.php — Compatible with PHP 7.2+
//  Fixed: redirect() return type, session_set_cookie_params,
//         arrow functions, null coalescing
// ============================================================

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // session_set_cookie_params array form requires PHP 7.3+
        // Use individual ini_set calls for PHP 7.2 compatibility
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict'); // PHP 7.3+, ignored on older
        ini_set('session.use_strict_mode',  1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_trans_sid',    0);
        ini_set('session.gc_maxlifetime',   7200);

        session_start();

        if (!isset($_SESSION['__init'])) {
            session_regenerate_id(true);
            $_SESSION['__init'] = true;
        }
    }
}

// ── Auth checks ───────────────────────────────────────────────
function isLoggedIn(): bool {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    // 2-hour inactivity timeout
    if (isset($_SESSION['last_activity'])) {
        if ((time() - (int)$_SESSION['last_activity']) > 7200) {
            sessionClear();
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    $name     = (string)($_SESSION['user_name'] ?? '');
    $parts    = array_slice(explode(' ', $name), 0, 2);
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper($part[0]);
        }
    }
    return [
        'id'       => (int)$_SESSION['user_id'],
        'name'     => $name,
        'email'    => (string)($_SESSION['user_email'] ?? ''),
        'role'     => (string)($_SESSION['user_role']  ?? ''),
        'initials' => $initials,
    ];
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        startSession();
        $_SESSION['redirect_after_login'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        doRedirect('login.php', 'error', 'Please sign in to continue.');
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
    if (!in_array($role, $roles, true)) {
        $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        safeLogActivity($uid, 'unauthorized_access', 'Tried to access ' . $uri . ' as ' . $role);
        doRedirect('home.php', 'error', 'You do not have permission to access that page.');
    }
}

function isRole(string ...$roles): bool {
    if (!isLoggedIn()) {
        return false;
    }
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
    return in_array($role, $roles, true);
}

// ── Session ───────────────────────────────────────────────────
function sessionSet(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['user_name']     = (string)$user['full_name'];
    $_SESSION['user_email']    = (string)$user['email'];
    $_SESSION['user_role']     = (string)$user['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['__init']        = true;
}

function sessionClear(): void {
    startSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $p['path'],
            isset($p['domain']) ? $p['domain'] : '',
            isset($p['secure']) ? $p['secure'] : false,
            isset($p['httponly']) ? $p['httponly'] : true
        );
    }
    session_destroy();
}

// ── CSRF ──────────────────────────────────────────────────────
function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="'
         . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrf(): void {
    $token = '';
    if (isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    } elseif (isset($_GET['csrf_token'])) {
        $token = $_GET['csrf_token'];
    } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;">'
          . '<h2 style="color:#d62828;">Invalid Request</h2>'
          . '<p>Security token mismatch. '
          . '<a href="javascript:history.back()">Go back</a> and try again.</p>'
          . '</div>');
    }
}

// ── Rate limiting ─────────────────────────────────────────────
function checkLoginRateLimit(string $email): bool {
    startSession();
    $key      = 'login_attempts_' . md5($email);
    $attempts = isset($_SESSION[$key]) ? $_SESSION[$key] : ['count' => 0, 'first' => time()];
    if ((time() - (int)$attempts['first']) > 300) {
        return true; // window expired — allow
    }
    return (int)$attempts['count'] < 5;
}

function recordLoginFailure(string $email): void {
    startSession();
    $key      = 'login_attempts_' . md5($email);
    $attempts = isset($_SESSION[$key]) ? $_SESSION[$key] : ['count' => 0, 'first' => time()];
    if ((time() - (int)$attempts['first']) > 300) {
        $attempts = ['count' => 0, 'first' => time()];
    }
    $attempts['count'] = (int)$attempts['count'] + 1;
    $_SESSION[$key] = $attempts;
}

function clearLoginFailures(string $email): void {
    startSession();
    unset($_SESSION['login_attempts_' . md5($email)]);
}

function loginAttemptsLeft(string $email): int {
    startSession();
    $key      = 'login_attempts_' . md5($email);
    $attempts = isset($_SESSION[$key]) ? $_SESSION[$key] : ['count' => 0, 'first' => time()];
    if ((time() - (int)$attempts['first']) > 300) {
        return 5;
    }
    return max(0, 5 - (int)$attempts['count']);
}

// ── Flash messages ────────────────────────────────────────────
function flash(string $key, string $msg): void {
    startSession();
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$key] = $msg;
}

function getFlash(string $key): ?string {
    startSession();
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }
    $msg = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $msg;
}

// ── Helpers ───────────────────────────────────────────────────
function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    return floor($diff / 86400) . ' days ago';
}

// Safe activity logger — won't crash if DB not available
function safeLogActivity(int $userId, string $action, string $details = ''): void {
    try {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        DB::query(
            "INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?,?,?,?)",
            [$userId, $action, $details, $ip]
        );
    } catch (Exception $e) {
        error_log('Activity log error: ' . $e->getMessage());
    }
}

// Alias used throughout pages
function logActivity(int $userId, string $action, string $details = ''): void {
    safeLogActivity($userId, $action, $details);
}

// redirect() — use void instead of never (never requires PHP 8.1)
function doRedirect(string $url, string $flash_key = '', string $flash_msg = ''): void {
    if ($flash_key !== '' && $flash_msg !== '') {
        flash($flash_key, $flash_msg);
    }
    header('Location: ' . $url);
    exit();
}

// Keep redirect() as an alias for backward compatibility
function redirect(string $url, string $flash_key = '', string $flash_msg = ''): void {
    doRedirect($url, $flash_key, $flash_msg);
}
