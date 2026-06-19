-- =============================================================
--  Support Panel — Database Schema
--  MySQL 8.0+ / MariaDB 10.6+
--
--  Usage:
--    mysql -u root -p support_app < db/schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS support_app
    CHARACTER SET utf8mb4
    COLLATE       utf8mb4_unicode_ci;

USE support_app;

-- =============================================================
--  operators  (support panel users / authenticated agents)
-- =============================================================
CREATE TABLE IF NOT EXISTS operators (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150)  NOT NULL,
    email         VARCHAR(255)  NOT NULL,
    password_hash VARCHAR(255)  NOT NULL,             -- bcrypt via Nette\Security\Passwords
    role          ENUM('agent', 'senior', 'admin')
                                NOT NULL DEFAULT 'agent',
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_operators_email       (email),
    INDEX       idx_operators_is_active  (is_active)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


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

    INDEX idx_activities_customer_date (customer_id, created_at DESC),
    INDEX idx_activities_customer_type (customer_id, activity_type),
    INDEX idx_activities_created_at    (created_at)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =============================================================
--  comments  — author_name replaced with operator_id FK
-- =============================================================
CREATE TABLE IF NOT EXISTS comments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    activity_id INT UNSIGNED NOT NULL,
    operator_id INT UNSIGNED NOT NULL,                -- who wrote the comment
    body        TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_comments_activity
        FOREIGN KEY (activity_id)
        REFERENCES  activities(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_comments_operator
        FOREIGN KEY (operator_id)
        REFERENCES  operators(id)
        ON DELETE RESTRICT                            -- keep comments if operator deleted
        ON UPDATE CASCADE,

    INDEX idx_comments_activity_date (activity_id, created_at ASC),
    INDEX idx_comments_operator      (operator_id)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
