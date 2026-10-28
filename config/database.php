<?php
// config/database.php

$host = "localhost";
$dbname = "ticket_management_system";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

// Mail Configuration
define('MAIL_ENABLED', false); // Set to true to enable sending emails
define('MAIL_FROM', 'noreply@ticketmanagement.local');
define('MAIL_FROM_NAME', 'Ticket Management System');
// For PHP mail() function, SMTP settings are typically set in php.ini, 
// but we keep them here as placeholders for future PHPMailer implementation if needed.
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 25);
define('SMTP_USER', '');
define('SMTP_PASS', '');
?>
