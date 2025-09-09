<?php
// Example (in config or db connection file)
date_default_timezone_set('Asia/Manila');

// Database credentials
$host = 'localhost';
$dbname = 'blazelms_db';
$username = 'root';
$password = ''; // (or your MySQL password)

// Create a new MySQLi connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set charset to utf8mb4 for full Unicode support
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'"); // Set MySQL session timezone