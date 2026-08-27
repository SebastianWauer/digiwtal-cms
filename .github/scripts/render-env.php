<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$type = (string)($argv[1] ?? '');
$source = (string)($argv[2] ?? 'php://stdin');
if (!in_array($type, ['cms', 'frontend'], true)) {
    fwrite(STDERR, "Usage: php render-env.php <cms|frontend> [target.json]\n");
    exit(1);
}

try {
    $json = file_get_contents($source);
    if (!is_string($json)) {
        throw new RuntimeException('Konfiguration konnte nicht gelesen werden.');
    }
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('Konfiguration ist kein JSON-Objekt.');
    }

    $line = static function (string $key, mixed $value): string {
        $value = (string)$value;
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException($key . ' enthaelt einen ungueltigen Zeilenumbruch.');
        }
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        return $key . '="' . $escaped . '"';
    };

    if ($type === 'cms') {
        $values = [
            'APP_ENV' => 'production',
            'DB_HOST' => $data['db']['host'] ?? '',
            'DB_PORT' => $data['db']['port'] ?? 3306,
            'DB_NAME' => $data['db']['name'] ?? '',
            'DB_USER' => $data['db']['user'] ?? '',
            'DB_PASS' => $data['db']['password'] ?? '',
            'MIGRATION_TOKEN' => $data['tokens']['migration'] ?? '',
            'HEALTH_TOKEN' => $data['tokens']['health'] ?? '',
            'DEPLOY_TOKEN' => $data['tokens']['deploy'] ?? '',
            'SETUP_TOKEN' => $data['tokens']['setup'] ?? '',
            'CMS_API_TOKEN' => $data['tokens']['api'] ?? '',
            'UPDATES_AVAILABLE_UNTIL' => $data['customer']['updates_available_until'] ?? 'unlimited',
            'CMS_BASE_PATH' => $data['urls']['base_path'] ?? '',
            'FRONTEND_SOURCE_DIR' => $data['ssh']['frontend_path'] ?? '',
            'FRONTEND_BASE_URL' => $data['urls']['frontend'] ?? '',
            'SUPPORT_URL' => $data['support']['url'] ?? '',
            'SUPPORT_TOKEN' => $data['support']['token'] ?? '',
        ];
    } else {
        $cmsUrl = rtrim((string)($data['urls']['cms'] ?? ''), '/');
        $values = [
            'CMS_API_URL' => $cmsUrl . '/api.php/api/v1',
            'CMS_API_TOKEN' => $data['tokens']['api'] ?? '',
            'CMS_TIMEOUT' => '5',
            'CMS_CACHE_TTL' => '300',
            'FRONTEND_BASE_URL' => $data['urls']['frontend'] ?? '',
            'CMS_SITEMAP_URL' => $cmsUrl . '/sitemap.xml',
        ];
    }

    foreach ($values as $key => $value) {
        echo $line($key, $value), "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Env-Konfiguration ungueltig: " . $e->getMessage() . "\n");
    exit(1);
}

