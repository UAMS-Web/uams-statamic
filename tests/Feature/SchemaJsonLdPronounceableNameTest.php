<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Asset;
use Tests\Support\SchemaFixtureStacheTestCase;

/**
 * End-to-end proof that a person's name properties reach the composed JSON-LD
 * graph as BOTH an IPA and an SSML `PronounceableText` object (UAMS-Web/uams-statamic-archive#554).
 *
 * `SchemaPronounceableTextTest` pins the tag's own contract. It cannot show that
 * the derived SSML survives schema composition, and the existing person suite
 * cannot either: `SchemaJsonLdPersonEntriesTest` asserts graph-shape invariants
 * and makes no assertion about any name property, so it stays green whether the
 * SSML is emitted or silently dropped. This file closes that gap and only that
 * gap.
 *
 * Helpers here are deliberately named `pronounceable*` rather than reusing the
 * person suite's `schemaLdRenderPersonGraph()`. Pest loads every test file into
 * one process, so a duplicate top-level function name is a fatal error for the
 * whole suite rather than a failure in one file.
 *
 * **JSON-LD caches:** mirrors the person suite's regime — see
 * {@see useProductionJsonLdFragmentCacheRegime()}. Do not substitute a bare
 * `json_ld_cache.enabled` override; that is the master switch and takes the
 * cross-request fragment cache down with it (UAMS-Web/uams-statamic-archive#1842).
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdPronounceableNameTest.php
 */
uses(SchemaFixtureStacheTestCase::class, MockeryPHPUnitIntegration::class);

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
});

/**
 * Canonical `@id` of the fixture person whose name carries phonetic text.
 */
const PRONOUNCEABLE_KIRK_PERSON_URL = 'http://uams-statamic.test/people/james-kirk';

/**
 * Render the JSON-LD document for a fixture profile URL and return it decoded.
 *
 * @return array<string, mixed> Decoded top-level JSON-LD document
 */
function pronounceableRenderGraph(string $url): array
{
    // Bind a request URL so the service resolves the entity without a full render
    $request = Request::create($url, 'GET');
    app()->instance('request', $request);

    // Stub asset lookups so rendering needs no asset metadata tables
    Asset::shouldReceive('find')->andReturn(null);

    // Render and extract the JSON-LD script block
    $scriptTag = app(SchemaOutputService::class)->outputForCurrentContext();

    $matched = preg_match(
        '/<script[^>]*type=[\'"]application\/ld\+json[\'"][^>]*>(.*?)<\/script>/is',
        $scriptTag,
        $matches
    );

    Assert::assertSame(1, $matched, 'Expected a JSON-LD script tag in the rendered output.');
    Assert::assertArrayHasKey(1, $matches);

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
}

/**
 * Collect every `PronounceableText` node anywhere in the decoded graph.
 *
 * Returns rather than accumulating by reference so the element type stays
 * provable to static analysis.
 *
 * @param  mixed  $value  Current branch
 * @return array<int, array<string, mixed>> Nodes found in this branch
 */
function pronounceableCollectNodes(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $found = [];

    // Record this branch when it is a PronounceableText object
    if (($value['@type'] ?? null) === 'PronounceableText') {
        /** @var array<string, mixed> $node */
        $node = $value;
        $found[] = $node;
    }

    // Walk every child branch
    foreach ($value as $child) {
        foreach (pronounceableCollectNodes($child) as $descendant) {
            $found[] = $descendant;
        }
    }

    return $found;
}

/**
 * Read one node's `phoneticText` as a string.
 *
 * @param  array<string, mixed>  $node  A PronounceableText node
 * @return string The phonetic text, or an empty string when absent
 */
function pronounceablePhoneticString(array $node): string
{
    $value = $node['phoneticText'] ?? '';

    return is_string($value) ? $value : '';
}

it('emits both an IPA and an SSML pronounceable node into the composed graph', function () {
    $decoded = pronounceableRenderGraph(PRONOUNCEABLE_KIRK_PERSON_URL);

    // Gather every PronounceableText node in the rendered document
    $nodes = pronounceableCollectNodes($decoded);

    // Assert the fixture actually exercises this path, so the assertions below
    // cannot pass vacuously on a graph that carries no phonetic text at all
    expect($nodes)->not->toBeEmpty();

    // Split the nodes by the markup each one declares
    $markups = array_map(
        static fn (array $node): mixed => $node['speechToTextMarkup'] ?? null,
        $nodes
    );

    // Assert both forms survive composition and reach the page
    expect($markups)->toContain('IPA');
    expect($markups)->toContain('SSML');

    // Assert every SSML node carries a phoneme document rather than bare IPA
    $ssml = array_values(array_filter(
        $nodes,
        static fn (array $node): bool => ($node['speechToTextMarkup'] ?? null) === 'SSML'
    ));

    foreach ($ssml as $node) {
        $phonetic = pronounceablePhoneticString($node);

        expect($phonetic)->toStartWith('<speak><phoneme alphabet="ipa" ph="');
        expect($phonetic)->toEndWith('</phoneme></speak>');

        // Assert the derived document is well-formed XML after escaping
        expect(simplexml_load_string($phonetic))->not->toBeFalse();
    }

    // Assert the IPA and SSML forms come in pairs over the same surface text
    $ipaTextValues = array_map(
        static fn (array $node): mixed => $node['textValue'] ?? null,
        array_values(array_filter(
            $nodes,
            static fn (array $node): bool => ($node['speechToTextMarkup'] ?? null) === 'IPA'
        ))
    );

    foreach ($ssml as $node) {
        expect($ipaTextValues)->toContain($node['textValue'] ?? null);
    }
});
