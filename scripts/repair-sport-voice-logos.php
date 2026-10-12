<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Env;
use App\Core\FrontendSource;
use App\Services\SvgSanitizer;

if (Env::get('PAGEBUILDER_CUSTOMER_KEY', '') !== 'sport-voice') {
    fwrite(STDERR, "Reparatur ist ausschliesslich fuer Sport Voice vorgesehen.\n");
    exit(1);
}
$apply = in_array('--apply', $argv, true);
$names = ['gtwc-europe-mono.svg','gt4-europe-mono.svg','gt4-germany-mono.svg','dkm-germany-mono.svg','rmc-germany-mono.svg'];
$root = dirname(__DIR__);
$storage = realpath($root . '/storage/media');
if ($storage === false) throw new RuntimeException('Medienordner fehlt.');
$pdo = db();
$query = $pdo->prepare("SELECT id, original_filename, storage_filename FROM media_items WHERE ext = 'svg' AND is_deleted = 0 AND original_filename IN (" . implode(',', array_fill(0, count($names), '?')) . ')');
$query->execute($names);
$repaired = 0;
foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $name = (string)$row['original_filename'];
    $path = realpath($storage . '/' . $row['storage_filename']);
    $original = FrontendSource::file('assets/sport-voice/' . $name);
    if ($path === false || dirname($path) !== $storage || $original === null) throw new RuntimeException('Datei oder Original fehlt: ' . $name);
    $current = new DOMDocument();
    if (!$current->loadXML((string)file_get_contents($path), LIBXML_NONET) || $current->doctype !== null) throw new RuntimeException('Ungueltiger SVG-Bestand: ' . $name);
    $xpath = new DOMXPath($current);
    $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');
    // Nur den bekannten Uploadschaden reparieren; intakte/bearbeitete Logos bleiben erhalten.
    if ($current->documentElement?->namespaceURI !== 'http://www.w3.org/2000/svg'
        || $xpath->query('//svg:defs/svg:filter[@id="mono"]')->length !== 1
        || $xpath->query('//*[local-name()="path" or local-name()="image" or local-name()="rect" or local-name()="circle" or local-name()="ellipse" or local-name()="line" or local-name()="polygon" or local-name()="polyline" or local-name()="text" or local-name()="use"]')->length > 0) {
        echo 'Unveraendert: ' . $name . ' (ID ' . $row['id'] . ")\n";
        continue;
    }
    $restored = SvgSanitizer::sanitize((string)file_get_contents($original));
    if (!str_contains($restored, '<image')) throw new RuntimeException('Original enthaelt kein Logo: ' . $name);
    if (!$apply) {
        echo 'Wuerde reparieren: ' . $name . ' (ID ' . $row['id'] . ")\n";
        continue;
    }
    $backupDirectory = $root . '/storage/backups/svg-logos';
    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true)) throw new RuntimeException('Sicherungsordner konnte nicht angelegt werden.');
    $backup = $backupDirectory . '/' . $row['id'] . '-' . hash_file('sha256', $path) . '.svg';
    if (!is_file($backup) && !copy($path, $backup)) throw new RuntimeException('Sicherung fehlgeschlagen.');
    chmod($backup, 0600);
    $temp = tempnam($storage, 'svg-repair-');
    if ($temp === false) throw new RuntimeException('Tempfile fehlgeschlagen.');
    try {
        if (file_put_contents($temp, $restored) !== strlen($restored)) throw new RuntimeException('Schreiben fehlgeschlagen.');
        chmod($temp, 0644);
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE media_items SET size_bytes = ?, updated_at = NOW() WHERE id = ?');
            $update->execute([strlen($restored), (int)$row['id']]);
            if (!rename($temp, $path)) throw new RuntimeException('Dateitausch fehlgeschlagen.');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            copy($backup, $path);
            throw $e;
        }
    } finally {
        if (is_file($temp)) unlink($temp);
    }
    $repaired++;
    echo 'Repariert: ' . $name . ' (ID ' . $row['id'] . ")\n";
}
echo $apply ? "$repaired Logo(s) repariert.\n" : "Vorschau: keine Dateien veraendert. Mit --apply anwenden.\n";
