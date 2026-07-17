<?php
declare(strict_types=1);

namespace App\Core;

final class Setup
{
    /**
     * Prüft ob das CMS bereits installiert ist.
     *
     * Variante A: site_settings Tabelle existiert UND app_installed = '1'
     * Variante B (Fallback): Tabelle existiert nicht → false
     * Variante C: DB nicht erreichbar → false (Setup darf laufen)
     */
    public static function isInstalled(\PDO $pdo): bool
    {
        try {
            // Existiert die site_settings Tabelle überhaupt?
            $stmt = $pdo->query("SHOW TABLES LIKE 'site_settings'");
            if (!$stmt || $stmt->rowCount() === 0) {
                return false;
            }

            $stmt = $pdo->prepare(
                "SELECT `value` FROM site_settings WHERE `key` = 'app_installed' LIMIT 1"
            );
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return is_array($row) && ($row['value'] ?? '') === '1';

        } catch (\Throwable) {
            // DB nicht erreichbar oder Schema fehlt → nicht installiert
            return false;
        }
    }

    /**
     * Setzt app_installed = '1' in site_settings.
     * Muss NACH erfolgreichem Setup aufgerufen werden.
     */
    public static function markInstalled(\PDO $pdo): void
    {
        $st = $pdo->prepare(
            "INSERT INTO site_settings (`key`, `value`)
             VALUES ('app_installed', '1')
             ON DUPLICATE KEY UPDATE `value` = '1'"
        );
        $st->execute();
    }

    /**
     * Darf eine Setup-Anfrage bearbeitet werden?
     * true  = CMS noch nicht installiert → Setup erlaubt
     * false = CMS installiert → 404
     */
    public static function allowSetupRequest(\PDO $pdo): bool
    {
        return !self::isInstalled($pdo);
    }
}
