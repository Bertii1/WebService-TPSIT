<?php
require_once __DIR__ . '/env.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($hostname, $db_username, $db_password, $db_name);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('[Biblioteca] DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('Servizio temporaneamente non disponibile. Riprova più tardi.');
}
