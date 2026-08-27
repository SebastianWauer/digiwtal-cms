<?php
declare(strict_types=1);

/**
 * Die eigene oeffentliche Adresse der Verwaltung.
 *
 * Wird an zwei Stellen gebraucht: Die CI-Schnittstelle gibt sie an die
 * Pipeline weiter, und der Hilfe-Posteingang zeigt sie an, damit eine von Hand
 * gepflegte Instanz sich verbinden kann. Beide sollen dieselbe Antwort
 * bekommen.
 */
final class VerwaltungUrl
{
    public static function base(): string
    {
        $host = trim((string)(getenv('ADMIN_HOST') ?: ''));
        if ($host === '') {
            $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        }
        if ($host === '') {
            return '';
        }

        return preg_match('#^https?://#i', $host) === 1
            ? rtrim($host, '/')
            : 'https://' . rtrim($host, '/');
    }
}
