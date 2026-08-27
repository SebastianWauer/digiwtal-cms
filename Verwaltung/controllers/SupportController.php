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

        $success = $_SESSION['flash_success'] ?? null;
        $errors  = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_success'], $_SESSION['flash_errors']);

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

    private function back(int $id): never
    {
        header('Location: /admin/support/' . $id);
        exit;
    }
}
