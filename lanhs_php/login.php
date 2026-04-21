<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
startSession();

if (isLoggedIn()) {
    $dest = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin')
            ? 'dashboard.php' : 'home.php';
    header('Location: ' . $dest); exit();
}

$mode  = (isset($_GET['mode']) && $_GET['mode'] === 'signup') ? 'signup' : 'login';
$error = '';
$info  = '';

// ── LOGIN ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_login'])) {
    $email = strtolower(trim(isset($_POST['email'])    ? $_POST['email']    : ''));
    $pass  =           trim(isset($_POST['password'])  ? $_POST['password'] : '');

    if (!$email || !$pass) {
        $error = 'Please enter your email and password.';
    } elseif (!checkLoginRateLimit($email)) {
        $error = 'Too many failed attempts. Please wait 5 minutes.';
    } else {
        $user = DB::row("SELECT * FROM users WHERE email = ?", [$email]);
        if (!$user || !password_verify($pass, $user['password'])) {
            recordLoginFailure($email);
            $left  = loginAttemptsLeft($email);
            $error = 'Invalid email or password.' . ($left < 5 ? " ($left attempts left)" : '');
        } elseif ($user['status'] === 'pending') {
            $error = 'Your account is awaiting admin approval.';
        } elseif ($user['status'] === 'rejected') {
            $error = 'Your account was rejected. Contact the administrator.';
        } else {
            clearLoginFailures($email);
            sessionSet($user);
            DB::query("UPDATE users SET last_login = NOW() WHERE id = ?", [(int)$user['id']]);
            logActivity((int)$user['id'], 'login', 'Signed in');
            $dest = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : '';
            unset($_SESSION['redirect_after_login']);
            if (!$dest) $dest = ($user['role'] === 'admin') ? 'dashboard.php' : 'home.php';
            header('Location: ' . $dest); exit();
        }
    }
}

// ── SIGNUP ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_signup'])) {
    $name    = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
    $email   = strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
    $role    = (isset($_POST['role']) && in_array($_POST['role'], ['student','teacher'], true))
               ? $_POST['role'] : 'student';
    $pass    = isset($_POST['password']) ? $_POST['password'] : '';
    $terms   = isset($_POST['agree_terms']) ? $_POST['agree_terms'] : '';
    $mode    = 'signup';

    if (!$name || !$email || !$pass) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!$terms) {
        $error = 'You must agree to the Terms and Conditions.';
    } elseif (DB::val("SELECT COUNT(*) FROM users WHERE email = ?", [$email])) {
        $error = 'That email is already registered.';
    } else {
        DB::query(
            "INSERT INTO users (full_name, email, password, role, status) VALUES (?,?,?,?,'pending')",
            [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]
        );
        logActivity(0, 'signup_request', "$name ($email) → $role");
        $info = 'Request submitted! An admin will review and activate your account.';
        $mode = 'login';
    }
}

$flash_error   = getFlash('error');
$flash_success = getFlash('success');
$csrf          = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $mode === 'signup' ? 'Request Access' : 'Sign In' ?> — LANHS eLibrary</title>
<link rel="icon" href="assets/img/logo1.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
<style>
/* ── Login page layout ───────────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;font-family:'Sora',sans-serif;}
body{display:grid;grid-template-columns:1fr 420px;min-height:100vh;background:#f8f9fa;}

/* Left: school photo hero */
.login-hero{
  position:relative;overflow:hidden;
  background:#1a1a1a;
}
.login-hero-bg{
  position:absolute;inset:0;
  background-image:url('assets/img/school1.jpg');
  background-size:cover;background-position:center;
  filter:brightness(0.45);
  transition:opacity 1.2s ease;
}
.login-hero-bg.slide2{background-image:url('assets/img/school2.jpg');}
.login-hero-overlay{
  position:absolute;inset:0;
  background:linear-gradient(to bottom,rgba(214,40,40,0.25) 0%,rgba(0,0,0,0.6) 100%);
}
.login-hero-content{
  position:relative;z-index:2;
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  height:100%;padding:48px;text-align:center;color:#fff;
}
.login-hero-logo{
  width:120px;height:120px;border-radius:50%;
  border:4px solid rgba(255,255,255,0.3);
  object-fit:cover;margin-bottom:28px;
  box-shadow:0 8px 32px rgba(0,0,0,0.4);
}
.login-hero-title{
  font-size:clamp(22px,3vw,34px);font-weight:700;
  line-height:1.2;margin-bottom:10px;
  text-shadow:0 2px 12px rgba(0,0,0,0.6);
}
.login-hero-sub{
  font-size:14px;opacity:0.85;
  text-shadow:0 1px 6px rgba(0,0,0,0.5);
  margin-bottom:32px;line-height:1.5;
}
.login-hero-dots{display:flex;gap:8px;justify-content:center;}
.hero-dot{
  width:8px;height:8px;border-radius:50%;
  background:rgba(255,255,255,0.35);cursor:pointer;
  transition:background .2s,transform .2s;border:none;padding:0;
}
.hero-dot.active{background:#fff;transform:scale(1.2);}
.login-hero-stats{
  display:flex;gap:24px;margin-bottom:32px;flex-wrap:wrap;justify-content:center;
}
.hero-stat{
  background:rgba(255,255,255,0.12);
  backdrop-filter:blur(8px);
  border:1px solid rgba(255,255,255,0.2);
  border-radius:12px;padding:12px 20px;text-align:center;
}
.hero-stat-num{font-size:24px;font-weight:700;color:#fff;}
.hero-stat-lbl{font-size:10px;opacity:0.7;text-transform:uppercase;letter-spacing:0.08em;}

/* Right: form panel */
.login-panel{
  background:#fff;display:flex;flex-direction:column;
  justify-content:center;padding:48px 40px;
  overflow-y:auto;
}
.login-panel-logo{display:flex;align-items:center;gap:12px;margin-bottom:36px;}
.login-panel-logo img{width:44px;height:44px;border-radius:50%;object-fit:cover;}
.login-panel-logo strong{font-size:15px;color:#d62828;font-weight:700;display:block;}
.login-panel-logo span{font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:.08em;}

.form-title{font-size:22px;font-weight:700;color:#1a1a1a;margin-bottom:4px;}
.form-sub{font-size:12px;color:#888;margin-bottom:28px;}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:11px;font-weight:600;color:#555;
  text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
.form-group input,.form-group select{
  width:100%;padding:12px 14px;border:1.5px solid #e5e7eb;
  border-radius:10px;font-size:13px;font-family:'Sora',sans-serif;
  outline:none;transition:border .2s,box-shadow .2s;background:#fafafa;
}
.form-group input:focus,.form-group select:focus{
  border-color:#d62828;background:#fff;
  box-shadow:0 0 0 3px rgba(214,40,40,0.08);
}
.login-btn{
  width:100%;padding:13px;background:#d62828;color:#fff;
  border:none;border-radius:10px;font-size:14px;font-weight:700;
  font-family:'Sora',sans-serif;cursor:pointer;margin-top:6px;
  transition:background .2s,transform .15s;letter-spacing:.02em;
}
.login-btn:hover{background:#b81f2b;transform:translateY(-1px);}
.login-toggle{text-align:center;margin-top:18px;font-size:12px;color:#888;}
.login-toggle a{color:#d62828;font-weight:600;text-decoration:none;}
.demo-note{
  background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;
  padding:10px 14px;font-size:11px;color:#888;margin-top:16px;line-height:1.6;
}
.alert{padding:11px 14px;border-radius:8px;font-size:12px;margin-bottom:16px;}
.alert-error{background:#fee2e2;color:#b91c1c;border-left:4px solid #dc3545;}
.alert-success{background:#d1fae5;color:#065f46;border-left:4px solid #10b981;}

/* Password strength */
.pw-bar-wrap{height:4px;background:#eee;border-radius:2px;margin-top:6px;overflow:hidden;}
.pw-bar{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s;}
.pw-lbl{font-size:10px;margin-top:4px;}

/* Terms modal */
.terms-backdrop{
  position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1000;
  display:none;align-items:center;justify-content:center;padding:20px;
}
.terms-backdrop.open{display:flex;}
.terms-box{
  background:#fff;border-radius:16px;width:640px;max-width:100%;
  max-height:88vh;display:flex;flex-direction:column;
  box-shadow:0 20px 60px rgba(0,0,0,0.25);
}
.terms-hdr{
  padding:20px 24px 14px;border-bottom:1px solid #eee;
  display:flex;align-items:center;gap:12px;
}
.terms-hdr-icon{width:38px;height:38px;background:#fee2e2;border-radius:10px;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.terms-hdr-icon svg{width:18px;height:18px;stroke:#d62828;}
.terms-hdr h2{font-size:16px;font-weight:700;color:#1a1a1a;}
.terms-hdr p{font-size:11px;color:#888;margin-top:2px;}
.terms-body{padding:20px 24px;overflow-y:auto;flex:1;font-size:12.5px;line-height:1.75;color:#444;}
.terms-body h3{font-size:13px;font-weight:700;color:#d62828;margin:18px 0 8px;
  border-left:3px solid #d62828;padding-left:10px;}
.terms-body h3:first-child{margin-top:0;}
.terms-body p{margin-bottom:10px;}
.terms-body ul{padding-left:18px;margin-bottom:10px;}
.terms-body li{margin-bottom:5px;}
.terms-ftr{
  padding:14px 24px;border-top:1px solid #eee;
  display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
}
.terms-ftr label{display:flex;align-items:center;gap:9px;font-size:12px;font-weight:600;cursor:pointer;}
.terms-ftr input[type=checkbox]{width:16px;height:16px;accent-color:#d62828;cursor:pointer;}
.terms-accept{
  background:#d62828;color:#fff;border:none;border-radius:8px;
  padding:10px 22px;font-size:12px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer;
}
.terms-accept:disabled{background:#ccc;cursor:not-allowed;}
.terms-close{
  background:#f0f0f0;color:#555;border:none;border-radius:8px;
  padding:10px 16px;font-size:12px;font-weight:600;font-family:'Sora',sans-serif;cursor:pointer;
}
/* Terms indicator */
.terms-row{
  margin-top:14px;padding:12px 14px;
  background:#fff8f8;border:1.5px solid #fde8e8;border-radius:10px;
  display:flex;align-items:center;gap:10px;cursor:pointer;transition:border-color .2s;
}
.terms-row:hover{border-color:#d62828;}
.terms-check{
  width:22px;height:22px;border-radius:5px;border:2px solid #ddd;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;transition:all .2s;
}
.terms-check.agreed{background:#d62828;border-color:#d62828;}
.terms-check.agreed svg{display:block;}
.terms-check svg{display:none;width:12px;height:12px;stroke:#fff;stroke-width:2.5;}

@media(max-width:860px){
  body{grid-template-columns:1fr;}
  .login-hero{display:none;}
  .login-panel{padding:40px 28px;}
}
</style>
</head>
<body>

<!-- ═══ LEFT: Hero Panel ═══════════════════════════════════ -->
<div class="login-hero">
  <div class="login-hero-bg" id="hero-bg"></div>
  <div class="login-hero-overlay"></div>
  <div class="login-hero-content">
    <img src="assets/img/logo1.png" alt="LANHS Logo" class="login-hero-logo">
    <h1 class="login-hero-title">Luis Aguado<br>National High School</h1>
    <p class="login-hero-sub">Brgy. Aguado, Trece Martires City, Cavite<br>Est. 2007</p>
    <div class="login-hero-stats">
      <div class="hero-stat">
        <div class="hero-stat-num">📚</div>
        <div class="hero-stat-lbl">Digital Library</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num">🎓</div>
        <div class="hero-stat-lbl">For Students</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num">📝</div>
        <div class="hero-stat-lbl">Teachers Notes</div>
      </div>
    </div>
    <div class="login-hero-dots">
      <button class="hero-dot active" onclick="setSlide(0)"></button>
      <button class="hero-dot" onclick="setSlide(1)"></button>
    </div>
  </div>
</div>

<!-- ═══ RIGHT: Form Panel ══════════════════════════════════ -->
<div class="login-panel">
  <div class="login-panel-logo">
    <img src="assets/img/logo1.png" alt="Logo">
    <div>
      <strong>LANHS eLibrary</strong>
      <span>Luis Aguado National High School</span>
    </div>
  </div>

  <?php if ($mode === 'login'): ?>
    <h2 class="form-title">Welcome back</h2>
    <p class="form-sub">Sign in to access your school library</p>

    <?php if ($error || $flash_error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error ?: $flash_error) ?></div>
    <?php endif; ?>
    <?php if ($info || $flash_success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($info ?: $flash_success) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="on">
      <input type="hidden" name="_login" value="1">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" required autocomplete="email"
               placeholder="your@email.com"
               value="<?= htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : '') ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required autocomplete="current-password"
               placeholder="Enter your password">
      </div>
      <button class="login-btn" type="submit">Sign In →</button>
    </form>

    <div class="login-toggle">
      No account yet? <a href="login.php?mode=signup">Request Access</a>
    </div>
    <div class="demo-note">
      <strong>Demo accounts:</strong><br>
      admin@lanhs.edu &nbsp;/&nbsp; teacher@lanhs.edu &nbsp;/&nbsp; student@lanhs.edu<br>
      Default password: <code>admin123</code>
    </div>

  <?php else: ?>
    <h2 class="form-title">Request Access</h2>
    <p class="form-sub">Admin will review and approve your account</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php?mode=signup" id="signup-form">
      <input type="hidden" name="_signup"      value="1">
      <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="agree_terms"  id="agree_terms_hidden" value="">

      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="full_name" required placeholder="Your full name"
               value="<?= htmlspecialchars(isset($_POST['full_name']) ? $_POST['full_name'] : '') ?>">
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" required placeholder="your@email.com"
               value="<?= htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : '') ?>">
      </div>
      <div class="form-group">
        <label>I am a…</label>
        <select name="role">
          <option value="student">Student</option>
          <option value="teacher">Teacher</option>
        </select>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" id="pw-input" required
               placeholder="Min. 8 characters"
               oninput="checkPwStrength(this.value)">
        <div class="pw-bar-wrap"><div class="pw-bar" id="pw-bar"></div></div>
        <div class="pw-lbl" id="pw-lbl" style="color:#aaa;"></div>
      </div>

      <!-- Terms row -->
      <div class="terms-row" onclick="openTerms()" id="terms-row">
        <div class="terms-check" id="terms-check">
          <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        </div>
        <span style="font-size:12px;color:#444;">
          I agree to the
          <strong style="color:#d62828;">Terms and Conditions</strong>
        </span>
        <span id="terms-status" style="margin-left:auto;font-size:10px;color:#aaa;">Click to read</span>
      </div>
      <div id="terms-err" style="font-size:11px;color:#d62828;margin-top:5px;display:none;">
        Please read and accept the Terms and Conditions.
      </div>

      <button class="login-btn" type="button" onclick="doSignup()" style="margin-top:16px;">
        Submit Request
      </button>
    </form>

    <div class="login-toggle">
      Already have an account? <a href="login.php">Sign In</a>
    </div>
  <?php endif; ?>
</div>

<!-- ═══ Terms Modal ════════════════════════════════════════ -->
<div class="terms-backdrop" id="terms-modal">
  <div class="terms-box">
    <div class="terms-hdr">
      <div class="terms-hdr-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </div>
      <div>
        <h2>Terms and Conditions</h2>
        <p>LANHS eLibrary · Luis Aguado National High School · Please read carefully</p>
      </div>
    </div>
    <div class="terms-body">
      <h3>1. Acceptance of Terms</h3>
      <p>By registering for the LANHS eLibrary, you agree to these Terms. If you disagree, you may not use this system. These terms apply to all students, teachers, and administrators of Luis Aguado National High School.</p>
      <h3>2. Eligibility</h3>
      <p>Access is restricted to enrolled students, faculty, and authorized staff of LANHS. You confirm all registration information is accurate. All accounts require administrator approval before activation.</p>
      <h3>3. Acceptable Use</h3>
      <p>The eLibrary is for educational purposes only. You must NOT:</p>
      <ul>
        <li>Share your login credentials with anyone</li>
        <li>Access accounts or data you are not authorized to view</li>
        <li>Upload or distribute illegal, harmful, or offensive content</li>
        <li>Attempt to bypass system security measures</li>
        <li>Use the system for commercial purposes</li>
      </ul>
      <h3>4. Copyright and Intellectual Property</h3>
      <p>All books and materials in this library are protected by copyright. You may only access and read materials for personal educational use. Downloading, copying, or distributing copyrighted materials without authorization is strictly prohibited.</p>
      <h3>5. Privacy and Data</h3>
      <p>We collect your name, email, role, and activity logs solely to provide library services, monitor security, and comply with school requirements. Your data will not be shared outside school administration without consent, except as required by Philippine law.</p>
      <h3>6. Account Security</h3>
      <p>You are responsible for maintaining your password confidentiality. Use a strong password (min. 8 characters with uppercase and numbers). Report unauthorized account use to the administrator immediately. Log out when using shared devices.</p>
      <h3>7. Student Responsibilities</h3>
      <ul>
        <li>Use library resources only for educational purposes</li>
        <li>Respect intellectual property rights of all authors</li>
        <li>Follow teacher guidance on assigned reading materials</li>
        <li>Report technical issues or inappropriate content to admin</li>
      </ul>
      <h3>8. Teacher Responsibilities</h3>
      <ul>
        <li>Only upload legally licensed educational materials</li>
        <li>Use announcements and notes for legitimate educational purposes</li>
        <li>Do not upload inappropriate or offensive content</li>
      </ul>
      <h3>9. Disciplinary Action</h3>
      <p>The school administration may suspend or terminate any account violating these terms. Violations may also result in disciplinary action under the school's Code of Conduct. Grounds include but are not limited to: sharing credentials, copyright infringement, unauthorized access, or uploading inappropriate content.</p>
      <h3>10. Governing Law</h3>
      <p>These terms are governed by LANHS policies and applicable Philippine law including the Cybercrime Prevention Act of 2012 (RA 10175) and the Data Privacy Act of 2012 (RA 10173).</p>
      <p style="margin-top:18px;padding:10px;background:#f9fafb;border-radius:6px;font-size:11px;color:#888;">
        Last updated: 2025 · Luis Aguado National High School, Brgy. Aguado, Trece Martires City, Cavite
      </p>
    </div>
    <div class="terms-ftr">
      <label>
        <input type="checkbox" id="terms-cb" onchange="toggleTermsAccept()">
        I have read and understood all terms
      </label>
      <div style="display:flex;gap:8px;">
        <button class="terms-close" onclick="closeTerms()">Close</button>
        <button class="terms-accept" id="terms-accept" disabled onclick="acceptTerms()">
          I Agree &amp; Accept
        </button>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
// ── Hero slideshow ─────────────────────────────────────────────
var slides  = ['assets/img/school1.jpg', 'assets/img/school2.jpg'];
var curSlide = 0;
var heroEl   = document.getElementById('hero-bg');
var dots     = document.querySelectorAll('.hero-dot');

function setSlide(n) {
  curSlide = n;
  if (heroEl) heroEl.style.backgroundImage = "url('" + slides[n] + "')";
  for (var i = 0; i < dots.length; i++) {
    dots[i].classList.toggle('active', i === n);
  }
}
setInterval(function() { setSlide((curSlide + 1) % slides.length); }, 5000);

// ── Terms ──────────────────────────────────────────────────────
var termsAccepted = false;

function openTerms() {
  document.getElementById('terms-modal').classList.add('open');
  document.getElementById('terms-cb').checked = false;
  document.getElementById('terms-accept').disabled = true;
}
function closeTerms() {
  document.getElementById('terms-modal').classList.remove('open');
}
function toggleTermsAccept() {
  document.getElementById('terms-accept').disabled = !document.getElementById('terms-cb').checked;
}
function acceptTerms() {
  termsAccepted = true;
  document.getElementById('agree_terms_hidden').value = '1';
  var chk = document.getElementById('terms-check');
  chk.classList.add('agreed');
  document.getElementById('terms-status').textContent = 'Agreed ✓';
  document.getElementById('terms-status').style.color = '#28a745';
  document.getElementById('terms-err').style.display = 'none';
  closeTerms();
}
document.getElementById('terms-modal').addEventListener('click', function(e) {
  if (e.target === this) closeTerms();
});

// ── Signup submit ──────────────────────────────────────────────
function doSignup() {
  if (!termsAccepted) {
    document.getElementById('terms-err').style.display = 'block';
    document.getElementById('terms-row').style.borderColor = '#d62828';
    return;
  }
  document.getElementById('signup-form').submit();
}

// ── Password strength ──────────────────────────────────────────
function checkPwStrength(pw) {
  var bar = document.getElementById('pw-bar');
  var lbl = document.getElementById('pw-lbl');
  if (!pw) { bar.style.width = '0'; lbl.textContent = ''; return; }
  var score = 0;
  if (pw.length >= 8)  score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  var lvls = [
    {c:'#dc3545',l:'Too weak',   w:'20%'},
    {c:'#fd7e14',l:'Weak',       w:'40%'},
    {c:'#ffc107',l:'Fair',       w:'60%'},
    {c:'#28a745',l:'Strong',     w:'80%'},
    {c:'#0d6efd',l:'Very strong',w:'100%'},
  ];
  var lvl = lvls[Math.min(score, 4)];
  bar.style.width = lvl.w; bar.style.background = lvl.c;
  lbl.style.color = lvl.c; lbl.textContent = lvl.l;
}
</script>
</body>
</html>
