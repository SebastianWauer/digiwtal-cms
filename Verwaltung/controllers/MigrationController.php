<?php
declare(strict_types=1);

/**
 * Datenbank-Migrationen der Verwaltung im Browser.
 *
 * Der Webspace der Verwaltung hat keine Shell: PHP laeuft als cgi-fcgi, die
 * ssh2-Erweiterung fehlt, Port 21 ist zu. Eine Kommandozeile gibt es dort
 * nicht - aus "php scripts/migrate.php" wird hier ein Knopf.
 *
 * Nur fuer Superadmins: Das Schema ist nichts, was nebenbei angefasst wird.
 */
class MigrationController
{
    public function __construct(
        private PDO $pdo,
        private AuditLogger $audit
    ) {}

    public function index(): void
    {
        $this->requireSuperadmin();

        $runner   = MigrationRunner::fuerVerwaltung($this->pdo);
        $dbFehler = null;
        $applied  = [];
        $analyse  = [];

        try {
            $applied = $runner->applied();
            $analyse = $runner->analyse();
        } catch (Throwable $e) {
            $dbFehler = $e->getMessage();
        }

        $offen = count($analyse);

        // Wie viele der offenen Dateien betreffen Tabellen, die es schon gibt?
        $bestand = 0;
        foreach ($analyse as $eintrag) {
            if ($eintrag['bestand']) {
                $bestand++;
            }
        }

        // Nichts markiert, aber Tabellen sind da: eine gewachsene Installation,
        // die noch nie einen Runner gesehen hat. Erst Bestand markieren.
        $erstlauf = ($dbFehler === null && $applied === [] && $bestand > 0);

        $success = $_SESSION['flash_success'] ?? null;
        $errors  = $_SESSION['flash_errors'] ?? [];
        $lauf    = $_SESSION['flash_migration_lauf'] ?? [];
        unset($_SESSION['flash_success'], $_SESSION['flash_errors'], $_SESSION['flash_migration_lauf']);

        require __DIR__ . '/../views/migrations/index.php';
    }

    /**
     * Markiert ausgewaehlte Dateien als angewendet, ohne SQL auszufuehren.
     *
     * Ausgewaehlt wird bewusst einzeln: Wuerde man pauschal alles markieren,
     * traefe es auch die wirklich neue Migration - die liefe dann nie.
     */
    public function baseline(): void
    {
        $this->requireSuperadmin();
        $this->requireCsrf();

        $auswahl = $_POST['dateien'] ?? [];
        if (!is_array($auswahl)) {
            $auswahl = [];
        }
        $auswahl = array_values(array_filter(array_map('strval', $auswahl), static fn(string $n): bool => $n !== ''));

        if ($auswahl === []) {
            $_SESSION['flash_errors'] = ['Keine Datei ausgewaehlt.'];
            $this->back();
        }

        try {
            $markiert = MigrationRunner::fuerVerwaltung($this->pdo)->baseline($auswahl);
        } catch (Throwable $e) {
            $_SESSION['flash_errors'] = ['Bestand konnte nicht markiert werden: ' . $e->getMessage()];
            $this->back();
        }

        $this->audit->log('migration.baseline', 'schema', null, count($markiert) . ' Datei(en)');
        $_SESSION['flash_migration_lauf'] = $markiert;
        $_SESSION['flash_success'] = count($markiert) . ' Migration(en) als angewendet markiert. '
            . 'Ausgefuehrt wurde nichts - die Tabellen waren bereits da.';
        $this->back();
    }

    /** Fuehrt die offenen Migrationen aus. */
    public function apply(): void
    {
        $this->requireSuperadmin();
        $this->requireCsrf();

        try {
            $ergebnis = MigrationRunner::fuerVerwaltung($this->pdo)->migrate();
        } catch (Throwable $e) {
            $_SESSION['flash_errors'] = ['Migration fehlgeschlagen: ' . $e->getMessage()];
            $this->back();
        }

        $_SESSION['flash_migration_lauf'] = $ergebnis['angewendet'];

        if ($ergebnis['fehler'] !== null) {
            $this->audit->log(
                'migration.failed',
                'schema',
                null,
                (string)$ergebnis['fehlerBei'] . ' - ' . substr((string)$ergebnis['fehler'], 0, 400)
            );
            $_SESSION['flash_errors'] = [
                'Migration ' . (string)$ergebnis['fehlerBei'] . ' fehlgeschlagen: ' . (string)$ergebnis['fehler'],
                'Abgebrochen nach ' . count($ergebnis['angewendet']) . ' erfolgreichen Migration(en). '
                    . 'Die uebrigen bleiben offen.',
            ];
            $this->back();
        }

        if ($ergebnis['angewendet'] === []) {
            $_SESSION['flash_success'] = 'Keine offenen Migrationen.';
            $this->back();
        }

        $this->audit->log('migration.applied', 'schema', null, implode(', ', $ergebnis['angewendet']));
        $_SESSION['flash_success'] = count($ergebnis['angewendet']) . ' Migration(en) angewendet.';
        $this->back();
    }

    private function requireSuperadmin(): void
    {
        AdminAuth::requireAuth();

        if (($_SESSION['admin_role'] ?? '') !== 'superadmin') {
            http_response_code(403);
            $title   = 'Kein Zugriff';
            $content = '<div class="view-stack"><section class="surface">'
                . '<h1 class="page-title">Kein Zugriff</h1>'
                . '<p class="section-copy">Datenbank-Migrationen sind Superadmins vorbehalten.</p>'
                . '<a class="btn btn--secondary btn--sm" href="/admin/dashboard">Zum Dashboard</a>'
                . '</section></div>';
            require __DIR__ . '/../views/layout.php';
            exit;
        }
    }

    private function requireCsrf(): void
    {
        if (!Csrf::verify((string)($_POST['csrf_token'] ?? ''))) {
            $_SESSION['flash_errors'] = ['CSRF-Token ungueltig. Seite neu laden und erneut versuchen.'];
            $this->back();
        }
    }

    private function back(): never
    {
        header('Location: /admin/migrations');
        exit;
    }
}
