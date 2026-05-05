<?php
session_start();

$host = '127.0.0.1';
$db = 'karaje_gym';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Errore connessione database: ' . $e->getMessage());
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: db.php');
    exit;
}

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === 'karaje' && $password === 'verifica') {
        $_SESSION['autenticato'] = true;
        $_SESSION['utente'] = $username;
        header('Location: db.php');
        exit;
    } else {
        $errore = 'Credenziali non valide';
    }
}

$autenticato = $_SESSION['autenticato'] ?? false;
?>

<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Karaje Gym</title>
</head>
<body>
<?php if (!$autenticato): ?>
    <h1>Accesso Karaje Gym</h1>
    <?php if ($errore !== ''): ?>
        <p><?= htmlspecialchars($errore) ?></p>
    <?php endif; ?>
    <form method="post" action="db.php">
        <input type="hidden" name="azione" value="login">
        <div>
            <label>Nome utente</label>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">Accedi</button>
    </form>
<?php else: ?>
    <h1>Area riservata</h1>
    <p>Benvenuto <?= htmlspecialchars($_SESSION['utente']) ?></p>
    <ul>
        <li>Step 2: Inserimento nuovo iscritto a corso e istruttore da menu a tendina</li>
        <li>Step 3: Corso con maggior numero di iscritti per istruttore (minimo 5)</li>
        <li>Step 4: Elenco iscritti a corso con cambio corso</li>
        <li>Step 5: Report completo corsi e iscritti</li>
    </ul>
    <a href="db.php?logout=1">Logout</a>
<?php endif; ?>
</body>
</html>
