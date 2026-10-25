-- SimLocator database. Run once:  mysql -u root -p < database.sql
CREATE DATABASE IF NOT EXISTS simlocator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simlocator;

CREATE TABLE IF NOT EXISTS registrations (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100)  NOT NULL,
  email      VARCHAR(150)  NOT NULL,
  phone      CHAR(11)      NOT NULL,            -- always stored as 01XXXXXXXXX
  operator   VARCHAR(30)   NOT NULL,            -- detected from the phone prefix
  district   VARCHAR(50)   NOT NULL,
  area       VARCHAR(60)   NOT NULL,
  password_hash VARCHAR(255) NOT NULL,          -- bcrypt hash, the password itself is never stored
  failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until  DATETIME   NULL,                -- set after too many wrong passwords
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_email UNIQUE (email),
  CONSTRAINT uq_phone UNIQUE (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Local-development account used by backend/.env.example. Change the password for any real server.
-- It can only read, add and update rows in this one table (no delete, no other tables).
CREATE USER IF NOT EXISTS 'simlocator'@'localhost' IDENTIFIED BY 'simlocator_pass';
GRANT SELECT, INSERT, UPDATE ON simlocator.registrations TO 'simlocator'@'localhost';
