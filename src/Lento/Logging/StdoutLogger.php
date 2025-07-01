<?php

namespace Lento\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;
use Lento\Logging\LogLevel;

class StdoutLogger implements LoggerInterface
{
    private $output;
    private array $allowedLevels;

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

    private function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements["{{$key}}"] = is_scalar($value) ? $value : json_encode($value);
        }
        return strtr($message, $replacements);
    }

    public function log($level, $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $message = $this->interpolate($message, $context);
        $timestamp = (new \DateTime('now', new \DateTimeZone('Europe/Berlin')))
            ->format('Y-m-d H:i:s');

        fwrite($this->output, "[$timestamp] [" . strtoupper($level) . "] $message\n");
    }

    public function emergency($message, array $context = []): void
    {
        $this->log(PsrLogLevel::EMERGENCY, $message, $context);
    }
    public function alert($message, array $context = []): void
    {
        $this->log(PsrLogLevel::ALERT, $message, $context);
    }
    public function critical($message, array $context = []): void
    {
        $this->log(PsrLogLevel::CRITICAL, $message, $context);
    }
    public function error($message, array $context = []): void
    {
        $this->log(PsrLogLevel::ERROR, $message, $context);
    }
    public function warning($message, array $context = []): void
    {
        $this->log(PsrLogLevel::WARNING, $message, $context);
    }
    public function notice($message, array $context = []): void
    {
        $this->log(PsrLogLevel::NOTICE, $message, $context);
    }
    public function info($message, array $context = []): void
    {
        $this->log(PsrLogLevel::INFO, $message, $context);
    }
    public function debug($message, array $context = []): void
    {
        $this->log(PsrLogLevel::DEBUG, $message, $context);
    }
}
