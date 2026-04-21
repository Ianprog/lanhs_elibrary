<?php
// ============================================================
//  manage_books.php
//  Fully rewritten to fix blank page on submit
// ============================================================

// Turn on output buffering and error display FIRST
// so any crash shows an error page instead of blank white
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';

requireRole('admin', 'teacher');
$currentUser = currentUser();

// ── Helpers ───────────────────────────────────────────────────
function mb_fmtBytes(int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB'];
    $i     = (int)floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
}

/**
 * Process a file upload field.
 * Returns array with keys: ok, data, name, mime, size, error
 */
function mb_receiveFile(string $field, array $exts, int $maxBytes): array {
    $empty = ['ok'=>false,'data'=>null,'name'=>null,'mime'=>null,'size'=>0,'error'=>null];

    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE
        || empty($_FILES[$field]['name'])) {
        return $empty; // nothing selected — not an error
    }

    $code = (int)$_FILES[$field]['error'];
    if ($code !== UPLOAD_ERR_OK) {
        $msgs = [
            1 => 'File too large (server upload_max_filesize limit). Edit php.ini or .user.ini.',
            2 => 'File too large (form MAX_FILE_SIZE).',
            3 => 'File only partially uploaded — try again.',
            6 => 'No temp folder on server.',
            7 => 'Cannot write to server disk.',
        ];
        return array_merge($empty, ['error' => isset($msgs[$code]) ? $msgs[$code] : "Upload error code $code."]);
    }

    $tmp  = $_FILES[$field]['tmp_name'];
    $name = $_FILES[$field]['name'];

    if (!is_uploaded_file($tmp)) {
        return array_merge($empty, ['error' => 'Security: not a valid upload.']);
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true)) {
        return array_merge($empty, ['error' => ".$ext files are not allowed. Use: " . implode(', ', $exts)]);
    }

    $size = (int)filesize($tmp);
    if ($size <= 0) {
        return array_merge($empty, ['error' => 'Uploaded file is empty.']);
    }
    if ($size > $maxBytes) {
        return array_merge($empty, ['error' => 'Too large (' . mb_fmtBytes($size) . '). Max: ' . mb_fmtBytes($maxBytes)]);
    }

    $data = file_get_contents($tmp);
    if ($data === false) {
        return array_merge($empty, ['error' => 'Could not read uploaded file.']);
    }

    // MIME from extension (most reliable on XAMPP/Windows)
    $mimeMap = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',  'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];
    $mime = isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream';

    return ['ok'=>true, 'data'=>$data, 'name'=>basename($name),
            'mime'=>$mime, 'size'=>strlen($data), 'error'=>null];
}

// ── Delete ────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    verifyCsrf();
    $delId = (int)$_GET['delete'];
    $book  = DB::row("SELECT title FROM books WHERE id=?", [$delId]);
    if ($book) {
        DB::query("DELETE FROM books WHERE id=?", [$delId]);
        logActivity($currentUser['id'], 'delete_book', $book['title']);
    }
    redirect('manage_books.php', 'success', $book ? "Book deleted." : "Not found.");
}

// ── Edit: load existing book ──────────────────────────────────
$editBook = null;
if (isset($_GET['edit'])) {
    $editBook = DB::row(
        "SELECT id,title,author,description,subject,has_pdf,pdf_name,pdf_size,has_cover
         FROM books WHERE id=?",
        [(int)$_GET['edit']]
    );
}

// ── Form values helper (no closure — PHP 7.x safe) ───────────
$posted  = [];
$errors  = [];

function fv(string $k): string {
    global $errors, $posted, $editBook;
    if (!empty($errors) && isset($posted[$k])) return sanitize((string)$posted[$k]);
    if ($editBook && isset($editBook[$k]))      return sanitize((string)$editBook[$k]);
    return '';
}

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_add_book'])) {
    verifyCsrf();

    $posted  = $_POST;
    $title   = trim(isset($_POST['title'])       ? $_POST['title']       : '');
    $author  = trim(isset($_POST['author'])      ? $_POST['author']      : '');
    $subject = trim(isset($_POST['subject'])     ? $_POST['subject']     : 'Science');
    $desc    = trim(isset($_POST['description']) ? $_POST['description'] : '');
    $editId  = (int)(isset($_POST['edit_id'])    ? $_POST['edit_id']     : 0);

    if ($title  === '') $errors[] = 'Book title is required.';
    if ($author === '') $errors[] = 'Author name is required.';

    // Process uploads
    $pdf   = mb_receiveFile('pdf',   ['pdf'],                              100 * 1024 * 1024);
    $cover = mb_receiveFile('cover', ['jpg','jpeg','png','webp','gif'],    5   * 1024 * 1024);

    if ($pdf['error']   !== null) $errors[] = 'PDF: '   . $pdf['error'];
    if ($cover['error'] !== null) $errors[] = 'Cover: ' . $cover['error'];

    if (empty($errors)) {
        try {
            if ($editId > 0) {
                // ── UPDATE ────────────────────────────────────
                $set  = ['title=?','author=?','description=?','subject=?','category=?'];
                $vals = [$title, $author, $desc, $subject, $subject];

                if ($pdf['ok']) {
                    $set[]  = 'has_pdf=1';
                    $set[]  = 'pdf_name=?';
                    $set[]  = 'pdf_size=?';
                    $set[]  = 'pdf_data=?';
                    $vals[] = $pdf['name'];
                    $vals[] = (int)$pdf['size'];
                    $vals[] = $pdf['data'];
                }
                if ($cover['ok']) {
                    $set[]  = 'has_cover=1';
                    $set[]  = 'cover_name=?';
                    $set[]  = 'cover_mime=?';
                    $set[]  = 'cover_data=?';
                    $vals[] = $cover['name'];
                    $vals[] = $cover['mime'];
                    $vals[] = $cover['data'];
                }
                $vals[] = $editId;
                DB::updateWithBlobs('UPDATE books SET ' . implode(',', $set) . ' WHERE id=?', $vals);
                logActivity($currentUser['id'], 'edit_book', $title);
                redirect('manage_books.php', 'success', "Book \"$title\" updated.");

            } else {
                // ── INSERT ────────────────────────────────────
                $vals = [
                    $title, $author, $desc, $subject, $subject,
                    (int)($pdf['ok']   ? 1 : 0),
                    $pdf['ok']   ? $pdf['name']   : null,
                    $pdf['ok']   ? (int)$pdf['size'] : 0,
                    $pdf['ok']   ? $pdf['data']   : null,
                    (int)($cover['ok'] ? 1 : 0),
                    $cover['ok'] ? $cover['name'] : null,
                    $cover['ok'] ? $cover['mime'] : null,
                    $cover['ok'] ? $cover['data'] : null,
                    (int)$currentUser['id'],
                ];
                $newId = DB::insertWithBlobs(
                    "INSERT INTO books
                        (title,author,description,subject,category,
                         has_pdf,pdf_name,pdf_size,pdf_data,
                         has_cover,cover_name,cover_mime,cover_data,
                         added_by)
                     VALUES
                        (?,?,?,?,?,
                         ?,?,?,?,
                         ?,?,?,?,
                         ?)",
                    $vals
                );
                logActivity($currentUser['id'], 'add_book',
                    "\"$title\" (ID $newId)"
                    . ($pdf['ok']   ? ' +PDF '   . mb_fmtBytes($pdf['size'])   : '')
                    . ($cover['ok'] ? ' +Cover ' . mb_fmtBytes($cover['size']) : ''));

                $msg = "Book \"$title\" added!";
                if ($pdf['ok'])   $msg .= ' PDF saved.';
                if ($cover['ok']) $msg .= ' Cover saved.';
                redirect('manage_books.php', 'success', $msg);
            }

        } catch (PDOException $e) {
            error_log('manage_books PDO error: ' . $e->getMessage());
            $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
            if (strpos($e->getMessage(), 'packet') !== false || strpos($e->getMessage(), 'max_allowed') !== false) {
                $errors[] = 'Fix: Add <code>max_allowed_packet=128M</code> to the [mysqld] section of your my.ini, then restart MySQL.';
            }
        } catch (Exception $e) {
            error_log('manage_books error: ' . $e->getMessage());
            $errors[] = 'Error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Book list ─────────────────────────────────────────────────
$sortOk  = ['title','author','subject','created_at'];
$sort    = in_array((isset($_GET['sort']) ? $_GET['sort'] : ''), $sortOk)
           ? $_GET['sort'] : 'created_at';
$dir     = (isset($_GET['dir']) && $_GET['dir'] === 'asc') ? 'ASC' : 'DESC';

$books = DB::rows(
    "SELECT id,title,author,subject,has_pdf,pdf_size,has_cover,created_at
     FROM books ORDER BY $sort $dir"
);

$subjectColors = [
    'Mathematics'=>'#dbeafe','Science'=>'#dcfce7',
    'Literature' =>'#fef9c3','History'=>'#fce7f3',
    'Technology' =>'#e0e7ff','Filipino'=>'#f3e8ff',
];
$subjectIcons  = [
    'Mathematics'=>'dashboard','Science'=>'shield',
    'Literature' =>'read',     'History'=>'notes',
    'Technology' =>'manage',   'Filipino'=>'book',
];

// ── Render ────────────────────────────────────────────────────
renderHead('Manage Books');
renderSidebar('manage_books');
renderPageHeader('Manage Books',
    'Add books — cover images and PDFs stored in the database',
    false, count($books) . ' books');
?>
<div class="page-content">
<?php renderFlash(); ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
  <strong>Please fix the following:</strong>
  <ul style="margin:8px 0 0 18px;line-height:1.9;">
    <?php foreach ($errors as $e): ?>
      <li><?= $e /* already sanitized or is a trusted string */ ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<!-- ═══════════ FORM ═══════════════════════════════════════ -->
<div class="card" id="book-form-card">
  <div class="card-title">
    <?= $editBook ? 'Edit: ' . sanitize($editBook['title']) : 'Add New Book' ?>
  </div>

  <form method="POST" enctype="multipart/form-data" id="book-form"
        onsubmit="return onBookFormSubmit()">
    <input type="hidden" name="_add_book"     value="1">
    <input type="hidden" name="MAX_FILE_SIZE" value="104857600">
    <?= csrfField() ?>
    <?php if ($editBook): ?>
      <input type="hidden" name="edit_id" value="<?= (int)$editBook['id'] ?>">
    <?php endif; ?>

    <div class="form-grid">
      <div class="form-group">
        <label>Title <span style="color:var(--red)">*</span></label>
        <input type="text" name="title" required maxlength="255"
               placeholder="Book title"
               value="<?= fv('title') ?>">
      </div>
      <div class="form-group">
        <label>Author <span style="color:var(--red)">*</span></label>
        <input type="text" name="author" required maxlength="255"
               placeholder="Author name"
               value="<?= fv('author') ?>">
      </div>
      <div class="form-group">
        <label>Subject</label>
        <select name="subject">
          <?php foreach (['Mathematics','Science','Literature','History','Technology','Filipino'] as $s): ?>
            <option value="<?= $s ?>" <?= fv('subject') === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Description <span style="color:#aaa;font-size:10px;font-weight:400;">(optional)</span></label>
        <input type="text" name="description" maxlength="500"
               placeholder="Brief description"
               value="<?= fv('description') ?>">
      </div>
    </div>

    <!-- Uploads -->
    <div class="form-grid" style="margin-top:8px;align-items:start;">

      <!-- Cover image -->
      <div class="form-group">
        <label>Cover Image
          <span style="color:#aaa;font-weight:400;font-size:10px;"> JPG/PNG/WEBP · max 5 MB</span>
        </label>
        <?php if ($editBook && !empty($editBook['has_cover'])): ?>
          <div style="margin-bottom:8px;padding:8px 12px;background:#f0fdf4;border:1px solid #bbf7d0;
                      border-radius:6px;font-size:11px;color:#166534;display:flex;align-items:center;gap:10px;">
            <img src="serve_cover.php?id=<?= (int)$editBook['id'] ?>"
                 style="height:40px;border-radius:4px;">
            Cover saved. Upload new to replace.
          </div>
        <?php endif; ?>
        <div class="upz" id="cover-zone">
          <input type="file" name="cover" id="cover-input"
                 accept=".jpg,.jpeg,.png,.webp,.gif"
                 onchange="onCoverPick(this)">
          <div id="cover-idle" class="upz-idle">
            <?= icon('upload','upz-icon') ?>
            <div class="upz-label">Click to upload cover</div>
            <div class="upz-sub">JPG, PNG, WEBP · max 5 MB</div>
          </div>
          <div id="cover-preview" class="upz-preview" style="display:none">
            <img id="cover-img" src="" alt=""
                 style="max-height:140px;border-radius:6px;object-fit:contain;">
            <button type="button" class="clr-btn" onclick="clearCover()">Remove</button>
          </div>
        </div>
      </div>

      <!-- PDF file -->
      <div class="form-group">
        <label>PDF File
          <span style="color:#aaa;font-weight:400;font-size:10px;"> PDF only · max 100 MB</span>
        </label>
        <?php if ($editBook && !empty($editBook['has_pdf'])): ?>
          <div style="margin-bottom:8px;padding:8px 12px;background:#f0fdf4;border:1px solid #bbf7d0;
                      border-radius:6px;font-size:11px;color:#166534;">
            <?= sanitize($editBook['pdf_name'] ?? '') ?>
            (<?= mb_fmtBytes((int)($editBook['pdf_size'] ?? 0)) ?>) — Upload new to replace.
          </div>
        <?php endif; ?>
        <div class="upz" id="pdf-zone">
          <input type="file" name="pdf" id="pdf-input"
                 accept=".pdf,application/pdf"
                 onchange="onPdfPick(this)">
          <div id="pdf-idle" class="upz-idle">
            <?= icon('pdf','upz-icon') ?>
            <div class="upz-label">Click to upload PDF</div>
            <div class="upz-sub">PDF only · max 100 MB</div>
          </div>
          <div id="pdf-selected" class="upz-selected" style="display:none">
            <?= icon('pdf','sel-ico') ?>
            <div class="sel-info">
              <strong id="pdf-fname"></strong>
              <span   id="pdf-fsize"></span>
            </div>
            <button type="button" class="clr-btn" onclick="clearPdf()">Remove</button>
          </div>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:18px;align-items:center;flex-wrap:wrap;">
      <button class="btn btn-primary" type="submit" id="submit-btn">
        <?= $editBook ? 'Save Changes' : 'Add Book to Library' ?>
      </button>
      <?php if ($editBook): ?>
        <a href="manage_books.php" class="btn btn-secondary">Cancel</a>
      <?php endif; ?>
      <span id="upload-msg" style="display:none;font-size:12px;color:#888;">
        Uploading — please wait…
      </span>
    </div>
  </form>
</div>

<!-- ═══════════ BOOK LIST ════════════════════════════════════ -->
<?php if (empty($books)): ?>
<div class="card" style="text-align:center;padding:40px;color:#aaa;">
  <?= icon('book','nav-icon') ?>
  <p style="margin-top:12px;font-size:13px;">No books yet — add your first book above.</p>
</div>
<?php else: ?>
<div class="card">
  <div class="card-title"
       style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <span>All Books (<?= count($books) ?>)</span>
    <div style="display:flex;gap:12px;font-size:11px;font-weight:600;align-items:center;">
      <span style="color:#aaa;">Sort:</span>
      <?php foreach (['title'=>'Title','subject'=>'Subject','created_at'=>'Date'] as $k=>$v):
        $nd = ($sort===$k && $dir==='ASC') ? 'desc' : 'asc';
      ?>
      <a href="manage_books.php?sort=<?= $k ?>&dir=<?= $nd ?>"
         style="color:<?= $sort===$k?'var(--red)':'#888' ?>;
                border-bottom:2px solid <?= $sort===$k?'var(--red)':'transparent'?>;
                padding-bottom:1px;text-decoration:none;">
        <?= $v ?><?= $sort===$k?($dir==='ASC'?' ↑':' ↓'):'' ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php foreach ($books as $b):
    $bg  = isset($subjectColors[$b['subject']]) ? $subjectColors[$b['subject']] : '#f0f4f8';
    $ico = isset($subjectIcons[$b['subject']])  ? $subjectIcons[$b['subject']]  : 'book';
  ?>
  <div class="bk-row">
    <!-- Thumb -->
    <div class="bk-thumb">
      <?php if ($b['has_cover']): ?>
        <img src="serve_cover.php?id=<?= (int)$b['id'] ?>"
             style="width:52px;height:72px;object-fit:cover;border-radius:5px;
                    display:block;box-shadow:0 2px 6px rgba(0,0,0,.12);">
      <?php else: ?>
        <div style="width:52px;height:72px;background:<?= $bg ?>;border-radius:5px;
                    display:flex;align-items:center;justify-content:center;">
          <?= icon($ico,'bk-ico') ?>
        </div>
      <?php endif; ?>
    </div>
    <!-- Info -->
    <div class="bk-info">
      <div style="font-size:13px;font-weight:600;color:var(--ink);"><?= sanitize($b['title']) ?></div>
      <div style="font-size:11px;color:var(--gray);margin-top:2px;">
        by <?= sanitize($b['author'] ?? '') ?>
      </div>
      <div style="display:flex;gap:5px;margin-top:5px;flex-wrap:wrap;">
        <span class="bktag bktag-subj"><?= sanitize($b['subject'] ?? '') ?></span>
        <?php if ($b['has_pdf']): ?>
          <span class="bktag bktag-ok">PDF · <?= mb_fmtBytes((int)$b['pdf_size']) ?></span>
        <?php else: ?>
          <span class="bktag bktag-no">No PDF</span>
        <?php endif; ?>
        <?php if ($b['has_cover']): ?>
          <span class="bktag bktag-ok">Cover</span>
        <?php else: ?>
          <span class="bktag bktag-no">No Cover</span>
        <?php endif; ?>
      </div>
    </div>
    <!-- Actions -->
    <div class="bk-acts">
      <?php if ($b['has_pdf']): ?>
        <a href="serve_pdf.php?id=<?= (int)$b['id'] ?>" target="_blank"
           class="btn btn-secondary btn-sm">Preview PDF</a>
      <?php endif; ?>
      <a href="manage_books.php?edit=<?= (int)$b['id'] ?>"
         class="btn btn-secondary btn-sm"
         onclick="scrollToForm()">Edit</a>
      <a href="manage_books.php?delete=<?= (int)$b['id'] ?>&csrf_token=<?= urlencode(csrfToken()) ?>"
         class="btn btn-danger btn-sm"
         onclick="return confirm('Delete \'<?= addslashes(sanitize($b['title'])) ?>\' permanently?')">
        Delete
      </a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div><!-- .page-content -->

<style>
/* Upload zones */
.upz{border:2px dashed var(--border);border-radius:var(--radius-lg);background:#fafafa;
     position:relative;cursor:pointer;overflow:hidden;min-height:130px;
     display:flex;align-items:stretch;transition:border-color .2s,background .2s;}
.upz:hover,.upz:focus-within{border-color:var(--red);background:#fff9f9;}
.upz input[type=file]{position:absolute;inset:0;opacity:0;width:100%;height:100%;cursor:pointer;z-index:2;}
.upz-idle{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
          gap:6px;padding:20px;text-align:center;pointer-events:none;}
.upz-icon{width:28px;height:28px;stroke:#bbb;}
.upz-label{font-size:12px;font-weight:600;color:var(--ink);}
.upz-sub{font-size:10px;color:#aaa;}
.upz-preview{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
             gap:10px;padding:14px;pointer-events:none;}
.upz-selected{flex:1;display:flex;align-items:center;gap:10px;padding:14px;pointer-events:none;}
.sel-ico{width:28px;height:28px;stroke:var(--red);flex-shrink:0;}
.sel-info{flex:1;}
.sel-info strong{display:block;font-size:12px;font-weight:600;color:var(--ink);word-break:break-all;}
.sel-info span{font-size:11px;color:#aaa;}
.clr-btn{pointer-events:auto;background:#fee2e2;border:none;border-radius:6px;
         padding:5px 10px;font-size:11px;font-weight:600;color:#dc3545;cursor:pointer;flex-shrink:0;}
.clr-btn:hover{background:#fca5a5;}
/* Book rows */
.bk-row{display:flex;align-items:center;gap:14px;padding:12px 0;
        border-bottom:1px solid var(--border);}
.bk-row:last-child{border-bottom:none;}
.bk-thumb{flex-shrink:0;}
.bk-info{flex:1;min-width:0;}
.bk-acts{display:flex;gap:5px;flex-shrink:0;flex-wrap:wrap;}
.bk-ico{width:22px;height:22px;stroke:rgba(0,0,0,.18);}
.bktag{font-size:9px;font-weight:600;padding:2px 7px;border-radius:4px;}
.bktag-subj{background:var(--red-soft);color:var(--red);}
.bktag-ok{background:#d4edda;color:#155724;}
.bktag-no{background:#f0f0f0;color:#aaa;}
</style>

<script>
function onCoverPick(input) {
  var file = input.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    alert('Cover image exceeds 5 MB. Please choose a smaller image.');
    clearCover(); return;
  }
  var reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('cover-idle').style.display    = 'none';
    document.getElementById('cover-img').src               = e.target.result;
    document.getElementById('cover-preview').style.display = 'flex';
  };
  reader.readAsDataURL(file);
}
function clearCover() {
  document.getElementById('cover-input').value             = '';
  document.getElementById('cover-preview').style.display   = 'none';
  document.getElementById('cover-idle').style.display      = 'flex';
}

function onPdfPick(input) {
  var file = input.files[0];
  if (!file) return;
  if (file.size > 100 * 1024 * 1024) {
    alert('PDF exceeds 100 MB. Please choose a smaller file.');
    clearPdf(); return;
  }
  document.getElementById('pdf-idle').style.display     = 'none';
  document.getElementById('pdf-fname').textContent      = file.name;
  document.getElementById('pdf-fsize').textContent      = (file.size / 1024 / 1024).toFixed(2) + ' MB';
  document.getElementById('pdf-selected').style.display = 'flex';
}
function clearPdf() {
  document.getElementById('pdf-input').value             = '';
  document.getElementById('pdf-selected').style.display  = 'none';
  document.getElementById('pdf-idle').style.display      = 'flex';
}

function scrollToForm() {
  setTimeout(function() {
    var el = document.getElementById('book-form-card');
    if (el) el.scrollIntoView({behavior:'smooth'});
  }, 80);
}

function onBookFormSubmit() {
  var title  = document.querySelector('[name=title]').value.trim();
  var author = document.querySelector('[name=author]').value.trim();
  if (!title || !author) return true; // let browser validation handle it
  var btn = document.getElementById('submit-btn');
  var msg = document.getElementById('upload-msg');
  btn.disabled    = true;
  btn.textContent = 'Saving…';
  msg.style.display = 'inline';
  return true; // must return true to actually submit
}
</script>
<?php
// Flush output buffer — if any PHP error occurred above it will show here
// instead of a blank page
ob_end_flush();
renderFooter();
?>
