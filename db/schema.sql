-- =============================================================
--  Support Panel — Database Schema
--  MySQL 8.0+ / MariaDB 10.6+
--
--  Usage:
--    mysql -u root -p support_app < schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS support_app
    CHARACTER SET utf8mb4
    COLLATE       utf8mb4_unicode_ci;

USE support_app;

-- =============================================================
--  customers
-- =============================================================
CREATE TABLE IF NOT EXISTS customers (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name       VARCHAR(150)  NOT NULL,
    email      VARCHAR(255)  NOT NULL,
    phone      VARCHAR(50)       NULL DEFAULT NULL,
    is_active  TINYINT(1)    NOT NULL DEFAULT 1,
    notes      TEXT              NULL DEFAULT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_customers_email         (email),
    INDEX       idx_customers_name         (name),
    INDEX       idx_customers_is_active    (is_active),
    INDEX       idx_customers_created_at   (created_at),
    INDEX       idx_customers_active_name  (is_active, name),
    INDEX       idx_customers_active_email (is_active, email)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =============================================================
--  activities
--
--  activity_type is stored as an ENUM directly on the column.
--  Pros:  no JOIN needed, values are DB-validated, storage is
--         1–2 bytes (index into the enum list).
--  Cons:  adding a new type requires ALTER TABLE.
--         Acceptable for a small, stable set like this one.
-- =============================================================
CREATE TABLE IF NOT EXISTS activities (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id   INT UNSIGNED NOT NULL,
    activity_type ENUM(
                      'login',
                      'purchase',
                      'support_ticket',
                      'password_reset',
                      'profile_update',
                      'subscription',
                      'refund',
                      'note'
                  )            NOT NULL,
    details       TEXT             NULL DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_activities_customer
        FOREIGN KEY (customer_id)
        REFERENCES  customers(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    -- covering index: all activities for customer X, newest first
    INDEX idx_activities_customer_date (customer_id, created_at DESC),

    -- for type-filtered queries inside a customer's activity list
    INDEX idx_activities_customer_type (customer_id, activity_type),

    -- for global date-range scans / reports
    INDEX idx_activities_created_at    (created_at)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =============================================================
--  comments
-- =============================================================
CREATE TABLE IF NOT EXISTS comments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    activity_id INT UNSIGNED NOT NULL,
    author_name VARCHAR(100) NOT NULL,
    body        TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_comments_activity
        FOREIGN KEY (activity_id)
        REFERENCES  activities(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    -- covering index: all comments for activity Y in chronological order
    INDEX idx_comments_activity_date (activity_id, created_at ASC)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
