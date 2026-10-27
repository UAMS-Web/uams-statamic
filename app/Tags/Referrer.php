<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Forms\FormRequestContext;
use Statamic\Tags\Tags;

/**
 * Emulates Gravity Forms {referrer} merge tag.
 *
 * Provides a Statamic tag that returns the referrer page URL, matching the behavior
 * of Gravity Forms' {referrer} merge tag.
 */
class Referrer extends Tags
{
    /**
     * Displays the address of the page which referred the user agent to the current
     * page.
     *
     * Prefers the referrer captured at submit time when a form-email render is in
     * progress (so it stays correct when a queue worker renders the email with no
     * submitter request), and falls back to live request state otherwise.
     *
     * Usage: {{ referrer }}
     *
     * @return string The referrer page URL, HTML-escaped for safe output in
     *                templates
     */
    public function index(): string
    {
        // Prefer the submitter referrer captured at submit time during a form-email render
        $captured = FormRequestContext::active();
        if ($captured instanceof FormRequestContext) {
            return e($captured->referrer);
        }

        // Initialize raw referrer from the request (client-controlled header)
        $rawReferrer = $_SERVER['HTTP_REFERER'] ?? '';
        $referrer = is_string($rawReferrer) ? $rawReferrer : '';

        // Build HTML-safe output for the template
        $result = e($referrer);

        unset($referrer);

        return $result;
    }
}
