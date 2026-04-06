<?php
/**
 * Database Configuration & Connection
 */
date_default_timezone_set('Africa/Accra');

// Load .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'qams_db');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306); // Change this to your MySQL port (e.g., 3306, 3307, etc.)

// Create connection
function getDbConnection() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            die("Database connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        $conn->query("SET time_zone = '+00:00'");
    }
    return $conn;
}

// Helper: run a prepared statement and return result
function dbQuery($sql, $types = '', $params = []) {
    $conn = getDbConnection();
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Query preparation failed: " . $conn->error);
    }
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

// Helper: fetch all rows
function dbFetchAll($sql, $types = '', $params = []) {
    $stmt = dbQuery($sql, $types, $params);
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Helper: fetch single row
function dbFetchOne($sql, $types = '', $params = []) {
    $stmt = dbQuery($sql, $types, $params);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

// Helper: insert and return insert id
function dbInsert($sql, $types = '', $params = []) {
    $stmt = dbQuery($sql, $types, $params);
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

// Helper: update/delete and return affected rows
function dbExecute($sql, $types = '', $params = []) {
    $stmt = dbQuery($sql, $types, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}
