<?php
declare(strict_types=1);

/**
 * Misst alle Instanzen, die die Verwaltung ausgibt.
 *
 * Aufruf:
 *   php .github/scripts/health_sweep.php <instanzen.json> <report.json> [quelle]
 *
 * Gemessen wird mit HealthMonitor - denselben Methoden, die auch der Cron der
 * Verwaltung benutzt. Bewertet wird hier bewusst nichts: Aus einer Messung
 * einen Status zu machen ist Sache der Verwaltung (HealthMonitor::evaluate),
 * sonst gibt es zwei Wahrheiten.
 *
 * Tokens werden nie ausgegeben. Die Zusammenfassung auf STDOUT landet im
 * Actions-Log und ist die schnellste Antwort auf "laeuft alles?".
 */

require_once dirname(__DIR__, 2) . '/Verwaltung/services/HealthMonitor.php';

$eingabe = (string)($argv[1] ?? '');
$ausgabe = (string)($argv[2] ?? '');
$quelle  = (string)($argv[3] ?? 'ci');

if ($eingabe === '' || $ausgabe === '') {
    fwrite(STDERR, "Aufruf: health_sweep.php <instanzen.json> <report.json> [quelle]\n");
    exit(2);
}

$roh = @file_get_contents($eingabe);
if (!is_string($roh) || $roh === '') {
    fwrite(STDERR, "Instanzliste nicht lesbar: {$eingabe}\n");
    exit(2);
}

$daten = json_decode($roh, true);
$instanzen = is_array($daten) && is_array($daten['instances'] ?? null) ? $daten['instances'] : null;
if ($instanzen === null) {
    fwrite(STDERR, "Instanzliste enthaelt kein 'instances'-Feld.\n");
    exit(2);
}

if ($instanzen === []) {
    // Kein Fehler, aber erklaerungsbeduerftig: ohne Health-URL und Token in der
    // Verwaltung ist eine Instanz nicht ueberwachbar.
    fwrite(STDOUT, "Keine ueberwachbaren Instanzen gemeldet.\n");
}

$ergebnisse = [];
foreach ($instanzen as $instanz) {
    if (!is_array($instanz)) {
        continue;
    }

    $kunde = (int)($instanz['customer'] ?? 0);
    $name  = (string)($instanz['name'] ?? ('Kunde ' . $kunde));
    $token = (string)($instanz['token'] ?? '');
    $cmsUrl      = (string)($instanz['cms_health_url'] ?? '');
    $frontendUrl = (string)($instanz['frontend_url'] ?? '');

    if ($kunde <= 0 || $cmsUrl === '' || $token === '') {
        fwrite(STDOUT, sprintf("%-24s uebersprungen (unvollstaendige Angaben)\n", $name));
        continue;
    }

    $cms      = HealthMonitor::probeCms($cmsUrl, $token);
    $frontend = HealthMonitor::probeFrontend($frontendUrl);

    $ergebnisse[] = [
        'customer' => $kunde,
        'cms'      => $cms,
        'frontend' => $frontend,
    ];

    fwrite(STDOUT, sprintf(
        "%-24s CMS %3d in %5dms%s | Frontend %s\n",
        $name,
        (int)$cms['http_code'],
        (int)$cms['response_ms'],
        $cms['error'] !== null ? ' (' . $cms['error'] . ')' : '',
        $frontend['checked'] ? ((int)$frontend['http_code'] . ' in ' . (int)$frontend['response_ms'] . 'ms') : 'nicht konfiguriert'
    ));
}

$report = ['source' => $quelle, 'results' => $ergebnisse];
if (@file_put_contents($ausgabe, json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
    fwrite(STDERR, "Report nicht schreibbar: {$ausgabe}\n");
    exit(2);
}

fwrite(STDOUT, count($ergebnisse) . " Instanz(en) gemessen.\n");
exit(0);
