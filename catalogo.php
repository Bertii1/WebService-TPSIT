<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Catalogo Libri - Biblioteca ITIS';

$cerca     = trim($_GET['cerca'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');

$libri     = [];
$categorie = [];
$db_errore = false;

try {
    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($cerca !== '') {
        $where[]  = '(titolo LIKE ? OR autore LIKE ?)';
        $params[] = "%{$cerca}%";
        $params[] = "%{$cerca}%";
        $types   .= 'ss';
    }
    if ($categoria !== '') {
        $where[]  = 'categoria = ?';
        $params[] = $categoria;
        $types   .= 's';
    }

    $sql  = 'SELECT * FROM libri WHERE ' . implode(' AND ', $where) . ' ORDER BY titolo ASC';
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $libri = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $categorie = $conn->query(
        "SELECT DISTINCT categoria FROM libri WHERE categoria != '' ORDER BY categoria"
    )->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    error_log('[Biblioteca] catalogo.php query error: ' . $e->getMessage());
    $db_errore = true;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Catalogo Libri</h2>
    <span class="text-muted"><?= count($libri) ?> libri trovati</span>
</div>

<?php if ($db_errore): ?>
<div class="alert alert-danger">
    Errore nel caricamento del catalogo. Riprova più tardi.
</div>
<?php else: ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <input type="text" class="form-control" name="cerca"
                       placeholder="Cerca per titolo o autore..."
                       value="<?= htmlspecialchars($cerca) ?>">
            </div>
            <div class="col-md-4">
                <select class="form-select" name="categoria">
                    <option value="">Tutte le categorie</option>
                    <?php foreach ($categorie as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['categoria']) ?>"
                        <?= $categoria === $cat['categoria'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['categoria']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100">Cerca</button>
            </div>
        </form>
        <?php if ($cerca !== '' || $categoria !== ''): ?>
        <div class="mt-2">
            <a href="catalogo.php" class="text-muted small">✕ Rimuovi filtri</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($libri)): ?>
<div class="alert alert-info">Nessun libro trovato.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Titolo</th>
                <th>Autore</th>
                <th>Categoria</th>
                <th class="text-center">Copie totali</th>
                <th class="text-center">Disponibilità</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($libri as $libro): ?>
            <tr>
                <td class="fw-semibold"><?= htmlspecialchars($libro['titolo']) ?></td>
                <td><?= htmlspecialchars($libro['autore']) ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($libro['categoria']) ?></span></td>
                <td class="text-center"><?= $libro['copie_totali'] ?></td>
                <td class="text-center">
                    <?php if ($libro['copie_disponibili'] > 0): ?>
                        <span class="badge bg-success">
                            ✓ Disponibile (<?= $libro['copie_disponibili'] ?>)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger">✗ Non disponibile</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
