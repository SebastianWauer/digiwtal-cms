<?php
declare(strict_types=1);

namespace App\Setup;

use PDO;
use Throwable;

final class EnsureSiteSettings
{
    public static function run(PDO $pdo): void
    {
        // Nur wenn Tabelle existiert
        if (!function_exists('db_table_exists') || !db_table_exists('site_settings')) {
            return;
        }

        // Defaults als Key/Value sicherstellen
        $keys = ['site_title','site_tagline','logo_media_id','locale','timezone'];

        $pdo->beginTransaction();
        try {
            $in = implode(',', array_fill(0, count($keys), '?'));
            $existing = $pdo->prepare("SELECT `key` FROM site_settings WHERE `key` IN ($in)");
            $existing->execute($keys);
            $have = $existing->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $have = array_flip($have);

            $stmt = $pdo->prepare("INSERT INTO site_settings (`key`,`value`) VALUES (:k,:v)");

            if (!isset($have['site_title']))   $stmt->execute([':k'=>'site_title',   ':v'=>'']);
            if (!isset($have['site_tagline'])) $stmt->execute([':k'=>'site_tagline', ':v'=>'']);
            if (!isset($have['logo_media_id']))$stmt->execute([':k'=>'logo_media_id',':v'=>'']);
            if (!isset($have['locale']))       $stmt->execute([':k'=>'locale',       ':v'=>'de-DE']);
            if (!isset($have['timezone']))     $stmt->execute([':k'=>'timezone',     ':v'=>'Europe/Berlin']);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
