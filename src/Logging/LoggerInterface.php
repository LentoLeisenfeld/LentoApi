<?php

namespace Lento\Logging;

interface LoggerInterface
{
    public function emergency(string $message): void;
    public function alert(string $message): void;
    public function critical(string $message): void;
    public function error(string $message): void;
    public function warning(string $message): void;
    public function notice(string $message): void;
    public function info(string $message): void;
    public function debug(string $message): void;
    public function log(string $level, string $message): void;
}