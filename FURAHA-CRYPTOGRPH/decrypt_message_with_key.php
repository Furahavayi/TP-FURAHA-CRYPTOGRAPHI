<?php
require_once 'config.php';
require_once 'encrypt.php';

if (!estConnecte()) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $encrypted_data = $_POST['encrypted_data'];
    $algo = $_POST['algo'];
    $user_key = $_POST['key'];
    
    if (empty($user_key)) {
        echo json_encode(['success' => false, 'error' => 'Clé de déchiffrement requise']);
        exit();
    }
    
    // Créer un encryptor avec la clé fournie par l'utilisateur
    $encryptor = new MessageEncryptorCustom($algo, $user_key);
    $decrypted = $encryptor->decrypt($encrypted_data);
    
    if ($decrypted !== false && $decrypted !== null && $decrypted !== '') {
        echo json_encode(['success' => true, 'decrypted' => htmlspecialchars($decrypted)]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Clé incorrecte ou algorithme invalide. Impossible de déchiffrer le message.']);
    }
}
?>