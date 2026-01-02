<?php

namespace Kotchasan;

/**
 * Kotchasan Logger Class
 *
 * This class provides a simple logging interface for Kotchasan applications.
 * It supports different log levels and can be extended to use various logging backends.
 *
 * @package Kotchasan
 */
class Logger
{
    /**
     * Singleton instance
     *
     * @var \Kotchasan\Logger\LoggerInterface
     */
    private static $instance = null;

    /**
     * Create and return a Logger instance
     *
     * @return \Kotchasan\Logger\LoggerInterface
     */
    public static function create()
    {
        if (null === self::$instance) {
            // Create a file logger by default
            self::$instance = new \Kotchasan\Logger\FileLogger();

            // Enable debug mode based on DEBUG constant
            if (defined('DEBUG') && DEBUG > 0) {
                self::$instance->setDebugEnabled(true);
            }
        }
        return self::$instance;
    }

    /**
     * Log a debug message
     *
     * @param string $message The message to log
     * @param array $context Additional context information
     */
    public static function debug($message, $context = [])
    {
        self::create()->debug($message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message The message to log
     * @param array $context Additional context information
     */
    public static function info($message, $context = [])
    {
        self::create()->info($message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message The message to log
     * @param array $context Additional context information
     */
    public static function warning($message, $context = [])
    {
        self::create()->warning($message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message The message to log
     * @param array $context Additional context information
     */
    public static function error($message, $context = [])
    {
        self::create()->error($message, $context);
    }

    /**
     * Log a security event
     *
     * @param string $event The security event name
     * @param array $context Additional context information (IP, user_agent, etc.)
     */
    public static function security($event, $context = [])
    {
        $message = "Security Event: {$event}";

        // Add timestamp and format context for better readability
        $formattedContext = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'event_type' => $event
        ], $context);

        self::create()->warning($message, $formattedContext);
    }
}
