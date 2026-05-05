<?php
session_start();
require_once __DIR__ . '/db.php';

function v($array, $key, $default)
{
    return isset($array[$key]) ? $array[$key] : $default;
}

$pdo = getPdo();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$erroreLogin = '';
$msgStep2 = '';
$errStep2 = '';
$msgStep4 = '';
$errStep4 = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && v($_POST, 'azione', '') === 'login') {
    $username = trim(v($_POST, 'username', ''));
    $password = trim(v($_POST, 'password', ''));

    if ($username === 'karaje' && $password === 'verifica') {
        $_SESSION['autenticato'] = true;
        $_SESSION['utente'] = $username;
        header('Location: index.php');
        exit;
    }

    $erroreLogin = 'Credenziali non valide';
}

$autenticato = v($_SESSION, 'autenticato', false);

$istruttori = array();
$corsi = array();
$step3 = array();
$step4 = array();
$step5 = array();

$corsoFiltro = (int)v($_GET, 'id_corso_filtro', 0);

if ($autenticato) {
    $istruttori = $pdo->query('SELECT id_istruttore, nome, cognome FROM Istruttori ORDER BY cognome, nome')->fetchAll();

    $corsi = $pdo->query('
        SELECT c.id_corso, c.nome_corso, c.id_istruttore, i.nome AS nome_istruttore, i.cognome AS cognome_istruttore
        FROM Corsi c
        LEFT JOIN Istruttori i ON i.id_istruttore = c.id_istruttore
        ORDER BY c.nome_corso
    ')->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && v($_POST, 'azione', '') === 'inserisci_iscritto') {
        $nome = trim(v($_POST, 'nome', ''));
        $cognome = trim(v($_POST, 'cognome', ''));
        $dataNascita = trim(v($_POST, 'data_nascita', ''));
        $tipoAbbonamento = trim(v($_POST, 'tipo_abbonamento', ''));
        $statoPagamento = isset($_POST['stato_pagamento']) ? 1 : 0;
        $idIstruttore = (int)v($_POST, 'id_istruttore', 0);
        $idCorso = (int)v($_POST, 'id_corso', 0);
        $orario = trim(v($_POST, 'orario_preferito', ''));

        if ($nome === '' || $cognome === '' || $dataNascita === '' || $tipoAbbonamento === '' || $idIstruttore <= 0 || $idCorso <= 0) {
            $errStep2 = 'Compila tutti i campi obbligatori';
        } else {
            $check = $pdo->prepare('SELECT COUNT(*) FROM Corsi WHERE id_corso = ? AND id_istruttore = ?');
            $check->execute(array($idCorso, $idIstruttore));

            if ((int)$check->fetchColumn() === 0) {
                $errStep2 = 'Il corso non appartiene all istruttore selezionato';
            } else {
                try {
                    $pdo->beginTransaction();

                    $insMembro = $pdo->prepare('INSERT INTO Membri (nome, cognome, data_nascita, tipo_abbonamento, stato_pagamento) VALUES (?, ?, ?, ?, ?)');
                    $insMembro->execute(array($nome, $cognome, $dataNascita, $tipoAbbonamento, $statoPagamento));

                    $idMembro = (int)$pdo->lastInsertId();
                    $orarioFinale = $orario === '' ? null : $orario;

                    $insIscrizione = $pdo->prepare('INSERT INTO Iscrizioni_Corsi (id_corso, id_membro, data_iscrizione, orario_preferito) VALUES (?, ?, CURDATE(), ?)');
                    $insIscrizione->execute(array($idCorso, $idMembro, $orarioFinale));

                    $pdo->commit();
                    $msgStep2 = 'Nuovo iscritto inserito correttamente';
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errStep2 = 'Errore durante l inserimento';
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && v($_POST, 'azione', '') === 'cambia_corso') {
        $idIscrizione = (int)v($_POST, 'id_iscrizione', 0);
        $idNuovoCorso = (int)v($_POST, 'id_nuovo_corso', 0);
        $corsoFiltro = (int)v($_POST, 'id_corso_filtro', 0);

        if ($idIscrizione <= 0 || $idNuovoCorso <= 0) {
            $errStep4 = 'Dati cambio corso non validi';
        } else {
            $upd = $pdo->prepare('UPDATE Iscrizioni_Corsi SET id_corso = ? WHERE id_iscrizione = ?');
            $upd->execute(array($idNuovoCorso, $idIscrizione));
            $msgStep4 = 'Corso aggiornato correttamente';
        }
    }

    $step3 = $pdo->query('
        SELECT i.nome AS nome_istruttore, i.cognome AS cognome_istruttore, c.nome_corso, t.totale
        FROM Istruttori i
        JOIN (
            SELECT c1.id_istruttore, c1.id_corso, COUNT(ic1.id_iscrizione) AS totale
            FROM Corsi c1
            LEFT JOIN Iscrizioni_Corsi ic1 ON ic1.id_corso = c1.id_corso
            GROUP BY c1.id_istruttore, c1.id_corso
        ) t ON t.id_istruttore = i.id_istruttore
        JOIN (
            SELECT z.id_istruttore, MAX(z.totale) AS massimo
            FROM (
                SELECT c2.id_istruttore, c2.id_corso, COUNT(ic2.id_iscrizione) AS totale
                FROM Corsi c2
                LEFT JOIN Iscrizioni_Corsi ic2 ON ic2.id_corso = c2.id_corso
                GROUP BY c2.id_istruttore, c2.id_corso
            ) z
            GROUP BY z.id_istruttore
        ) m ON m.id_istruttore = t.id_istruttore AND m.massimo = t.totale
        JOIN Corsi c ON c.id_corso = t.id_corso
        WHERE t.totale >= 5
        ORDER BY i.cognome, i.nome, c.nome_corso
    ')->fetchAll();

    if ($corsoFiltro > 0) {
        $q4 = $pdo->prepare('
            SELECT ic.id_iscrizione, m.cognome, m.nome, m.tipo_abbonamento, ic.data_iscrizione, ic.orario_preferito
            FROM Iscrizioni_Corsi ic
            JOIN Membri m ON m.id_membro = ic.id_membro
            WHERE ic.id_corso = ?
            ORDER BY m.cognome, m.nome
        ');
        $q4->execute(array($corsoFiltro));
        $step4 = $q4->fetchAll();
    }

    $step5 = $pdo->query('
        SELECT
            i.cognome AS cognome_istruttore,
            i.nome AS nome_istruttore,
            c.nome_corso,
            c.livello_difficolta,
            c.durata_minuti,
            m.cognome AS cognome_membro,
            m.nome AS nome_membro,
            m.tipo_abbonamento,
            m.stato_pagamento,
            ic.data_iscrizione,
            ic.orario_preferito
        FROM Istruttori i
        JOIN Corsi c ON c.id_istruttore = i.id_istruttore
        LEFT JOIN Iscrizioni_Corsi ic ON ic.id_corso = c.id_corso
        LEFT JOIN Membri m ON m.id_membro = ic.id_membro
        ORDER BY i.cognome, i.nome, c.nome_corso, m.cognome, m.nome
    ')->fetchAll();
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karaje Gym</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" href="assets/icon.ico">
</head>
<body>
<?php if (!$autenticato): ?>
    <h1>Accesso</h1>
    <?php if ($erroreLogin !== ''): ?><p class="alert error"><?= htmlspecialchars($erroreLogin) ?></p><?php endif; ?>
    <form method="post" action="index.php">
        <input type="hidden" name="azione" value="login">
        <label for="username">Nome utente</label>
        <input id="username" type="text" name="username" required>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
        <button type="submit">Accedi</button>
    </form>
<?php else: ?>
    <h1>💪Karaje Gym🏋️‍♂️</h1>
    <p>Utente: <?= htmlspecialchars(v($_SESSION, 'utente', '')) ?> - <a href="index.php?logout=1">Logout</a></p>

    <h2>1) Inserisci nuovo iscritto</h2>
    <?php if ($msgStep2 !== ''): ?><p class="alert ok"><?= htmlspecialchars($msgStep2) ?></p><?php endif; ?>
    <?php if ($errStep2 !== ''): ?><p class="alert error"><?= htmlspecialchars($errStep2) ?></p><?php endif; ?>
    <form method="post" action="index.php">
        <input type="hidden" name="azione" value="inserisci_iscritto">
        <label for="nome">Nome</label>
        <input id="nome" type="text" name="nome" required>
        <label for="cognome">Cognome</label>
        <input id="cognome" type="text" name="cognome" required>
        <label for="data_nascita">Data nascita</label>
        <input id="data_nascita" type="date" name="data_nascita" required>
        <label for="tipo_abbonamento">Abbonamento</label>
        <select id="tipo_abbonamento" name="tipo_abbonamento" required>
            <option value="">Seleziona</option>
            <option value="Mensile">Mensile</option>
            <option value="Trimestrale">Trimestrale</option>
            <option value="Annuale">Annuale</option>
        </select>
        <label for="stato_pagamento">Pagato</label>
        <input id="stato_pagamento" type="checkbox" name="stato_pagamento" checked>
        <label for="id_istruttore">Istruttore</label>
        <select id="id_istruttore" name="id_istruttore" required>
            <option value="">Seleziona</option>
            <?php foreach ($istruttori as $i): ?>
                <option value="<?= (int)$i['id_istruttore'] ?>"><?= htmlspecialchars($i['cognome'] . ' ' . $i['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="id_corso">Corso</label>
        <select id="id_corso" name="id_corso" required>
            <option value="">Seleziona</option>
            <?php foreach ($corsi as $c): ?>
                <option value="<?= (int)$c['id_corso'] ?>"><?= htmlspecialchars($c['nome_corso'] . ' - ' . $c['cognome_istruttore']) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="orario_preferito">Orario preferito</label>
        <input id="orario_preferito" type="time" name="orario_preferito">
        <button type="submit">Inserisci</button>
    </form>

    <h2>2) Corso top per istruttore (minimo 5 iscritti)</h2>
    <?php if (count($step3) === 0): ?>
        <p>Nessun risultato.</p>
    <?php else: ?>
        <table>
            <tr><th>Istruttore</th><th>Corso</th><th>Iscritti</th></tr>
            <?php foreach ($step3 as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['cognome_istruttore'] . ' ' . $r['nome_istruttore']) ?></td>
                    <td><?= htmlspecialchars($r['nome_corso']) ?></td>
                    <td><?= (int)$r['totale'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>3) Elenco iscritti e cambio corso</h2>
    <?php if ($msgStep4 !== ''): ?><p class="alert ok"><?= htmlspecialchars($msgStep4) ?></p><?php endif; ?>
    <?php if ($errStep4 !== ''): ?><p class="alert error"><?= htmlspecialchars($errStep4) ?></p><?php endif; ?>
    <form method="get" action="index.php">
        <label for="id_corso_filtro">Corso</label>
        <select id="id_corso_filtro" name="id_corso_filtro" required>
            <option value="">Seleziona</option>
            <?php foreach ($corsi as $c): ?>
                <option value="<?= (int)$c['id_corso'] ?>" <?= $corsoFiltro === (int)$c['id_corso'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome_corso']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Mostra</button>
    </form>

    <?php if ($corsoFiltro > 0 && count($step4) === 0): ?><p>Nessun iscritto.</p><?php endif; ?>
    <?php if ($corsoFiltro > 0 && count($step4) > 0): ?>
        <table>
            <tr><th>Iscritto</th><th>Abbonamento</th><th>Data</th><th>Orario</th><th>Cambia</th></tr>
            <?php foreach ($step4 as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['cognome'] . ' ' . $r['nome']) ?></td>
                    <td><?= htmlspecialchars($r['tipo_abbonamento']) ?></td>
                    <td><?= htmlspecialchars($r['data_iscrizione']) ?></td>
                    <td><?= htmlspecialchars(v($r, 'orario_preferito', '')) ?></td>
                    <td>
                        <form method="post" action="index.php">
                            <input type="hidden" name="azione" value="cambia_corso">
                            <input type="hidden" name="id_iscrizione" value="<?= (int)$r['id_iscrizione'] ?>">
                            <input type="hidden" name="id_corso_filtro" value="<?= (int)$corsoFiltro ?>">
                            <label for="id_nuovo_corso_<?= (int)$r['id_iscrizione'] ?>">Nuovo corso</label>
                            <select id="id_nuovo_corso_<?= (int)$r['id_iscrizione'] ?>" name="id_nuovo_corso" required>
                                <option value="">Seleziona</option>
                                <?php foreach ($corsi as $c): ?>
                                    <option value="<?= (int)$c['id_corso'] ?>"><?= htmlspecialchars($c['nome_corso']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Cambia</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>4) Report completo</h2>
    <?php if (count($step5) === 0): ?>
        <p>Nessun dato.</p>
    <?php else: ?>
        <table>
            <tr><th>Istruttore</th><th>Corso</th><th>Livello</th><th>Durata</th><th>Iscritto</th><th>Abbon.</th><th>Pagamento</th><th>Data</th><th>Orario</th></tr>
            <?php foreach ($step5 as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['cognome_istruttore'] . ' ' . $r['nome_istruttore']) ?></td>
                    <td><?= htmlspecialchars($r['nome_corso']) ?></td>
                    <td><?= htmlspecialchars($r['livello_difficolta']) ?></td>
                    <td><?= htmlspecialchars((string)$r['durata_minuti']) ?></td>
                    <td><?= $r['cognome_membro'] === null ? 'Nessun iscritto' : htmlspecialchars($r['cognome_membro'] . ' ' . $r['nome_membro']) ?></td>
                    <td><?= htmlspecialchars(v($r, 'tipo_abbonamento', '')) ?></td>
                    <td><?= $r['stato_pagamento'] === null ? '' : ((int)$r['stato_pagamento'] === 1 ? 'Pagato' : 'Non pagato') ?></td>
                    <td><?= htmlspecialchars(v($r, 'data_iscrizione', '')) ?></td>
                    <td><?= htmlspecialchars(v($r, 'orario_preferito', '')) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

<?php endif; ?>
</body>
</html>

