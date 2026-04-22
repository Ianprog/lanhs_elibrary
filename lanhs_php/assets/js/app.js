/* ============================================================
   LANHS eLibrary — app.js
   Simple, reliable, no arrow functions, ES5-compatible
   ============================================================ */

/* ── Reader state ─────────────────────────────────────────── */
var READER_ZOOM   = 100;
var TTS_RUNNING   = false;
var TTS_UTT       = null;
var IS_PDF_MODE   = false;

/* ── Open reader ──────────────────────────────────────────── */
function openReader(bookId, title, hasPdf) {
  IS_PDF_MODE = hasPdf;
  READER_ZOOM = 100;

  var overlay = document.getElementById('reader-overlay');
  var body    = document.getElementById('reader-body');
  var rtitle  = document.getElementById('r-title');
  var rzoom   = document.getElementById('r-zoom');
  var ttsBtn  = document.getElementById('r-tts-btn');
  var zoomOut = document.getElementById('r-zoom-out');
  var zoomIn  = document.getElementById('r-zoom-in');

  if (!overlay || !body) {
    alert('Reader not found on page. Please reload.');
    return;
  }

  stopTTS();

  rtitle.textContent    = title;
  rzoom.textContent     = '100%';
  overlay.className     = 'reader-overlay open';
  document.body.style.overflow = 'hidden';

  /* Loading spinner */
  body.innerHTML =
    '<div style="display:flex;flex-direction:column;align-items:center;'
  + 'justify-content:center;height:100%;gap:18px;">'
  + '<div class="reader-spinner"></div>'
  + '<div style="color:rgba(255,255,255,.7);font-size:13px;font-family:Sora,sans-serif;">'
  + 'Opening <strong style="color:#fff;">' + title + '</strong>…</div>'
  + '</div>';

  if (hasPdf) {
    /* ── PDF mode ─────────────────────────────────────────── */
    if (ttsBtn)  ttsBtn.style.display  = 'none';
    if (zoomOut) zoomOut.style.display = 'none';
    if (zoomIn)  zoomIn.style.display  = 'none';
    rzoom.style.display = 'none';

    /* Small delay lets browser paint the spinner first */
    setTimeout(function() {
      /* Build the iframe directly — no innerHTML trick */
      var iframe     = document.createElement('iframe');
      iframe.id      = 'pdf-frame';
      iframe.src     = 'serve_pdf.php?id=' + bookId;
      iframe.title   = title;
      iframe.style.cssText = 'flex:1;width:100%;height:100%;border:none;display:block;background:#fff;';

      iframe.onerror = function() {
        body.innerHTML =
          '<div style="color:#f87171;text-align:center;padding:60px;font-family:Sora,sans-serif;">'
        + '<div style="font-size:42px;margin-bottom:16px;">⚠️</div>'
        + '<div style="font-size:14px;margin-bottom:12px;">Could not load PDF.</div>'
        + '<a href="serve_pdf.php?id=' + bookId + '" target="_blank" '
        + '   style="background:#d62828;color:#fff;padding:10px 24px;border-radius:8px;'
        + '          text-decoration:none;font-size:13px;font-weight:600;">Open in new tab</a>'
        + '</div>';
      };

      body.innerHTML = '';
      body.appendChild(iframe);
    }, 250);

  } else {
    /* ── Text mode ────────────────────────────────────────── */
    if (ttsBtn)  { ttsBtn.style.display  = '';  ttsBtn.textContent = 'Read Aloud'; }
    if (zoomOut) zoomOut.style.display = '';
    if (zoomIn)  zoomIn.style.display  = '';
    rzoom.style.display = '';

    fetch('get_book_content.php?id=' + bookId)
      .then(function(r) {
        if (!r.ok) throw new Error('Server returned ' + r.status);
        return r.json();
      })
      .then(function(data) {
        var page = document.createElement('div');
        page.className = 'reader-page';
        page.id        = 'reader-page';
        page.style.fontSize = READER_ZOOM + '%';

        var wm = document.createElement('div');
        wm.className = 'reader-watermark';
        page.appendChild(wm);

        var content = (data && data.content) ? String(data.content) : '';
        if (content.trim()) {
          var blocks = content.split(/\n{2,}/);
          for (var i = 0; i < blocks.length; i++) {
            var block = blocks[i].trim();
            if (!block) continue;
            if (/^(Chapter\s|Kabanata\s|Aralin\s|CHAPTER\s)/i.test(block)) {
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
          msg.style.color  = '#999';
          msg.style.textAlign = 'center';
          msg.style.padding = '40px';
          msg.textContent = 'No text content available. Ask your teacher or admin to upload the PDF for this book.';
          page.appendChild(msg);
        }

        var scroll = document.createElement('div');
        scroll.className = 'reader-scroll';
        scroll.appendChild(page);

        body.innerHTML = '';
        body.appendChild(scroll);
      })
      .catch(function(err) {
        body.innerHTML =
          '<div class="reader-scroll"><div class="reader-page" id="reader-page">'
        + '<div class="reader-watermark"></div>'
        + '<p style="color:#999;text-align:center;">Could not load content: ' + err.message + '</p>'
        + '</div></div>';
      });
  }
}

/* ── Close reader ─────────────────────────────────────────── */
function closeReader() {
  stopTTS();
  var overlay = document.getElementById('reader-overlay');
  var body    = document.getElementById('reader-body');
  if (overlay) overlay.className = 'reader-overlay';
  if (body)    body.innerHTML    = '';
  document.body.style.overflow   = '';
  IS_PDF_MODE = false;
}

/* ── Zoom ─────────────────────────────────────────────────── */
function zoomIn() {
  READER_ZOOM = Math.min(200, READER_ZOOM + 15);
  applyZoom();
}
function zoomOut() {
  READER_ZOOM = Math.max(70, READER_ZOOM - 15);
  applyZoom();
}
function applyZoom() {
  var el = document.getElementById('r-zoom');
  if (el) el.textContent = READER_ZOOM + '%';
  var page = document.getElementById('reader-page');
  if (page) page.style.fontSize = READER_ZOOM + '%';
}

/* ── TTS ──────────────────────────────────────────────────── */
function toggleTTS() {
  if (TTS_RUNNING) { stopTTS(); return; }
  if (!window.speechSynthesis) {
    showToast('Text-to-speech is not supported in this browser.');
    return;
  }
  var page = document.getElementById('reader-page');
  if (!page) { showToast('Open a text book first.'); return; }
  var text = (page.innerText || page.textContent || '').trim();
  if (!text) { showToast('No text found to read aloud.'); return; }

  TTS_UTT         = new SpeechSynthesisUtterance(text);
  TTS_UTT.rate    = 0.88;
  TTS_UTT.pitch   = 1;
  TTS_UTT.volume  = 1;
  TTS_UTT.lang    = 'en-PH';

  TTS_UTT.onstart = function() {
    TTS_RUNNING = true;
    var btn = document.getElementById('r-tts-btn');
    var bar = document.getElementById('reader-tts-bar');
    if (btn) { btn.classList.add('active'); btn.textContent = 'Stop Reading'; }
    if (bar) bar.classList.add('on');
    tickTTS();
  };
  TTS_UTT.onend   = function() { cleanupTTS(); };
  TTS_UTT.onerror = function(e) { cleanupTTS(); };

  window.speechSynthesis.speak(TTS_UTT);
}

function stopTTS() {
  if (window.speechSynthesis) window.speechSynthesis.cancel();
  cleanupTTS();
}

function cleanupTTS() {
  TTS_RUNNING = false;
  TTS_UTT     = null;
  var btn  = document.getElementById('r-tts-btn');
  var bar  = document.getElementById('reader-tts-bar');
  var fill = document.getElementById('tts-fill');
  if (btn)  { btn.classList.remove('active'); btn.textContent = 'Read Aloud'; }
  if (bar)  bar.classList.remove('on');
  if (fill) fill.style.width = '0%';
}

function tickTTS() {
  if (!TTS_RUNNING) return;
  var fill = document.getElementById('tts-fill');
  if (fill) {
    var w = parseFloat(fill.style.width) || 0;
    fill.style.width = Math.min(99, w + 0.03) + '%';
  }
  requestAnimationFrame(tickTTS);
}

/* ── Favorites ────────────────────────────────────────────── */
function toggleFavorite(bookId, btn) {
  btn.disabled = true;
  var fd = new FormData();
  fd.append('book_id', bookId);
  fetch('toggle_favorite.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      btn.disabled = false;
      if (d.action === 'added') {
        btn.classList.add('saved');
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" '
          + 'fill="currentColor" stroke="currentColor" stroke-width="1" '
          + 'class="nav-icon"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67'
          + 'l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78'
          + '1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
        showToast('Added to favorites!', 'success');
      } else {
        btn.classList.remove('saved');
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" '
          + 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
          + 'stroke-linejoin="round" class="nav-icon"><path d="M20.84 4.61a5.5 5.5 0 0 0'
          + '-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23'
          + 'l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
        showToast('Removed from favorites.', 'success');
      }
    })
    .catch(function() {
      btn.disabled = false;
      showToast('Could not update. Please try again.', 'error');
    });
}

/* ── Toast ────────────────────────────────────────────────── */
var TOAST_TIMER = null;
function showToast(msg, type) {
  var t = document.getElementById('app-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'app-toast';
    t.style.cssText =
      'position:fixed;bottom:24px;right:24px;max-width:300px;'
    + 'border-radius:10px;padding:12px 18px;font-size:13px;font-weight:500;'
    + 'z-index:99999;font-family:Sora,sans-serif;color:#fff;'
    + 'box-shadow:0 6px 20px rgba(0,0,0,.25);'
    + 'transform:translateY(90px);opacity:0;'
    + 'transition:transform .3s ease,opacity .3s ease;pointer-events:none;';
    document.body.appendChild(t);
  }
  t.style.background = (type === 'error') ? '#dc3545' : (type === 'success' ? '#28a745' : '#2f2f2f');
  t.textContent = msg;
  t.style.transform = 'translateY(0)';
  t.style.opacity   = '1';
  clearTimeout(TOAST_TIMER);
  TOAST_TIMER = setTimeout(function() {
    t.style.transform = 'translateY(90px)';
    t.style.opacity   = '0';
  }, 3500);
}

/* ── confirmDelete ────────────────────────────────────────── */
function confirmDelete(msg) {
  return confirm(msg || 'Are you sure you want to delete this?');
}

/* ── Notepad ──────────────────────────────────────────────── */
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
      s.textContent = 'Saved';
      clearTimeout(s._t);
      s._t = setTimeout(function() { s.textContent = ''; }, 2000);
    }
  });
}

function npFormat(pre, suf) {
  var ta = document.getElementById('notepad-ta');
  if (!ta) return;
  var s = ta.selectionStart, e = ta.selectionEnd;
  ta.value = ta.value.substring(0, s) + pre + ta.value.substring(s, e) + suf + ta.value.substring(e);
  ta.setSelectionRange(s + pre.length, s + pre.length + (e - s));
  ta.focus();
  ta.dispatchEvent(new Event('input'));
}

function npClear() {
  if (!confirm('Clear all notepad content?')) return;
  var ta = document.getElementById('notepad-ta');
  if (ta) { ta.value = ''; ta.dispatchEvent(new Event('input')); ta.focus(); }
}

/* ── Auto-dismiss alerts ──────────────────────────────────── */
function initAlerts() {
  var alerts = document.querySelectorAll('.alert');
  for (var i = 0; i < alerts.length; i++) {
    (function(el) {
      setTimeout(function() {
        el.style.transition = 'opacity .5s';
        el.style.opacity    = '0';
        setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 520);
      }, 5000);
    }(alerts[i]));
  }
}

/* ── Keyboard shortcuts ───────────────────────────────────── */
document.addEventListener('keydown', function(e) {
  var overlay = document.getElementById('reader-overlay');
  if (!overlay || overlay.className.indexOf('open') === -1) return;
  if (e.key === 'Escape')            { closeReader(); return; }
  if (!IS_PDF_MODE) {
    if (e.key === '+' || e.key === '=') zoomIn();
    if (e.key === '-')                  zoomOut();
  }
});

/* ── Init ─────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
  initNotepad();
  initAlerts();
});
