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