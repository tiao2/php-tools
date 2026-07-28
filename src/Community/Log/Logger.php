<?php

declare(strict_types=1);

namespace PhpTools\Community\Log;

class Logger
{
    private string $logDir;
    private string $logFile;
    private string $channel;

    public function __construct(string $channel = 'app', ?string $logDir = null)
    {
        $this->channel = $channel;
        $this->logDir = $logDir ?? ($_ENV['LOG_DIR'] ?? __DIR__ . '/../../logs/');
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        $this->logFile = $this->logDir . $channel . '.log';
    }

    /**
     * Log a message at a given level.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $date = date('Y-m-d H:i:s');
        $contextStr = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        $line = "[{$date}] {$this->channel}.{$level}: {$message}{$contextStr}\n";
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public function emergency(string $message, array $context = []): void { $this->log('EMERGENCY', $message, $context); }
    public function alert(string $message, array $context = []): void     { $this->log('ALERT', $message, $context); }
    public function critical(string $message, array $context = []): void  { $this->log('CRITICAL', $message, $context); }
    public function error(string $message, array $context = []): void     { $this->log('ERROR', $message, $context); }
    public function warning(string $message, array $context = []): void   { $this->log('WARNING', $message, $context); }
    public function notice(string $message, array $context = []): void    { $this->log('NOTICE', $message, $context); }
    public function info(string $message, array $context = []): void      { $this->log('INFO', $message, $context); }
    public function debug(string $message, array $context = []): void     { $this->log('DEBUG', $message, $context); }
}