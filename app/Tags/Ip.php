<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Forms\FormRequestContext;
use Statamic\Tags\Tags;

/**
 * Emulates Gravity Forms {ip} merge tag.
 *
 * Provides a Statamic tag that returns the IP address of the form submitter,
 * matching the behavior of Gravity Forms' {ip} merge tag.
 */
class Ip extends Tags
{
    /**
     * Displays the form submitter's IP address.
     *
     * Prefers the IP captured at submit time when a form-email render is in
     * progress (so it stays correct when a queue worker renders the email with no
     * submitter request), and falls back to live request state otherwise.
     *
     * Usage: {{ ip }}
     *
     * @return string The IP address of the form submitter, HTML-escaped for
     *                safe output in templates
     */
    public function index(): string
    {
        // Prefer the submitter IP captured at submit time during a form-email render
        $captured = FormRequestContext::active();
        if ($captured instanceof FormRequestContext) {
            return e($captured->ip);
        }

        // Initialize raw address from the server (may be absent in CLI)
        $remoteAddr = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';

        // Return escaped text so template output cannot break HTML context
        return e($remoteAddr);
    }
}
