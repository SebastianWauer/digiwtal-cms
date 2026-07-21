<?php
declare(strict_types=1);

$certDir = __DIR__ . '/certs';
$certFile = $certDir . '/localhost.crt';
$keyFile = $certDir . '/localhost.key';
$configFile = $certDir . '/openssl-san.cnf';

if (!is_dir($certDir) && !mkdir($certDir, 0700, true) && !is_dir($certDir)) {
    fwrite(STDERR, "Zertifikat-Verzeichnis konnte nicht erstellt werden.\n");
    exit(1);
}

$config = <<<CONF
[req]
default_bits = 2048
prompt = no
default_md = sha256
x509_extensions = v3_req
distinguished_name = dn

[dn]
C = DE
ST = Local
L = Local
O = DIGIWTAL
OU = Local Agent
CN = 127.0.0.1

[v3_req]
subjectAltName = @alt_names

[alt_names]
IP.1 = 127.0.0.1
DNS.1 = localhost
CONF;

file_put_contents($configFile, $config);

$cmd = 'openssl req -x509 -nodes -days 365 -newkey rsa:2048'
    . ' -keyout ' . escapeshellarg($keyFile)
    . ' -out ' . escapeshellarg($certFile)
    . ' -config ' . escapeshellarg($configFile);

passthru($cmd, $exitCode);
if ($exitCode !== 0) {
    fwrite(STDERR, "Zertifikat konnte nicht erzeugt werden.\n");
    exit($exitCode);
}

fwrite(STDOUT, "Zertifikat erzeugt:\n{$certFile}\n{$keyFile}\n");
fwrite(STDOUT, "Vertraue das Zertifikat einmal in deinem Betriebssystem/Browser, damit https://127.0.0.1:8765 ohne Warnung funktioniert.\n");
