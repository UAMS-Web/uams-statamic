<?php

declare(strict_types=1);

namespace App\Tags;

use App\Http\Middleware\SecurityHeaders;
use Statamic\Tags\Tags;

/**
 * Antlers tag exposing the per-request Content-Security-Policy nonce.
 *
 * Usage: nonce="{{ csp_nonce }}" on inline <style>/<script> blocks we
 * control. The value is generated and bound by App\Http\Middleware\
 * SecurityHeaders before the view renders, and the same value is injected
 * into the nonce-gated CSP directives, so the attribute and the policy
 * header always match.
 *
 * Returns an empty string when no nonce is bound (e.g. security headers
 * disabled), which renders a harmless empty nonce="" attribute.
 */
class CspNonce extends Tags
{
    /**
     * Resolve the current request's CSP nonce.
     *
     * @return string The per-request nonce, or empty string when unbound
     */
    public function index(): string
    {
        // Bail early when no nonce is bound for this request
        if (! app()->bound(SecurityHeaders::NONCE_KEY)) {
            return '';
        }

        $nonce = app(SecurityHeaders::NONCE_KEY);

        // Coerce the bound value to a string for the attribute
        return is_scalar($nonce) ? (string) $nonce : '';
    }
}
