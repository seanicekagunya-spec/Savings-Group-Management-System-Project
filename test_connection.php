<?php
/**
 * tests/test_connection.php
 * Open in the browser: http://localhost/sgms/tests/test_connection.php
 */
header('Content-Type: text/plain; charset=utf-8');

// __DIR__ = the folder this file lives in, so the path works from anywhere.
require_once __DIR__ . '/database.php';

try {
    $db = getDB();

    // 1. A plain query. No outside input is involved, so this is safe as it is.
    $row = $db->query(
        'SELECT DATABASE() AS dbname, COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE()'
    )->fetch();
    echo "Connected to {$row['dbname']} successfully. Tables found: {$row['n']} \n";

    // 2. A PREPARED statement. The ? is a placeholder: the SQL text is sent
    //    first, and the value travels separately afterwards.
    $stmt = $db->prepare('SELECT COUNT(*) AS n FROM SAVINGS_GROUP WHERE status = ?');
    $stmt->execute(['Active']);
    echo "Active savings groups: " . $stmt->fetch()['n'] . "\n";

    // 3. Attack it on purpose. A hacker could type this into a form field.
    //    Because it travels as DATA, MySQL never reads it as SQL.
    $stmt = $db->prepare('SELECT ? AS echoed');
    $stmt->execute(["x' OR '1'='1"]);
    echo "Malicious input came back as plain text: " . $stmt->fetch()['echoed'] . "\n";

} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}