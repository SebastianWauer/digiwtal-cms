<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

/**
 * Verschluesselt/entschluesselt den gespeicherten Instagram-Access-Token
 * (AES-256-GCM), analog zu Verwaltung/app/VaultCrypto.php.
 */
final class InstagramTokenCrypto
{
    private const ALGO = 'aes-256-gcm';
    private const NONCE_SIZE = 12;
    private const TAG_SIZE = 16;
    private const AAD = 'instagram_access_token';

    private static function key(): string
    {
        $keyB64 = (string)Env::get('INSTAGRAM_TOKEN_KEY_BASE64', '');
        if ($keyB64 === '') {
            throw new RuntimeException('INSTAGRAM_TOKEN_KEY_BASE64 not set');
        }

        $key = base64_decode($keyB64, true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('INSTAGRAM_TOKEN_KEY_BASE64 must be valid base64 of 32 bytes');
        }

        return $key;
    }

    /** Liefert einen einzelnen String zum Speichern (nonce + tag + ciphertext, base64). */
    public static function encrypt(string $plaintext): string
    {
        $key = self::key();
        $nonce = random_bytes(self::NONCE_SIZE);
        $tag = '';

        $ciphertext = openssl_encrypt($plaintext, self::ALGO, $key, OPENSSL_RAW_DATA, $nonce, $tag, self::AAD);
        if ($ciphertext === false) {
            throw new RuntimeException('instagram_token_encrypt_failed');
        }

        return base64_encode($nonce . $tag . $ciphertext);
    }

    public static function decrypt(string $stored): string
    {
        $key = self::key();
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) <= self::NONCE_SIZE + self::TAG_SIZE) {
            throw new RuntimeException('instagram_token_decrypt_failed');
        }

        $nonce = substr($raw, 0, self::NONCE_SIZE);
        $tag = substr($raw, self::NONCE_SIZE, self::TAG_SIZE);
        $ciphertext = substr($raw, self::NONCE_SIZE + self::TAG_SIZE);

        $plaintext = openssl_decrypt($ciphertext, self::ALGO, $key, OPENSSL_RAW_DATA, $nonce, $tag, self::AAD);
        if ($plaintext === false) {
            throw new RuntimeException('instagram_token_decrypt_failed');
        }

        return $plaintext;
    }
}
