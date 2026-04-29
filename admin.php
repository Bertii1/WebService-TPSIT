<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_admin();

$page_title = 'Pannello Admin - Biblioteca ITIS';
$messaggio  = '';
$errore     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'aggiungi_libro') {
        $titolo    = trim($_POST['titolo'] ?? '');
        $autore    = trim($_POST['autore'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $copie     = (int)($_POST['copie_totali'] ?? 1);

        if (empty($titolo) || empty($autore)) {
            $errore = 'Titolo e autore sono obbligatori.';
        } elseif ($copie < 1) {
            $errore = 'Il numero di copie deve essere almeno 1.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO libri (titolo, autore, categoria, copie_totali, copie_disponibili)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('sssii', $titolo, $autore, $categoria, $copie, $copie);
            $stmt->execute();
            $messaggio = "Libro \"{$titolo}\" aggiunto ({$copie} " . ($copie === 1 ? 'copia' : 'copie') . ').';
        }
    }

    if ($azione === 'elimina_libro') {
        $id = (int)$_POST['id'];

        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM prestiti WHERE id_libro = ? AND data_restituzione IS NULL"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $aperti = $stmt->get_result()->fetch_row()[0];

        if ($aperti > 0) {
            $errore = 'Impossibile eliminare: il libro ha prestiti ancora aperti.';
        } else {
            $stmt = $conn->prepare("DELETE FROM libri WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $messaggio = 'Libro eliminato.';
        }
    }

    if ($azione === 'aggiungi_utente') {
        $nome     = trim($_POST['nome'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ruolo    = $_POST['ruolo'] ?? 'lettore';

        if (empty($nome) || empty($email) || empty($password)) {
            $errore = 'Tutti i campi sono obbligatori.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errore = 'Email non valida.';
        } elseif (strlen($password) < 6) {
            $errore = 'Password di almeno 6 caratteri.';
        } elseif (!in_array($ruolo, ['lettore', 'bibliotecario', 'admin'], true)) {
            $errore = 'Ruolo non valido.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO lettori (nome, email, password, ruolo, data_iscrizione)
                 VALUES (?, ?, ?, ?, CURDATE())"
            );
            $stmt->bind_param('ssss', $nome, $email, $hash, $ruolo);
            if ($stmt->execute()) {
                $messaggio = "Utente \"{$nome}\" aggiunto come {$ruolo}.";
            } else {
                $errore = 'Email già registrata.';
            }
        }
    }

    if ($azione === 'elimina_utente') {
        $id = (int)$_POST['id'];
        if ($id === (int)$_SESSION['id']) {
            $errore = 'Non puoi eliminare il tuo account.';
        } else {
            $stmt = $conn->prepare("DELETE FROM lettori WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $messaggio = 'Utente eliminato.';
        }
    }
}

$libri  = $conn->query("SELECT * FROM libri ORDER BY titolo")->fetch_all(MYSQLI_ASSOC);
$utenti = $conn->query(
    "SELECT id, nome, email, ruolo, data_iscrizione FROM lettori ORDER BY ruolo, nome"
)->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<h2 class="mb-4">Pannello Amministratore</h2>

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

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-libri">
            Libri (<?= count($libri) ?>)
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-utenti">
            Utenti (<?= count($utenti) ?>)
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Tab Libri -->
    <div class="tab-pane fade show active" id="tab-libri">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">Aggiungi libro</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="azione" value="aggiungi_libro">
                            <div class="mb-3">
                                <label class="form-label">Titolo *</label>
                                <input type="text" class="form-control" name="titolo" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Autore *</label>
                                <input type="text" class="form-control" name="autore" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Categoria</label>
                                <input type="text" class="form-control" name="categoria">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Numero copie</label>
                                <input type="number" class="form-control" name="copie_totali"
                                       value="1" min="1">
                            </div>
                            <button type="submit" class="btn btn-dark w-100">Aggiungi</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">Catalogo</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Titolo</th>
                                        <th>Autore</th>
                                        <th>Categoria</th>
                                        <th class="text-center">Copie</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($libri as $l): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($l['titolo']) ?></td>
                                        <td><?= htmlspecialchars($l['autore']) ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($l['categoria']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?= $l['copie_disponibili'] ?>/<?= $l['copie_totali'] ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="azione" value="elimina_libro">
                                                <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger btn-elimina">
                                                    Elimina
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Utenti -->
    <div class="tab-pane fade" id="tab-utenti">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">Aggiungi utente</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="azione" value="aggiungi_utente">
                            <div class="mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" class="form-control" name="nome" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password"
                                       required minlength="6">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ruolo</label>
                                <select class="form-select" name="ruolo">
                                    <option value="lettore">Lettore</option>
                                    <option value="bibliotecario">Bibliotecario</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-dark w-100">Aggiungi</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">Utenti registrati</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Ruolo</th>
                                        <th>Iscritto il</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($utenti as $u): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($u['nome']) ?></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td>
                                            <?php
                                            $badge = match($u['ruolo']) {
                                                'admin'         => 'bg-danger',
                                                'bibliotecario' => 'bg-warning text-dark',
                                                default         => 'bg-info text-dark',
                                            };
                                            ?>
                                            <span class="badge <?= $badge ?>">
                                                <?= $u['ruolo'] ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($u['data_iscrizione'])) ?></td>
                                        <td>
                                            <?php if ($u['id'] !== (int)$_SESSION['id']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="azione" value="elimina_utente">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger btn-elimina">
                                                    Elimina
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <span class="text-muted small">tu</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
