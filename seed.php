<?php
/**
 * Inserisce dati demo nel database.
 * Esegui UNA VOLTA dopo aver importato biblioteca.sql
 * Poi elimina o proteggi questo file.
 *
 * Credenziali demo:
 *   admin@biblioteca.it       / admin123
 *   bibliotecario@biblioteca.it / biblio123
 *   giovanni@studenti.it      / studente123
 *   lucia@studenti.it         / studente123
 */

require_once __DIR__ . '/includes/db.php';

$utenti = [
    ['Admin Sistema',    'admin@biblioteca.it',         'admin123',    'admin'],
    ['Mario Rossi',      'bibliotecario@biblioteca.it', 'biblio123',   'bibliotecario'],
    ['Giovanni Bianchi', 'giovanni@studenti.it',        'studente123', 'lettore'],
    ['Lucia Ferrari',    'lucia@studenti.it',           'studente123', 'lettore'],
];

foreach ($utenti as [$nome, $email, $pass, $ruolo]) {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO lettori (nome, email, password, ruolo, data_iscrizione)
         VALUES (?, ?, ?, ?, CURDATE())"
    );
    $stmt->bind_param('ssss', $nome, $email, $hash, $ruolo);
    $stmt->execute();
    echo "Utente: {$nome} ({$ruolo})<br>";
}

$libri = [
    ['I Promessi Sposi',           'Alessandro Manzoni',       'Narrativa',           3, 3],
    ['La Divina Commedia',         'Dante Alighieri',          'Poesia',              2, 2],
    ['Il Nome della Rosa',         'Umberto Eco',              'Romanzo storico',     2, 2],
    ['Pinocchio',                  'Carlo Collodi',            'Narrativa ragazzi',   4, 4],
    ['Se questo è un uomo',        'Primo Levi',               'Autobiografia',       2, 2],
    ['Il Gattopardo',              'G. Tomasi di Lampedusa',   'Narrativa',           1, 1],
    ['La luna e i falò',           'Cesare Pavese',            'Narrativa',           2, 2],
    ['Cronache di poveri amanti',  'Vasco Pratolini',          'Narrativa',           1, 1],
];

foreach ($libri as [$titolo, $autore, $categoria, $tot, $disp]) {
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO libri (titolo, autore, categoria, copie_totali, copie_disponibili)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssii', $titolo, $autore, $categoria, $tot, $disp);
    $stmt->execute();
    echo "Libro: {$titolo}<br>";
}

echo "<br><strong>Setup completato!</strong><br>";
echo "<a href='index.php'>Vai alla homepage</a>";
