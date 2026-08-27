<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\SupportClient;

/**
 * "Hilfe" im CMS: aufschreiben, was nicht geht - und die Antwort an derselben
 * Stelle wiederfinden.
 *
 * Bewusst ohne eigenes Recht: Wer sich anmelden kann, darf um Hilfe bitten.
 * Ein Recht, das erst jemand vergeben muss, waere genau die Huerde, an der so
 * eine Funktion stirbt.
 */
final class HelpController
{
    public function show(): void
    {
        $user  = \admin_require_login();
        $theme = \admin_theme_for_user((int)$user['id']);

        $client    = new SupportClient();
        $verbunden = $client->istVerbunden();
        $meldungen = $verbunden ? $client->meldungen() : [];

        $flash = self::flashHolen();

        \admin_layout_begin([
            'title'    => 'Hilfe',
            'theme'    => $theme,
            'active'   => 'help',
            'user'     => $user,
            'next'     => '/hilfe',
            'pageCss'  => 'pages-list',
            'headline' => 'Hilfe',
            'subtitle' => 'Probleme melden, Vorschläge einreichen - und Antworten nachlesen.',
        ]);

        require __DIR__ . '/../Views/help.php';
        \admin_layout_end();
    }

    public function submit(): void
    {
        $user = \admin_require_login();
        \admin_verify_csrf();

        $art     = (string)($_POST['kind'] ?? 'problem');
        $betreff = trim((string)($_POST['subject'] ?? ''));
        $text    = trim((string)($_POST['body'] ?? ''));

        if ($betreff === '' || $text === '') {
            self::flashSetzen('error', 'Bitte Betreff und Beschreibung ausfüllen.');
            self::zurueck();
        }

        $client = new SupportClient();
        $ergebnis = $client->melden(
            $art,
            $betreff,
            $text,
            (string)($user['name'] ?? $user['username'] ?? ''),
            (string)($user['email'] ?? ''),
            SupportClient::kontext((string)($user['username'] ?? ''))
        );

        if ($ergebnis['ok']) {
            self::flashSetzen('success', 'Deine Meldung ist angekommen. Die Antwort erscheint hier auf dieser Seite.');
        } else {
            self::flashSetzen('error', $ergebnis['fehler']);
        }

        self::zurueck();
    }

    private static function zurueck(): never
    {
        header('Location: ' . \cms_base_path() . '/hilfe');
        exit;
    }

    private static function flashSetzen(string $typ, string $text): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['cms_help_flash'] = ['type' => $typ, 'msg' => $text];
    }

    /** @return array{type: string, msg: string}|null */
    private static function flashHolen(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $flash = $_SESSION['cms_help_flash'] ?? null;
        unset($_SESSION['cms_help_flash']);

        return is_array($flash) ? $flash : null;
    }
}
