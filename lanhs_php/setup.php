<?php
// ============================================================
//  setup.php — Run ONCE then DELETE
//  Initializes users, seeds sample data, runs ALTER TABLE
//  to add any new columns from the latest schema.
// ============================================================
require_once 'config.php';
require_once 'includes/db.php';

$results = [];

try {
    DB::query("SET SESSION max_allowed_packet = 134217728");

    // ── Ensure new columns exist (idempotent ALTERs) ──────────
    $alters = [
        // Books: cover image columns
        "ALTER TABLE books ADD COLUMN IF NOT EXISTS has_cover  TINYINT(1)   DEFAULT 0         AFTER pdf_data",
        "ALTER TABLE books ADD COLUMN IF NOT EXISTS cover_name VARCHAR(255)                   AFTER has_cover",
        "ALTER TABLE books ADD COLUMN IF NOT EXISTS cover_mime VARCHAR(100) DEFAULT 'image/jpeg' AFTER cover_name",
        "ALTER TABLE books ADD COLUMN IF NOT EXISTS cover_data MEDIUMBLOB                     AFTER cover_mime",
        // Announcements: section targeting
        "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS target_type    ENUM('all','section') DEFAULT 'all' AFTER author_id",
        "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS target_section VARCHAR(100) DEFAULT NULL           AFTER target_type",
        // Remove old emoji column if exists
        "ALTER TABLE books DROP COLUMN IF EXISTS emoji",
    ];
    foreach ($alters as $sql) {
        try { DB::query($sql); } catch (Exception $e) { /* column may already exist */ }
    }
    $results[] = ['✓', 'Database schema updated (new columns added)', '', ''];

    // ── Create/reset demo users ────────────────────────────────
    DB::query("DELETE FROM users WHERE email IN ('admin@lanhs.edu','teacher@lanhs.edu','student@lanhs.edu')");

    $users = [
        ['Maria Santos',  'admin@lanhs.edu',   password_hash('admin123',   PASSWORD_DEFAULT), 'admin',   'approved', '', ''],
        ['Ms. Dela Cruz', 'teacher@lanhs.edu', password_hash('teacher123', PASSWORD_DEFAULT), 'teacher', 'approved', '', ''],
        ['Juan Reyes',    'student@lanhs.edu', password_hash('student123', PASSWORD_DEFAULT), 'student', 'approved', 'Grade 8', 'Rosal'],
    ];
    foreach ($users as [$name,$email,$hash,$role,$status,$grade,$section]) {
        DB::query(
            "INSERT INTO users (full_name,email,password,role,status,grade,section) VALUES (?,?,?,?,?,?,?)",
            [$name,$email,$hash,$role,$status,$grade,$section]
        );
        $results[] = ['✓', "Created $role account", $email,
            $role==='admin'?'admin123':($role==='teacher'?'teacher123':'student123')];
    }

    // ── Seed books ─────────────────────────────────────────────
    $bookCount = (int)DB::val("SELECT COUNT(*) FROM books");
    if ($bookCount === 0) {
        $adminId = DB::val("SELECT id FROM users WHERE email='admin@lanhs.edu'");
        $books = [
            ['Algebra & Trigonometry',  'Dr. Ramos',       'Core algebra and trigonometry for junior high school.', 'Mathematics'],
            ['Kasaysayan ng Pilipinas', 'Prof. Villanueva', 'Philippine history from pre-colonial to modern era.',    'History'],
            ['General Biology',         'Dr. Mendoza',      'Introduction to cells, genetics, and ecosystems.',      'Science'],
            ['Philippine Literature',  'Bb. Santos',        'Anthology of Filipino literary works.',                 'Literature'],
            ['Introduction to ICT',     'Engr. Lim',        'Digital citizenship and technology basics.',            'Technology'],
            ['Physics for Junior High', 'Dr. Aquino',       "Kinematics, Newton's laws, and energy.",                'Science'],
            ['Filipino Gramatika',      'Gng. Bautista',    'Komprehensibong aralin sa gramatika ng Filipino.',      'Filipino'],
            ['World History',           'Prof. Garcia',     'Survey of world civilizations.',                        'History'],
        ];
        foreach ($books as [$t,$a,$d,$s]) {
            DB::query(
                "INSERT INTO books (title,author,description,subject,category,has_pdf,has_cover,added_by)
                 VALUES (?,?,?,?,?,0,0,?)",
                [$t,$a,$d,$s,$s,$adminId]
            );
        }
        $results[] = ['✓', count($books).' sample books added (no PDFs/covers yet)', 'Upload via Manage Books',''];
    } else {
        $results[] = ['–', "Books exist ($bookCount) — skipped", '', ''];
    }

    // ── Seed announcements ─────────────────────────────────────
    if ((int)DB::val("SELECT COUNT(*) FROM announcements") === 0) {
        $adminId   = DB::val("SELECT id FROM users WHERE email='admin@lanhs.edu'");
        $teacherId = DB::val("SELECT id FROM users WHERE email='teacher@lanhs.edu'");
        DB::query("INSERT INTO announcements (title,body,author_id,target_type) VALUES (?,?,?,'all')",
            ['Library Hours Extension', 'The LANHS eLibrary is now accessible 24/7. Use your registered credentials.', $adminId]);
        DB::query("INSERT INTO announcements (title,body,author_id,target_type,target_section) VALUES (?,?,?,'section',?)",
            ['Quiz Alert — Chapter 3', 'Written quiz on Cells and Cellular Processes on Thursday.', $teacherId, 'Grade 8 - Rosal']);
        $results[] = ['✓','2 sample announcements added (1 broadcast, 1 section-targeted)','',''];
    } else {
        $results[] = ['–','Announcements exist — skipped','',''];
    }

    // ── Seed section notes ─────────────────────────────────────
    if ((int)DB::val("SELECT COUNT(*) FROM section_notes") === 0) {
        $teacherId = DB::val("SELECT id FROM users WHERE email='teacher@lanhs.edu'");
        DB::query("INSERT INTO section_notes (section,title,body,author_id) VALUES (?,?,?,?)",
            ['Grade 7 - Sampaguita','Assignment #5','Complete pages 58–62 in your workbook. Submit Friday.',$teacherId]);
        $results[] = ['✓','1 sample section note added','',''];
    } else {
        $results[] = ['–','Notes exist — skipped','',''];
    }

} catch (Exception $e) {
    $results[] = ['✗', 'ERROR: '.$e->getMessage(), '', ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>LANHS eLibrary Setup</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Sora',sans-serif;background:#fff7f5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .box{background:#fff;border-radius:18px;box-shadow:0 4px 32px rgba(0,0,0,.12);padding:38px 44px;width:660px;max-width:100%}
  h1{color:#d62828;font-size:22px;margin-bottom:4px}
  .sub{color:#888;font-size:12px;margin-bottom:26px}
  table{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:22px}
  th{background:#f8f9fa;padding:9px 12px;text-align:left;font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.07em;border-bottom:2px solid #eee}
  td{padding:9px 12px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
  .ok{color:#28a745;font-weight:700}.err{color:#dc3545;font-weight:700}.skip{color:#bbb}
  .creds{background:#fff5f5;border:1px solid #fde8e8;border-radius:10px;padding:16px 20px;margin-bottom:18px}
  .creds h3{color:#d62828;font-size:11px;font-weight:700;margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em}
  .cr{display:flex;justify-content:space-between;align-items:center;font-size:12px;padding:6px 0;border-bottom:1px solid #fde8e8}
  .cr:last-child{border:none}.cr-label{color:#888}.cr-val{font-family:monospace;font-weight:600;color:#2f2f2f;background:#f8f8f8;padding:2px 7px;border-radius:4px}
  .warn{background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:12px 16px;font-size:12px;color:#7a5c00;margin-bottom:18px;line-height:1.6}
  .btn{display:inline-block;padding:12px 28px;background:#d62828;color:#fff;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none}
  code{background:#f0f0f0;padding:1px 5px;border-radius:3px;font-size:11px}
</style>
</head>
<body>
<div class="box">
  <h1>LANHS eLibrary — Setup</h1>
  <p class="sub">Initializing database…</p>
  <table>
    <thead><tr><th></th><th>Action</th><th>Detail</th><th>Password</th></tr></thead>
    <tbody>
    <?php foreach ($results as [$icon,$msg,$detail,$pass]): ?>
    <tr>
      <td class="<?= $icon==='✓'?'ok':($icon==='✗'?'err':'skip') ?>"><?= $icon ?></td>
      <td><?= htmlspecialchars($msg) ?></td>
      <td style="font-family:monospace;font-size:11px;color:#555"><?= htmlspecialchars($detail) ?></td>
      <td style="font-family:monospace;font-size:11px;font-weight:600"><?= htmlspecialchars($pass) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="creds">
    <h3>Login Credentials</h3>
    <div class="cr"><span class="cr-label">Admin</span><span class="cr-val">admin@lanhs.edu / admin123</span></div>
    <div class="cr"><span class="cr-label">Teacher</span><span class="cr-val">teacher@lanhs.edu / teacher123</span></div>
    <div class="cr"><span class="cr-label">Student (Grade 8 - Rosal)</span><span class="cr-val">student@lanhs.edu / student123</span></div>
  </div>

  <div class="warn">
    <strong>What to do next:</strong><br>
    1. Click "Go to Login" and sign in with the admin account.<br>
    2. Go to <strong>Manage Books</strong> to upload book covers (JPG/PNG) and PDFs.<br>
    3. Go to <strong>Announcements</strong> to try section-targeted posting.<br>
    4. Go to <strong>User Management</strong> to see the tabbed view of students, teachers, admins.<br><br>
    <strong>Delete <code>setup.php</code> from your server after confirming login works.</strong><br><br>
    If PDF uploads fail, add to <code>my.ini</code> (MySQL): <code>max_allowed_packet = 128M</code>
  </div>

  <a href="login.php" class="btn">Go to Login →</a>
</div>
</body>
</html>
