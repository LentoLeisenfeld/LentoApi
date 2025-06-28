<?php

namespace Lento\Logging;

use Lento\Logging\LoggerInterface;

class Logger implements LoggerInterface
{
    /** @var LoggerInterface[] */
    private array $loggers;

    public function __construct(array $loggers)
    {
        foreach ($loggers as $logger) {
            if (!$logger instanceof LoggerInterface) {
                throw new \InvalidArgumentException('All loggers must implement LoggerInterface');
            }
        }
        $this->loggers = $loggers;
    }

    public function log(string $level, string $message): void
    {
        foreach ($this->loggers as $logger) {
            $logger->log($level, $message);
        }
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
