<?php
namespace Lento\Logging;

use Lento\Logging\LoggerInterface;

class FileLogger implements LoggerInterface
{
    private string $logFile;

    public function __construct(array $options)
    {
        $this->logFile = $options['path'] ?? throw new \InvalidArgumentException('FileLogger requires "path"');
        $this->allowedLevels = array_map('strtolower', $options['levels'] ?? LogLevel::LEVELS);

        $this->prepareFile();
    }

    private function shouldLog(string $level): bool
    {
        return in_array(strtolower($level), $this->allowedLevels, true);
    }

    private function prepareFile(): void
    {
        $dir = dirname($this->logFile);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }

        if (!is_writable($this->logFile)) {
            throw new \Exception("Log file is not writable: {$this->logFile}");
        }
    }

    private function write(string $level, string $message): void
    {
        $timestamp = (new \DateTime('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d H:i:s');
        $entry = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }

    public function log(string $level, string $message): void
    {
        if (!$this->shouldLog($level)) return;

        $this->write(strtoupper($level), $message);
    }

    public function emergency(string $message): void { $this->log('emergency', $message); }
    public function alert(string $message): void     { $this->log('alert', $message); }
    public function critical(string $message): void  { $this->log('critical', $message); }
    public function error(string $message): void     { $this->log('error', $message); }
    public function warning(string $message): void   { $this->log('warning', $message); }
    public function notice(string $message): void    { $this->log('notice', $message); }
    public function info(string $message): void      { $this->log('info', $message); }
    public function debug(string $message): void     { $this->log('debug', $message); }
}
