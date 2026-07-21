<?php
declare(strict_types=1);

/**
 * Lightweight PSR-3-compatible file logger.
 * Supports the standard log-level methods and placeholder interpolation.
 */
final class FileLogger
{
    /** @var array<string, self> */
    private static array $instances = [];

    public static function channel(string $name, ?string $path = null): self
    {
        $key = $name . '|' . (string)$path;
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($path ?? self::defaultPath($name));
        }
        return self::$instances[$key];
    }

    private static function defaultPath(string $name): string
    {
        $root = dirname(__DIR__);
        $dir = $root . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/' . preg_replace('/[^a-z0-9._-]+/i', '-', $name) . '.log';
    }

    private function __construct(private string $logFile) {}

    public function emergency(string|\Stringable $message, array $context = []): void { $this->log('emergency', $message, $context); }
    public function alert(string|\Stringable $message, array $context = []): void { $this->log('alert', $message, $context); }
    public function critical(string|\Stringable $message, array $context = []): void { $this->log('critical', $message, $context); }
    public function error(string|\Stringable $message, array $context = []): void { $this->log('error', $message, $context); }
    public function warning(string|\Stringable $message, array $context = []): void { $this->log('warning', $message, $context); }
    public function notice(string|\Stringable $message, array $context = []): void { $this->log('notice', $message, $context); }
    public function info(string|\Stringable $message, array $context = []): void { $this->log('info', $message, $context); }
    public function debug(string|\Stringable $message, array $context = []): void { $this->log('debug', $message, $context); }

    public function log(string $level, string|\Stringable $message, array $context = []): void
    {
        $msg = $this->interpolate((string)$message, $context);
        $line = '[' . gmdate('c') . '] ' . strtoupper($level) . ' ' . $msg . "\n";
        @file_put_contents($this->logFile, $line, FILE_APPEND);
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string)$val;
            } else {
                $replace['{' . $key . '}'] = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[complex]';
            }
        }
        return strtr($message, $replace);
    }
}
