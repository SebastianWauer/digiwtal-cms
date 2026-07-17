<?php
declare(strict_types=1);

// app/admin_csrf.php
// Zentraler Admin-CSRF Schutz.

const ADMIN_CSRF_SESSION_KEY = 'cms_admin_csrf';

function admin_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // bootstrap startet Session i.d.R. bereits, aber defensiv:
        @session_start();
    }

    $t = (string)($_SESSION[ADMIN_CSRF_SESSION_KEY] ?? '');
    if ($t === '' || strlen($t) < 32) {
        $t = bin2hex(random_bytes(32));
        $_SESSION[ADMIN_CSRF_SESSION_KEY] = $t;
    }
    return $t;
}

/**
 * Hidden field für klassische <form method="post"> Submits.
 * Default Feldname: "_token"
 */
function admin_csrf_field(string $fieldName = '_token'): string
{
    $fieldName = trim($fieldName);
    if ($fieldName === '') $fieldName = '_token';

    $name  = htmlspecialchars($fieldName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $value = htmlspecialchars(admin_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<input type="hidden" name="' . $name . '" value="' . $value . '">';
}

/**
 * Verifiziert CSRF für Admin-POSTs.
 * Akzeptiert Token aus:
 *  - POST Feld "_token" (Default)
 *  - Header "X-CSRF-Token" (für fetch/AJAX)
 *
 * Bei Fehler: 403 + Exit.
 */
function admin_verify_csrf(?string $token = null, string $fieldName = '_token'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $expected = (string)($_SESSION[ADMIN_CSRF_SESSION_KEY] ?? '');
    if ($expected === '') {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    if ($token === null) {
        $fieldName = trim($fieldName);
        if ($fieldName === '') $fieldName = '_token';

        $token = (string)($_POST[$fieldName] ?? '');

        if ($token === '' && function_exists('getallheaders')) {
            $h = getallheaders();
            if (is_array($h)) {
                foreach ($h as $k => $v) {
                    if (!is_string($k)) continue;
                    if (strcasecmp($k, 'X-CSRF-Token') === 0) {
                        $token = is_string($v) ? $v : (string)$v;
                        break;
                    }
                }
            }
        }
    }

    if ($token === '' || !hash_equals($expected, (string)$token)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function admin_csrf_reset(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    unset($_SESSION[ADMIN_CSRF_SESSION_KEY]);
}
