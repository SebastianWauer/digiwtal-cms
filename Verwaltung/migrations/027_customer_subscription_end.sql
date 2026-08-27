-- Zeitliche Begrenzung eines aktiven Kunden-Abonnements.
-- NULL bedeutet: Das Abonnement ist unbegrenzt aktiv.

ALTER TABLE customers
    ADD COLUMN abo_active_until DATE NULL AFTER abo_status,
    ADD INDEX idx_abo_active_until (abo_active_until);
