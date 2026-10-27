<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

use function Pest\Laravel\get;

/**
 * Feature tests for SecurityHeaders middleware header emission.
 *
 * Registers a probe route wrapped in the middleware and asserts baseline,
 * CSP, and HSTS behavior against config/security-headers.php defaults.
 *
 * Mutation: 100% on App\Http\Middleware\SecurityHeaders (87 mutants, --covered-only).
 */
uses(TestCase::class);

beforeEach(function (): void {
    // Register a GET route wrapped in SecurityHeaders for assertions
    Route::middleware(SecurityHeaders::class)
        ->get(
            '/__security_headers_probe',
            fn () => 'ok'
        );
});

/**
 * Extract a single directive (with its sources) from a CSP policy string.
 *
 * @param  string  $policy  The full semicolon-separated CSP policy
 * @param  string  $name  The directive name to isolate (e.g. style-src)
 * @return string The matched directive segment, or empty when not present
 */
function securityHeadersMiddlewareTestDirective(
    string $policy,
    string $name
): string {
    // Split the policy into its individual directive segments
    foreach (explode('; ', $policy) as $segment) {
        // Match the requested directive by its name and trailing space
        if (str_starts_with($segment, "{$name} ")) {
            return $segment;
        }
    }

    // Return empty when the directive is absent from the policy
    return '';
}

/**
 * Pull the style-src nonce value out of a response's Report-Only policy.
 *
 * @param  TestResponse<Response>  $response  The response to inspect
 * @return string The nonce value, or empty string when none was emitted
 */
function securityHeadersMiddlewareTestNonceFromResponse(
    TestResponse $response
): string {
    // Read the report-only policy from the response headers
    $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    // Capture the nonce token emitted within the policy
    preg_match(
        "/'nonce-([A-Za-z0-9+\/=]+)'/",
        $policy,
        $matches
    );

    // Return the captured nonce or an empty string when absent
    return $matches[1] ?? '';
}

it('applies baseline headers', function (): void {
    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // ---
    // Status is irrelevant — globally-appended middleware runs on any
    // response Statamic returns (200 for the probe route, 404 otherwise)
    // ---

    // Assert baseline headers match configured defaults
    $response->assertHeader(
        'X-Content-Type-Options',
        'nosniff'
    );
    $response->assertHeader(
        'Referrer-Policy',
        'strict-origin-when-cross-origin'
    );
    $response->assertHeader(
        'X-Frame-Options',
        'SAMEORIGIN'
    );
    expect($response->headers->get('Permissions-Policy'))->not->toBeEmpty();
});

it('defaults csp to report only', function (): void {
    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Assert Report-Only header is set and enforce header is absent
    expect($response->headers->get('Content-Security-Policy-Report-Only'))->not->toBeEmpty();
    expect($response->headers->get('Content-Security-Policy'))->toBeNull();

    // Read the report-only policy for substring checks
    $policy = $response->headers->get('Content-Security-Policy-Report-Only');

    // Assert key directives and third-party sources appear in the policy
    expect($policy)->toContain("default-src 'self'");
    expect($policy)->toContain("frame-ancestors 'self'");
    expect($policy)->toContain('youtube.com');
    expect($policy)->toContain('vimeo.com');
    expect($policy)->toContain('mychart');
    expect($policy)->toContain('upgrade-insecure-requests');
});

it('nonce-gates style-src without unsafe-inline', function (): void {
    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Read the report-only policy for substring checks
    $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    // Isolate the style-src directive from the policy string
    $styleSrc = securityHeadersMiddlewareTestDirective(
        $policy,
        'style-src'
    );

    // Assert the per-request nonce is present on style-src
    expect($styleSrc)->toMatch("/'nonce-[A-Za-z0-9+\/=]+'/");

    // Assert 'unsafe-inline' has been removed from style-src elements
    expect($styleSrc)->not->toContain("'unsafe-inline'");
});

it('keeps unsafe-inline on style-src-attr', function (): void {
    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Read the report-only policy
    $policy = $response->headers->get('Content-Security-Policy-Report-Only');

    // Assert style-src-attr still permits inline attribute styles
    expect($policy)->toContain("style-src-attr 'unsafe-inline'");
});

it('nonce-gates script-src without unsafe-inline', function (): void {
    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Read the report-only policy for substring checks
    $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    // Isolate the script-src directive from the policy string
    $scriptSrc = securityHeadersMiddlewareTestDirective(
        $policy,
        'script-src'
    );

    // Assert the per-request nonce is present on script-src
    expect($scriptSrc)->toMatch("/'nonce-[A-Za-z0-9+\/=]+'/");

    // Assert 'unsafe-inline' has been removed from script-src elements (UAMS-Web/uams-statamic-archive#1741)
    expect($scriptSrc)->not->toContain("'unsafe-inline'");
});

it('drops unsafe-eval from script-src now that alpine runs the csp build', function (): void {
    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Read the report-only policy
    $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    // Isolate the script-src directive from the policy string
    $scriptSrc = securityHeadersMiddlewareTestDirective(
        $policy,
        'script-src'
    );

    // ---
    // The front end bundles @alpinejs/csp, which evaluates every x-* attribute
    // through an AST interpreter instead of new Function(), so the last blanket
    // allowance in script-src is gone (UAMS-Web/uams-statamic-archive#1859). Asserted rather than assumed so
    // re-adding it is a deliberate edit to this test, not silent config drift.
    // ---
    expect($scriptSrc)->not->toContain("'unsafe-eval'");
});

it('allow-lists every external script host the views load', function (): void {
    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Read the report-only policy
    $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    // Isolate the script-src directive from the policy string
    $scriptSrc = securityHeadersMiddlewareTestDirective(
        $policy,
        'script-src'
    );

    // ---
    // Every host resources/views/** loads a <script src> from. The UAMS-Web/uams-statamic-archive#1741
    // audit found calendar.uams.edu missing, which reports as a violation
    // today and breaks the LiveWhale calendar when #667 flips CSP to
    // enforcing. radiomd.com serves the Health Talk podcast embed on
    // clinical provider profiles (UAMS-Web/uams-statamic-archive#1683).
    // ---
    expect($scriptSrc)->toContain('https://www.googletagmanager.com');
    expect($scriptSrc)->toContain('https://calendar.uams.edu');
    expect($scriptSrc)->toContain('https://radiomd.com');
});

it('allow-lists the livewhale widget on every directive it actually uses', function (): void {
    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Read the report-only policy
    $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    // ---
    // A script-src entry alone does NOT make this widget work. `lwcw.js`
    // appends a <link rel="stylesheet"> from the same host (style-src), and
    // because both templates request `format=html` it fetches the events
    // feed over a cross-origin XHR rather than JSONP (connect-src). Miss
    // either and the #667 enforce flip leaves the widget empty or unstyled,
    // which is precisely what allow-listing only script-src would have done.
    // ---
    // Collect the directives that fail to allow-list the widget's host
    $missing = collect(['script-src', 'style-src', 'connect-src'])
        ->reject(fn (string $directive): bool => str_contains(
            securityHeadersMiddlewareTestDirective($policy, $directive),
            'https://calendar.uams.edu'
        ))
        ->values()
        ->all();

    expect($missing)->toBe([], 'these directives are missing the LiveWhale calendar host');
});

it('matches the nonce in the header to the shared view nonce', function (): void {
    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Read the report-only policy and the nonce shared with the views
    $policy = $response->headers->get('Content-Security-Policy-Report-Only');
    $shared = view()->shared('csp_nonce');

    // Narrow the shared nonce to a string for header substring assertions
    $sharedNonce = is_string($shared) ? $shared : '';

    // Read the container binding the CspNonce tag resolves at render time
    /** @var string $boundNonce */
    $boundNonce = app(SecurityHeaders::NONCE_KEY);

    // Assert a nonce was shared and the header references the same value
    expect($shared)->not->toBeEmpty();
    expect($policy)->toContain("'nonce-{$sharedNonce}'");

    // Assert the container binding read by the CspNonce tag matches too
    expect($boundNonce)->toBe($shared);
});

it('issues a fresh, unique nonce per request', function (): void {
    // Capture the nonce emitted on two separate requests
    $first = securityHeadersMiddlewareTestNonceFromResponse(get('/__security_headers_probe'));
    $second = securityHeadersMiddlewareTestNonceFromResponse(get('/__security_headers_probe'));

    // Assert both requests produced a nonce and the values differ
    expect($first)->not->toBeEmpty();
    expect($second)->not->toBeEmpty();
    expect($first)->not->toBe($second);
});

it('swaps to the enforce header in enforce mode', function (): void {
    // Disable report-only mode so the enforce header is used
    config()->set(
        'security-headers.csp.report_only',
        false
    );

    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Assert enforce header is set and Report-Only is absent
    expect($response->headers->get('Content-Security-Policy'))->not->toBeEmpty();
    expect($response->headers->get('Content-Security-Policy-Report-Only'))->toBeNull();
});

it('can disable csp while keeping baseline headers', function (): void {
    // Disable CSP in configuration
    config()->set(
        'security-headers.csp.enabled',
        false
    );

    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Assert both CSP header variants are absent
    expect($response->headers->get('Content-Security-Policy'))->toBeNull();
    expect($response->headers->get('Content-Security-Policy-Report-Only'))->toBeNull();

    // Assert baseline headers are still applied
    $response->assertHeader(
        'X-Content-Type-Options',
        'nosniff'
    );
});

it('skips csp on excluded paths', function (): void {
    // Exclude the probe path from CSP
    config()->set(
        'security-headers.csp.exclude_paths',
        ['__security_headers_probe']
    );

    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Assert both CSP header variants are absent
    expect($response->headers->get('Content-Security-Policy'))->toBeNull();
    expect($response->headers->get('Content-Security-Policy-Report-Only'))->toBeNull();
});

it('appends report-uri when configured', function (): void {
    // Set a CSP violation report endpoint
    config()->set(
        'security-headers.csp.report_uri',
        'https://example.test/csp'
    );

    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Read the report-only policy
    $policy = $response->headers->get('Content-Security-Policy-Report-Only');

    // Assert report-uri appears in the assembled policy string
    expect($policy)->toContain('report-uri https://example.test/csp');
});

it('does not emit hsts on insecure requests', function (): void {
    // Enable HSTS in configuration
    config()->set(
        'security-headers.hsts.enabled',
        true
    );

    // Issue an insecure request to the probe route
    $response = get('http://localhost/__security_headers_probe');

    // Assert Strict-Transport-Security is absent
    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
});

it('emits hsts on secure requests when enabled', function (): void {
    // Enable HSTS with full directive flags in configuration
    config()->set(
        'security-headers.hsts.enabled',
        true
    );
    config()->set(
        'security-headers.hsts.max_age',
        63072000
    );
    config()->set(
        'security-headers.hsts.include_subdomains',
        true
    );
    config()->set(
        'security-headers.hsts.preload',
        true
    );

    // Issue a secure request to the probe route
    $response = get('https://localhost/__security_headers_probe');

    // Assert the assembled Strict-Transport-Security value
    expect($response->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=63072000; includeSubDomains; preload');
});

it('does not emit hsts when disabled', function (): void {
    // Disable HSTS in configuration
    config()->set(
        'security-headers.hsts.enabled',
        false
    );

    // Issue a secure request to the probe route
    $response = get('https://localhost/__security_headers_probe');

    // Assert Strict-Transport-Security is absent
    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
});

it('disables all headers via the master switch', function (): void {
    // Disable all security headers via the master switch
    config()->set(
        'security-headers.enabled',
        false
    );

    // Issue a request through the probe route
    $response = get('/__security_headers_probe');

    // Assert baseline and CSP headers are absent
    expect($response->headers->get('X-Content-Type-Options'))->toBeNull();
    expect($response->headers->get('Referrer-Policy'))->toBeNull();
    expect($response->headers->get('Content-Security-Policy-Report-Only'))->toBeNull();
    expect($response->headers->get('Content-Security-Policy'))->toBeNull();
});
