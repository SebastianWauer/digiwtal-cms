<?php
declare(strict_types=1);

/**
 * Sendet Web-Push-Notifications via VAPID (RFC 8030 / RFC 8292).
 */
class PushService
{
    private string $vapidPublic;
    private string $vapidPrivate;
    private string $subject;

    public function __construct()
    {
        $this->vapidPublic  = (string)(getenv('VAPID_PUBLIC')  ?: '');
        $this->vapidPrivate = (string)(getenv('VAPID_PRIVATE') ?: '');
        $this->subject      = 'mailto:noreply@digiwtal.de';
    }

    public function isConfigured(): bool
    {
        return $this->vapidPublic !== '' && $this->vapidPrivate !== '';
    }

    public function sendToAll(array $subscriptions, string $title, string $body, string $url = '/admin/dashboard', string $tag = 'digiwtal'): int
    {
        if (!$this->isConfigured()) {
            FileLogger::channel('verwaltung')->error('[PUSH] VAPID keys not configured – skipping');
            return 0;
        }

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'tag'   => $tag,
        ]);

        $sent = 0;
        foreach ($subscriptions as $sub) {
            try {
                $success = $this->sendOne(
                    (string)$sub['endpoint'],
                    (string)$sub['p256dh'],
                    (string)$sub['auth'],
                    (string)$payload
                );
                if ($success) $sent++;
            } catch (Throwable $e) {
                FileLogger::channel('verwaltung')->error('[PUSH] sendOne failed: ' . $e->getMessage());
            }
        }
        return $sent;
    }

    private function sendOne(string $endpoint, string $p256dh, string $auth, string $payload): bool
    {
        $encrypted = $this->encryptPayload($payload, $p256dh, $auth);
        $jwt = $this->buildVapidJwt($endpoint);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $encrypted['ciphertext'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 86400',
                'Authorization: vapid t=' . $jwt . ', k=' . $this->vapidPublic,
                'Encryption: salt=' . $encrypted['salt'],
                'Crypto-Key: dh=' . $encrypted['dh'],
            ],
        ]);

        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 410 || $code === 404) {
            return false;
        }

        return $code >= 200 && $code < 300;
    }

    private function encryptPayload(string $payload, string $p256dh, string $auth): array
    {
        $userPublicKey = $this->base64urlDecode($p256dh);
        $userAuth      = $this->base64urlDecode($auth);

        $serverKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $serverDetails = openssl_pkey_get_details($serverKey);
        $serverPublicKey = chr(4) . $serverDetails['ec']['x'] . $serverDetails['ec']['y'];

        $userKey = openssl_pkey_get_public(['key' => $this->ecPublicKeyToPem($userPublicKey)]);
        openssl_pkey_export($serverKey, $serverPrivPem);

        $sharedSecret = openssl_pkey_derive($userKey, openssl_pkey_get_private($serverPrivPem));
        if ($sharedSecret === false) {
            throw new RuntimeException('ECDH key derivation failed – check key formats');
        }

        $salt = random_bytes(16);
        $prk  = hash_hmac('sha256', $sharedSecret, $userAuth, true);
        $info = 'WebPush: info' . chr(0) . $userPublicKey . $serverPublicKey;
        $ikm  = $this->hkdfExpand($prk, $info . chr(1), 32);

        $prk2   = hash_hmac('sha256', $ikm, $salt, true);
        $keyInfo = 'Content-Encoding: aes128gcm' . chr(0) . chr(1);
        $key     = $this->hkdfExpand($prk2, $keyInfo, 16);
        $nonceInfo = 'Content-Encoding: nonce' . chr(0) . chr(1);
        $nonce   = $this->hkdfExpand($prk2, $nonceInfo, 12);

        $tag = '';
        $ciphertext = openssl_encrypt(
            $payload . chr(2),
            'aes-128-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        return [
            'ciphertext' => $ciphertext . $tag,
            'salt'       => $this->base64urlEncode($salt),
            'dh'         => $this->base64urlEncode($serverPublicKey),
        ];
    }

    private function buildVapidJwt(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $audience = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        $header  = $this->base64urlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = $this->base64urlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 86400,
            'sub' => $this->subject,
        ]));

        $unsigned = $header . '.' . $payload;

        $privKeyDer = $this->base64urlDecode($this->vapidPrivate);
        $pem = $this->ecPrivateKeyToPem($privKeyDer);
        $privKey = openssl_pkey_get_private($pem);

        openssl_sign($unsigned, $signature, $privKey, 'SHA256');

        $signature = $this->derToRawSignature($signature);

        return $unsigned . '.' . $this->base64urlEncode($signature);
    }

    private function hkdfExpand(string $prk, string $info, int $length): string
    {
        $output = '';
        $t = '';
        $i = 1;
        while (strlen($output) < $length) {
            $t = hash_hmac('sha256', $t . $info . chr($i++), $prk, true);
            $output .= $t;
        }
        return substr($output, 0, $length);
    }

    private function ecPublicKeyToPem(string $rawKey): string
    {
        $der  = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $rawKey;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64) . "-----END PUBLIC KEY-----\n";
    }

    private function ecPrivateKeyToPem(string $rawKey): string
    {
        $der = hex2bin('308193020100301306072a8648ce3d020106082a8648ce3d030107047930770201010420')
            . $rawKey
            . hex2bin('a00a06082a8648ce3d030107a14403420004');
        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64) . "-----END EC PRIVATE KEY-----\n";
    }

    private function derToRawSignature(string $der): string
    {
        $r = '';
        $s = '';
        $offset = 2;
        $offset++;
        $rLen = ord($der[$offset++]);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;
        $offset++;
        $sLen = ord($der[$offset++]);
        $s = substr($der, $offset, $sLen);

        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
        return substr($r, -32) . substr($s, -32);
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): string
    {
        return (string)base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
