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

$erroreLogin = '';
$messaggioStep2 = '';
$erroreStep2 = '';
$messaggioStep4 = '';
$erroreStep4 = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === 'karaje' && $password === 'verifica') {
        $_SESSION['autenticato'] = true;
        $_SESSION['utente'] = $username;
        header('Location: db.php');
        exit;
    } else {
        $erroreLogin = 'Credenziali non valide';
    }
}

$autenticato = $_SESSION['autenticato'] ?? false;

$istruttori = [];
$corsiTutti = [];
$corsiIstruttore = [];
$datiStep3 = [];
$datiStep4 = [];
$datiStep5 = [];

$istruttoreSelezionato = (int)($_GET['id_istruttore'] ?? 0);
$corsoFiltroStep4 = (int)($_GET['id_corso_filtro'] ?? 0);

if ($autenticato) {
    $istruttori = $pdo->query("SELECT id_istruttore, nome, cognome FROM Istruttori ORDER BY cognome, nome")->fetchAll();
    $corsiTutti = $pdo->query("
        SELECT c.id_corso, c.nome_corso, i.nome AS nome_istruttore, i.cognome AS cognome_istruttore
        FROM Corsi c
        LEFT JOIN Istruttori i ON i.id_istruttore = c.id_istruttore
        ORDER BY c.nome_corso
    ")->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'inserisci_iscritto') {
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
        $stmtCorsiIstruttore = $pdo->prepare("SELECT id_corso, nome_corso FROM Corsi WHERE id_istruttore = ? ORDER BY nome_corso");
        $stmtCorsiIstruttore->execute([$istruttoreSelezionato]);
        $corsiIstruttore = $stmtCorsiIstruttore->fetchAll();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'cambia_corso') {
        $id_iscrizione = (int)($_POST['id_iscrizione'] ?? 0);
        $id_nuovo_corso = (int)($_POST['id_nuovo_corso'] ?? 0);
        $corsoFiltroStep4 = (int)($_POST['id_corso_filtro'] ?? 0);

        if ($id_iscrizione <= 0 || $id_nuovo_corso <= 0) {
            $erroreStep4 = 'Dati cambio corso non validi';
        } else {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Corsi WHERE id_corso = ?");
            $stmtCheck->execute([$id_nuovo_corso]);
            $corsoEsiste = (int)$stmtCheck->fetchColumn();

            if ($corsoEsiste === 0) {
                $erroreStep4 = 'Corso selezionato non valido';
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE Iscrizioni_Corsi SET id_corso = ? WHERE id_iscrizione = ?");
                $stmtUpdate->execute([$id_nuovo_corso, $id_iscrizione]);
                $messaggioStep4 = 'Corso aggiornato correttamente';
            }
        }
    }

    $sqlStep3 = "
        SELECT
            i.id_istruttore,
            i.nome AS nome_istruttore,
            i.cognome AS cognome_istruttore,
            c.id_corso,
            c.nome_corso,
            t.totale_iscritti
        FROM Istruttori i
        JOIN (
            SELECT
                c1.id_istruttore,
                c1.id_corso,
                COUNT(ic1.id_iscrizione) AS totale_iscritti
            FROM Corsi c1
            LEFT JOIN Iscrizioni_Corsi ic1 ON ic1.id_corso = c1.id_corso
            GROUP BY c1.id_istruttore, c1.id_corso
        ) t ON t.id_istruttore = i.id_istruttore
        JOIN (
            SELECT
                y.id_istruttore,
                MAX(y.totale_iscritti) AS massimo_iscritti
            FROM (
                SELECT
                    c2.id_istruttore,
                    c2.id_corso,
                    COUNT(ic2.id_iscrizione) AS totale_iscritti
                FROM Corsi c2
                LEFT JOIN Iscrizioni_Corsi ic2 ON ic2.id_corso = c2.id_corso
                GROUP BY c2.id_istruttore, c2.id_corso
            ) y
            GROUP BY y.id_istruttore
        ) m ON m.id_istruttore = t.id_istruttore AND m.massimo_iscritti = t.totale_iscritti
        JOIN Corsi c ON c.id_corso = t.id_corso
        WHERE t.totale_iscritti >= 5
        ORDER BY i.cognome, i.nome, c.nome_corso
    ";
    $datiStep3 = $pdo->query($sqlStep3)->fetchAll();

    if ($corsoFiltroStep4 > 0) {
        $stmtStep4 = $pdo->prepare("
            SELECT
                ic.id_iscrizione,
                m.id_membro,
                m.nome,
                m.cognome,
                m.tipo_abbonamento,
                ic.data_iscrizione,
                ic.orario_preferito,
                c.nome_corso
            FROM Iscrizioni_Corsi ic
            JOIN Membri m ON m.id_membro = ic.id_membro
            JOIN Corsi c ON c.id_corso = ic.id_corso
            WHERE ic.id_corso = ?
            ORDER BY m.cognome, m.nome
        ");
        $stmtStep4->execute([$corsoFiltroStep4]);
        $datiStep4 = $stmtStep4->fetchAll();
    }

    $sqlStep5 = "
        SELECT
            i.nome AS nome_istruttore,
            i.cognome AS cognome_istruttore,
            c.id_corso,
            c.nome_corso,
            c.livello_difficolta,
            c.durata_minuti,
            m.nome AS nome_membro,
            m.cognome AS cognome_membro,
            m.tipo_abbonamento,
            m.stato_pagamento,
            ic.data_iscrizione,
            ic.orario_preferito
        FROM Istruttori i
        JOIN Corsi c ON c.id_istruttore = i.id_istruttore
        LEFT JOIN Iscrizioni_Corsi ic ON ic.id_corso = c.id_corso
        LEFT JOIN Membri m ON m.id_membro = ic.id_membro
        ORDER BY i.cognome, i.nome, c.nome_corso, m.cognome, m.nome
    ";
    $datiStep5 = $pdo->query($sqlStep5)->fetchAll();
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Karaje Gym</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if (!$autenticato): ?>
    <h1>Accesso Karaje Gym</h1>
    <?php if ($erroreLogin !== ''): ?>
        <p><?= htmlspecialchars($erroreLogin) ?></p>
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
    <h1>Karaje Gym</h1>
    <p>Utente autenticato: <?= htmlspecialchars($_SESSION['utente']) ?></p>
    <p><a href="db.php?logout=1">Logout</a></p>

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

    <h2>Step 3 - Corso con maggior numero di iscritti per istruttore con almeno 5 iscritti</h2>

    <?php if (count($datiStep3) === 0): ?>
        <p>Nessun istruttore ha corsi con almeno 5 iscritti.</p>
    <?php else: ?>
        <table border="1" cellpadding="6" cellspacing="0">
            <tr>
                <th>Istruttore</th>
                <th>Corso</th>
                <th>Totale iscritti</th>
            </tr>
            <?php foreach ($datiStep3 as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['cognome_istruttore'] . ' ' . $r['nome_istruttore']) ?></td>
                    <td><?= htmlspecialchars($r['nome_corso']) ?></td>
                    <td><?= (int)$r['totale_iscritti'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>Step 4 - Elenco iscritti a un corso e cambio corso</h2>

    <?php if ($messaggioStep4 !== ''): ?>
        <p><?= htmlspecialchars($messaggioStep4) ?></p>
    <?php endif; ?>

    <?php if ($erroreStep4 !== ''): ?>
        <p><?= htmlspecialchars($erroreStep4) ?></p>
    <?php endif; ?>

    <form method="get" action="db.php">
        <div>
            <label>Corso</label>
            <select name="id_corso_filtro" required>
                <option value="">Seleziona corso</option>
                <?php foreach ($corsiTutti as $c): ?>
                    <option value="<?= (int)$c['id_corso'] ?>" <?= $corsoFiltroStep4 === (int)$c['id_corso'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nome_corso']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Mostra iscritti</button>
        </div>
    </form>

    <?php if ($corsoFiltroStep4 > 0): ?>
        <?php if (count($datiStep4) === 0): ?>
            <p>Nessun iscritto per il corso selezionato.</p>
        <?php else: ?>
            <table border="1" cellpadding="6" cellspacing="0">
                <tr>
                    <th>Iscritto</th>
                    <th>Abbonamento</th>
                    <th>Data iscrizione</th>
                    <th>Orario preferito</th>
                    <th>Cambio corso</th>
                </tr>
                <?php foreach ($datiStep4 as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['cognome'] . ' ' . $r['nome']) ?></td>
                        <td><?= htmlspecialchars($r['tipo_abbonamento']) ?></td>
                        <td><?= htmlspecialchars($r['data_iscrizione']) ?></td>
                        <td><?= htmlspecialchars($r['orario_preferito'] ?? '') ?></td>
                        <td>
                            <form method="post" action="db.php">
                                <input type="hidden" name="azione" value="cambia_corso">
                                <input type="hidden" name="id_iscrizione" value="<?= (int)$r['id_iscrizione'] ?>">
                                <input type="hidden" name="id_corso_filtro" value="<?= (int)$corsoFiltroStep4 ?>">
                                <select name="id_nuovo_corso" required>
                                    <option value="">Nuovo corso</option>
                                    <?php foreach ($corsiTutti as $c): ?>
                                        <option value="<?= (int)$c['id_corso'] ?>"><?= htmlspecialchars($c['nome_corso']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit">Cambia corso</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Step 5 - Report completo istruttori, corsi e iscritti</h2>

    <?php if (count($datiStep5) === 0): ?>
        <p>Nessun dato disponibile.</p>
    <?php else: ?>
        <table border="1" cellpadding="6" cellspacing="0">
            <tr>
                <th>Istruttore</th>
                <th>Corso</th>
                <th>Livello</th>
                <th>Durata</th>
                <th>Iscritto</th>
                <th>Abbonamento</th>
                <th>Pagamento</th>
                <th>Data iscrizione</th>
                <th>Orario</th>
            </tr>
            <?php foreach ($datiStep5 as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['cognome_istruttore'] . ' ' . $r['nome_istruttore']) ?></td>
                    <td><?= htmlspecialchars($r['nome_corso']) ?></td>
                    <td><?= htmlspecialchars($r['livello_difficolta']) ?></td>
                    <td><?= htmlspecialchars((string)$r['durata_minuti']) ?></td>
                    <td>
                        <?php
                        if ($r['cognome_membro'] === null) {
                            echo 'Nessun iscritto';
                        } else {
                            echo htmlspecialchars($r['cognome_membro'] . ' ' . $r['nome_membro']);
                        }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($r['tipo_abbonamento'] ?? '') ?></td>
                    <td>
                        <?php
                        if ($r['stato_pagamento'] === null) {
                            echo '';
                        } else {
                            echo (int)$r['stato_pagamento'] === 1 ? 'Pagato' : 'Non pagato';
                        }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($r['data_iscrizione'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['orario_preferito'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

<?php endif; ?>
</body>
</html>
