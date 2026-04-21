// ============================================================
//  LANHS eLibrary — app.js
//  Full reader: PDF iframe + text reader + TTS + Zoom
// ============================================================
'use strict';

// ── State ─────────────────────────────────────────────────────
var readerZoom    = 100;
var ttsRunning    = false;
var ttsUtterance  = null;
var currentBookId = null;
var isPdfMode     = false;

// ── Open Reader ───────────────────────────────────────────────
function openReader(bookId, title, hasPdf) {
  currentBookId = bookId;
  isPdfMode     = hasPdf;

  var overlay = document.getElementById('reader-overlay');
  var rTitle  = document.getElementById('r-title');
  var body    = document.getElementById('reader-body');
  var ttsBtn  = document.getElementById('r-tts-btn');
  var zoomOut = document.getElementById('r-zoom-out');
  var zoomIn  = document.getElementById('r-zoom-in');

  if (!overlay || !body) return;

  stopTTS();
  rTitle.textContent = title;
  readerZoom = 100;
  document.getElementById('r-zoom').textContent = '100%';
  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';

  // Loading state
  body.innerHTML =
    '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;'
  + 'height:100%;color:#ccc;font-family:Sora,sans-serif;gap:16px;">'
  + '<div class="reader-spinner"></div>'
  + '<div style="font-size:13px;">Loading <strong style="color:#fff;">' + title + '</strong>…</div>'
  + '</div>';

  if (hasPdf) {
    // ── PDF mode: iframe ──────────────────────────────────────
    if (ttsBtn)  ttsBtn.style.display  = 'none';
    if (zoomOut) zoomOut.style.display = 'none';
    if (zoomIn)  zoomIn.style.display  = 'none';
    document.getElementById('r-zoom').style.display = 'none';

    setTimeout(function() {
      body.innerHTML =
        '<iframe id="pdf-frame" '
      + 'src="serve_pdf.php?id=' + bookId + '" '
      + 'style="width:100%;height:100%;border:none;display:block;background:#fff;" '
      + 'allowfullscreen>'
      + '<p style="color:#fff;padding:40px;text-align:center;">'
      + 'Your browser cannot display PDFs inline. '
      + '<a href="serve_pdf.php?id=' + bookId + '" target="_blank" style="color:#f87171;">Open PDF</a>'
      + '</p>'
      + '</iframe>';
    }, 300);

  } else {
    // ── Text mode ─────────────────────────────────────────────
    if (ttsBtn)  { ttsBtn.style.display  = ''; ttsBtn.textContent = 'Read Aloud'; }
    if (zoomOut) zoomOut.style.display = '';
    if (zoomIn)  zoomIn.style.display  = '';
    document.getElementById('r-zoom').style.display = '';

    fetch('get_book_content.php?id=' + bookId)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var page = document.createElement('div');
        page.className = 'reader-page';
        page.id        = 'reader-page';
        page.style.fontSize = readerZoom + '%';

        var wm = document.createElement('div');
        wm.className = 'reader-watermark';
        page.appendChild(wm);

        var content = (data && data.content) ? data.content : '';
        if (content.trim()) {
          var blocks = content.split(/\n{2,}/);
          for (var i = 0; i < blocks.length; i++) {
            var block = blocks[i].trim();
            if (!block) continue;
            if (/^(Chapter\s|Kabanata\s|CHAPTER\s|KABANATA\s|Aralin\s)/i.test(block)) {
              var h = document.createElement('h2');
              h.textContent = block;
              page.appendChild(h);
            } else {
              var lines = block.split('\n');
              for (var j = 0; j < lines.length; j++) {
                var line = lines[j].trim();
                if (!line) continue;
                var p = document.createElement('p');
                p.textContent = line;
                page.appendChild(p);
              }
            }
          }
        } else {
          var msg = document.createElement('p');
          msg.style.color = '#999';
          msg.textContent = 'No content available. Ask your teacher or admin to upload the PDF for this book.';
          page.appendChild(msg);
        }

        var scrollWrap = document.createElement('div');
        scrollWrap.style.cssText = 'flex:1;overflow-y:auto;padding:32px 20px;display:flex;justify-content:center;background:#525659;';
        scrollWrap.appendChild(page);
        body.innerHTML = '';
        body.appendChild(scrollWrap);
      })
      .catch(function() {
        body.innerHTML =
          '<div class="reader-page" id="reader-page">'
        + '<div class="reader-watermark"></div>'
        + '<p style="color:#999;text-align:center;padding:40px;">Could not load content. Please check your connection and try again.</p>'
        + '</div>';
      });
  }
}

// ── Close reader ──────────────────────────────────────────────
function closeReader() {
  stopTTS();
  var overlay = document.getElementById('reader-overlay');
  var body    = document.getElementById('reader-body');
  if (overlay) overlay.classList.remove('open');
  if (body)    body.innerHTML = '';
  document.body.style.overflow = '';
  currentBookId = null;
}

// ── Zoom (text mode only) ─────────────────────────────────────
function zoomIn() {
  readerZoom = Math.min(200, readerZoom + 15);
  applyZoom();
}
function zoomOut() {
  readerZoom = Math.max(70, readerZoom - 15);
  applyZoom();
}
function applyZoom() {
  document.getElementById('r-zoom').textContent = readerZoom + '%';
  var page = document.getElementById('reader-page');
  if (page) page.style.fontSize = readerZoom + '%';
}

// ── TTS ───────────────────────────────────────────────────────
function toggleTTS() {
  if (ttsRunning) { stopTTS(); return; }
  if (!window.speechSynthesis) {
    showToast('Text-to-speech is not supported in this browser.');
    return;
  }
  var page = document.getElementById('reader-page');
  if (!page) { showToast('No text to read.'); return; }
  var text = (page.innerText || page.textContent || '').trim();
  if (!text) { showToast('No readable text found.'); return; }

  ttsUtterance        = new SpeechSynthesisUtterance(text);
  ttsUtterance.rate   = 0.88;
  ttsUtterance.pitch  = 1.0;
  ttsUtterance.volume = 1.0;
  ttsUtterance.lang   = 'en-PH';

  ttsUtterance.onstart = function() {
    ttsRunning = true;
    var btn = document.getElementById('r-tts-btn');
    var bar = document.getElementById('reader-tts-bar');
    if (btn) { btn.classList.add('active'); btn.textContent = 'Stop Reading'; }
    if (bar) bar.classList.add('on');
    animateTTS();
  };
  ttsUtterance.onend = function() { cleanupTTS(); };
  ttsUtterance.onerror = function() { cleanupTTS(); };

  window.speechSynthesis.speak(ttsUtterance);
}

function stopTTS() {
  if (window.speechSynthesis) window.speechSynthesis.cancel();
  cleanupTTS();
}

function cleanupTTS() {
  ttsRunning = false;
  var btn  = document.getElementById('r-tts-btn');
  var bar  = document.getElementById('reader-tts-bar');
  var fill = document.getElementById('tts-fill');
  if (btn)  { btn.classList.remove('active'); btn.textContent = 'Read Aloud'; }
  if (bar)  bar.classList.remove('on');
  if (fill) fill.style.width = '0%';
}

function animateTTS() {
  if (!ttsRunning) return;
  var fill = document.getElementById('tts-fill');
  if (fill) {
    var w = parseFloat(fill.style.width) || 0;
    fill.style.width = Math.min(99, w + 0.03) + '%';
  }
  requestAnimationFrame(animateTTS);
}

// ── Favorites ─────────────────────────────────────────────────
function toggleFavorite(bookId, btn) {
  btn.disabled = true;
  var fd = new FormData();
  fd.append('book_id', bookId);
  fetch('toggle_favorite.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      var isSaved = data.action === 'added';
      if (isSaved) {
        btn.classList.add('saved');
        btn.innerHTML = document.getElementById('heart-filled-svg') ?
          document.getElementById('heart-filled-svg').innerHTML : '♥';
        showToast('Added to favorites!');
      } else {
        btn.classList.remove('saved');
        btn.innerHTML = document.getElementById('heart-outline-svg') ?
          document.getElementById('heart-outline-svg').innerHTML : '♡';
        showToast('Removed from favorites.');
      }
    })
    .catch(function() {
      btn.disabled = false;
      showToast('Could not update favorites. Please try again.');
    });
}

// ── Toast ─────────────────────────────────────────────────────
var toastTimer;
function showToast(msg, type) {
  var t = document.getElementById('app-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'app-toast';
    document.body.appendChild(t);
    t.style.cssText =
      'position:fixed;bottom:24px;right:24px;max-width:300px;'
    + 'border-radius:10px;padding:12px 18px;font-size:13px;font-weight:500;'
    + 'z-index:99999;font-family:Sora,sans-serif;'
    + 'box-shadow:0 6px 20px rgba(0,0,0,0.2);'
    + 'transform:translateY(80px);opacity:0;'
    + 'transition:transform 0.3s ease,opacity 0.3s ease;';
  }
  var bg = (type === 'error') ? '#dc3545' : (type === 'success' ? '#28a745' : '#2f2f2f');
  t.style.background = bg;
  t.style.color = '#fff';
  t.textContent = msg;
  t.style.transform = 'translateY(0)';
  t.style.opacity   = '1';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(function() {
    t.style.transform = 'translateY(80px)';
    t.style.opacity   = '0';
  }, 3500);
}

// ── Notepad ───────────────────────────────────────────────────
function initNotepad() {
  var ta = document.getElementById('notepad-ta');
  if (!ta) return;
  var key   = 'lanhs_notepad_' + (window.LANHS_USER_ID || 0);
  var saved = localStorage.getItem(key);
  if (saved !== null) ta.value = saved;

  ta.addEventListener('input', function() {
    localStorage.setItem(key, ta.value);
    var s = document.getElementById('np-status');
    if (s) {
      s.textContent = 'Auto-saved';
      clearTimeout(s._t);
      s._t = setTimeout(function() { s.textContent = ''; }, 2000);
    }
  });
}

function npFormat(pre, suf) {
  var ta = document.getElementById('notepad-ta');
  if (!ta) return;
  var s   = ta.selectionStart, e = ta.selectionEnd;
  var sel = ta.value.substring(s, e);
  ta.value = ta.value.substring(0, s) + pre + sel + suf + ta.value.substring(e);
  ta.setSelectionRange(s + pre.length, s + pre.length + sel.length);
  ta.focus();
  ta.dispatchEvent(new Event('input'));
}

function npClear() {
  if (!confirm('Clear all notepad content? This cannot be undone.')) return;
  var ta = document.getElementById('notepad-ta');
  if (ta) { ta.value = ''; ta.dispatchEvent(new Event('input')); ta.focus(); }
}

// ── Auto-dismiss alerts ───────────────────────────────────────
function initAlerts() {
  var alerts = document.querySelectorAll('.alert');
  for (var i = 0; i < alerts.length; i++) {
    (function(el) {
      setTimeout(function() {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 520);
      }, 5000);
    })(alerts[i]);
  }
}

// ── Confirm delete ────────────────────────────────────────────
function confirmDelete(msg) {
  return confirm(msg || 'Are you sure you want to delete this?');
}

// ── Keyboard shortcuts ────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  var overlay = document.getElementById('reader-overlay');
  if (!overlay || !overlay.classList.contains('open')) return;
  if (e.key === 'Escape')    { closeReader(); }
  if (e.key === '+' || e.key === '=') { if (!isPdfMode) zoomIn(); }
  if (e.key === '-')          { if (!isPdfMode) zoomOut(); }
  if (e.key === 'ArrowRight') { if (!isPdfMode) zoomIn(); }
  if (e.key === 'ArrowLeft')  { if (!isPdfMode) zoomOut(); }
});

// ── DOMContentLoaded ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  initNotepad();
  initAlerts();
});
