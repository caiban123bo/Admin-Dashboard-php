<?php
$host = 'localhost';         // or your database host
$db   = 'Shop_Nhom2';        // your database name
$user = 'root';              // your MySQL username
$pass = '';                  // your MySQL password ('' if none)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepares if possible
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}