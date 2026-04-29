<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_login();

if (ruolo_attuale() !== 'lettore') {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'I miei prestiti - Biblioteca ITIS';
$id_lettore = (int)$_SESSION['id'];
$messaggio  = '';
$errore     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rinnova'])) {
    $id_prestito = (int)($_POST['id_prestito'] ?? 0);

    if ($id_prestito <= 0) {
        $errore = 'Richiesta non valida.';
    } else {
        try {
            $stmt = $conn->prepare(
                "SELECT id, data_scadenza, numero_rinnovi, data_restituzione
                 FROM prestiti WHERE id = ? AND id_lettore = ?"
            );
            $stmt->bind_param('ii', $id_prestito, $id_lettore);
            $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();

            if (!$p) {
                $errore = 'Prestito non trovato.';
            } elseif ($p['data_restituzione'] !== null) {
                $errore = 'Libro già restituito.';
            } elseif ($p['numero_rinnovi'] >= MAX_RINNOVI) {
                $errore = 'Raggiunto il numero massimo di rinnovi (' . MAX_RINNOVI . ').';
            } elseif (new DateTime() > new DateTime($p['data_scadenza'])) {
                $errore = 'Prestito scaduto: restituisci il libro in biblioteca.';
            } else {
                $giorni = GIORNI_PRESTITO;
                $stmt   = $conn->prepare(
                    "UPDATE prestiti
                     SET data_scadenza = DATE_ADD(data_scadenza, INTERVAL ? DAY),
                         numero_rinnovi = numero_rinnovi + 1
                     WHERE id = ? AND id_lettore = ?"
                );
                $stmt->bind_param('iii', $giorni, $id_prestito, $id_lettore);
                $stmt->execute();

                if ($stmt->affected_rows === 0) {
                    $errore = 'Rinnovo non riuscito. Riprova.';
                } else {
                    $messaggio = 'Rinnovo effettuato! Scadenza spostata di ' . GIORNI_PRESTITO . ' giorni.';
                }
            }
        } catch (mysqli_sql_exception $e) {
            error_log('[Biblioteca] profilo.php rinnovo error: ' . $e->getMessage());
            $errore = 'Errore durante il rinnovo. Riprova più tardi.';
        }
    }
}

$prestiti_attivi = [];
$storico         = [];
$db_errore       = false;

try {
    $stmt = $conn->prepare(
        "SELECT p.id, l.titolo, l.autore, p.data_inizio, p.data_scadenza, p.numero_rinnovi AS rinnovi
         FROM prestiti p
         JOIN libri l ON l.id = p.id_libro
         WHERE p.id_lettore = ? AND p.data_restituzione IS NULL
         ORDER BY p.data_scadenza ASC"
    );
    $stmt->bind_param('i', $id_lettore);
    $stmt->execute();
    $prestiti_attivi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare(
        "SELECT p.id, l.titolo, l.autore, p.data_inizio, p.data_restituzione, p.multa
         FROM prestiti p
         JOIN libri l ON l.id = p.id_libro
         WHERE p.id_lettore = ? AND p.data_restituzione IS NOT NULL
         ORDER BY p.data_restituzione DESC
         LIMIT 10"
    );
    $stmt->bind_param('i', $id_lettore);
    $stmt->execute();
    $storico = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    error_log('[Biblioteca] profilo.php load error: ' . $e->getMessage());
    $db_errore = true;
}

$oggi = new DateTime();

require_once __DIR__ . '/includes/header.php';
?>

<h2 class="mb-4">I miei prestiti</h2>

<?php if ($messaggio): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= htmlspecialchars($messaggio) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($errore): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= htmlspecialchars($errore) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($db_errore): ?>
<div class="alert alert-danger">Errore nel caricamento dei dati. Riprova più tardi.</div>
<?php else: ?>

<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">Prestiti attivi (<?= count($prestiti_attivi) ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($prestiti_attivi)): ?>
        <div class="p-4 text-muted">Nessun prestito attivo.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Libro</th>
                        <th>Autore</th>
                        <th>Inizio</th>
                        <th>Scadenza</th>
                        <th>Stato</th>
                        <th class="text-center">Rinnovi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prestiti_attivi as $p): ?>
                    <?php
                        $scadenza       = new DateTime($p['data_scadenza']);
                        $giorni_rimasti = (int)$oggi->diff($scadenza)->format('%r%a');
                        $scaduto        = $giorni_rimasti < 0;
                        $in_scadenza    = !$scaduto && $giorni_rimasti <= GIORNI_AVVISO_SCADENZA;
                        $puo_rinnovare  = !$scaduto && $p['rinnovi'] < MAX_RINNOVI;
                    ?>
                    <tr class="<?= $scaduto ? 'table-danger' : ($in_scadenza ? 'table-warning' : '') ?>">
                        <td class="fw-semibold"><?= htmlspecialchars($p['titolo']) ?></td>
                        <td><?= htmlspecialchars($p['autore']) ?></td>
                        <td><?= date('d/m/Y', strtotime($p['data_inizio'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($p['data_scadenza'])) ?></td>
                        <td>
                            <?php if ($scaduto): ?>
                                <span class="badge bg-danger">
                                    Scaduto (<?= abs($giorni_rimasti) ?> gg fa)
                                </span>
                            <?php elseif ($in_scadenza): ?>
                                <span class="badge bg-warning text-dark">
                                    Scade in <?= $giorni_rimasti ?> gg
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success">
                                    Attivo (<?= $giorni_rimasti ?> gg)
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $p['rinnovi'] ?>/<?= MAX_RINNOVI ?></td>
                        <td>
                            <?php if ($puo_rinnovare): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="id_prestito" value="<?= $p['id'] ?>">
                                <button type="submit" name="rinnova" class="btn btn-sm btn-outline-primary">
                                    Rinnova +30gg
                                </button>
                            </form>
                            <?php else: ?>
                                <span class="text-muted small">Non disponibile</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($storico)): ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Storico ultimi prestiti</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Libro</th>
                        <th>Autore</th>
                        <th>Preso il</th>
                        <th>Restituito il</th>
                        <th>Multa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($storico as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['titolo']) ?></td>
                        <td><?= htmlspecialchars($p['autore']) ?></td>
                        <td><?= date('d/m/Y', strtotime($p['data_inizio'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($p['data_restituzione'])) ?></td>
                        <td>
                            <?php if ($p['multa'] > 0): ?>
                                <span class="text-danger fw-semibold">
                                    € <?= number_format((float)$p['multa'], 2, ',', '.') ?>
                                </span>
                            <?php else: ?>
                                <span class="text-success">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
