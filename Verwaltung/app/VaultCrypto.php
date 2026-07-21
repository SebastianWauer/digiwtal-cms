<?php
declare(strict_types=1);

class VaultCrypto
{
    private const ALGO = 'aes-256-gcm';
    private const NONCE_SIZE = 12;
    private const TAG_SIZE = 16;

    private static function key(): string
    {
        $keyB64 = (string)(getenv('VAULT_KEY_BASE64') ?: '');
        if ($keyB64 === '') {
            throw new RuntimeException('VAULT_KEY_BASE64 not set');
        }
        
        $key = base64_decode($keyB64, true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('VAULT_KEY_BASE64 must be valid base64 of 32 bytes');
        }
        
        return $key;
    }

    public static function encrypt(string $plaintext, string $aad): array
    {
        $key = self::key();
        $nonce = random_bytes(self::NONCE_SIZE);
        $tag = '';
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad
        );
        
        if ($ciphertext === false) {
            // ADDED: Log OpenSSL error
            $opensslError = openssl_error_string();
            FileLogger::channel('verwaltung')->error("[VAULT] openssl_encrypt failed: " . ($opensslError ?: 'no error string'));
            throw new RuntimeException('encryption_failed');
        }
        
        return [
            'ciphertext_b64' => base64_encode($ciphertext),
            'nonce_b64' => base64_encode($nonce),
            'tag_b64' => base64_encode($tag),
        ];
    }

    public static function decrypt(string $ciphertextB64, string $nonceB64, string $tagB64, string $aad): string
    {
        $key = self::key();
        
        $ciphertext = base64_decode($ciphertextB64, true);
        $nonce = base64_decode($nonceB64, true);
        $tag = base64_decode($tagB64, true);
        
        if ($ciphertext === false || $nonce === false || $tag === false) {
            throw new RuntimeException('decrypt_failed');
        }
        
        if (strlen($nonce) !== self::NONCE_SIZE || strlen($tag) !== self::TAG_SIZE) {
            throw new RuntimeException('decrypt_failed');
        }
        
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad
        );
        
        if ($plaintext === false) {
            throw new RuntimeException('decrypt_failed');
        }
        
        return $plaintext;
    }
}
