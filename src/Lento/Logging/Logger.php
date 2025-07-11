<?php

namespace Lento\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\LogLevel;
use Lento\Logging\LoggerOptions;

/**
 * Undocumented class
 */
class Logger
{
    /**
     * Each entry: ['logger'=>LoggerInterface, 'levels'=>array (assoc), 'name'=>string|null]
     *
     * @var array
     */
    private static array $loggers = [];

    /**
     * Add one or more loggers (LoggerInterface), with flexible options:
     * - options can be LoggerOptions, string (level), array (levels), or assoc array.
     *
     * @param [type] $logger
     * @param [type] $options
     * @return void
     */
    public static function add($logger, $options = null): void
    {
        $loggers = is_array($logger) ? $logger : [$logger];

        // Normalize options to LoggerOptions
        if ($options instanceof LoggerOptions) {
            $opts = $options;
        } elseif (is_string($options)) {
            $opts = new LoggerOptions([$options]);
        } elseif (is_array($options) && array_keys($options) === range(0, count($options) - 1)) {
            // Numeric array = list of levels
            $opts = new LoggerOptions($options);
        } elseif (is_array($options)) {
            // Assoc array
            $levels = $options['levels'] ?? [];
            $name = $options['name'] ?? null;
            $opts = new LoggerOptions($levels, $name);
        } else {
            $opts = new LoggerOptions();
        }

        // Convert levels to associative map for O(1) lookup
        $levelMap = [];
        foreach (
            $opts->levels ?: [
                LogLevel::EMERGENCY,
                LogLevel::ALERT,
                LogLevel::CRITICAL,
                LogLevel::ERROR,
                LogLevel::WARNING,
                LogLevel::NOTICE,
                LogLevel::INFO,
                LogLevel::DEBUG
            ] as $lvl
        ) {
            $levelMap[$lvl] = true;
        }

        foreach ($loggers as $lg) {
            if ($lg instanceof LoggerInterface) {
                self::$loggers[] = [
                    'logger' => $lg,
                    'levels' => $levelMap,
                    'name' => $opts->name,
                ];
            }
        }
    }

    /**
     * Add a Monolog logger for a channel and handler, with level filtering.
     * @param string $channel
     * @param mixed $handler
     * @param string[] $levels Accept only these log levels for this logger (default: all)
     */
    public static function addMono(string $channel, $handler, array $levels = []): void
    {
        if (class_exists('\Monolog\Logger')) {
            $logger = new \Monolog\Logger($channel);
            if ($handler instanceof \Monolog\Handler\HandlerInterface) {
                $logger->pushHandler($handler);
            }
            self::add($logger, new LoggerOptions($levels, $channel));
        }
    }

    /**
     * Return all registered loggers. If none, use NullLogger (accept all).
     * @return array
     */
    private static function getLoggers(): array
    {
        if (empty(self::$loggers)) {
            return [
                [
                    'logger' => new NullLogger(),
                    'levels' => [
                        LogLevel::EMERGENCY => true,
                        LogLevel::ALERT => true,
                        LogLevel::CRITICAL => true,
                        LogLevel::ERROR => true,
                        LogLevel::WARNING => true,
                        LogLevel::NOTICE => true,
                        LogLevel::INFO => true,
                        LogLevel::DEBUG => true,
                    ],
                    'name' => null
                ]
            ];
        }
        return self::$loggers;
    }

    /**
     * Generic log dispatch to all loggers accepting this level
     *
     * @param [type] $level
     * @param [type] $message
     * @param array $context
     * @return void
     */
    public static function log($level, $message, array $context = []): void
    {
        foreach (self::getLoggers() as $entry) {
            if (isset($entry['levels'][$level])) {
                $entry['logger']->log($level, $message, $context);
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param [type] $message
     * @param array $context
     * @return void
     */
    public static function emergency($message, array $context = []): void
    {
        self::log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param [type] $message
     * @param array $context
     * @return void
     */
    public static function alert($message, array $context = []): void
    {
        self::log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param [type] $message
     * @param array $context
     * @return void
     */
    public static function critical($message, array $context = []): void
    {
        self::log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param [type] $message
     * @param array $context
     * @return void
     */
    public static function error($message, array $context = []): void
    {
        self::log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param [type] $message
     * @param array $context
     * @return void
     */
    public static function warning($message, array $context = []): void
    {
        self::log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param [type] $message
     * @param array $context
     * @return void
     */
    public static function notice($message, array $context = []): void
    {
        self::log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param [type] $message
     * @param array $context
     * @return void
     */
    public static function info($message, array $context = []): void
    {
        self::log(LogLevel::INFO, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param [type] $message
     * @param array $context
     * @return void
     */
    public static function debug($message, array $context = []): void
    {
        self::log(LogLevel::DEBUG, $message, $context);
    }


    /**
     * Remove all registered loggers
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$loggers = [];
    }
}
