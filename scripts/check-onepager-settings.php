<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
use App\Services\OnepagerService;
use App\Core\WebsiteMode;
$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$pdo = new class('sqlite::memory:') extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(preg_replace('/ON DUPLICATE KEY UPDATE .+$/s', 'ON CONFLICT("key") DO UPDATE SET "value" = excluded."value"', $query), $options);
    }
};
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE site_settings (key TEXT PRIMARY KEY, value TEXT)');
$pdo->exec("CREATE TABLE pages (id INTEGER PRIMARY KEY, slug TEXT, title TEXT, status TEXT DEFAULT 'live', is_home INTEGER DEFAULT 0, redirect_type TEXT DEFAULT 'none', is_deleted INTEGER DEFAULT 0, nav_order INTEGER DEFAULT 0)");
$pdo->exec("INSERT INTO pages(id,slug,title,nav_order,is_home) VALUES (1,'/','Start',10,1),(2,'kontakt','Kontakt',20,0),(3,'leistungen','Leistungen',30,0),(4,'impressum','Impressum',40,0),(5,'datenschutz','Datenschutz',50,0)");
$pdo->exec("INSERT INTO pages(id,slug,title,status,nav_order) VALUES (6,'entwurf','Entwurf','draft',60)");
$pdo->exec("INSERT INTO pages(id,slug,title,redirect_type,nav_order) VALUES (7,'redirect','Redirect','url',70)");
$service = new OnepagerService($pdo);
$original = getenv('WEBSITE_MODE');
try {
    putenv('WEBSITE_MODE=multipage');
    $check(!WebsiteMode::isOnepager(), 'Onepager ist ohne Freigabe aktiv.');
    putenv('WEBSITE_MODE=invalid');
    $check(WebsiteMode::get() === 'multipage', 'Ungueltiger Modus ist nicht geschlossen.');
    putenv('WEBSITE_MODE=onepager');
    $check(WebsiteMode::isOnepager(), 'Kundenmodus wird nicht angewendet.');
    $initial = $service->snapshot();
    $check($service->publicPageIds() === [1,3,2], 'Default muss Start zuerst, Kontakt zuletzt und Rechtsseiten separat lassen.');
    $order = [1,3,4,5,6,7,2];
    $service->save($order, [3,2], $initial['revision']);
    $check($service->publicPageIds() === [1,3,2], 'Home-Pinning oder Speichern fehlgeschlagen.');
    $saved = $service->snapshot();
    $service->save([1,2,3,4,5,6,7], [2,4], $saved['revision']);
    $check($service->publicPageIds() === [1,2,4], 'Explizite Auswahl/Reihenfolge darf nicht durch Defaultregeln ueberschrieben werden.');
    $revision = $service->snapshot()['revision'];
    foreach ([
        [[1,2,3,4,5,6,7], [2], $initial['revision']],
        [[1,2,3,4,5,6,99], [2], $revision],
        [[1,2,3,4,5,6,7], [6], $revision],
        [[1,2,3,4,5,6,7], [7], $revision],
        [[1,2,3,4,5,6,7], [99], $revision],
        [[1,2,3,4,5,6,7], [2,2], $revision],
    ] as [$badOrder,$badSelected,$badRevision]) {
        $rejected = false;
        try { $service->save($badOrder, $badSelected, $badRevision); } catch (DomainException) { $rejected = true; }
        $check($rejected && $service->publicPageIds() === [1,2,4], 'Fehlerhafte Anfrage veraendert bestehende Auswahl.');
    }
    $pdo->exec("INSERT INTO pages(id,slug,title) VALUES (8,'neue-seite','Neue Seite')");
    $check(!in_array(8, $service->publicPageIds(), true), 'Neue Seite nach gespeicherter Auswahl wurde automatisch aufgenommen.');
    $pdo->exec('UPDATE pages SET is_deleted = 1 WHERE id = 4');
    $check($service->publicPageIds() === [1,2], 'Geloeschte Seite wird noch ausgegeben.');
    $pdo->exec("UPDATE pages SET status = 'draft' WHERE id = 2");
    $check($service->publicPageIds() === [1], 'Entwurf ist im oeffentlichen Onepager sichtbar.');
    echo "PASS: kundenspezifischer Modus; sichere Defaults; Auswahl/Reihenfolge; Home-Pinning; Kontakt frei sortierbar; neue Seiten ausgeschlossen; Entwuerfe/Redirects/Loeschungen; stale/foreign/duplicate POST abgewiesen.\n";
} finally {
    putenv($original === false ? 'WEBSITE_MODE' : 'WEBSITE_MODE=' . $original);
}
