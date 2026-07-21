<?php
declare(strict_types=1);

const AGENT_CERT_FILE = __DIR__ . '/certs/localhost.crt';
const AGENT_KEY_FILE = __DIR__ . '/certs/localhost.key';
const AGENT_HOST = '127.0.0.1';
const AGENT_PORT = 8765;

if (!is_file(AGENT_CERT_FILE) || !is_file(AGENT_KEY_FILE)) {
    fwrite(STDERR, "Lokales HTTPS-Zertifikat fehlt. Erzeuge es zuerst mit:\n");
    fwrite(STDERR, "php Verwaltung/agent/generate_cert.php\n");
    exit(1);
}

$context = stream_context_create([
    'ssl' => [
        'local_cert' => AGENT_CERT_FILE,
        'local_pk' => AGENT_KEY_FILE,
        'allow_self_signed' => true,
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);

$server = @stream_socket_server(
    'tls://' . AGENT_HOST . ':' . AGENT_PORT,
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context
);

if ($server === false) {
    fwrite(STDERR, "HTTPS-Agent konnte nicht starten: {$errstr} ({$errno})\n");
    exit(1);
}

fwrite(STDOUT, "Lokaler HTTPS-Agent läuft auf https://" . AGENT_HOST . ':' . AGENT_PORT . "\n");

while (true) {
    $client = @stream_socket_accept($server, 5);
    if ($client === false) {
        continue;
    }

    handle_client($client);
    fclose($client);
}

function handle_client($client): void
{
    stream_set_timeout($client, 5);

    $requestLine = fgets($client);
    if ($requestLine === false) {
        return;
    }

    $requestLine = rtrim($requestLine, "\r\n");
    if ($requestLine === '') {
        return;
    }

    $parts = explode(' ', $requestLine, 3);
    $method = $parts[0] ?? 'GET';
    $uri = $parts[1] ?? '/';

    $headers = [];
    while (($line = fgets($client)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($line === '') {
            break;
        }
        $pos = strpos($line, ':');
        if ($pos === false) {
            continue;
        }
        $name = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        $headers[strtolower($name)] = $value;
    }

    $body = '';
    $contentLength = (int)($headers['content-length'] ?? 0);
    while (strlen($body) < $contentLength) {
        $chunk = fread($client, $contentLength - strlen($body));
        if ($chunk === '' || $chunk === false) {
            break;
        }
        $body .= $chunk;
    }

    [$status, $responseHeaders, $responseBody] = dispatch_request($method, $uri, $headers, $body);
    write_response($client, $status, $responseHeaders, $responseBody);
}

function dispatch_request(string $method, string $uri, array $headers, string $body): array
{
    $parts = parse_url($uri);
    $path = (string)($parts['path'] ?? '/');
    $query = (string)($parts['query'] ?? '');

    $_GET = [];
    parse_str($query, $_GET);
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_REQUEST = [];
    $_SERVER = [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $uri,
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'REMOTE_ADDR' => '127.0.0.1',
    ];

    foreach ($headers as $name => $value) {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $_SERVER[$key] = $value;
    }

    if (($headers['content-type'] ?? '') !== '') {
        $_SERVER['CONTENT_TYPE'] = $headers['content-type'];
    }
    if (($headers['content-length'] ?? '') !== '') {
        $_SERVER['CONTENT_LENGTH'] = $headers['content-length'];
    }

    $tmpFiles = [];
    $GLOBALS['__agent_headers'] = [];

    try {
        if (!defined('AGENT_EMBEDDED')) {
            define('AGENT_EMBEDDED', true);
        }

        $contentType = (string)($headers['content-type'] ?? '');
        if (str_starts_with($contentType, 'multipart/form-data')) {
            parse_multipart_body($body, $contentType, $tmpFiles);
        } elseif (str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($body, $_POST);
        }

        $_REQUEST = array_merge($_GET, $_POST);

        ob_start();
        include __DIR__ . '/router.php';
        $output = ob_get_clean();

        $responseHeaders = $GLOBALS['__agent_headers'] ?? [];
        if (!isset($responseHeaders['Content-Type'])) {
            $responseHeaders['Content-Type'] = 'application/json';
        }

        return [200, $responseHeaders, (string)$output];
    } catch (AgentEmbeddedResponse $response) {
        return [$response->status, $response->headers, $response->body];
    } catch (Throwable $e) {
        return [
            500,
            array_merge($GLOBALS['__agent_headers'] ?? [], ['Content-Type' => 'application/json']),
            (string)json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES),
        ];
    } finally {
        foreach ($tmpFiles as $tmpFile) {
            if (is_string($tmpFile) && is_file($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }
}

function parse_multipart_body(string $body, string $contentType, array &$tmpFiles): void
{
    if (!preg_match('/boundary=(?:"([^"]+)"|([^;]+))/i', $contentType, $matches)) {
        throw new RuntimeException('Multipart-Boundary fehlt.');
    }

    $boundary = $matches[1] !== '' ? $matches[1] : $matches[2];
    $delimiter = '--' . $boundary;
    $parts = explode($delimiter, $body);

    foreach ($parts as $part) {
        $part = ltrim($part, "\r\n");
        $part = rtrim($part, "\r\n");
        if ($part === '' || $part === '--') {
            continue;
        }

        $segments = explode("\r\n\r\n", $part, 2);
        if (count($segments) !== 2) {
            continue;
        }

        [$rawHeaders, $content] = $segments;
        $content = preg_replace("/\r\n$/", '', $content) ?? $content;

        $disposition = '';
        $partContentType = 'application/octet-stream';
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (stripos($line, 'Content-Disposition:') === 0) {
                $disposition = trim(substr($line, 20));
            } elseif (stripos($line, 'Content-Type:') === 0) {
                $partContentType = trim(substr($line, 13));
            }
        }

        if (!preg_match('/name="([^"]+)"/', $disposition, $nameMatch)) {
            continue;
        }
        $fieldName = $nameMatch[1];

        if (preg_match('/filename="([^"]*)"/', $disposition, $fileMatch)) {
            $filename = $fileMatch[1];
            $tmpPath = tempnam(sys_get_temp_dir(), 'agent_upload_');
            if ($tmpPath === false || file_put_contents($tmpPath, $content) === false) {
                throw new RuntimeException('Temporäre Upload-Datei konnte nicht geschrieben werden.');
            }
            $tmpFiles[] = $tmpPath;

            $_FILES[$fieldName] = [
                'name' => $filename,
                'type' => $partContentType,
                'tmp_name' => $tmpPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($content),
            ];
            continue;
        }

        $_POST[$fieldName] = $content;
    }
}

function write_response($client, int $status, array $headers, string $body): void
{
    $statusText = match ($status) {
        200 => 'OK',
        204 => 'No Content',
        400 => 'Bad Request',
        404 => 'Not Found',
        500 => 'Internal Server Error',
        default => 'OK',
    };

    $headers['Content-Length'] = (string)strlen($body);
    $headers['Connection'] = 'close';

    fwrite($client, "HTTP/1.1 {$status} {$statusText}\r\n");
    foreach ($headers as $name => $value) {
        fwrite($client, $name . ': ' . $value . "\r\n");
    }
    fwrite($client, "\r\n");
    fwrite($client, $body);
}
