<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression test for stored XSS via JSON-LD output.
 *
 * Schema property values are editor-controlled and serialized into a
 * `<script type="application/ld+json">` element on every public page. Without
 * `JSON_HEX_TAG`, a value containing a literal `</script>` would terminate the
 * script element and inject markup. {@see SchemaOutputService::encodeJsonLdForScript()}
 * is the single seam both script-bound encode sites share; this pins its escaping.
 *
 * Mutation: not recorded. An earlier figure here (100% on App\Services\SchemaTemplates\SchemaOutputService,
 * 2 mutants, --covered-only) named no command or revision and was removed (UAMS-Web/uams-statamic-archive#2915).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaJsonLdScriptEncodingTest.php
 */
function schemaJsonLdScriptEncode(mixed $data): string
{
    // Invoke the private script encoder via reflection (the production seam is private).
    $service = app(SchemaOutputService::class);
    $encoded = (new ReflectionMethod($service, 'encodeJsonLdForScript'))->invoke($service, $data);

    // Guard the reflective mixed return down to the declared string type.
    if (! is_string($encoded)) {
        throw new RuntimeException('encodeJsonLdForScript() should return a string.');
    }

    return $encoded;
}

it('prevents a script closing tag in a value from breaking out of the script element', function () {
    $payload = [
        '@context' => 'https://schema.org',
        '@type' => 'MedicalClinic',
        'name' => '</script><img src=x onerror=alert(document.cookie)>',
    ];

    $json = schemaJsonLdScriptEncode($payload);

    // No literal angle brackets survive, so the HTML parser never sees a tag to act on.
    expect($json)->not->toContain('<');
    expect($json)->not->toContain('>');
    expect($json)->not->toContain('</script>');

    // The payload is unchanged on decode — escaping is representational only.
    expect(json_decode($json, true))->toBe($payload);
});

it('keeps the output as valid equivalent json', function () {
    $payload = ['name' => 'Cardiology </script> & "quotes"', 'url' => 'https://uamshealth.com/x'];

    // The escaped output round-trips to exactly the original data.
    expect(json_decode(schemaJsonLdScriptEncode($payload), true))->toBe($payload);
});

it('leaves slashes and unicode unescaped for benign values', function () {
    $json = schemaJsonLdScriptEncode(['url' => 'https://uamshealth.com/heart/care', 'name' => 'Café']);

    // Behavior preserved: slashes and unicode are not hex-escaped.
    expect($json)->toContain('https://uamshealth.com/heart/care');
    expect($json)->toContain('Café');
});
