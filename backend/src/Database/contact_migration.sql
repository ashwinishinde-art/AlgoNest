-- Contact Messages Migration
-- Run this after schema.sql to add the contact/support messaging system.

CREATE TABLE IF NOT EXISTS contact_messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NULL COMMENT 'NULL for anonymous (future), FK to users',
    sender_name     VARCHAR(120) NOT NULL,
    sender_email    VARCHAR(255) NOT NULL,
    subject         VARCHAR(255) NOT NULL,
    category        ENUM('bug','suggestion','question','content','other') NOT NULL DEFAULT 'other',
    message         TEXT NOT NULL,
    status          ENUM('open','replied','closed') NOT NULL DEFAULT 'open',
    admin_reply     TEXT NULL,
    replied_by      INT NULL COMMENT 'admin user_id who replied',
    replied_at      DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_status     (status),
    INDEX idx_user_id    (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
