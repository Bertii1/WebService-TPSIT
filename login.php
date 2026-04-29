<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (is_logged()) {
    header('Location: index.php');
    exit;
}

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errore = 'Inserisci email e password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errore = 'Formato email non valido.';
    } else {
        try {
            $stmt = $conn->prepare(
                "SELECT id, CONCAT(nome, ' ', cognome) AS nome, email, password, ruolo FROM lettori WHERE email = ? AND attivo = 1"
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if ($row && password_verify($password, $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['id']    = $row['id'];
                $_SESSION['nome']  = $row['nome'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['ruolo'] = $row['ruolo'];

                if (in_array($row['ruolo'], ['bibliotecario', 'admin'], true)) {
                    header('Location: dashboard.php');
                } else {
                    header('Location: profilo.php');
                }
                exit;
            } else {
                // Stesso messaggio per utente non trovato e password errata (no user enumeration)
                $errore = 'Credenziali non valide.';
            }
        } catch (mysqli_sql_exception $e) {
            error_log('[Biblioteca] login.php query error: ' . $e->getMessage());
            $errore = 'Errore di sistema. Riprova più tardi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accedi - Biblioteca ITIS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-box {
            width: 100%;
            max-width: 400px;
            background: #fff;
            padding: 40px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        h1 { text-align: center; font-size: 1.6rem; font-weight: 600; margin-bottom: 8px; }
        .subtitle { text-align: center; color: #6c757d; font-size: 0.9rem; margin-bottom: 28px; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 6px; }
        input[type="email"],
        input[type="password"] {
            width: 100%; padding: 10px 14px; border: 1px solid #ced4da;
            border-radius: 4px; font-size: 14px; margin-bottom: 18px;
        }
        input:focus { outline: none; border-color: #212529; box-shadow: 0 0 0 2px rgba(33,37,41,.15); }
        .btn {
            width: 100%; padding: 11px; background: #212529; color: #fff;
            border: none; border-radius: 4px; font-size: 15px; font-weight: 500;
            cursor: pointer; transition: background .2s;
        }
        .btn:hover { background: #343a40; }
        .alert { background: #f8d7da; color: #842029; padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 14px; }
        .footer-link { text-align: center; margin-top: 20px; font-size: 13px; color: #6c757d; }
        .footer-link a { color: #212529; font-weight: 500; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>📚 Biblioteca ITIS</h1>
        <p class="subtitle">Accedi al tuo account</p>

        <?php if ($errore): ?>
        <div class="alert"><?= htmlspecialchars($errore) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   placeholder="nome@esempio.it"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>

            <button type="submit" class="btn">Accedi</button>
        </form>
        <div class="footer-link">
            <a href="index.php">← Torna al catalogo</a>
        </div>
    </div>
</body>
</html>
