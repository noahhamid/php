-- 1. Create database
CREATE DATABASE asset_system;
USE asset_system;

---

## -- 2. Roles table (Admin / Manager / Staff)

CREATE TABLE roles (
id INT AUTO_INCREMENT PRIMARY KEY,
role_name VARCHAR(50) NOT NULL
);

INSERT INTO roles (role_name)
VALUES ('Admin'), ('Manager'), ('Staff');

---

## -- 3. Users table (login system)

CREATE TABLE users (
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(100) NOT NULL,
password VARCHAR(255) NOT NULL,
role_id INT,
FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Test users
INSERT INTO users (username, password, role_id)
VALUES
('admin', '1234', 1),
('manager', '1234', 2),
('staff', '1234', 3);

---

## -- 4. Assets table (inventory controlled by admin)

CREATE TABLE assets (
id INT AUTO_INCREMENT PRIMARY KEY,
asset_name VARCHAR(100),
category VARCHAR(100),
serial_number VARCHAR(100),
status VARCHAR(50) DEFAULT 'Available'
);

---

## -- 5. Requests table (staff → manager workflow)

CREATE TABLE requests (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT,
asset_type VARCHAR(100),
reason TEXT,
status VARCHAR(50) DEFAULT 'Pending',
manager_message TEXT DEFAULT NULL,
admin_message TEXT DEFAULT NULL,
FOREIGN KEY (user_id) REFERENCES users(id)
);

---

## -- 6. Assignments table (admin final allocation)

CREATE TABLE assignments (
id INT AUTO_INCREMENT PRIMARY KEY,
request_id INT,
asset_id INT,
assigned_by INT,
assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (request_id) REFERENCES requests(id),
FOREIGN KEY (asset_id) REFERENCES assets(id),
FOREIGN KEY (assigned_by) REFERENCES users(id)
);

---

## -- 7. Notifications table (system messages)

CREATE TABLE notifications (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT,
message TEXT,
is_read BOOLEAN DEFAULT 0,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (user_id) REFERENCES users(id)
);
