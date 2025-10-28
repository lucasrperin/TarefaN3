<?php
// Config/Database.php - read DB configuration from environment (works in Docker)
// Defaults use 127.0.0.1 to force TCP (avoid unix socket when MySQL is remote)

$host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: getenv('DB_PASSWORD') ?: getenv('MYSQL_ROOT_PASSWORD') ?: '';
$db   = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'TarefaN3';

// If host explicitly set to 'localhost' but we don't have a socket, prefer TCP
if ($host === 'localhost') {
    $host = '127.0.0.1';
}

// Enable exceptions for mysqli so we can catch them and give a clearer message
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
} catch (mysqli_sql_exception $e) {
    // Log and show a helpful message. In production you might want to hide details.
    error_log("MySQL connection failed: " . $e->getMessage());
    die("Falha na conexão com o banco de dados. Host: " . htmlspecialchars($host) . " Usuário: " . htmlspecialchars($user) . " Erro: " . htmlspecialchars($e->getMessage()));
}

?>