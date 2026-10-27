<?php

declare(strict_types=1);

use App\Modifiers\SanitizeUrl;
use Illuminate\Support\Str;
use Statamic\Fields\Value;

/**
 * Behavior of the `sanitize_url` Antlers modifier (app/Modifiers/SanitizeUrl.php):
 * make an editor/importer-controlled URL safe to render into an `href`/`itemid`/`src`
 * attribute. It neutralizes a dangerous scheme (`javascript:`, `data:`, `vbscript:`,
 * `file:`, …) — collapsing it to an inert `#` — and HTML-encodes the result so a `"`
 * cannot break out of the attribute. Detection runs on the browser's resolved value
 * (HTML-decoded + control-stripped), so entity-, hex-, named-, whitespace-, and
 * double-encoded obfuscations are all seen; the output is re-encoded with
 * `double_encode: true` so no surviving reference can be re-decoded back into a scheme.
 *
 * The load-bearing invariant is exercised directly: whatever bytes the modifier emits,
 * the URL a browser resolves after its single HTML-decode of the attribute value must
 * never be a disallowed scheme. That is asserted over a battery of obfuscations so the
 * suite is not false-green on the character-reference class (the UAMS-Web/uams-statamic-archive#1500 adversarial
 * review caught exactly that gap in the first cut).
 *
 * Mutation: every security-relevant mutant is dead — the exact `#` assertions on
 * the entity-obfuscated cases pin the `html_entity_decode` step and its
 * `ENT_QUOTES | ENT_HTML5` flags as load-bearing. The residual survivors are
 * equivalents — the `instanceof Value` unwrap (Statamic's `Value` is `Stringable`),
 * the `(string)` cast on `preg_replace`'s `string|null` return, and the strict
 * `in_array(…, true)` flag (both operands are always strings). Left clean rather
 * than `@pest-mutate-ignore`.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/SanitizeUrlTest.php
 */

/**
 * The scheme a browser resolves from the modifier's output, after its single
 * HTML-decode of the attribute value (null for a relative, no-scheme URL).
 */
function sanitizeUrlBrowserScheme(string $rendered): ?string
{
    $decoded = html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $probe = (string) preg_replace('/[\x00-\x20]+/', '', $decoded);

    return preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $probe, $m) === 1
        ? strtolower($m[1])
        : null;
}

it('passes safe and relative URLs through unchanged, and neutralizes dangerous schemes', function (mixed $value, array $params, string $expected): void {
    expect((new SanitizeUrl)->index($value, $params, []))->toBe($expected);
})->with([
    // ── Safe: site-relative (no scheme) survives verbatim ──
    'absolute-path relative URL' => ['/cardiology/heart', [], '/cardiology/heart'],
    'relative URL with no leading slash' => ['cardiology/heart', [], 'cardiology/heart'],
    'fragment-only URL' => ['#main-content', [], '#main-content'],
    'query-only URL' => ['?page=2', [], '?page=2'],
    'protocol-relative URL (no scheme)' => ['//cdn.example.test/a', [], '//cdn.example.test/a'],
    'relative path containing a colon is not misread as a scheme' => ['/a/b:c/d', [], '/a/b:c/d'],

    // ── Safe: allowlisted schemes survive ──
    'http URL' => ['http://example.test/a', [], 'http://example.test/a'],
    'https URL' => ['https://sub.uams.edu/clinic', [], 'https://sub.uams.edu/clinic'],
    'scheme match is case-insensitive' => ['HTTPS://sub.uams.edu/clinic', [], 'HTTPS://sub.uams.edu/clinic'],
    'mailto URL' => ['mailto:info@uams.edu', [], 'mailto:info@uams.edu'],
    'tel URL' => ['tel:+15015551234', [], 'tel:+15015551234'],

    // ── A benign ampersand is single-encoded for the attribute (not doubled) ──
    'query ampersand is entity-encoded once' => ['/search?a=1&b=2', [], '/search?a=1&amp;b=2'],

    // ── Attribute breakout: the quote is encoded so it cannot escape the attribute ──
    'a double-quote is entity-encoded' => ['/x"onmouseover=alert(1)', [], '/x&quot;onmouseover=alert(1)'],

    // ── Dangerous: disallowed literal schemes collapse to the inert fallback ──
    'javascript scheme is neutralized' => ['javascript:alert(1)', [], '#'],
    'mixed-case javascript scheme is neutralized' => ['JaVaScRiPt:alert(1)', [], '#'],
    'leading whitespace cannot smuggle javascript past detection' => ['   javascript:alert(1)', [], '#'],
    'embedded tab cannot smuggle javascript past detection' => ["java\tscript:alert(1)", [], '#'],
    'embedded newline cannot smuggle javascript past detection' => ["java\nscript:alert(1)", [], '#'],
    'leading control character cannot smuggle javascript past detection' => ["\x01javascript:alert(1)", [], '#'],
    'data scheme is neutralized' => ['data:text/html,alert(1)', [], '#'],
    'vbscript scheme is neutralized' => ['vbscript:msgbox(1)', [], '#'],
    'file scheme is neutralized' => ['file:///etc/passwd', [], '#'],

    // ── Entity-obfuscated schemes are decoded, then neutralized to the fallback.
    //    (These pin `html_entity_decode` + its ENT_HTML5 flag as load-bearing.) ──
    'entity-decimal colon collapses to fallback' => ['javascript&#58;alert(1)', [], '#'],
    'entity letter in scheme collapses to fallback' => ['jav&#97;script:alert(1)', [], '#'],
    'named colon entity (needs ENT_HTML5) collapses to fallback' => ['javascript&colon;alert(1)', [], '#'],

    // ── The fallback is configurable via the first parameter ──
    'a custom fallback replaces a dangerous scheme' => ['javascript:alert(1)', ['/'], '/'],
    'a non-scalar fallback param is ignored' => ['javascript:alert(1)', [['nested']], '#'],
    'an integer fallback param is stringified' => ['javascript:alert(1)', [42], '42'],

    // ── Type handling ──
    'empty string stays empty' => ['', [], ''],
    'null becomes an empty string' => [null, [], ''],
    'a non-scalar value becomes an empty string' => [['nested'], [], ''],
    'a Statamic Value is unwrapped' => [new Value('/from-value'), [], '/from-value'],
    'a dangerous scheme inside a Value is neutralized' => [new Value('javascript:alert(1)'), [], '#'],
    'a Stringable is coerced to string' => [Str::of('/stringable'), [], '/stringable'],
]);

it('never lets a disallowed scheme survive to the browser-resolved URL, however obfuscated', function (string $payload): void {
    $scheme = sanitizeUrlBrowserScheme((new SanitizeUrl)->index($payload, [], []));

    expect($scheme)->toBeIn([null, 'http', 'https', 'mailto', 'tel']);
})->with([
    'literal javascript' => 'javascript:alert(1)',
    'entity-decimal colon' => 'javascript&#58;alert(1)',
    'entity-decimal letter in scheme' => 'jav&#97;script:alert(1)',
    'entity-hex letter in scheme' => 'jav&#x61;script:alert(1)',
    'entity-hex colon' => 'javascript&#x3a;alert(1)',
    'named colon entity' => 'javascript&colon;alert(1)',
    'zero-padded decimal colon' => 'javascript&#0000058;alert(1)',
    'entity tab inside the scheme' => 'java&#9;script:alert(1)',
    'double-encoded colon' => 'javascript&amp;#58;alert(1)',
    'entity-obfuscated data scheme' => 'dat&#97;:text/html,x',
]);

it('single-encodes a legitimate URL so a browser resolves it back byte-for-byte', function (string $url): void {
    $rendered = (new SanitizeUrl)->index($url, [], []);

    // The value a browser resolves (one HTML-decode) must equal the original URL —
    // no double-encoding, no broken link (UAMS-Web/uams-statamic-archive#1500 acceptance criterion 3).
    expect(html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->toBe($url);
})->with([
    'plain relative' => '/cardiology/heart-vascular',
    'absolute ancestor-site URL' => 'https://childrens.uams.edu/',
    'query with ampersand' => '/search?a=1&b=2',
]);
