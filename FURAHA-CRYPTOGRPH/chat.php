<?php
require_once 'config.php';
require_once 'encrypt.php';

if (!estConnecte()) {
    header("Location: login.php");
    exit();
}

// Vérifier si l'utilisateur est bloqué
if (estBloque($_SESSION['user_id'], $pdo)) {
    session_destroy();
    header("Location: login.php?error=bloque");
    exit();
}

// Traitement de l'envoi de message (AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'send_message') {
    header('Content-Type: application/json');
    
    try {
        $message = trim($_POST['message']);
        $cle_chiffrement = $_POST['cle_chiffrement'];
        $algorithme = $_POST['algorithme'] ?? 'AES-256-CBC';
        
        $response = ['success' => false, 'message' => ''];
        
        if (empty($message)) {
            $response['message'] = "Veuillez écrire un message";
            echo json_encode($response);
            exit();
        }
        
        if (empty($cle_chiffrement)) {
            $response['message'] = "Veuillez entrer une clé de chiffrement";
            echo json_encode($response);
            exit();
        }
        
        if (strlen($cle_chiffrement) < 6) {
            $response['message'] = "La clé de chiffrement doit contenir au moins 6 caractères";
            echo json_encode($response);
            exit();
        }
        
        // Récupérer l'ID de l'algorithme
        $stmt = $pdo->prepare("SELECT id FROM algorithmes WHERE libelle = ?");
        $stmt->execute([$algorithme]);
        $algo = $stmt->fetch();
        
        if (!$algo) {
            $response['message'] = "Algorithme invalide";
            echo json_encode($response);
            exit();
        }
        
        // Créer un encryptor avec la clé personnalisée
        $encryptor = new MessageEncryptorCustom($algorithme, $cle_chiffrement);
        $message_chiffre = $encryptor->encrypt($message);
        
        if (!$message_chiffre) {
            $response['message'] = "Erreur lors du chiffrement du message";
            echo json_encode($response);
            exit();
        }
        
        // Stocker le message chiffré
        $stmt = $pdo->prepare("INSERT INTO message (contenu_chiffre, id_user, id_algorithme) VALUES (?, ?, ?)");
        if ($stmt->execute([$message_chiffre, $_SESSION['user_id'], $algo['id']])) {
            $response['success'] = true;
            $response['message'] = "Message chiffré et envoyé avec succès!";
        } else {
            $response['message'] = "Erreur lors de l'envoi du message";
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

// Traitement pour rafraîchir les messages (AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'refresh_messages') {
    header('Content-Type: text/html');
    
    $stmt = $pdo->query("
        SELECT m.*, u.username, a.libelle as algorithme 
        FROM message m 
        JOIN utilisateur u ON m.id_user = u.id 
        JOIN algorithmes a ON m.id_algorithme = a.id 
        ORDER BY m.date_envoi DESC 
        LIMIT 50
    ");
    $messages = $stmt->fetchAll();
    ?>
    
    <?php if (empty($messages)): ?>
        <div style="text-align: center; color: #718096; padding: 40px;">
            💬 Aucun message pour le moment. Soyez le premier à envoyer un message chiffré !
        </div>
    <?php else: ?>
        <?php foreach(array_reverse($messages) as $msg): ?>
            <div class="message <?php echo $msg['id_user'] == $_SESSION['user_id'] ? 'message-own' : 'message-other'; ?>" data-msg-id="<?php echo $msg['id']; ?>">
                <div class="message-header">
                    <strong><?php echo htmlspecialchars($msg['username']); ?></strong>
                    <small><?php echo date('d/m/Y H:i', strtotime($msg['date_envoi'])); ?></small>
                    <span class="algo-badge">🔒 <?php echo htmlspecialchars($msg['algorithme']); ?></span>
                </div>
                <div class="message-content" data-encrypted="<?php echo htmlspecialchars($msg['contenu_chiffre']); ?>" data-algo="<?php echo htmlspecialchars($msg['algorithme']); ?>">
                    <div class="encrypted-placeholder">
                        <p><strong>🔐 Message chiffré :</strong></p>
                        <div class="encrypted-text"><?php echo htmlspecialchars($msg['contenu_chiffre']); ?></div>
                        <button class="decrypt-btn" onclick="showDecryptPanel(this)">🔓 Déchiffrer le message</button>
                    </div>
                    <div class="decrypt-panel" style="display: none;">
                        <input type="password" class="decrypt-key" placeholder="Entrez la clé de déchiffrement" autocomplete="off">
                        <div style="margin-top: 8px;">
                            <button class="btn-small" onclick="decryptMessageWithKey(this)">✅ Valider et déchiffrer</button>
                            <button class="btn-small" onclick="cancelDecrypt(this)">❌ Annuler</button>
                        </div>
                    </div>
                    <div class="decrypted-message" style="display: none;">
                        <strong>📝 Message déchiffré :</strong>
                        <div class="message-text"></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    exit();
}

// Récupérer les messages (pour l'affichage initial)
$stmt = $pdo->query("
    SELECT m.*, u.username, a.libelle as algorithme 
    FROM message m 
    JOIN utilisateur u ON m.id_user = u.id 
    JOIN algorithmes a ON m.id_algorithme = a.id 
    ORDER BY m.date_envoi DESC 
    LIMIT 50
");
$messages = $stmt->fetchAll();

// Récupérer les algorithmes disponibles
$stmt = $pdo->query("SELECT * FROM algorithmes");
$algorithmes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Furaha - Chiffrement sécurisé</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .chat-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .chat-header h1 {
            font-size: 1.5rem;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .encryption-demo {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 20px;
            padding: 25px;
            border-radius: 12px;
            color: white;
        }

        .encryption-demo h3 {
            margin-bottom: 20px;
            font-size: 1.3rem;
        }

        .demo-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .step {
            background: rgba(255,255,255,0.15);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s;
        }

        .step:hover {
            transform: translateY(-3px);
            background: rgba(255,255,255,0.25);
        }

        .step h4 {
            color: #ffd700;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .step p {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .alert-success, .alert-error {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 12px 20px;
            border-radius: 8px;
            animation: slideDown 0.3s ease-out;
            display: none;
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chat-messages {
            height: 450px;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease-in;
        }

        .message-own {
            text-align: right;
        }

        .message-other {
            text-align: left;
        }

        .message-header {
            margin-bottom: 8px;
            font-size: 0.85rem;
        }

        .message-header strong {
            color: #2d3748;
            font-weight: 600;
        }

        .message-header small {
            color: #718096;
            margin-left: 8px;
        }

        .algo-badge {
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 8px;
        }

        .message-content {
            display: inline-block;
            max-width: 80%;
            background: white;
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: left;
        }

        .message-own .message-content {
            background: #667eea;
            color: white;
        }

        .message-own .message-content .encrypted-text {
            color: #e0e0e0;
        }

        .encrypted-placeholder p {
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .encrypted-text {
            background: rgba(0,0,0,0.05);
            padding: 8px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 0.75rem;
            word-break: break-all;
            margin-bottom: 10px;
            max-height: 100px;
            overflow-y: auto;
        }

        .message-own .encrypted-text {
            background: rgba(255,255,255,0.1);
        }

        .decrypt-btn {
            background: #48bb78;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .decrypt-btn:hover {
            background: #38a169;
            transform: scale(1.05);
        }

        .decrypt-panel {
            margin-top: 12px;
            padding: 12px;
            background: #f1f3f5;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }

        .decrypt-key {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
            margin-right: 8px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-small:first-of-type {
            background: #48bb78;
            color: white;
        }

        .btn-small:first-of-type:hover {
            background: #38a169;
        }

        .btn-small:last-of-type {
            background: #f56565;
            color: white;
        }

        .btn-small:last-of-type:hover {
            background: #e53e3e;
        }

        .decrypted-message {
            margin-top: 12px;
            padding: 12px;
            background: #e8f5e9;
            border-radius: 8px;
            border-left: 3px solid #4caf50;
            animation: fadeIn 0.5s ease-in;
        }

        .message-text {
            margin-top: 8px;
            word-wrap: break-word;
            font-size: 1rem;
            font-weight: 500;
            color: #2d3748;
            background: white;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #cbd5e0;
        }

        .message-own .decrypted-message .message-text {
            background: #f0f0f0;
            color: #2d3748;
        }

        .chat-input {
            padding: 20px;
            background: white;
            border-top: 2px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.9rem;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .key-input-group {
            position: relative;
        }

        .key-input-group input {
            width: 100%;
            padding: 10px 80px 10px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .key-input-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .toggle-key {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #667eea;
            font-size: 0.85rem;
            padding: 5px 8px;
            border-radius: 5px;
        }

        .toggle-key:hover {
            background: #f0f0f0;
        }

        small {
            display: block;
            margin-top: 5px;
            font-size: 0.75rem;
            color: #718096;
        }

        .btn-send {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-send:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-send:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-admin, .btn-logout {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .btn-admin {
            background: #48bb78;
            color: white;
        }

        .btn-admin:hover {
            background: #38a169;
        }

        .btn-logout {
            background: #f56565;
            color: white;
        }

        .btn-logout:hover {
            background: #e53e3e;
        }

        .refresh-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .refresh-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #5a67d8;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .chat-header {
                padding: 15px 20px;
            }

            .chat-header h1 {
                font-size: 1.2rem;
            }

            .encryption-demo {
                margin: 15px;
                padding: 15px;
            }

            .demo-steps {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .step {
                padding: 10px;
            }

            .step h4 {
                font-size: 0.9rem;
            }

            .step p {
                font-size: 0.8rem;
            }

            .message-content {
                max-width: 90%;
            }

            .chat-messages {
                height: 400px;
                padding: 15px;
            }

            .chat-input {
                padding: 15px;
            }

            .btn-send {
                padding: 10px 20px;
            }
            
            .alert-success, .alert-error {
                left: 20px;
                right: 20px;
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h1>🔐 Chat Furaha - Chiffrement sécurisé</h1>
            <div class="header-actions">
                <span>👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <button onclick="refreshMessages()" class="refresh-btn">🔄 Actualiser</button>
                <?php if (estAdmin()): ?>
                    <a href="admin.php" class="btn-admin">⚙️ Administration</a>
                <?php endif; ?>
                <a href="logout.php" class="btn-logout">🚪 Déconnexion</a>
            </div>
        </div>
        
        <div class="encryption-demo">
            <h3>📖 Comment ça fonctionne ?</h3>
            <div class="demo-steps">
                <div class="step">
                    <h4>1️⃣ Écrire le message</h4>
                    <p>Saisissez votre message à chiffrer</p>
                </div>
                <div class="step">
                    <h4>2️⃣ Choisir la clé</h4>
                    <p>Entrez une clé secrète (gardez-la précieusement)</p>
                </div>
                <div class="step">
                    <h4>3️⃣ Choisir l'algorithme</h4>
                    <p>Sélectionnez AES-256-CBC ou ChaCha20</p>
                </div>
                <div class="step">
                    <h4>4️⃣ Envoyer</h4>
                    <p>Le message est chiffré et stocké dans la base de données</p>
                </div>
                <div class="step">
                    <h4>5️⃣ Déchiffrer</h4>
                    <p>Utilisez la MÊME clé pour déchiffrer le message</p>
                </div>
            </div>
        </div>
        
        <div id="alert-success" class="alert-success"></div>
        <div id="alert-error" class="alert-error"></div>
        
        <div class="chat-messages" id="chatMessages">
            <?php if (empty($messages)): ?>
                <div style="text-align: center; color: #718096; padding: 40px;">
                    💬 Aucun message pour le moment. Soyez le premier à envoyer un message chiffré !
                </div>
            <?php else: ?>
                <?php foreach(array_reverse($messages) as $msg): ?>
                    <div class="message <?php echo $msg['id_user'] == $_SESSION['user_id'] ? 'message-own' : 'message-other'; ?>" data-msg-id="<?php echo $msg['id']; ?>">
                        <div class="message-header">
                            <strong><?php echo htmlspecialchars($msg['username']); ?></strong>
                            <small><?php echo date('d/m/Y H:i', strtotime($msg['date_envoi'])); ?></small>
                            <span class="algo-badge">🔒 <?php echo htmlspecialchars($msg['algorithme']); ?></span>
                        </div>
                        <div class="message-content" data-encrypted="<?php echo htmlspecialchars($msg['contenu_chiffre']); ?>" data-algo="<?php echo htmlspecialchars($msg['algorithme']); ?>">
                            <div class="encrypted-placeholder">
                                <p><strong>🔐 Message chiffré :</strong></p>
                                <div class="encrypted-text"><?php echo htmlspecialchars($msg['contenu_chiffre']); ?></div>
                                <button class="decrypt-btn" onclick="showDecryptPanel(this)">🔓 Déchiffrer le message</button>
                            </div>
                            <div class="decrypt-panel" style="display: none;">
                                <input type="password" class="decrypt-key" placeholder="Entrez la clé de déchiffrement" autocomplete="off">
                                <div style="margin-top: 8px;">
                                    <button class="btn-small" onclick="decryptMessageWithKey(this)">✅ Valider et déchiffrer</button>
                                    <button class="btn-small" onclick="cancelDecrypt(this)">❌ Annuler</button>
                                </div>
                            </div>
                            <div class="decrypted-message" style="display: none;">
                                <strong>📝 Message déchiffré (en clair) :</strong>
                                <div class="message-text"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="chat-input">
            <form id="messageForm" onsubmit="sendMessage(event)">
                <div class="form-group">
                    <label for="algorithme">🔧 Algorithme de chiffrement :</label>
                    <select name="algorithme" id="algorithme" required>
                        <?php foreach($algorithmes as $algo): ?>
                            <option value="<?php echo htmlspecialchars($algo['libelle']); ?>">
                                <?php echo htmlspecialchars($algo['libelle']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="cle_chiffrement">🔑 Clé de chiffrement (SECRÈTE) :</label>
                    <div class="key-input-group">
                        <input type="password" name="cle_chiffrement" id="cle_chiffrement" 
                               placeholder="Entrez une clé secrète (ex: MonMotDePasse123)" required autocomplete="off">
                        <button type="button" class="toggle-key" onclick="toggleKeyVisibility()">👁️ Afficher</button>
                    </div>
                    <small>⚠️ GARDEZ CETTE CLÉ PRÉCIEUSEMENT ! Vous aurez besoin de la MÊME clé pour déchiffrer le message.</small>
                </div>
                
                <div class="form-group">
                    <label for="message">💬 Message à chiffrer :</label>
                    <textarea name="message" id="message" rows="3" 
                              placeholder="Écrivez votre message ici... Il sera chiffré avant d'être envoyé" required></textarea>
                </div>
                
                <button type="submit" class="btn-send" id="sendBtn">🔒 Chiffrer et envoyer</button>
            </form>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Fonction pour afficher/masquer la clé
        function toggleKeyVisibility() {
            var keyInput = document.getElementById('cle_chiffrement');
            var toggleBtn = document.querySelector('.toggle-key');
            if (keyInput.type === 'password') {
                keyInput.type = 'text';
                toggleBtn.textContent = '🙈 Masquer';
            } else {
                keyInput.type = 'password';
                toggleBtn.textContent = '👁️ Afficher';
            }
        }
        
        // Fonction pour afficher les alertes
        function showAlert(type, message) {
            var alertDiv = document.getElementById('alert-' + type);
            alertDiv.innerHTML = (type === 'success' ? '✅ ' : '❌ ') + message;
            alertDiv.style.display = 'block';
            
            setTimeout(function() {
                alertDiv.style.display = 'none';
            }, 5000);
        }
        
        // Envoi du message avec AJAX
        function sendMessage(event) {
            event.preventDefault();
            
            var message = document.getElementById('message').value.trim();
            var key = document.getElementById('cle_chiffrement').value.trim();
            var algo = document.getElementById('algorithme').value;
            var sendBtn = document.getElementById('sendBtn');
            
            if (message === '') {
                showAlert('error', 'Veuillez écrire un message');
                return false;
            }
            
            if (key === '') {
                showAlert('error', 'Veuillez entrer une clé de chiffrement');
                return false;
            }
            
            if (key.length < 6) {
                showAlert('error', 'La clé de chiffrement doit contenir au moins 6 caractères');
                return false;
            }
            
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="loading-spinner"></span> Chiffrement en cours...';
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'send_message',
                    message: message,
                    cle_chiffrement: key,
                    algorithme: algo
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', response.message);
                        document.getElementById('message').value = '';
                        document.getElementById('cle_chiffrement').value = '';
                        refreshMessages();
                    } else {
                        showAlert('error', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erreur AJAX:', error);
                    showAlert('error', 'Erreur de connexion au serveur');
                },
                complete: function() {
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '🔒 Chiffrer et envoyer';
                }
            });
        }
        
        // Rafraîchir les messages
        function refreshMessages() {
            $.ajax({
                url: window.location.href + '?action=refresh_messages',
                method: 'GET',
                dataType: 'html',
                success: function(response) {
                    $('#chatMessages').html(response);
                    var messagesDiv = document.getElementById('chatMessages');
                    messagesDiv.scrollTop = messagesDiv.scrollHeight;
                },
                error: function() {
                    showAlert('error', 'Erreur lors du rafraîchissement');
                }
            });
        }
        
        // Afficher le panneau de déchiffrement
        function showDecryptPanel(button) {
            var messageDiv = button.closest('.message-content');
            var placeholder = messageDiv.querySelector('.encrypted-placeholder');
            var decryptPanel = messageDiv.querySelector('.decrypt-panel');
            
            placeholder.style.display = 'none';
            decryptPanel.style.display = 'block';
            decryptPanel.querySelector('.decrypt-key').focus();
        }
        
        // Annuler le déchiffrement
        function cancelDecrypt(button) {
            var messageDiv = button.closest('.message-content');
            var placeholder = messageDiv.querySelector('.encrypted-placeholder');
            var decryptPanel = messageDiv.querySelector('.decrypt-panel');
            var keyInput = messageDiv.querySelector('.decrypt-key');
            
            placeholder.style.display = 'block';
            decryptPanel.style.display = 'none';
            keyInput.value = '';
        }
        
        // Déchiffrer avec la clé fournie
        function decryptMessageWithKey(button) {
            var messageDiv = button.closest('.message-content');
            var encryptedData = messageDiv.getAttribute('data-encrypted');
            var algo = messageDiv.getAttribute('data-algo');
            var keyInput = messageDiv.querySelector('.decrypt-key');
            var key = keyInput.value.trim();
            
            if (key === '') {
                alert('Veuillez entrer la clé de déchiffrement');
                keyInput.focus();
                return;
            }
            
            var originalText = button.textContent;
            button.textContent = '⏳ Chargement...';
            button.disabled = true;
            
            $.ajax({
                url: 'decrypt_message_with_key.php',
                method: 'POST',
                data: {
                    encrypted_data: encryptedData,
                    algo: algo,
                    key: key
                },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        var decryptPanel = messageDiv.querySelector('.decrypt-panel');
                        decryptPanel.style.display = 'none';
                        
                        var decryptedDiv = messageDiv.querySelector('.decrypted-message');
                        var messageText = decryptedDiv.querySelector('.message-text');
                        messageText.innerHTML = data.decrypted;
                        decryptedDiv.style.display = 'block';
                        showAlert('success', 'Message déchiffré avec succès! Le message s\'affiche en clair ci-dessus.');
                    } else {
                        alert('❌ Échec du déchiffrement !\n\n' + data.error + '\n\nVérifiez que vous utilisez la BONNE clé.');
                        button.textContent = originalText;
                        button.disabled = false;
                        keyInput.value = '';
                        keyInput.focus();
                    }
                },
                error: function() {
                    alert('Erreur de connexion au serveur');
                    button.textContent = originalText;
                    button.disabled = false;
                }
            });
        }
        
        // Scroll automatique vers le bas
        var messagesDiv = document.getElementById('chatMessages');
        if(messagesDiv) {
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
        
        console.log('%c🔐 Chat Furaha - Chiffrement sécurisé', 'color: #667eea; font-size: 16px; font-weight: bold;');
        console.log('%c⚠️ N\'oubliez jamais vos clés de chiffrement !', 'color: #f56565; font-size: 12px;');
    </script>
</body>
</html>