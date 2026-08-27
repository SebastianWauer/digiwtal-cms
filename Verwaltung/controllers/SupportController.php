<?php
declare(strict_types=1);

/**
 * Posteingang fuer die Meldungen aus allen Kunden-CMS.
 *
 * Eine Liste, eine Detailansicht, eine Antwort. Die Antwort geht keinen Umweg
 * ueber E-Mail - sie erscheint direkt im CMS des Kunden, unter demselben
 * "Hilfe", wo er die Meldung geschrieben hat.
 */
class SupportController
{
    public function __construct(
        private SupportTicketRepository $tickets,
        private CustomerRepository $customerRepo,
        private SupportTokenRepository $supportTokens,
        private AuditLogger $audit
    ) {}

    public function index(): void
    {
        AdminAuth::requireAuth();

        $status     = (string)($_GET['status'] ?? '');
        $customerId = (int)($_GET['kunde'] ?? 0);

        $eintraege = $this->tickets->all($status, $customerId);
        $zaehler   = $this->tickets->countsByStatus();
        $kunden    = $this->customerRepo->listAllWithHealth();

        $success     = $_SESSION['flash_success'] ?? null;
        $errors      = $_SESSION['flash_errors'] ?? [];
        $zugangsdaten = $_SESSION['flash_support_zugang'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_errors'], $_SESSION['flash_support_zugang']);

        $verwaltungUrl = VerwaltungUrl::base();

        require __DIR__ . '/../views/support/index.php';
    }

    public function show(int $id): void
    {
        AdminAuth::requireAuth();

        $ticket = $this->tickets->find($id);
        if ($ticket === null) {
            http_response_code(404);
            $title   = 'Meldung nicht gefunden';
            $content = '<div class="view-stack"><section class="surface">'
                . '<h1 class="page-title">Meldung nicht gefunden</h1>'
                . '<a class="btn btn--secondary btn--sm" href="/admin/support">Zurueck zum Posteingang</a>'
                . '</section></div>';
            require __DIR__ . '/../views/layout.php';
            exit;
        }

        $kontext = [];
        if (($ticket['context_json'] ?? null) !== null) {
            $entschluesselt = json_decode((string)$ticket['context_json'], true);
            if (is_array($entschluesselt)) {
                $kontext = $entschluesselt;
            }
        }

        $success = $_SESSION['flash_success'] ?? null;
        $errors  = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_success'], $_SESSION['flash_errors']);

        require __DIR__ . '/../views/support/show.php';
    }

    /** Antwort speichern und/oder Status setzen. */
    public function update(int $id): void
    {
        AdminAuth::requireAuth();
        if (!Csrf::verify((string)($_POST['csrf_token'] ?? ''))) {
            $_SESSION['flash_errors'] = ['CSRF-Token ungueltig. Seite neu laden und erneut versuchen.'];
            $this->back($id);
        }

        if ($this->tickets->find($id) === null) {
            $_SESSION['flash_errors'] = ['Meldung nicht gefunden.'];
            $this->back($id);
        }

        $antwort = trim((string)($_POST['answer'] ?? ''));
        $status  = trim((string)($_POST['status'] ?? ''));

        if ($antwort !== '') {
            $this->tickets->answer($id, $antwort);
            $this->audit->log('support.answered', 'support_ticket', $id);
            $_SESSION['flash_success'] = 'Antwort gespeichert. Sie erscheint im CMS des Kunden unter "Hilfe".';
        }

        // Ein ausdruecklich gesetzter Status gewinnt gegen den, den answer()
        // gesetzt hat - sonst liesse sich "erledigt" nicht zusammen mit einer
        // Antwort speichern.
        if ($status !== '' && in_array($status, SupportTicketRepository::STATUS, true)) {
            $this->tickets->setStatus($id, $status);
            $this->audit->log('support.status', 'support_ticket', $id, $status);
            if ($antwort === '') {
                $_SESSION['flash_success'] = 'Status auf "' . $status . '" gesetzt.';
            }
        }

        if ($antwort === '' && $status === '') {
            $_SESSION['flash_errors'] = ['Nichts zu speichern: weder Antwort noch Status angegeben.'];
        }

        $this->back($id);
    }

    /**
     * Zeigt die zwei .env-Zeilen, mit denen sich eine Instanz verbindet.
     *
     * Gebraucht fuer alles, was die Instanz-Pipeline nicht ausrollt - die
     * eigene Installation zum Beispiel. Das Token wird dabei erzeugt, falls es
     * noch keines gibt, und danach nicht mehr angezeigt.
     */
    public function zugang(int $customerId): void
    {
        AdminAuth::requireAuth();
        if (!Csrf::verify((string)($_POST['csrf_token'] ?? ''))) {
            $_SESSION['flash_errors'] = ['CSRF-Token ungueltig. Seite neu laden und erneut versuchen.'];
            $this->zurueckZurListe();
        }

        $kunde = $this->customerRepo->findById($customerId);
        if ($kunde === null) {
            $_SESSION['flash_errors'] = ['Kunde nicht gefunden.'];
            $this->zurueckZurListe();
        }

        $url = VerwaltungUrl::base();
        if ($url === '') {
            $_SESSION['flash_errors'] = ['Eigene Adresse unbekannt. ADMIN_HOST in der .env der Verwaltung setzen.'];
            $this->zurueckZurListe();
        }

        $token = $this->supportTokens->ensureFor($customerId);
        if ($token === null) {
            $_SESSION['flash_errors'] = [
                'Fuer "' . (string)($kunde['name'] ?? '') . '" ist kein Serverzugang hinterlegt. '
                . 'Das Token haengt daran - erst den Serverzugang anlegen.',
            ];
            $this->zurueckZurListe();
        }

        $this->audit->log('support.token_revealed', 'customer', $customerId);

        $_SESSION['flash_support_zugang'] = [
            'kunde' => (string)($kunde['name'] ?? ''),
            'url'   => $url,
            'token' => $token,
        ];
        $this->zurueckZurListe();
    }

    private function zurueckZurListe(): never
    {
        header('Location: /admin/support');
        exit;
    }

    private function back(int $id): never
    {
        header('Location: /admin/support/' . $id);
        exit;
    }
}
