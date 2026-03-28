<?php
require_once 'config.php';

class MessageEncryptorCustom {
    private $key;
    private $method;
    
    public function __construct($method = 'AES-256-CBC', $userKey = null) {
        $this->method = $method;
        
        // Si une clé utilisateur est fournie, l'utiliser
        if ($userKey !== null) {
            // Dériver une clé de chiffrement à partir de la clé utilisateur
            $this->key = $this->deriveKey($userKey);
        } else {
            $this->key = SECRET_KEY;
        }
    }
    
    // Dériver une clé de chiffrement à partir de la clé utilisateur
    private function deriveKey($userKey) {
        // Utiliser PBKDF2 pour dériver une clé sécurisée
        $salt = 'chat_furaha_salt_2024'; // Salt fixe pour la démonstration
        return hash_pbkdf2('sha256', $userKey, $salt, 10000, 32, true);
    }
    
    // Chiffrement du message avec la clé utilisateur
    public function encrypt($message) {
        if ($this->method == 'AES-256-CBC') {
            $iv = random_bytes(16);
            $encrypted = openssl_encrypt($message, $this->method, $this->key, OPENSSL_RAW_DATA, $iv);
            return base64_encode($iv . $encrypted);
        } elseif ($this->method == 'ChaCha20-Poly1305') {
            if (function_exists('sodium_crypto_aead_chacha20poly1305_encrypt')) {
                $nonce = random_bytes(12);
                $encrypted = sodium_crypto_aead_chacha20poly1305_encrypt(
                    $message,
                    '',
                    $nonce,
                    $this->key
                );
                return base64_encode($nonce . $encrypted);
            } else {
                // Fallback to AES si ChaCha20 n'est pas disponible
                $iv = random_bytes(16);
                $encrypted = openssl_encrypt($message, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $iv);
                return base64_encode($iv . $encrypted);
            }
        }
        return false;
    }
    
    // Déchiffrement du message avec la clé utilisateur
    public function decrypt($encryptedData) {
        $decoded = base64_decode($encryptedData);
        
        if ($this->method == 'AES-256-CBC') {
            $iv = substr($decoded, 0, 16);
            $encrypted = substr($decoded, 16);
            $decrypted = openssl_decrypt($encrypted, $this->method, $this->key, OPENSSL_RAW_DATA, $iv);
            
            if ($decrypted === false) {
                return false;
            }
            return $decrypted;
            
        } elseif ($this->method == 'ChaCha20-Poly1305') {
            if (function_exists('sodium_crypto_aead_chacha20poly1305_decrypt')) {
                $nonce = substr($decoded, 0, 12);
                $ciphertext = substr($decoded, 12);
                return sodium_crypto_aead_chacha20poly1305_decrypt(
                    $ciphertext,
                    '',
                    $nonce,
                    $this->key
                );
            } else {
                // Fallback to AES
                $iv = substr($decoded, 0, 16);
                $encrypted = substr($decoded, 16);
                return openssl_decrypt($encrypted, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $iv);
            }
        }
        return false;
    }
}
?>