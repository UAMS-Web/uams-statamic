<?php

declare(strict_types=1);

namespace App\Tags;

use Illuminate\Support\Facades\Log as LogFacade;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers wrapper for Laravel's log facade.
 *
 * Usage: {{ log message="Something happened" level="info" }}
 */
class Log extends Tags
{
    /**
     * Valid PSR-3 log levels supported by Laravel's Log facade.
     *
     * @var list<string>
     */
    private const array LEVELS = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ];

    /**
     * Write a message to the application log.
     *
     * Parameters:
     *   - message (string): The message to log.
     *   - level (string, optional): Log level; defaults to `debug`. Unknown values
     *     fall back to `debug`.
     *
     * @return string Empty string (no template output).
     */
    public function index(): string
    {
        // Resolve message and level from tag parameters
        /** @var Parameters $params */
        $params = $this->params;
        $messageParam = $params->get(
            'message',
            ''
        );
        $message = is_scalar($messageParam) || $messageParam instanceof \Stringable
            ? (string) $messageParam
            : '';
        $levelParam = $params->get(
            'level',
            'debug'
        );
        $level = strtolower(
            is_scalar($levelParam) || $levelParam instanceof \Stringable
                ? (string) $levelParam
                : 'debug'
        );

        // Normalize level to a supported PSR-3 method name
        if (! in_array(
            $level,
            self::LEVELS,
            true
        )) {
            $level = 'debug';
        }

        // Dispatch to Log::{level}($message)
        LogFacade::{$level}($message);

        unset(
            $level,
            $message
        );

        return '';
    }
}
