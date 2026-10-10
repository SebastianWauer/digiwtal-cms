<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});

use App\PageBuilder\BlockRegistry;
use App\PageBuilder\BlockValidator;

$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$originalKey = getenv('PAGEBUILDER_CUSTOMER_KEY');
$originalAllowed = getenv('PAGEBUILDER_ALLOWED_BLOCKS');
try {
    putenv('PAGEBUILDER_CUSTOMER_KEY=');
    putenv('PAGEBUILDER_ALLOWED_BLOCKS=');
    $standard = BlockRegistry::definitions();
    $check(array_keys(BlockRegistry::allDefinitions()) === array_keys(\App\PageBuilder\BlockAccess::catalog()), 'Registry und Katalog unterscheiden sich.');
    $check(count($standard) === 18, 'Standardbestand wurde veraendert.');
    $check(!isset($standard['hero']['fields']['layout']), 'Standard-Hero enthaelt Kundenfelder.');
    $check(!isset($standard['columns']['fields']['col_6_title']), 'Standard-Kacheln enthalten Kundenfelder.');
    $check(!BlockRegistry::has('sport_voice_hero'), 'Kundenblock ohne Freigabe sichtbar.');

    $own = array_keys(array_filter(\App\PageBuilder\BlockAccess::catalog(), static fn($block) => $block['customer_key'] === 'sport-voice'));
    $check(count($own) === 9, 'Es muessen neun getrennte Sport-Voice-Bloecke existieren.');
    putenv('PAGEBUILDER_ALLOWED_BLOCKS=' . json_encode($own));
    putenv('PAGEBUILDER_CUSTOMER_KEY=anderer-kunde');
    $check(BlockRegistry::definitions() === [], 'Fremde Kundenbloecke wurden freigegeben.');
    putenv('PAGEBUILDER_CUSTOMER_KEY=sport-voice');
    $check(array_keys(BlockRegistry::definitions()) === $own, 'Whitelist wird nicht exakt angewendet.');
    $validator = new BlockValidator(new BlockRegistry());
    $blocks = array_map(static fn($type) => array_merge(BlockRegistry::defaults($type), ['type' => $type]), $own);
    $clean = json_decode($validator->validateJson(json_encode(['blocks' => $blocks])), true);
    $check(array_column($clean['blocks'], 'type') === $own, 'Kundenbloecke gehen beim Speichern verloren.');
    $check($clean['blocks'][0]['layout'] === 'cover', 'Kunden-Hero verliert Darstellung.');

    foreach (['[]', '{"hero":true}', 'invalid'] as $policy) {
        putenv('PAGEBUILDER_ALLOWED_BLOCKS=' . $policy);
        $check(BlockRegistry::definitions() === [], 'Leere/ungueltige Freigabe ist nicht geschlossen.');
    }
    putenv('PAGEBUILDER_ALLOWED_BLOCKS=["three_columns_layout"]');
    foreach ([
        ['type' => 'sport_voice_hero'],
        ['type' => 'three_columns_layout', 'columns' => [['blocks' => [['type' => 'sport_voice_hero']]]]],
        ['type' => 'three_columns_layout', 'left_blocks' => [['type' => 'hero']]],
        ['type' => 'three_columns_layout', 'data' => ['columns' => [['blocks' => [['type' => 'hero']]]]]],
    ] as $forbidden) {
        $rejected = false;
        try { $validator->validateJson(json_encode(['blocks' => [$forbidden]])); }
        catch (DomainException) { $rejected = true; }
        $check($rejected, 'Gesperrte Bloecke wurden gespeichert oder still entfernt.');
    }
    putenv('PAGEBUILDER_ALLOWED_BLOCKS=null');
    $check(array_keys(BlockRegistry::definitions()) === array_keys($standard), 'Bestandsfallback ist nicht auf Standardbloecke begrenzt.');
    $pipes = [];
    $process = proc_open([PHP_BINARY, $root . '/.github/scripts/render-env.php', 'cms'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Env-Renderer konnte nicht gestartet werden.');
    fwrite($pipes[0], json_encode(['frontend' => ['key' => 'sport-voice'], 'pagebuilder' => ['allowed_blocks' => $own]], JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    $env = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $check(proc_close($process) === 0, 'CI-Env-Renderer fehlgeschlagen: ' . $error);
    $directory = sys_get_temp_dir() . '/pagebuilder-env-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    try {
        file_put_contents($directory . '/.env', $env);
        \App\Core\Env::load($directory);
        $check(array_keys(BlockRegistry::definitions()) === $own, 'CI-Env und CMS-Env-Parser verlieren Freigaben.');
    } finally {
        unlink($directory . '/.env');
        rmdir($directory);
    }
    echo "PASS: Standardfelder unveraendert; neun Kundenbloecke; Whitelist; Kundentrennung; fail closed; verschachtelte Sperren ohne Datenverlust.\n";
} finally {
    putenv($originalKey === false ? 'PAGEBUILDER_CUSTOMER_KEY' : 'PAGEBUILDER_CUSTOMER_KEY=' . $originalKey);
    putenv($originalAllowed === false ? 'PAGEBUILDER_ALLOWED_BLOCKS' : 'PAGEBUILDER_ALLOWED_BLOCKS=' . $originalAllowed);
}
