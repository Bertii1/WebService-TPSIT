<?php
/**
 * Cron: invia email di promemoria 3 giorni prima della scadenza.
 *
 * Configurazione cron (Linux):
 *   0 8 * * * php /var/www/html/biblioteca/cron/notifiche.php >> /var/log/biblioteca_cron.log 2>&1
 */

require_once __DIR__ . '/../includes/db.php';

$giorni_avviso = 3;

$stmt = $conn->prepare(
    "SELECT p.id, p.data_scadenza,
            l.titolo,
            u.nome, u.email
     FROM prestiti p
     JOIN libri   l ON l.id = p.id_libro
     JOIN lettori u ON u.id = p.id_lettore
     WHERE p.data_restituzione IS NULL
       AND p.data_scadenza = DATE_ADD(CURDATE(), INTERVAL ? DAY)"
);
$stmt->bind_param('i', $giorni_avviso);
$stmt->execute();
$promemoria = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$inviati = 0;

foreach ($promemoria as $p) {
    $oggetto = 'Promemoria scadenza prestito - Biblioteca ITIS';
    $corpo   = "Ciao {$p['nome']},\n\n"
             . "Il libro \"{$p['titolo']}\" che hai in prestito\n"
             . "scade il " . date('d/m/Y', strtotime($p['data_scadenza']))
             . " (tra {$giorni_avviso} giorni).\n\n"
             . "Se vuoi, puoi rinnovarlo dal tuo profilo sul sito.\n\n"
             . "Biblioteca ITIS\n";

    $headers = "From: biblioteca@scuola.it\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    if (mail($p['email'], $oggetto, $corpo, $headers)) {
        $inviati++;
        echo date('Y-m-d H:i:s') . " - Email inviata a {$p['email']} per \"{$p['titolo']}\"\n";
    } else {
        echo date('Y-m-d H:i:s') . " - ERRORE invio a {$p['email']}\n";
    }
}

echo date('Y-m-d H:i:s') . " - {$inviati}/" . count($promemoria) . " email inviate.\n";
