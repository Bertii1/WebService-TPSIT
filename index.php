<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Home - Biblioteca ITIS';

$totale_libri     = $conn->query("SELECT COUNT(*) FROM libri")->fetch_row()[0] ?? 0;
$copie_disponibili = $conn->query("SELECT COALESCE(SUM(copie_disponibili),0) FROM libri")->fetch_row()[0] ?? 0;
$prestiti_aperti  = $conn->query("SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL")->fetch_row()[0] ?? 0;
$totale_lettori   = $conn->query("SELECT COUNT(*) FROM lettori WHERE ruolo = 'lettore'")->fetch_row()[0] ?? 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="p-5 mb-4 bg-dark text-white rounded-3">
    <div class="py-3">
        <h1 class="display-5 fw-bold">📚 Biblioteca ITIS</h1>
        <p class="fs-4 col-md-8">Sistema di gestione prestiti per la biblioteca scolastica.</p>
        <a href="catalogo.php" class="btn btn-light btn-lg">Sfoglia il catalogo</a>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center h-100">
            <div class="card-body py-4">
                <div class="display-4 text-primary"><?= $totale_libri ?></div>
                <p class="text-muted mb-0">Titoli in catalogo</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center h-100">
            <div class="card-body py-4">
                <div class="display-4 text-success"><?= $copie_disponibili ?></div>
                <p class="text-muted mb-0">Copie disponibili</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center h-100">
            <div class="card-body py-4">
                <div class="display-4 text-warning"><?= $prestiti_aperti ?></div>
                <p class="text-muted mb-0">Prestiti attivi</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center h-100">
            <div class="card-body py-4">
                <div class="display-4 text-info"><?= $totale_lettori ?></div>
                <p class="text-muted mb-0">Studenti iscritti</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
