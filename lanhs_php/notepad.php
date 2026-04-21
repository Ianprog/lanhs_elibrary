<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireRole('teacher','admin');
$u = currentUser();

renderHead('My Notepad');
renderSidebar('notepad');
renderPageHeader('My Notepad', 'Personal workspace — auto-saved in your browser');
?>
<script>window.LANHS_USER_ID = <?= (int)$u['id'] ?>;</script>
<div class="page-content">
  <div class="notepad-wrap">
    <div class="notepad-toolbar">
      <button class="tb-btn" onclick="npFormat('**','**')"><strong>B</strong></button>
      <button class="tb-btn" onclick="npFormat('_','_')"><em>I</em></button>
      <button class="tb-btn" onclick="npFormat('\n• ','')">List</button>
      <button class="tb-btn" onclick="npFormat('# ','')">H1</button>
      <button class="tb-btn" onclick="npClear()">Clear</button>
      <span class="notepad-status" id="np-status"></span>
    </div>
    <textarea id="notepad-ta" placeholder="Start writing your notes here… Changes are auto-saved locally in your browser."></textarea>
  </div>
</div>
<?php renderFooter(); ?>
