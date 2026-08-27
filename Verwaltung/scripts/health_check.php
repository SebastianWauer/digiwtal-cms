<?php
declare(strict_types=1);

/**
 * Periodischer Pruflauf der Verwaltung.
 *
 * Duenner Aufruf um HealthMonitor - die Logik liegt im Service, damit Cron und
 * Pipeline nicht auseinanderlaufen koennen. Empfohlener Takt: alle fuenf
 * Minuten, siehe Verwaltung/docs/cron-setup.md.
 *
 * Der Lauf traegt sich in monitor_runs ein. Bleibt der Eintrag stehen, meldet
 * das Dashboard, dass die Ueberwachung selbst ausgefallen ist.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/VaultCrypto.php';
require_once dirname(__DIR__) . '/repositories/PushSubscriptionRepository.php';
require_once dirname(__DIR__) . '/services/PushService.php';
require_once dirname(__DIR__) . '/services/HealthMonitor.php';

$debug = (getenv('HC_DEBUG') === '1');
$log = static function (string $line) use ($debug): void {
    if ($debug) {
        FileLogger::channel('verwaltung')->error('[HC] ' . $line);
    }
};

try {
    $pdo = new PDO(
        'mysql:host=' . (string)(getenv('DB_HOST') ?: 'localhost')
            . ';dbname=' . (string)(getenv('DB_NAME') ?: '')
            . ';charset=utf8mb4',
        (string)(getenv('DB_USER') ?: ''),
        (string)(getenv('DB_PASS') ?: ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    // Ohne Datenbank ist kein Lauf moeglich - und ohne diese Zeile im Log
    // sieht ein toter Cron aus wie ein gesunder Kunde.
    FileLogger::channel('verwaltung')->error('[HC] db_connect_failed: ' . $e->getMessage());
    exit(1);
}

$monitor = new HealthMonitor(
    $pdo,
    (string)(getenv('HEALTH_ALERT_EMAIL') ?: 'info@digiwtal.de'),
    new PushService(),
    new PushSubscriptionRepository($pdo)
);

$targets = $monitor->targets();
$log('targets=' . count($targets));

$geprueft = 0;
foreach ($targets as $target) {
    $cms      = HealthMonitor::probeCms($target['cms_url'], $target['token']);
    $frontend = HealthMonitor::probeFrontend($target['frontend_url']);
    $result   = $monitor->evaluate($cms, $frontend, 'cron');

    try {
        $monitor->record($target['id'], $target['name'], $result, 'cron');
        $geprueft++;
        $log("customer_id={$target['id']} status={$result['status']} response_ms={$result['response_ms']}");
    } catch (Throwable $e) {
        FileLogger::channel('verwaltung')->error("[HC] insert_failed customer_id={$target['id']} err=" . $e->getMessage());
    }
}

try {
    $monitor->noteRun('cron', $geprueft);
} catch (Throwable $e) {
    FileLogger::channel('verwaltung')->error('[HC] heartbeat_failed: ' . $e->getMessage());
}

$log('complete geprueft=' . $geprueft);
exit(0);
