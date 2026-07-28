-- OTP Verifications table
-- Run this migration to add email OTP support for registration and password reset.

CREATE TABLE IF NOT EXISTS otp_verifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(100)                    NOT NULL,
    otp_hash    VARCHAR(255)                    NOT NULL,
    type        ENUM('register', 'reset')       NOT NULL,
    attempts    TINYINT UNSIGNED                NOT NULL DEFAULT 0,
    expires_at  DATETIME                        NOT NULL,
    used        TINYINT(1)                      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_email_type (email, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
