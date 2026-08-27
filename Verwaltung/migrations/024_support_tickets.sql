-- Hilfe-Funktion: Meldungen aus den Kunden-CMS landen hier.
CREATE TABLE IF NOT EXISTS support_tickets (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT NOT NULL,
    kind           ENUM('problem', 'vorschlag', 'frage') NOT NULL DEFAULT 'problem',
    subject        VARCHAR(190) NOT NULL,
    body           TEXT NOT NULL,
    status         ENUM('neu', 'in_arbeit', 'beantwortet', 'erledigt') NOT NULL DEFAULT 'neu',
    answer         TEXT NULL,
    answered_at    DATETIME NULL,
    reporter_name  VARCHAR(190) NOT NULL DEFAULT '',
    reporter_email VARCHAR(190) NOT NULL DEFAULT '',
    -- Umgebung der meldenden Instanz: CMS-Version, PHP, Adresse der Seite.
    -- Erspart die Rueckfrage "welche Version denn?" bei jeder Meldung.
    context_json   TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_support_customer (customer_id, status),
    INDEX idx_support_created (created_at),
    CONSTRAINT fk_support_customer FOREIGN KEY (customer_id)
        REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
