<?php
namespace Lento\Logging;

use Lento\Logging\LoggerInterface;

class StdoutLogger implements LoggerInterface
{
    private $output;

    public function __construct(array $options = [])
    {
        if (!defined('STDOUT')) {
            define('STDOUT', fopen('php://stdout', 'w'));
        }
        $this->output = STDOUT;
        $this->allowedLevels = array_map('strtolower', $options['levels'] ?? LogLevel::LEVELS);
    }

    private function shouldLog(string $level): bool
    {
        return in_array(strtolower($level), $this->allowedLevels, true);
    }

    private function write(string $level, string $message): void
    {
        $timestamp = (new \DateTime('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d H:i:s');
        fwrite($this->output, "[$timestamp] [$level] $message\n");
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
