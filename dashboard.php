<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_bibliotecario();

$page_title = 'Dashboard - Biblioteca ITIS';
$messaggio  = '';
$errore     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'nuovo_prestito') {
        $email_lettore = trim($_POST['email_lettore'] ?? '');
        $id_libro      = (int)($_POST['id_libro'] ?? 0);

        if (empty($email_lettore) || $id_libro <= 0) {
            $errore = 'Compila tutti i campi.';
        } elseif (!filter_var($email_lettore, FILTER_VALIDATE_EMAIL)) {
            $errore = 'Email non valida.';
        } else {
            try {
                $stmt = $conn->prepare(
                    "SELECT id, nome FROM lettori WHERE email = ? AND ruolo = 'lettore'"
                );
                $stmt->bind_param('s', $email_lettore);
                $stmt->execute();
                $lettore = $stmt->get_result()->fetch_assoc();

                if (!$lettore) {
                    $errore = 'Nessun lettore trovato con email: ' . htmlspecialchars($email_lettore);
                } else {
                    $stmt = $conn->prepare(
                        "SELECT id, titolo, copie_disponibili FROM libri WHERE id = ?"
                    );
                    $stmt->bind_param('i', $id_libro);
                    $stmt->execute();
                    $libro = $stmt->get_result()->fetch_assoc();

                    if (!$libro) {
                        $errore = 'Libro non trovato.';
                    } elseif ($libro['copie_disponibili'] <= 0) {
                        $errore = 'Nessuna copia disponibile per questo libro.';
                    } else {
                        $stmt = $conn->prepare(
                            "SELECT id FROM prestiti
                             WHERE id_lettore = ? AND id_libro = ? AND data_restituzione IS NULL"
                        );
                        $stmt->bind_param('ii', $lettore['id'], $id_libro);
                        $stmt->execute();

                        if ($stmt->get_result()->num_rows > 0) {
                            $errore = 'Il lettore ha già questo libro in prestito.';
                        } else {
                            // Transazione: INSERT prestito + decremento copie atomici
                            $conn->begin_transaction();
                            try {
                                $giorni = GIORNI_PRESTITO;
                                $stmt   = $conn->prepare(
                                    "INSERT INTO prestiti (id_lettore, id_libro, data_inizio, data_scadenza)
                                     VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY))"
                                );
                                $stmt->bind_param('iii', $lettore['id'], $id_libro, $giorni);
                                $stmt->execute();

                                $stmt = $conn->prepare(
                                    "UPDATE libri SET copie_disponibili = copie_disponibili - 1
                                     WHERE id = ? AND copie_disponibili > 0"
                                );
                                $stmt->bind_param('i', $id_libro);
                                $stmt->execute();

                                if ($stmt->affected_rows === 0) {
                                    throw new RuntimeException('Copia non più disponibile.');
                                }

                                $conn->commit();
                                $scad      = date('d/m/Y', strtotime('+' . GIORNI_PRESTITO . ' days'));
                                $messaggio = "Prestito registrato per {$lettore['nome']}: "
                                           . "\"{$libro['titolo']}\" — scadenza {$scad}.";
                            } catch (Throwable $e) {
                                $conn->rollback();
                                error_log('[Biblioteca] nuovo_prestito transaction error: ' . $e->getMessage());
                                $errore = $e instanceof RuntimeException
                                    ? $e->getMessage()
                                    : 'Errore durante la registrazione. Riprova.';
                            }
                        }
                    }
                }
            } catch (mysqli_sql_exception $e) {
                error_log('[Biblioteca] nuovo_prestito query error: ' . $e->getMessage());
                $errore = 'Errore di sistema. Riprova più tardi.';
            }
        }
    }

    if ($azione === 'restituisci') {
        $id_prestito = (int)($_POST['id_prestito'] ?? 0);

        if ($id_prestito <= 0) {
            $errore = 'Richiesta non valida.';
        } else {
            try {
                $stmt = $conn->prepare(
                    "SELECT p.id, p.id_libro, p.data_scadenza, p.data_restituzione, l.titolo
                     FROM prestiti p JOIN libri l ON l.id = p.id_libro
                     WHERE p.id = ?"
                );
                $stmt->bind_param('i', $id_prestito);
                $stmt->execute();
                $p = $stmt->get_result()->fetch_assoc();

                if (!$p) {
                    $errore = 'Prestito non trovato.';
                } elseif ($p['data_restituzione'] !== null) {
                    $errore = 'Libro già restituito.';
                } else {
                    $oggi     = new DateTime();
                    $scadenza = new DateTime($p['data_scadenza']);
                    $multa    = 0.0;
                    $ritardo  = 0;

                    if ($oggi > $scadenza) {
                        $ritardo = (int)$oggi->diff($scadenza)->days;
                        $multa   = $ritardo * MULTA_GIORNALIERA;
                    }

                    // Transazione: UPDATE prestito + incremento copie atomici
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare(
                            "UPDATE prestiti SET data_restituzione = CURDATE(), multa = ?
                             WHERE id = ? AND data_restituzione IS NULL"
                        );
                        $stmt->bind_param('di', $multa, $id_prestito);
                        $stmt->execute();

                        if ($stmt->affected_rows === 0) {
                            throw new RuntimeException('Restituzione già registrata o prestito non trovato.');
                        }

                        $stmt = $conn->prepare(
                            "UPDATE libri
                             SET copie_disponibili = LEAST(copie_disponibili + 1, copie_totali)
                             WHERE id = ?"
                        );
                        $stmt->bind_param('i', $p['id_libro']);
                        $stmt->execute();

                        $conn->commit();

                        if ($multa > 0) {
                            $messaggio = "Restituzione registrata: \"{$p['titolo']}\". "
                                       . "Ritardo: {$ritardo} giorni. "
                                       . "Multa: € " . number_format($multa, 2, ',', '.') . ".";
                        } else {
                            $messaggio = "Restituzione registrata: \"{$p['titolo']}\". Nessuna multa.";
                        }
                    } catch (Throwable $e) {
                        $conn->rollback();
                        error_log('[Biblioteca] restituisci transaction error: ' . $e->getMessage());
                        $errore = $e instanceof RuntimeException
                            ? $e->getMessage()
                            : 'Errore durante la registrazione. Riprova.';
                    }
                }
            } catch (mysqli_sql_exception $e) {
                error_log('[Biblioteca] restituisci query error: ' . $e->getMessage());
                $errore = 'Errore di sistema. Riprova più tardi.';
            }
        }
    }
}

$libri_disponibili = [];
$prestiti_aperti   = [];
$db_errore         = false;

try {
    $libri_disponibili = $conn->query(
        "SELECT id, titolo, autore FROM libri WHERE copie_disponibili > 0 ORDER BY titolo"
    )->fetch_all(MYSQLI_ASSOC);

    $prestiti_aperti = $conn->query(
        "SELECT p.id, p.data_inizio, p.data_scadenza, p.numero_rinnovi,
                l.titolo, l.autore,
                CONCAT(u.nome, ' ', u.cognome) AS nome_lettore, u.email
         FROM prestiti p
         JOIN libri l   ON l.id = p.id_libro
         JOIN lettori u ON u.id = p.id_lettore
         WHERE p.data_restituzione IS NULL
         ORDER BY p.data_scadenza ASC"
    )->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    error_log('[Biblioteca] dashboard.php load error: ' . $e->getMessage());
    $db_errore = true;
}

$oggi          = new DateTime();
$n_scaduti     = 0;
$n_in_scadenza = 0;

foreach ($prestiti_aperti as $p) {
    $gg = (int)$oggi->diff(new DateTime($p['data_scadenza']))->format('%r%a');
    if ($gg < 0) {
        $n_scaduti++;
    } elseif ($gg <= GIORNI_AVVISO_SCADENZA) {
        $n_in_scadenza++;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<h2 class="mb-4">Dashboard Bibliotecario</h2>

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
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Nuovo prestito</h5>
            </div>
            <div class="card-body">
                <form method="POST" novalidate>
                    <input type="hidden" name="azione" value="nuovo_prestito">
                    <div class="mb-3">
                        <label class="form-label">Email studente</label>
                        <input type="email" class="form-control" name="email_lettore"
                               placeholder="studente@esempio.it" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Libro</label>
                        <select class="form-select" name="id_libro" required>
                            <option value="">— Seleziona un libro —</option>
                            <?php foreach ($libri_disponibili as $l): ?>
                            <option value="<?= $l['id'] ?>">
                                <?= htmlspecialchars($l['titolo']) ?>
                                — <?= htmlspecialchars($l['autore']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($libri_disponibili) && !$db_errore): ?>
                        <div class="form-text text-warning">Nessun libro disponibile al momento.</div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-dark w-100">Registra prestito</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="row g-3">
            <div class="col-sm-4">
                <div class="card text-center">
                    <div class="card-body py-4">
                        <div class="h2 text-primary"><?= count($prestiti_aperti) ?></div>
                        <small class="text-muted">Prestiti aperti</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card text-center">
                    <div class="card-body py-4">
                        <div class="h2 text-danger"><?= $n_scaduti ?></div>
                        <small class="text-muted">In ritardo</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card text-center">
                    <div class="card-body py-4">
                        <div class="h2 text-warning"><?= $n_in_scadenza ?></div>
                        <small class="text-muted">In scadenza (<?= GIORNI_AVVISO_SCADENZA ?>gg)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Prestiti aperti</h5>
        <span class="badge bg-light text-dark"><?= count($prestiti_aperti) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if ($db_errore): ?>
        <div class="p-4 text-danger">Dati non disponibili.</div>
        <?php elseif (empty($prestiti_aperti)): ?>
        <div class="p-4 text-muted">Nessun prestito aperto.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Studente</th>
                        <th>Libro</th>
                        <th>Inizio</th>
                        <th>Scadenza</th>
                        <th>Stato</th>
                        <th>Azione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prestiti_aperti as $p): ?>
                    <?php
                        $gg      = (int)$oggi->diff(new DateTime($p['data_scadenza']))->format('%r%a');
                        $scaduto = $gg < 0;
                        $in_sc   = !$scaduto && $gg <= GIORNI_AVVISO_SCADENZA;
                    ?>
                    <tr class="<?= $scaduto ? 'table-danger' : ($in_sc ? 'table-warning' : '') ?>">
                        <td><?= (int)$p['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($p['nome_lettore']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($p['email']) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($p['titolo']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($p['autore']) ?></small>
                        </td>
                        <td><?= date('d/m/Y', strtotime($p['data_inizio'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($p['data_scadenza'])) ?></td>
                        <td>
                            <?php if ($scaduto): ?>
                                <?php $multa_prev = abs($gg) * MULTA_GIORNALIERA; ?>
                                <span class="badge bg-danger">
                                    Ritardo <?= abs($gg) ?>gg
                                    — multa € <?= number_format($multa_prev, 2, ',', '.') ?>
                                </span>
                            <?php elseif ($in_sc): ?>
                                <span class="badge bg-warning text-dark">
                                    Scade in <?= $gg ?>gg
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success">Regolare</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="azione" value="restituisci">
                                <input type="hidden" name="id_prestito" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-dark"
                                        onclick="return confirm('Confermi la restituzione?')">
                                    Restituisci
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
