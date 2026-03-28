<?php
session_start();

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'chat_furaha');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8");
} catch(PDOException $e) {
    die("Erreur de connexion: " . $e->getMessage());
}

// Clé secrète pour le chiffrement (à changer et garder secrète)
define('SECRET_KEY', 'votre_cle_secrete_tres_longue_et_complexe_32bytes!');
define('SECRET_IV', 'iv_16_bytes_long!');

// Fonction pour vérifier si l'utilisateur est connecté
function estConnecte() {
    return isset($_SESSION['user_id']);
}

// Fonction pour vérifier si l'utilisateur est admin
function estAdmin() {
    return isset($_SESSION['est_admin']) && $_SESSION['est_admin'] === true;
}

// Fonction pour vérifier si l'utilisateur est bloqué
function estBloque($user_id, $pdo) {
    $stmt = $pdo->prepare("SELECT est_bloque FROM utilisateur WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result && $result['est_bloque'] == 1;
}
?>