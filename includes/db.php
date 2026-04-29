<?php
require_once __DIR__ . '/env.php';

$conn = new mysqli($hostname, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die('Errore di connessione: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
