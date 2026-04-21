-- ============================================================
--  LANHS eLibrary — database.sql  (Full schema v3)
--  STEP 1: Import in phpMyAdmin
--  STEP 2: Visit setup.php once, then DELETE it
-- ============================================================

CREATE DATABASE IF NOT EXISTS lanhs_elibrary
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lanhs_elibrary;

-- ── Users ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(255)        NOT NULL,
    email         VARCHAR(255) UNIQUE NOT NULL,
    password      VARCHAR(255)        NOT NULL,
    role          ENUM('admin','teacher','student') DEFAULT 'student',
    status        ENUM('pending','approved','rejected') DEFAULT 'pending',
    profile_image VARCHAR(255)        DEFAULT '',
    bio           TEXT,
    location      VARCHAR(100),
    grade         VARCHAR(20),
    section       VARCHAR(50),
    last_login    TIMESTAMP NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Books ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS books (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(255) NOT NULL,
    author        VARCHAR(255),
    description   TEXT,
    subject       VARCHAR(100),
    category      VARCHAR(100),
    -- PDF stored as LONGBLOB
    has_pdf       TINYINT(1)   DEFAULT 0,
    pdf_name      VARCHAR(255),
    pdf_size      INT          DEFAULT 0,
    pdf_mime      VARCHAR(100) DEFAULT 'application/pdf',
    pdf_data      LONGBLOB,
    -- Cover image stored as MEDIUMBLOB (max ~16MB for cover)
    has_cover     TINYINT(1)   DEFAULT 0,
    cover_name    VARCHAR(255),
    cover_mime    VARCHAR(100) DEFAULT 'image/jpeg',
    cover_data    MEDIUMBLOB,
    added_by      INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Favorites ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS favorites (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    book_id    INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_fav (user_id, book_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id)  ON DELETE CASCADE
);

-- ── Announcements (section-targeted) ──────────────────────────
-- target_type: 'all' = everyone, 'section' = specific section only
CREATE TABLE IF NOT EXISTS announcements (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    body        TEXT         NOT NULL,
    author_id   INT,
    target_type ENUM('all','section') DEFAULT 'all',
    target_section VARCHAR(100) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Section Notes ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS section_notes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    section    VARCHAR(100) NOT NULL,
    title      VARCHAR(255) NOT NULL,
    body       TEXT         NOT NULL,
    author_id  INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Activity Log ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    action     VARCHAR(255) NOT NULL,
    details    TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
