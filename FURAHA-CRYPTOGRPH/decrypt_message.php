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
    
    $encryptor = new MessageEncryptor($algo);
    $decrypted = $encryptor->decrypt($encrypted_data);
    
    if ($decrypted !== false) {
        echo json_encode(['success' => true, 'decrypted' => $decrypted]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur de déchiffrement']);
    }
}
?>