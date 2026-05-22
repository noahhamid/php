-- ============================================================
--  Asset Management System — Database Schema
--  Compatible with: MySQL 5.7+ / MariaDB (XAMPP)
--  Usage: Import via phpMyAdmin or run in MySQL console
-- ============================================================

CREATE DATABASE IF NOT EXISTS asset_management
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE asset_management;

-- ------------------------------------------------------------
-- 1. DEPARTMENTS
-- ------------------------------------------------------------
CREATE TABLE departments (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 2. USERS  (admin / manager / staff)
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,          -- use password_hash() in PHP
  role          ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff',
  department_id INT,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_users_dept
    FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE SET NULL
);

-- ------------------------------------------------------------
-- 3. CATEGORIES  (Laptops, Printers, Projectors, etc.)
-- ------------------------------------------------------------
CREATE TABLE categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 4. ASSETS
-- ------------------------------------------------------------
CREATE TABLE assets (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(150) NOT NULL,
  serial_number  VARCHAR(100) UNIQUE,
  category_id    INT,
  assigned_to    INT,                            -- FK → users.id (nullable)
  status         ENUM('available','in_use','maintenance','retired')
                   NOT NULL DEFAULT 'available',
  location       VARCHAR(150),
  purchase_date  DATE,
  purchase_price DECIMAL(10,2),
  notes          TEXT,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_assets_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE SET NULL,

  CONSTRAINT fk_assets_user
    FOREIGN KEY (assigned_to) REFERENCES users(id)
    ON DELETE SET NULL
);

-- ------------------------------------------------------------
-- 5. ASSET REQUESTS  (borrow / return / repair)
-- ------------------------------------------------------------
CREATE TABLE asset_requests (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,                  -- who made the request
  asset_id       INT NOT NULL,
  request_type   ENUM('borrow','return','repair') NOT NULL,
  status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reason         TEXT,
  reviewed_by    INT,                            -- manager / admin who acted
  reviewed_at    TIMESTAMP NULL,
  reject_reason  VARCHAR(255),
  requested_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_req_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_req_asset
    FOREIGN KEY (asset_id) REFERENCES assets(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_req_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
    ON DELETE SET NULL
);

-- ------------------------------------------------------------
-- 6. MAINTENANCE LOGS
-- ------------------------------------------------------------
CREATE TABLE maintenance_logs (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  asset_id      INT NOT NULL,
  reported_by   INT NOT NULL,                   -- FK → users.id
  description   TEXT NOT NULL,
  status        ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending',
  cost          DECIMAL(10,2),
  resolved_date DATE,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_maint_asset
    FOREIGN KEY (asset_id) REFERENCES assets(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_maint_user
    FOREIGN KEY (reported_by) REFERENCES users(id)
    ON DELETE CASCADE
);

-- ============================================================
--  SAMPLE SEED DATA
-- ============================================================

-- Departments
INSERT INTO departments (name) VALUES
  ('IT'),
  ('HR'),
  ('Finance'),
  ('Operations');

-- Categories
INSERT INTO categories (name, description) VALUES
  ('Laptop',    'Portable computers'),
  ('Desktop',   'Desktop computers and monitors'),
  ('Printer',   'Printers and scanners'),
  ('Projector', 'Projectors and display equipment'),
  ('Network',   'Switches, routers, access points');

-- Users  (passwords below are hashed versions of "password123")
-- In production generate with: password_hash('yourpassword', PASSWORD_DEFAULT)
INSERT INTO users (name, email, password_hash, role, department_id) VALUES
  ('Admin User',   'admin@company.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   1),
  ('Jane Manager', 'manager@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1),
  ('John Staff',   'staff@company.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff',   2);

-- Assets
INSERT INTO assets (name, serial_number, category_id, status, location, purchase_date, purchase_price) VALUES
  ('Dell Latitude 5520',   'SN-001-DELL', 1, 'available',    'IT Room',    '2023-01-15', 850.00),
  ('HP EliteBook 840',     'SN-002-HP',   1, 'in_use',       'HR Office',  '2022-06-10', 920.00),
  ('Canon ImageRunner',    'SN-003-CAN',  3, 'available',    'Print Room', '2021-09-01', 1200.00),
  ('Epson EB-X51',         'SN-004-EPS',  4, 'maintenance',  'Board Room', '2020-03-22', 450.00),
  ('Cisco Switch 24-port', 'SN-005-CIS',  5, 'in_use',       'Server Rm',  '2022-11-05', 600.00);