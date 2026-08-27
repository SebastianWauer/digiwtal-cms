-- Der Waechter wird bewacht.
--
-- Ohne diese Tabelle ist ein stiller Ausfall des Health-Checks nicht von einer
-- gesunden Lage zu unterscheiden: Das Dashboard zeigt weiter den letzten
-- gespeicherten Status, auch wenn seit Wochen niemand mehr geprueft hat.
-- Genau das ist am 26.08.2026 passiert - der Cron brach ab, die Anzeige blieb
-- auf dem Wert von 14:04 stehen.
--
-- Jeder Pruflauf traegt sich hier ein, egal von welcher Seite er kam.

CREATE TABLE IF NOT EXISTS monitor_runs (
    source ENUM('cron', 'ci', 'rollout', 'manual') NOT NULL PRIMARY KEY,
    last_run_at DATETIME NOT NULL,
    customers_checked INT NOT NULL DEFAULT 0,
    note VARCHAR(255) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
