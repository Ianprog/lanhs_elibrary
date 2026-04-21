<?php
// ============================================================
//  config.php — Edit this file with your settings
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'lanhs_elibrary');
define('DB_USER',    'root');
define('DB_PASS',    '');           // ← set your MySQL password
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',   'LANHS eLibrary');
define('APP_SUB',    'Luis Aguado National High School');
define('APP_ROOT',   __DIR__);

// ── Important for LONGBLOB PDF uploads ───────────────────────
// If PDF uploads fail, increase these in your php.ini:
//   upload_max_filesize = 100M
//   post_max_size       = 105M
//   max_allowed_packet  = 128M   ← in my.ini / my.cnf (MySQL)
//   memory_limit        = 256M
