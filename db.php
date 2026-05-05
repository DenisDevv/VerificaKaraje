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
$messaggioStep2 = '';
$erroreStep2 = '';

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

$istruttori = [];
$corsiIstruttore = [];
$istruttoreSelezionato = 0;

if ($autenticato) {
    $stmtIstruttori = $pdo->query("SELECT id_istruttore, nome, cognome FROM Istruttori ORDER BY cognome, nome");
    $istruttori = $stmtIstruttori->fetchAll();

    if (isset($_GET['id_istruttore'])) {
        $istruttoreSelezionato = (int)$_GET['id_istruttore'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'inserisci_iscritto') {
        $nome = trim($_POST['nome'] ?? '');
        $cognome = trim($_POST['cognome'] ?? '');
        $data_nascita = trim($_POST['data_nascita'] ?? '');
        $tipo_abbonamento = trim($_POST['tipo_abbonamento'] ?? '');
        $stato_pagamento = isset($_POST['stato_pagamento']) ? 1 : 0;
        $id_istruttore = (int)($_POST['id_istruttore'] ?? 0);
        $id_corso = (int)($_POST['id_corso'] ?? 0);
        $orario_preferito = trim($_POST['orario_preferito'] ?? '');

        $istruttoreSelezionato = $id_istruttore;

        if ($nome === '' || $cognome === '' || $data_nascita === '' || $tipo_abbonamento === '' || $id_istruttore <= 0 || $id_corso <= 0) {
            $erroreStep2 = 'Compila tutti i campi obbligatori';
        } else {
            $stmtControlloCorso = $pdo->prepare("SELECT COUNT(*) FROM Corsi WHERE id_corso = ? AND id_istruttore = ?");
            $stmtControlloCorso->execute([$id_corso, $id_istruttore]);
            $okCorso = (int)$stmtControlloCorso->fetchColumn();

            if ($okCorso === 0) {
                $erroreStep2 = 'Il corso non appartiene all istruttore selezionato';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmtMembro = $pdo->prepare("INSERT INTO Membri (nome, cognome, data_nascita, tipo_abbonamento, stato_pagamento) VALUES (?, ?, ?, ?, ?)");
                    $stmtMembro->execute([$nome, $cognome, $data_nascita, $tipo_abbonamento, $stato_pagamento]);
                    $id_membro = (int)$pdo->lastInsertId();

                    $stmtIscrizione = $pdo->prepare("INSERT INTO Iscrizioni_Corsi (id_corso, id_membro, data_iscrizione, orario_preferito) VALUES (?, ?, CURDATE(), ?)");
                    $orarioFinale = $orario_preferito !== '' ? $orario_preferito : null;
                    $stmtIscrizione->execute([$id_corso, $id_membro, $orarioFinale]);

                    $pdo->commit();
                    $messaggioStep2 = 'Nuovo iscritto inserito correttamente';
                } catch (Throwable $t) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $erroreStep2 = 'Errore durante l inserimento';
                }
            }
        }
    }

    if ($istruttoreSelezionato > 0) {
        $stmtCorsi = $pdo->prepare("SELECT id_corso, nome_corso FROM Corsi WHERE id_istruttore = ? ORDER BY nome_corso");
        $stmtCorsi->execute([$istruttoreSelezionato]);
        $corsiIstruttore = $stmtCorsi->fetchAll();
    }
}
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

    <h2>Step 2 - Inserisci nuovo iscritto</h2>

    <?php if ($messaggioStep2 !== ''): ?>
        <p><?= htmlspecialchars($messaggioStep2) ?></p>
    <?php endif; ?>

    <?php if ($erroreStep2 !== ''): ?>
        <p><?= htmlspecialchars($erroreStep2) ?></p>
    <?php endif; ?>

    <form method="get" action="db.php">
        <div>
            <label>Istruttore</label>
            <select name="id_istruttore" required>
                <option value="">Seleziona istruttore</option>
                <?php foreach ($istruttori as $i): ?>
                    <option value="<?= (int)$i['id_istruttore'] ?>" <?= $istruttoreSelezionato === (int)$i['id_istruttore'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($i['cognome'] . ' ' . $i['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Carica corsi</button>
        </div>
    </form>

    <form method="post" action="db.php">
        <input type="hidden" name="azione" value="inserisci_iscritto">
        <div>
            <label>Nome</label>
            <input type="text" name="nome" required>
        </div>
        <div>
            <label>Cognome</label>
            <input type="text" name="cognome" required>
        </div>
        <div>
            <label>Data nascita</label>
            <input type="date" name="data_nascita" required>
        </div>
        <div>
            <label>Tipo abbonamento</label>
            <select name="tipo_abbonamento" required>
                <option value="">Seleziona tipo</option>
                <option value="Mensile">Mensile</option>
                <option value="Trimestrale">Trimestrale</option>
                <option value="Annuale">Annuale</option>
            </select>
        </div>
        <div>
            <label>Stato pagamento</label>
            <input type="checkbox" name="stato_pagamento" checked>
        </div>
        <div>
            <label>Istruttore</label>
            <select name="id_istruttore" required>
                <option value="">Seleziona istruttore</option>
                <?php foreach ($istruttori as $i): ?>
                    <option value="<?= (int)$i['id_istruttore'] ?>" <?= $istruttoreSelezionato === (int)$i['id_istruttore'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($i['cognome'] . ' ' . $i['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Corso</label>
            <select name="id_corso" required>
                <option value="">Seleziona corso</option>
                <?php foreach ($corsiIstruttore as $c): ?>
                    <option value="<?= (int)$c['id_corso'] ?>"><?= htmlspecialchars($c['nome_corso']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Orario preferito</label>
            <input type="time" name="orario_preferito">
        </div>
        <button type="submit">Inserisci iscritto</button>
    </form>

    <ul>
        <li>Step 3: Corso con maggior numero di iscritti per istruttore con almeno 5 iscritti</li>
        <li>Step 4: Elenco iscritti a corso con cambia corso</li>
        <li>Step 5: Report completo corsi e iscritti ordinato</li>
    </ul>

    <a href="db.php?logout=1">Logout</a>
<?php endif; ?>
</body>
</html>