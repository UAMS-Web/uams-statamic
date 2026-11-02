<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that applies configurable HTTP security headers to responses.
 *
 * Reads config/security-headers.php for baseline headers, optional HSTS,
 * and Content-Security-Policy (report-only or enforce). Generates a single
 * per-request CSP nonce and exposes it to templates so inline <style>/<script>
 * we control can be allow-listed without 'unsafe-inline'.
 */
class SecurityHeaders
{
    /**
     * Container binding key holding the current request's CSP nonce.
     */
    public const string NONCE_KEY = 'security-headers.csp-nonce';

    /**
     * Request attribute listing the allowances this response takes up.
     */
    private const string ALLOWANCES_KEY = 'security-headers.csp-allowances';

    /**
     * The allowance for Google's search results.
     */
    public const string GOOGLE_SEARCH = 'google_search';

    /**
     * The per-request CSP nonce, generated before the response renders.
     */
    private ?string $nonce = null;

    /**
     * Handle an incoming request and apply security headers to the response.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware in the chain
     * @return Response The response with security headers applied
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        // Check if security headers are disabled globally
        $enabled = (bool) config(
            'security-headers.enabled',
            true
        );

        // Generate and expose the per-request nonce before the view renders
        // so inline <style>/<script> we control can carry nonce="...".
        if ($enabled) {
            $this->shareNonce();
        }

        // Delegate to the rest of the stack and capture the response
        /** @var Response $response */
        $response = $next($request);

        // Skip header application when disabled globally
        if (! $enabled) {
            return $response;
        }

        // Apply configured baseline security headers
        $this->applyBaseline($response);

        // Apply Strict-Transport-Security when appropriate
        $this->applyHsts($request, $response);

        // Apply Content-Security-Policy when enabled and not excluded
        $this->applyCsp($request, $response);

        return $response;
    }

    /**
     * Add an entry of `security-headers.csp.allowances` to this response's policy.
     *
     * For a third-party script only some renders need, so the policy of every
     * other page stays as narrow as the configured directives.
     *
     * @param  string  $allowance  The entry's name.
     */
    public static function allowOnThisResponse(string $allowance): void
    {
        $attributes = request()->attributes;

        /** @var list<string> $allowances */
        $allowances = $attributes->get(self::ALLOWANCES_KEY, []);
        $allowances[] = $allowance;

        $attributes->set(self::ALLOWANCES_KEY, $allowances);
    }

    /**
     * Generate a per-request CSP nonce and expose it to the container and
     * the view layer.
     *
     * The same value is later injected into the nonce-gated CSP directives
     * (see config security-headers.csp.nonce_directives) so the header and
     * the rendered nonce="..." attributes always match. The CspNonce Antlers
     * tag reads it from the container; Blade reads it as {{ $csp_nonce }}.
     */
    private function shareNonce(): void
    {
        // Generate a fresh base64 nonce from cryptographically secure bytes
        $this->nonce = base64_encode(random_bytes(16));

        // Bind the nonce in the container for the CspNonce Antlers tag
        app()->instance(
            self::NONCE_KEY,
            $this->nonce
        );

        // Share the nonce with the view layer for Blade and Antlers
        View::share(
            'csp_nonce',
            $this->nonce
        );
    }

    /**
     * Set baseline security headers from configuration.
     *
     * @param  Response  $response  The outgoing HTTP response
     */
    private function applyBaseline(
        Response $response
    ): void {
        // Set X-Content-Type-Options when configured
        if ($value = config('security-headers.content_type_options')) {
            $response->headers->set(
                'X-Content-Type-Options',
                $this->headerValue($value)
            );
        }

        // Set Referrer-Policy when configured
        if ($value = config('security-headers.referrer_policy')) {
            $response->headers->set(
                'Referrer-Policy',
                $this->headerValue($value)
            );
        }

        // Set X-Frame-Options when configured
        if ($value = config('security-headers.frame_options')) {
            $response->headers->set(
                'X-Frame-Options',
                $this->headerValue($value)
            );
        }

        // Set Permissions-Policy when configured
        if ($value = config('security-headers.permissions_policy')) {
            $response->headers->set(
                'Permissions-Policy',
                $this->headerValue($value)
            );
        }
    }

    /**
     * Narrow a mixed configured header value to the type expected by
     * ResponseHeaderBag::set(), coercing scalars to string and string lists
     * to array<string> without altering the underlying value.
     *
     * @param  mixed  $value  The configured header value
     * @return array<string>|string The narrowed header value
     */
    private function headerValue(
        mixed $value
    ): array|string {
        // Preserve array<string> values for multi-value headers
        if (is_array($value)) {
            return array_map(
                static fn ($item): string => is_scalar($item) ? (string) $item : '',
                $value
            );
        }

        // Coerce scalar values to string
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Set Strict-Transport-Security for secure requests when enabled.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Response  $response  The outgoing HTTP response
     */
    private function applyHsts(
        Request $request,
        Response $response
    ): void {
        // Check if HSTS is disabled in configuration
        if (! config('security-headers.hsts.enabled')) {
            return;
        }

        // Check if the request was served over HTTPS
        if (! $request->isSecure()) {
            return;
        }

        // Initialize HSTS directive parts with max-age
        $maxAgeConfig = config(
            'security-headers.hsts.max_age',
            31536000
        );
        $maxAge = is_numeric($maxAgeConfig) ? (int) $maxAgeConfig : 0;
        $parts = ['max-age='.$maxAge];

        // Append includeSubDomains when enabled
        if (config('security-headers.hsts.include_subdomains')) {
            $parts[] = 'includeSubDomains';
        }

        // Append preload token when enabled
        if (config('security-headers.hsts.preload')) {
            $parts[] = 'preload';
        }

        // Set the Strict-Transport-Security response header
        $response->headers->set(
            'Strict-Transport-Security',
            implode(
                '; ',
                $parts
            )
        );
    }

    /**
     * Set Content-Security-Policy headers when enabled and path is not
     * excluded.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Response  $response  The outgoing HTTP response
     */
    private function applyCsp(
        Request $request,
        Response $response
    ): void {
        // Check if CSP is disabled in configuration
        if (! config(
            'security-headers.csp.enabled',
            true
        )) {
            return;
        }

        // Check each exclude path pattern against the request path
        foreach ((array) config(
            'security-headers.csp.exclude_paths',
            []
        ) as $pattern) {
            if (
                Str::is(
                    is_scalar($pattern) ? (string) $pattern : '',
                    $request->path()
                )
            ) {
                return;
            }
        }

        // Build the semicolon-separated CSP policy string
        $policy = $this->buildCspPolicy($request);

        // Check if the policy has no directives
        if ($policy === '') {
            return;
        }

        // Select enforce vs report-only header name from configuration
        $header = config(
            'security-headers.csp.report_only',
            true
        )
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        // Set the CSP header on the response
        $response->headers->set(
            $header,
            $policy
        );
    }

    /**
     * Assemble a Content-Security-Policy value from config directives.
     *
     * @param  Request  $request  The request, carrying any allowances it took up.
     * @return string Semicolon-separated CSP policy, or empty when no parts
     */
    private function buildCspPolicy(Request $request): string
    {
        // Load directive map, nonce directive list, and report URI from config
        $directives = (array) config(
            'security-headers.csp.directives',
            []
        );
        $nonceDirectives = (array) config(
            'security-headers.csp.nonce_directives',
            []
        );
        $reportUri = config('security-headers.csp.report_uri');

        // Initialize policy directive parts
        $parts = [];

        // Widen the directives by whatever this response took up while rendering
        /** @var list<string> $allowances */
        $allowances = $request->attributes->get(self::ALLOWANCES_KEY, []);
        foreach (array_unique($allowances) as $allowance) {
            $additions = (array) config('security-headers.csp.allowances.'.$allowance, []);
            foreach ($additions as $directive => $sources) {
                // A directive configured as null stays omitted
                if (array_key_exists($directive, $directives) && $directives[$directive] === null) {
                    continue;
                }

                $directives[$directive] = [...(array) ($directives[$directive] ?? []), ...(array) $sources];
            }
        }

        // Append each configured directive and its sources
        foreach ($directives as $directive => $sources) {
            // Skip null directive entries
            if ($sources === null) {
                continue;
            }

            // Normalize sources to a non-empty string list
            $sources = array_map(
                static fn ($s): string => is_scalar($s) ? (string) $s : '',
                array_values(array_filter(
                    (array) $sources,
                    fn ($s): bool => $s !== null && $s !== ''
                ))
            );

            // Inject the per-request nonce into directives configured for it
            if (
                $this->nonce !== null
                && in_array(
                    $directive,
                    $nonceDirectives,
                    true
                )
            ) {
                $sources[] = sprintf("'nonce-%s'", $this->nonce);
            }

            // Append directive-only or directive with source list
            $parts[] = $sources === []
                ? $directive
                : $directive.' '.implode(
                    ' ',
                    $sources
                );
        }

        // Append report-uri when configured
        if ($reportUri) {
            $reportUriValue = is_scalar($reportUri) ? (string) $reportUri : '';
            $parts[] = 'report-uri '.$reportUriValue;
        }

        // Join directive parts into a single policy string
        return implode(
            '; ',
            $parts
        );
    }
}
