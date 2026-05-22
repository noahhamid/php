<?php
// ============================================================
//  db.php — Database Connection
//  Place this file in your project root folder
//  e.g. C:/xampp/htdocs/asset_management/db.php
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // default XAMPP MySQL user
define('DB_PASS', '');            // default XAMPP MySQL password (empty)
define('DB_NAME', 'asset_management');

// Create connection using MySQLi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("
        <div style='font-family:sans-serif;padding:2rem;color:#b91c1c;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;max-width:500px;margin:2rem auto'>
            <strong>Database connection failed:</strong><br>
            " . htmlspecialchars($conn->connect_error) . "
            <p style='color:#6b7280;font-size:.875rem'>Make sure MySQL is running in XAMPP Control Panel.</p>
        </div>
    ");
}

// Set charset to utf8mb4 (supports all characters + emojis)
$conn->set_charset('utf8mb4');