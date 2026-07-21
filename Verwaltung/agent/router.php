<?php
declare(strict_types=1);

if (!class_exists('AgentEmbeddedResponse')) {
    class AgentEmbeddedResponse extends RuntimeException
    {
        public function __construct(
            public readonly int $status,
            public readonly array $headers,
            public readonly string $body
        ) {
            parent::__construct('embedded response');
        }
    }
}

if (!function_exists('agent_emit_header')) {
    function agent_emit_header(string $headerLine): void
    {
        if (defined('AGENT_EMBEDDED') && AGENT_EMBEDDED) {
            $pos = strpos($headerLine, ':');
            if ($pos === false) {
                return;
            }
            $name = trim(substr($headerLine, 0, $pos));
            $value = trim(substr($headerLine, $pos + 1));
            $GLOBALS['__agent_headers'][$name] = $value;
            return;
        }

        header($headerLine);
    }
}

agent_emit_header('Access-Control-Allow-Origin: *');
agent_emit_header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
agent_emit_header('Access-Control-Allow-Headers: Content-Type');
agent_emit_header('Access-Control-Allow-Private-Network: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (defined('AGENT_EMBEDDED') && AGENT_EMBEDDED) {
        throw new AgentEmbeddedResponse(204, $GLOBALS['__agent_headers'] ?? [], '');
    }

    http_response_code(204);
    exit;
}

if (!function_exists('json_response')) {
    function json_response(array $payload, int $status = 200): void
    {
        $body = (string)json_encode($payload, JSON_UNESCAPED_SLASHES);

        if (defined('AGENT_EMBEDDED') && AGENT_EMBEDDED) {
            $headers = $GLOBALS['__agent_headers'] ?? [];
            $headers['Content-Type'] = 'application/json';
            throw new AgentEmbeddedResponse($status, $headers, $body);
        }

        http_response_code($status);
        header('Content-Type: application/json');
        echo $body;
        exit;
    }
}

if (!function_exists('ignore_artifact')) {
    function ignore_artifact(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        $parts = explode('/', $normalized);
        $filename = end($parts);
        if ($filename === false) {
            return true;
        }

        $macFiles = ['.DS_Store', '.AppleDouble', '.LSOverride'];
        $macDirs = ['__MACOSX', '.Spotlight-V100', '.Trashes', '.fseventsd'];

        return in_array($filename, $macFiles, true)
            || str_starts_with($filename, '._')
            || count(array_intersect($parts, $macDirs)) > 0;
    }
}

if (!function_exists('collect_files')) {
    function collect_files(string $baseDir, array $ignore = [], array $keep = []): array
    {
        $result = [];
        $ignore = array_unique(array_merge(['.git', '.env', '.claude', 'node_modules', 'storage', 'upload.sh'], $ignore));
        $keep = array_map(static fn(string $item): string => str_replace('\\', '/', ltrim($item, '/')), $keep);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $absPath = $file->getPathname();
            $relPath = ltrim(str_replace($baseDir, '', $absPath), '/\\');
            $normalizedRelPath = str_replace('\\', '/', $relPath);
            $parts = explode('/', str_replace('\\', '/', $relPath));
            if (ignore_artifact($relPath)) {
                continue;
            }

            if (in_array($normalizedRelPath, $keep, true)) {
                $result[$absPath] = $relPath;
                continue;
            }

            $skip = false;
            foreach ($ignore as $ign) {
                if (in_array($ign, $parts, true)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $result[$absPath] = $relPath;
        }

        return $result;
    }
}

if (!function_exists('build_frontend_env')) {
    function build_frontend_env(array $payload): string
    {
        $healthCmsUrl = rtrim(trim((string)(($payload['frontend']['health_cms_url'] ?? ''))), '/');
        $canonicalBase = rtrim(trim((string)(($payload['frontend']['canonical_base'] ?? ''))), '/');
        $cmsBaseUrl = $healthCmsUrl !== '' ? $healthCmsUrl : $canonicalBase;
        if ($cmsBaseUrl === '') {
            throw new RuntimeException('Frontend-Deploy benötigt Health CMS URL oder canonical_base im Serverzugang.');
        }

        $cmsPath = trim((string)($payload['server']['server_path'] ?? '/CMS'));
        $cmsSegment = basename(str_replace('\\', '/', rtrim($cmsPath, '/')));
        if ($cmsSegment === '' || $cmsSegment === '.' || $cmsSegment === '/') {
            $cmsSegment = 'CMS';
        }

        if ($healthCmsUrl !== '') {
            $apiUrl = $healthCmsUrl . '/api.php/api/v1';
        } else {
            $apiUrl = $cmsBaseUrl . '/' . rawurlencode($cmsSegment) . '/api.php/api/v1';
        }

        $lines = [
            'CMS_API_URL=' . $apiUrl,
            'CMS_API_TOKEN=',
            'CMS_TIMEOUT=5',
            'CMS_CACHE_TTL=0',
        ];

        return implode("\n", $lines) . "\n";
    }
}

if (!function_exists('write_frontend_env')) {
    function write_frontend_env(string $frontendDir, array $payload): string
    {
        $envPath = rtrim($frontendDir, '/\\') . '/.env';
        if (@file_put_contents($envPath, build_frontend_env($payload)) === false) {
            throw new RuntimeException('Frontend-.env konnte lokal nicht erzeugt werden.');
        }

        return $envPath;
    }
}

if (!function_exists('quote_sftp')) {
    function quote_sftp(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}

if (!function_exists('detect_mode')) {
    function detect_mode(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'winscp' : 'shell_sftp';
    }
}

if (!function_exists('find_winscp_path')) {
    function find_winscp_path(): ?string
    {
        $candidates = [
            getenv('WINSCP_PATH') ?: '',
            'C:\\Program Files\\WinSCP\\WinSCP.com',
            'C:\\Program Files (x86)\\WinSCP\\WinSCP.com',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('run_shell_sftp')) {
    function run_shell_sftp(array $server, array $files, string $remoteBasePath): array
    {
        $sftpPath = trim((string)@shell_exec('command -v sftp 2>/dev/null'));
        $sshpassPath = trim((string)@shell_exec('command -v sshpass 2>/dev/null'));
        if ($sftpPath === '' || $sshpassPath === '') {
            return [false, 'sftp oder sshpass nicht gefunden.'];
        }

        $commands = [];
        $dirs = [];
        foreach ($files as $local => $rel) {
            $remote = rtrim($remoteBasePath, '/') . '/' . ltrim(str_replace('\\', '/', $rel), '/');
            $dir = dirname($remote);
            while ($dir !== '.' && $dir !== '' && $dir !== '/') {
                $dirs[$dir] = true;
                $dir = dirname($dir);
            }
        }

        uksort($dirs, static fn(string $a, string $b): int => strlen($a) <=> strlen($b));
        foreach (array_keys($dirs) as $dir) {
            $commands[] = '-mkdir ' . quote_sftp($dir);
        }
        foreach ($files as $local => $rel) {
            $remote = rtrim($remoteBasePath, '/') . '/' . ltrim(str_replace('\\', '/', $rel), '/');
            $commands[] = 'put ' . quote_sftp($local) . ' ' . quote_sftp($remote);
        }

        $batchFile = tempnam(sys_get_temp_dir(), 'agent_sftp_');
        if ($batchFile === false) {
            return [false, 'Temporäre SFTP-Batchdatei konnte nicht erstellt werden.'];
        }

        try {
            file_put_contents($batchFile, implode("\n", $commands) . "\n");
            $cmd = escapeshellarg($sshpassPath)
                . ' -e '
                . escapeshellarg($sftpPath)
                . ' -oBatchMode=no -oStrictHostKeyChecking=no -oUserKnownHostsFile=/dev/null'
                . ' -P ' . (int)$server['port']
                . ' -b ' . escapeshellarg($batchFile)
                . ' ' . escapeshellarg($server['username'] . '@' . $server['host']);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = @proc_open($cmd, $descriptors, $pipes, null, ['SSHPASS' => (string)$server['password']]);
            if (!is_resource($process)) {
                return [false, 'Shell-SFTP konnte nicht gestartet werden.'];
            }

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            return [$exitCode === 0, trim($stderr !== '' ? $stderr : $stdout)];
        } finally {
            @unlink($batchFile);
        }
    }
}

if (!function_exists('run_winscp')) {
    function run_winscp(array $server, array $files, string $remoteBasePath): array
    {
        $winscpPath = find_winscp_path();
        if ($winscpPath === null) {
            return [false, 'WinSCP.com nicht gefunden. Setze WINSCP_PATH oder installiere WinSCP.'];
        }

        $commands = [
            'option batch abort',
            'option confirm off',
            'open sftp://' . rawurlencode((string)$server['username']) . ':' . rawurlencode((string)$server['password'])
                . '@' . $server['host'] . ':' . (int)$server['port'] . ' -hostkey=*',
        ];

        $dirs = [];
        foreach ($files as $local => $rel) {
            $remote = rtrim($remoteBasePath, '/') . '/' . ltrim(str_replace('\\', '/', $rel), '/');
            $dir = dirname($remote);
            while ($dir !== '.' && $dir !== '' && $dir !== '/') {
                $dirs[$dir] = true;
                $dir = dirname($dir);
            }
        }
        uksort($dirs, static fn(string $a, string $b): int => strlen($a) <=> strlen($b));
        foreach (array_keys($dirs) as $dir) {
            $commands[] = 'mkdir ' . quote_sftp($dir);
        }
        foreach ($files as $local => $rel) {
            $remote = rtrim($remoteBasePath, '/') . '/' . ltrim(str_replace('\\', '/', $rel), '/');
            $commands[] = 'put ' . quote_sftp($local) . ' ' . quote_sftp($remote);
        }
        $commands[] = 'exit';

        $scriptFile = tempnam(sys_get_temp_dir(), 'agent_winscp_');
        if ($scriptFile === false) {
            return [false, 'Temporäres WinSCP-Script konnte nicht erstellt werden.'];
        }

        try {
            file_put_contents($scriptFile, implode("\n", $commands) . "\n");
            $cmd = '"' . $winscpPath . '" /ini=nul /script="' . $scriptFile . '"';
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = @proc_open($cmd, $descriptors, $pipes);
            if (!is_resource($process)) {
                return [false, 'WinSCP konnte nicht gestartet werden.'];
            }

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            return [$exitCode === 0, trim($stderr !== '' ? $stderr : $stdout)];
        } finally {
            @unlink($scriptFile);
        }
    }
}

if (!function_exists('extract_archive')) {
    function extract_archive(string $tmpPath): string
    {
        $stageRoot = sys_get_temp_dir() . '/agent_frontend_' . bin2hex(random_bytes(8));
        if (!@mkdir($stageRoot, 0700, true) && !is_dir($stageRoot)) {
            throw new RuntimeException('Temporäres Frontend-Verzeichnis konnte nicht erstellt werden.');
        }

        $gzPath = $stageRoot . '/frontend.tar.gz';
        if (!@copy($tmpPath, $gzPath)) {
            throw new RuntimeException('Frontend-Archiv konnte nicht zwischengespeichert werden.');
        }

        $extractDir = $stageRoot . '/extract';
        @mkdir($extractDir, 0700, true);

        $gzArchive = new PharData($gzPath);
        $tarPath = substr($gzPath, 0, -3);
        if (!is_file($tarPath)) {
            $gzArchive->decompress();
        }

        $tarArchive = new PharData($tarPath);
        $tarArchive->extractTo($extractDir, null, true);

        return $extractDir;
    }
}

if (!function_exists('cleanup_dir')) {
    function cleanup_dir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/health' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response([
        'ok' => true,
        'mode' => detect_mode(),
        'php' => PHP_VERSION,
        'os' => PHP_OS_FAMILY,
    ]);
}

if ($path !== '/deploy' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Not found'], 404);
}

$payloadJson = (string)($_POST['payload'] ?? '');
if ($payloadJson === '') {
    json_response(['ok' => false, 'error' => 'Payload fehlt'], 400);
}

$payload = json_decode($payloadJson, true);
if (!is_array($payload) || !is_array($payload['server'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Payload ungültig'], 400);
}

$server = $payload['server'];
$type = (string)($payload['type'] ?? 'cms');
if (!in_array($type, ['cms', 'frontend', 'combined'], true)) {
    json_response(['ok' => false, 'error' => 'Deploy-Typ ungültig'], 400);
}
if (($server['protocol'] ?? 'sftp') !== 'sftp') {
    json_response(['ok' => false, 'error' => 'Der lokale Agent unterstützt aktuell nur SFTP.'], 400);
}

$operations = [];
$cleanup = [];

try {
    $debugInfo = [];

    if (in_array($type, ['cms', 'combined'], true)) {
        $cmsDir = dirname(__DIR__, 2) . '/CMS';
        if (!is_dir($cmsDir)) {
            throw new RuntimeException('Lokaler CMS-Ordner nicht gefunden: ' . $cmsDir);
        }
        $operations[] = [
            'label' => 'cms',
            'files' => collect_files($cmsDir),
            'remote_path' => (string)($server['server_path'] ?? '/CMS'),
        ];
    }

    if (in_array($type, ['frontend', 'combined'], true)) {
        if (!isset($_FILES['frontend_archive']) || (int)($_FILES['frontend_archive']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Frontend-Archiv fehlt.');
        }

        $extractDir = extract_archive((string)$_FILES['frontend_archive']['tmp_name']);
        $cleanup[] = dirname($extractDir);
        $envPath = write_frontend_env($extractDir, $payload);
        $envContent = build_frontend_env($payload);
        $debugInfo[] = 'Frontend .env lokal erzeugt: ' . $envPath;
        $debugInfo[] = 'Frontend .env Inhalt: ' . str_replace("\n", ' | ', trim($envContent));
        $operations[] = [
            'label' => 'frontend',
            'files' => collect_files($extractDir, [], ['.env']),
            'remote_path' => (string)($server['html_path'] ?? '/Frontend'),
        ];

        $frontendOperationIndex = array_key_last($operations);
        if ($frontendOperationIndex !== null) {
            $envIncluded = false;
            foreach ($operations[$frontendOperationIndex]['files'] as $relPath) {
                if (str_replace('\\', '/', (string)$relPath) === '.env') {
                    $envIncluded = true;
                    break;
                }
            }
            $debugInfo[] = 'Frontend .env in Upload-Liste: ' . ($envIncluded ? 'ja' : 'nein');
        }
    }

    if ($operations === []) {
        throw new RuntimeException('Keine Deploy-Operationen vorhanden.');
    }

    $results = [];
    foreach ($operations as $operation) {
        $mode = detect_mode();
        [$ok, $message] = $mode === 'winscp'
            ? run_winscp($server, $operation['files'], (string)$operation['remote_path'])
            : run_shell_sftp($server, $operation['files'], (string)$operation['remote_path']);

        if (!$ok) {
            $debugSuffix = $debugInfo !== [] ? ' | ' . implode(' | ', $debugInfo) : '';
            throw new RuntimeException(strtoupper((string)$operation['label']) . '-Upload fehlgeschlagen: ' . $message . $debugSuffix);
        }

        $results[] = strtoupper((string)$operation['label']) . ': ' . count($operation['files']) . ' Dateien übertragen';
    }

    if ($debugInfo !== []) {
        $results[] = implode(' | ', $debugInfo);
    }

    json_response([
        'ok' => true,
        'message' => implode(' | ', $results),
    ]);
} catch (AgentEmbeddedResponse $response) {
    throw $response;
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 500);
} finally {
    foreach ($cleanup as $dir) {
        cleanup_dir((string)$dir);
    }
}
