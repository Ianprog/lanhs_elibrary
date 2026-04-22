<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: home.php'); exit; }

$book = DB::row("SELECT id, title, author, subject, has_pdf, pdf_name FROM books WHERE id = ?", [$id]);
if (!$book) { header('Location: home.php'); exit; }

$title  = htmlspecialchars($book['title'],  ENT_QUOTES, 'UTF-8');
$author = htmlspecialchars($book['author'] ?? '', ENT_QUOTES, 'UTF-8');
$hasPdf = !empty($book['has_pdf']);

logActivity((int)$_SESSION['user_id'], 'view_book', $book['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $title ?> — LANHS eLibrary</title>
<link rel="icon" href="assets/img/logo1.png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
:root{
  --red:#d62828;--red-d:#b81f2b;
  --dark:#1a1a1a;--panel:#2b2b2b;
  --ctrl:rgba(255,255,255,0.12);--ctrl-h:rgba(255,255,255,0.22);
}
html,body{height:100%;background:var(--dark);font-family:'Sora',sans-serif;color:#fff;overflow:hidden;}

/* ── TOP BAR ──────────────────────────────────────────────── */
#topbar{
  position:fixed;top:0;left:0;right:0;height:54px;
  background:var(--red);
  display:flex;align-items:center;gap:10px;padding:0 16px;
  z-index:100;box-shadow:0 2px 10px rgba(0,0,0,.4);
}
#topbar .logo{height:30px;width:30px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.3);}
#book-title{flex:1;font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
#book-author{font-size:11px;opacity:.7;white-space:nowrap;}
.tbtn{
  background:var(--ctrl);border:1px solid rgba(255,255,255,.25);
  color:#fff;border-radius:7px;padding:7px 13px;font-size:12px;
  font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap;
  transition:background .15s;
}
.tbtn:hover{background:var(--ctrl-h);}
.tbtn.active{background:rgba(255,255,255,.9);color:var(--red);}
.tbtn:disabled{opacity:.4;cursor:not-allowed;}

/* ── TTS BAR ──────────────────────────────────────────────── */
#tts-bar{
  position:fixed;top:54px;left:0;right:0;height:38px;
  background:rgba(0,0,0,.82);
  display:none;align-items:center;gap:12px;padding:0 16px;
  z-index:99;
}
#tts-bar.on{display:flex;}
#tts-status{font-size:11px;color:#4ade80;white-space:nowrap;}
#tts-track{flex:1;height:4px;background:rgba(255,255,255,.15);border-radius:3px;overflow:hidden;}
#tts-fill{height:100%;width:0%;background:#4ade80;border-radius:3px;transition:width .3s;}
#tts-voice{background:#333;border:1px solid #555;color:#fff;font-size:11px;
           border-radius:5px;padding:3px 7px;font-family:inherit;}

/* ── VIEWER ───────────────────────────────────────────────── */
#viewer{
  position:fixed;
  top:54px;left:0;right:0;bottom:60px;
  overflow:auto;
  background:#525659;
  display:flex;flex-direction:column;align-items:center;
  padding:24px 16px;
  gap:16px;
}
#viewer.tts-open{top:92px;}

/* Loading / error */
#loading{
  position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
  text-align:center;color:rgba(255,255,255,.7);font-size:14px;
}
.spinner{width:40px;height:40px;border:3px solid rgba(255,255,255,.15);
         border-top-color:var(--red);border-radius:50%;
         animation:spin .7s linear infinite;margin:0 auto 16px;}
@keyframes spin{to{transform:rotate(360deg);}}

/* Canvas pages */
.pdf-page{
  background:#fff;border-radius:4px;
  box-shadow:0 4px 20px rgba(0,0,0,.5);
  display:block;max-width:100%;
}

/* No-PDF fallback */
#textreader{
  max-width:760px;width:100%;background:#fff;border-radius:8px;
  padding:52px 56px;font-family:Georgia,serif;color:#333;
  line-height:1.9;font-size:15px;
  box-shadow:0 4px 20px rgba(0,0,0,.4);
}
#textreader h2{font-size:20px;font-weight:700;color:var(--red);
               margin:22px 0 12px;border-bottom:2px solid #fde8e8;padding-bottom:8px;}
#textreader h2:first-child{margin-top:0;}
#textreader p{margin-bottom:14px;}

/* ── BOTTOM CONTROLS ──────────────────────────────────────── */
#controls{
  position:fixed;bottom:0;left:0;right:0;height:60px;
  background:rgba(0,0,0,.88);
  display:flex;align-items:center;justify-content:center;
  gap:8px;padding:0 16px;z-index:100;
  border-top:1px solid rgba(255,255,255,.08);
}
.divider{width:1px;height:26px;background:rgba(255,255,255,.15);margin:0 4px;}
#page-info{font-size:12px;color:rgba(255,255,255,.7);min-width:80px;text-align:center;}
#zoom-pct{font-size:12px;color:rgba(255,255,255,.7);min-width:44px;text-align:center;}

/* ── VOICE PANEL ─────────────────────────────────────────── */
#voice-panel{
  position:fixed;top:96px;right:16px;width:280px;
  background:rgba(20,20,20,.97);border:1px solid rgba(255,255,255,.12);
  border-radius:10px;padding:16px;z-index:200;display:none;
}
#voice-panel.show{display:block;}
#voice-panel h4{font-size:13px;font-weight:700;color:#4ade80;margin-bottom:12px;}
.vrow{display:flex;flex-direction:column;gap:4px;margin-bottom:10px;}
.vrow label{font-size:10px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em;}
.vrow select,.vrow input[type=range]{width:100%;background:#2a2a2a;border:1px solid #444;
  color:#fff;border-radius:5px;padding:5px 8px;font-family:inherit;font-size:12px;}

/* Security overlay */
#sec-overlay{
  position:fixed;inset:0;z-index:9998;pointer-events:none;
  background:transparent;
}

/* Print protection */
@media print{body{display:none!important;}}
</style>
</head>
<body>

<!-- Security overlay (prevents some right-click tools) -->
<div id="sec-overlay"></div>

<!-- ── TOP BAR ────────────────────────────────────────────── -->
<div id="topbar">
  <img src="assets/img/logo1.png" class="logo" alt="">
  <div style="flex:1;min-width:0;">
    <div id="book-title"><?= $title ?></div>
    <div id="book-author"><?= $author ? 'by ' . $author : '' ?></div>
  </div>
  <?php if ($hasPdf): ?>
    <button class="tbtn" id="btn-tts-toggle" onclick="toggleTTS()">🔊 Read Aloud</button>
    <button class="tbtn" id="btn-voice-set" onclick="toggleVoicePanel()">⚙ Voice</button>
  <?php endif; ?>
  <a href="home.php" class="tbtn">← Library</a>
</div>

<!-- ── TTS BAR ────────────────────────────────────────────── -->
<div id="tts-bar">
  <span id="tts-status">Reading…</span>
  <div id="tts-track"><div id="tts-fill"></div></div>
  <button class="tbtn" id="btn-playpause"
          onclick="togglePause()" style="padding:4px 10px;font-size:11px;">⏸ Pause</button>
  <button class="tbtn" onclick="stopTTS()" style="padding:4px 10px;font-size:11px;">⏹ Stop</button>
</div>

<!-- ── VIEWER ──────────────────────────────────────────────── -->
<div id="viewer">
  <div id="loading">
    <div class="spinner"></div>
    <?= $hasPdf ? 'Loading PDF…' : 'Loading content…' ?>
  </div>
</div>

<!-- ── BOTTOM CONTROLS ────────────────────────────────────── -->
<div id="controls">
  <?php if ($hasPdf): ?>
    <button class="tbtn" id="btn-prev" onclick="prevPage()" disabled>◀ Prev</button>
    <span id="page-info">Page 1 / 1</span>
    <button class="tbtn" id="btn-next" onclick="nextPage()" disabled>Next ▶</button>
    <div class="divider"></div>
    <button class="tbtn" onclick="zoom(-1)">A−</button>
    <span id="zoom-pct">100%</span>
    <button class="tbtn" onclick="zoom(1)">A+</button>
    <div class="divider"></div>
    <button class="tbtn" onclick="fitWidth()">Fit Width</button>
  <?php else: ?>
    <button class="tbtn" onclick="txtZoom(-1)">A−</button>
    <span id="zoom-pct">100%</span>
    <button class="tbtn" onclick="txtZoom(1)">A+</button>
    <div class="divider"></div>
    <button class="tbtn" id="btn-tts-toggle" onclick="toggleTTS()">🔊 Read Aloud</button>
  <?php endif; ?>
  <div class="divider"></div>
  <a href="home.php" class="tbtn">✕ Close</a>
</div>

<!-- ── VOICE SETTINGS PANEL ───────────────────────────────── -->
<div id="voice-panel">
  <h4>Voice Settings</h4>
  <div class="vrow">
    <label>Voice</label>
    <select id="voice-sel"></select>
  </div>
  <div class="vrow">
    <label>Speed — <span id="rate-val">1.0</span>x</label>
    <input type="range" id="rate-sl" min="0.5" max="2" step="0.1" value="1"
           oninput="document.getElementById('rate-val').textContent=parseFloat(this.value).toFixed(1)">
  </div>
  <div class="vrow">
    <label>Pitch — <span id="pitch-val">1.0</span></label>
    <input type="range" id="pitch-sl" min="0.5" max="2" step="0.1" value="1"
           oninput="document.getElementById('pitch-val').textContent=parseFloat(this.value).toFixed(1)">
  </div>
  <button class="tbtn" onclick="document.getElementById('voice-panel').classList.remove('show')"
          style="width:100%;margin-top:4px;">Close</button>
</div>

<script>
// ── Config ────────────────────────────────────────────────────
var BOOK_ID  = <?= $id ?>;
var HAS_PDF  = <?= $hasPdf ? 'true' : 'false' ?>;
var PDF_URL  = 'serve_pdf.php?id=' + BOOK_ID;

// ── PDF.js state ──────────────────────────────────────────────
var pdfDoc      = null;
var curPage     = 1;
var totalPages  = 1;
var scale       = 1.4;
var rendering   = false;

// ── TTS state ─────────────────────────────────────────────────
var ttsActive   = false;
var ttsUtt      = null;
var pageTexts   = {};  // cache extracted text per page
var ttsFillAnim = null;

// ── Security ──────────────────────────────────────────────────
document.addEventListener('contextmenu', function(e){ e.preventDefault(); });
document.addEventListener('keydown', function(e){
  if (e.ctrlKey && (e.key==='s'||e.key==='p'||e.key==='S'||e.key==='P')) e.preventDefault();
});

// ── PDF.js worker ─────────────────────────────────────────────
pdfjsLib.GlobalWorkerOptions.workerSrc =
  'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

// ── Load PDF ──────────────────────────────────────────────────
function loadPDF() {
  if (!HAS_PDF) { loadTextContent(); return; }

  pdfjsLib.getDocument({
    url: PDF_URL,
    withCredentials: true   // <-- sends session cookies with PDF.js fetch
  }).promise.then(function(pdf) {
    pdfDoc     = pdf;
    totalPages = pdf.numPages;
    document.getElementById('loading').style.display = 'none';
    updatePageInfo();
    renderPage(curPage);
    loadVoices();
  }).catch(function(err) {
    document.getElementById('loading').innerHTML =
      '<div style="color:#f87171;font-size:14px;">'
    + '<div style="font-size:36px;margin-bottom:14px;">⚠</div>'
    + 'Could not load PDF.<br>'
    + '<small style="opacity:.6;">' + err.message + '</small><br><br>'
    + '<a href="serve_pdf.php?id=' + BOOK_ID + '" target="_blank" '
    + '   style="background:#d62828;color:#fff;padding:10px 20px;border-radius:8px;'
    + '          text-decoration:none;font-size:13px;font-weight:600;">'
    + 'Open PDF Directly</a></div>';
  });
}

// ── Render one page ───────────────────────────────────────────
function renderPage(num) {
  if (rendering) return;
  rendering = true;

  pdfDoc.getPage(num).then(function(page) {
    var vp      = page.getViewport({ scale: scale });
    var canvas  = document.createElement('canvas');
    canvas.className = 'pdf-page';
    canvas.width  = vp.width;
    canvas.height = vp.height;

    var ctx = canvas.getContext('2d');
    return page.render({ canvasContext: ctx, viewport: vp }).promise.then(function() {
      var viewer = document.getElementById('viewer');
      viewer.innerHTML = '';
      viewer.appendChild(canvas);
      rendering = false;

      // Also extract text for TTS
      return page.getTextContent();
    }).then(function(tc) {
      pageTexts[num] = tc.items.map(function(i){ return i.str; }).join(' ');
    });
  }).then(function() {
    updatePageInfo();
    document.getElementById('btn-prev').disabled = curPage <= 1;
    document.getElementById('btn-next').disabled = curPage >= totalPages;
  }).catch(function(err) {
    rendering = false;
    console.error('Render error:', err);
  });
}

// ── Page navigation ───────────────────────────────────────────
function prevPage() {
  if (curPage <= 1 || rendering) return;
  if (ttsActive) stopTTS();
  curPage--;
  renderPage(curPage);
}
function nextPage() {
  if (curPage >= totalPages || rendering) return;
  if (ttsActive) stopTTS();
  curPage++;
  renderPage(curPage);
}
function updatePageInfo() {
  document.getElementById('page-info').textContent = 'Page ' + curPage + ' / ' + totalPages;
}

// ── Zoom ──────────────────────────────────────────────────────
function zoom(dir) {
  scale = Math.min(3, Math.max(0.6, scale + dir * 0.25));
  document.getElementById('zoom-pct').textContent = Math.round(scale * 100 / 1.4 * 100) + '%';
  if (pdfDoc) renderPage(curPage);
}
function fitWidth() {
  var viewerW = document.getElementById('viewer').clientWidth - 48;
  if (!pdfDoc) return;
  pdfDoc.getPage(curPage).then(function(page) {
    var vp = page.getViewport({ scale: 1 });
    scale  = viewerW / vp.width;
    document.getElementById('zoom-pct').textContent = 'Fit';
    renderPage(curPage);
  });
}

// ── TTS ───────────────────────────────────────────────────────
function loadVoices() {
  var sel = document.getElementById('voice-sel');
  if (!sel) return;
  function populate() {
    var voices = speechSynthesis.getVoices();
    sel.innerHTML = '';
    voices.forEach(function(v, i) {
      var opt = document.createElement('option');
      opt.value = i;
      opt.textContent = v.name + ' (' + v.lang + ')';
      sel.appendChild(opt);
    });
  }
  populate();
  speechSynthesis.onvoiceschanged = populate;
}

function toggleTTS() {
  if (ttsActive) { stopTTS(); return; }
  startTTS();
}

function startTTS() {
  var text = pageTexts[curPage] || '';
  if (!text.trim()) {
    // Try to extract if not cached yet
    if (pdfDoc) {
      pdfDoc.getPage(curPage).then(function(page) {
        return page.getTextContent();
      }).then(function(tc) {
        pageTexts[curPage] = tc.items.map(function(i){ return i.str; }).join(' ');
        startTTS();
      });
    } else {
      // Text mode: read from DOM
      var pg = document.getElementById('textreader');
      if (pg) text = pg.innerText || pg.textContent || '';
    }
    if (!text.trim()) return;
  }

  speechSynthesis.cancel();

  var voices    = speechSynthesis.getVoices();
  var voiceSel  = document.getElementById('voice-sel');
  var rateSl    = document.getElementById('rate-sl');
  var pitchSl   = document.getElementById('pitch-sl');

  ttsUtt         = new SpeechSynthesisUtterance(text);
  ttsUtt.rate    = rateSl  ? parseFloat(rateSl.value)  : 0.9;
  ttsUtt.pitch   = pitchSl ? parseFloat(pitchSl.value) : 1;
  ttsUtt.volume  = 1;

  if (voiceSel && voices[parseInt(voiceSel.value)]) {
    ttsUtt.voice = voices[parseInt(voiceSel.value)];
  }

  ttsUtt.onstart = function() {
    ttsActive = true;
    var btn = document.getElementById('btn-tts-toggle');
    if (btn) { btn.classList.add('active'); btn.textContent = '⏹ Stop Reading'; }
    document.getElementById('tts-bar').className = 'on';
    document.getElementById('viewer').classList.add('tts-open');
    document.getElementById('tts-status').textContent = 'Reading page ' + curPage + '…';
    tickTTS();
  };
  ttsUtt.onend = function() {
    // Auto advance to next page
    if (curPage < totalPages) {
      stopTTS();
      setTimeout(function() { nextPage(); setTimeout(startTTS, 600); }, 300);
    } else {
      stopTTS();
      document.getElementById('tts-status').textContent = 'Finished';
    }
  };
  ttsUtt.onerror = function() { stopTTS(); };

  speechSynthesis.speak(ttsUtt);
}

function stopTTS() {
  speechSynthesis.cancel();
  ttsActive = false;
  ttsUtt    = null;
  var btn   = document.getElementById('btn-tts-toggle');
  if (btn)  { btn.classList.remove('active'); btn.textContent = '🔊 Read Aloud'; }
  document.getElementById('tts-bar').className = '';
  document.getElementById('viewer').classList.remove('tts-open');
  document.getElementById('tts-fill').style.width = '0%';
  document.getElementById('tts-status').textContent = '';
  var pp = document.getElementById('btn-playpause');
  if (pp) { pp.textContent = '⏸ Pause'; }
}

function togglePause() {
  var pp = document.getElementById('btn-playpause');
  if (speechSynthesis.paused) {
    speechSynthesis.resume();
    if (pp) pp.textContent = '⏸ Pause';
    document.getElementById('tts-status').textContent = 'Reading…';
  } else if (speechSynthesis.speaking) {
    speechSynthesis.pause();
    if (pp) pp.textContent = '▶ Resume';
    document.getElementById('tts-status').textContent = 'Paused';
  }
}

function tickTTS() {
  if (!ttsActive) return;
  var f = document.getElementById('tts-fill');
  if (f) { var w=parseFloat(f.style.width)||0; f.style.width=Math.min(99,w+0.04)+'%'; }
  requestAnimationFrame(tickTTS);
}

function toggleVoicePanel() {
  document.getElementById('voice-panel').classList.toggle('show');
}

// ── Text-only mode ────────────────────────────────────────────
var txtZoomPct = 100;
function txtZoom(dir) {
  txtZoomPct = Math.min(200, Math.max(70, txtZoomPct + dir * 15));
  document.getElementById('zoom-pct').textContent = txtZoomPct + '%';
  var tr = document.getElementById('textreader');
  if (tr) tr.style.fontSize = txtZoomPct + '%';
}

function loadTextContent() {
  fetch('get_book_content.php?id=' + BOOK_ID)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var div = document.createElement('div');
      div.id  = 'textreader';
      var txt = (d && d.content) ? String(d.content) : '';
      if (txt.trim()) {
        txt.split(/\n{2,}/).forEach(function(bl) {
          bl = bl.trim(); if (!bl) return;
          if (/^(Chapter|Kabanata|Aralin)\s/i.test(bl)) {
            var h=document.createElement('h2'); h.textContent=bl; div.appendChild(h);
          } else {
            bl.split('\n').forEach(function(ln) {
              ln=ln.trim(); if(!ln)return;
              var p=document.createElement('p'); p.textContent=ln; div.appendChild(p);
            });
          }
        });
      } else {
        var p=document.createElement('p');
        p.style.cssText='color:#999;text-align:center;padding:40px;';
        p.textContent='No content available for this book yet.';
        div.appendChild(p);
      }
      var viewer = document.getElementById('viewer');
      viewer.innerHTML = '';
      viewer.style.justifyContent = 'flex-start';
      viewer.appendChild(div);
      document.getElementById('loading').style.display = 'none';
    });
}

// ── Keyboard shortcuts ────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key==='ArrowLeft'  || e.key==='PageUp')   { e.preventDefault(); prevPage(); }
  if (e.key==='ArrowRight' || e.key==='PageDown')  { e.preventDefault(); nextPage(); }
  if (e.key==='+' || e.key==='=')                  { e.preventDefault(); zoom(1); }
  if (e.key==='-')                                  { e.preventDefault(); zoom(-1); }
  if (e.key===' ')                                  { e.preventDefault(); togglePause(); }
  if (e.key==='Escape')                             { window.location='home.php'; }
});

window.addEventListener('beforeunload', function() { speechSynthesis.cancel(); });
loadPDF();
</script>
</body>
</html>
