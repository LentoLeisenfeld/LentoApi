<?php

namespace Lento\Services;

class LoggerService
{
/*if (!defined('STDOUT')) {
    define('STDOUT', fopen('php://stdout', 'w'));
}*/
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;

        // Ensure log directory exists
        $dir = dirname($this->logFile);

        // Ensure the directory exists
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // Ensure the file exists and is writable
        if (!file_exists($this->logFile)) {
            // Attempt to create the file
            if (file_put_contents($this->logFile, '') === false) {
                throw new \Exception("Failed to create log file: {$this->logFile}");
            }
        }
    }

    public function info(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    public function warning(string $message): void
    {
        $this->writeLog('WARNING', $message);
    }

    public function error(string $message): void
    {
        $this->writeLog('ERROR', $message);
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }
}
