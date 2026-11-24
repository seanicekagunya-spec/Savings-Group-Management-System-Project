<?php
/**
 * config/database.php
 *
 * The ONE place that knows how to reach the database.
 * Every other PHP file calls getDB() instead of connecting by itself,
 * so if a setting changes (password, database name) we edit it here only.
 */

// XAMPP defaults: user "root" with an empty password.
$host = 'localhost';
$dbname = 'sgms_db';
$username = 'root';
$password = '';

function getDB(
    string $host = 'localhost',
    string $dbname = 'sgms_db',
    string $username = 'root',
    string $password = ''
): PDO
{
    // "static" = PHP remembers this variable between calls, so we open the
    // connection once per request instead of every time getDB() is called.
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

        $pdo = new PDO($dsn,$username,$password, [
            // If SQL fails, throw an exception instead of failing silently.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return each row as an array like ['full_name' => 'Amina'].
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}