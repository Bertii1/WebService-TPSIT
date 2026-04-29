<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('MULTA_GIORNALIERA',    0.50);
define('GIORNI_PRESTITO',      30);
define('MAX_RINNOVI',          2);
define('GIORNI_AVVISO_SCADENZA', 3);

function require_login(): void
{
    if (empty($_SESSION['ruolo'])) {
        header('Location: login.php');
        exit;
    }
}

function require_bibliotecario(): void
{
    if (empty($_SESSION['ruolo']) || !in_array($_SESSION['ruolo'], ['bibliotecario', 'admin'], true)) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    if (empty($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
        header('Location: login.php');
        exit;
    }
}

function is_logged(): bool
{
    return !empty($_SESSION['ruolo']);
}

function ruolo_attuale(): string
{
    return $_SESSION['ruolo'] ?? '';
}
