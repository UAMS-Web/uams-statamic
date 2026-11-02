<?php

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\BardParser;

/**
 * @covers \UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\BardParser
 *
 * Pins the script-stripping invariant the WordPress import relies on (UAMS-Web/uams-statamic-archive#1742).
 *
 * `BardParser` wraps `ueberdosis/tiptap-php`, whose schema is an allowlist: a
 * node or mark the schema does not declare is discarded during parse. That is
 * what stands between a `<script>` in a WordPress post and an Antlers template
 * that renders Bard raw — but it is a side effect of a third-party library, not
 * an asserted invariant, so nothing failed if it regressed.
 * `ueberdosis/tiptap-php` arrives transitively via `statamic/cms`, which the
 * recurring dependency-update ticket pins to each new release, so the bump that
 * would silently widen this allowlist is a routine one.
 *
 * The allowlist filters node *types* and *marks* only, never node *attributes*.
 * For the vanilla nodes below that is equivalent, since none of them carries an
 * HTML-bearing attribute — but it is not equivalent for the importer's custom
 * `set` node. See {@see BardParserSetAttributePassthroughTest} for that boundary
 * and the gap behind it; these tests deliberately scope to the node-and-mark
 * allowlist so a failure here names the right component.
 *
 * The controls matter as much as the vectors: asserting only that links are
 * dropped would stay green if the `Link` mark were removed from the schema
 * wholesale, which is a bug in the other direction. Every safe-scheme case
 * below therefore asserts the link mark *survives* with its href intact.
 *
 * @command  php artisan test --compact tests/Unit/WordPressImport/BardParserScriptStrippingTest.php
 */

/**
 * Every `type` value appearing on a node or a mark anywhere in a Bard tree.
 *
 * @param  array<int|string, mixed>  $content  Bard (ProseMirror JSON) content
 * @return list<string> The node and mark type names, deduplicated
 */
function bardScriptStrippingTypes(array $content): array
{
    $types = [];

    $walk = function (array $nodes) use (&$walk, &$types): void {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (isset($node['type']) && is_string($node['type'])) {
                $types[] = $node['type'];
            }

            foreach (['content', 'marks'] as $key) {
                if (isset($node[$key]) && is_array($node[$key])) {
                    $walk($node[$key]);
                }
            }
        }
    };

    $walk($content);

    return array_values(array_unique($types));
}

/**
 * The concatenated text of every text node in a Bard tree.
 *
 * @param  array<int|string, mixed>  $content  Bard (ProseMirror JSON) content
 * @return string The visible text, in document order
 */
function bardScriptStrippingText(array $content): string
{
    $text = '';

    $walk = function (array $nodes) use (&$walk, &$text): void {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'text' && isset($node['text']) && is_string($node['text'])) {
                $text .= $node['text'];
            }

            if (isset($node['content']) && is_array($node['content'])) {
                $walk($node['content']);
            }
        }
    };

    $walk($content);

    return $text;
}

/**
 * Every `href` carried by a `link` mark anywhere in a Bard tree.
 *
 * @param  array<int|string, mixed>  $content  Bard (ProseMirror JSON) content
 * @return list<string> The link hrefs, in document order
 */
function bardScriptStrippingLinkHrefs(array $content): array
{
    $hrefs = [];

    $walk = function (array $nodes) use (&$walk, &$hrefs): void {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $marks = $node['marks'] ?? null;

            foreach (is_array($marks) ? $marks : [] as $mark) {
                if (! is_array($mark) || ($mark['type'] ?? null) !== 'link') {
                    continue;
                }

                $attrs = $mark['attrs'] ?? null;
                $href = is_array($attrs) ? ($attrs['href'] ?? null) : null;

                if (is_string($href)) {
                    $hrefs[] = $href;
                }
            }

            if (isset($node['content']) && is_array($node['content'])) {
                $walk($node['content']);
            }
        }
    };

    $walk($content);

    return $hrefs;
}

/**
 * Encode a parsed document so an assertion can search the whole tree.
 *
 * `JSON_UNESCAPED_SLASHES` is load-bearing rather than cosmetic: the default
 * encoding writes `</script>` as `<\/script>` and `https://evil.test` as
 * `https:\/\/evil.test`, so a `not->toContain()` on either would pass on a
 * document that does carry the payload. None of the current assertions holds a
 * slash, so this closes a latent gap rather than a live one — but the next
 * URL-shaped assertion added here would have gone silently green without it.
 *
 * @param  array<int|string, mixed>  $content  Bard (ProseMirror JSON) content
 * @return string JSON encoding of the content
 */
function bardScriptStrippingJson(array $content): string
{
    $encoded = json_encode($content, JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '';
}

it('reads the types, text and hrefs a surviving document carries', function (): void {
    // Every other case in this file leans on these four helpers to prove an
    // absence. A helper that always returned nothing would satisfy all of them
    // and assert nothing, so pin what they see on a document that survives.
    $parsed = (new BardParser)->parse(
        '<p>Sickle cell <strong>clinic</strong>, <a href="https://uams.edu/clinics">book online</a></p>'
    );

    expect(bardScriptStrippingTypes($parsed))
        ->toContain('paragraph')
        ->toContain('text')
        ->toContain('bold')
        ->toContain('link')
        ->and(bardScriptStrippingText($parsed))->toBe('Sickle cell clinic, book online')
        ->and(bardScriptStrippingLinkHrefs($parsed))->toBe(['https://uams.edu/clinics'])
        // Every JSON assertion in this file is a negative one, so an encoder
        // that returned nothing would satisfy all of them. This also pins
        // `JSON_UNESCAPED_SLASHES`: the default encoding writes the href as
        // `https:\/\/uams.edu\/clinics` and this `toContain` would fail.
        ->and(bardScriptStrippingJson($parsed))->toContain('"href":"https://uams.edu/clinics"');
});

it('discards executable nodes outright', function (string $html): void {
    $parsed = (new BardParser)->parse($html);

    // One assertion per vector in the dataset, and no more: `math` was asserted
    // here with no vector behind it, which reads as coverage and is not.
    expect(bardScriptStrippingTypes($parsed))
        ->not->toContain('script')
        ->not->toContain('style')
        ->not->toContain('iframe')
        ->not->toContain('object')
        ->not->toContain('embed')
        ->not->toContain('svg')
        ->and(bardScriptStrippingText($parsed))->not->toContain('alert(1)')
        ->and(bardScriptStrippingJson($parsed))
        ->not->toContain('alert(1)')
        ->not->toContain('evil.test');
})->with('bard script stripping executable vectors');

it('removes an executable node without abandoning the parse', function (string $html): void {
    // Every vector above parses to nothing at all today, which leaves its
    // negative assertions technically true of an empty document. Wrapping the
    // same vector in prose proves the node is what was removed, rather than the
    // parse being abandoned the moment one was seen.
    $parsed = (new BardParser)->parse('<p>Sickle cell clinic</p>'.$html.'<p>Call 501-686-1000</p>');

    expect(bardScriptStrippingText($parsed))->toBe('Sickle cell clinicCall 501-686-1000')
        ->and(bardScriptStrippingTypes($parsed))->toContain('paragraph')
        ->and(bardScriptStrippingJson($parsed))
        ->not->toContain('alert(1)')
        ->not->toContain('evil.test');
})->with('bard script stripping executable vectors');

it('drops event-handler attributes while keeping the surrounding text', function (): void {
    $parsed = (new BardParser)->parse('<p onclick="alert(1)">Sickle cell clinic</p>');

    expect(bardScriptStrippingText($parsed))->toBe('Sickle cell clinic')
        ->and(bardScriptStrippingJson($parsed))->not->toContain('onclick')
        ->and(bardScriptStrippingJson($parsed))->not->toContain('alert(1)');
});

it('does not allowlist the image node, so an onerror payload has nothing to ride', function (): void {
    $withPayload = (new BardParser)->parse('<p><img src=x onerror=alert(1)></p>');
    $withoutPayload = (new BardParser)->parse('<p><img src=x></p>');

    expect(bardScriptStrippingTypes($withPayload))->not->toContain('image')
        ->and(bardScriptStrippingJson($withPayload))->not->toContain('onerror')
        ->and(bardScriptStrippingJson($withPayload))->not->toContain('alert(1)');

    // The payload is not what removed the node, and the name has to say so.
    // `Image` is commented out of `BardParser`'s extension list, so the node is
    // discarded whole and an identical image carrying no handler parses to a
    // byte-identical document. Attribute stripping on a node the schema *does*
    // declare is the `onclick` case above.
    //
    // Registering `Image` inverts this case, and the inverted behavior is
    // correct rather than a regression: measured with the node registered, the
    // parse yields `{"type":"image","attrs":{"src":"x"}}` — the node survives
    // and the handler is dropped. Rewrite this case then; do not read its red
    // as `onerror` stripping having broken.
    expect(bardScriptStrippingJson($withPayload))->toBe(bardScriptStrippingJson($withoutPayload));
});

it('strips the link mark from an unsafe scheme but keeps the anchor text', function (string $href): void {
    $parsed = (new BardParser)->parse(sprintf('<p><a href="%s">Book an appointment</a></p>', $href));

    expect(bardScriptStrippingLinkHrefs($parsed))->toBe([])
        ->and(bardScriptStrippingText($parsed))->toBe('Book an appointment')
        ->and(bardScriptStrippingJson($parsed))->not->toContain('alert(1)');
})->with([
    'javascript' => ['javascript:alert(1)'],
    'mixed-case javascript' => ['JaVaScRiPt:alert(1)'],
    'leading-whitespace javascript' => [' javascript:alert(1)'],
    'data url' => ['data:text/html,<script>alert(1)</script>'],
]);

it('keeps the link mark on a safe scheme', function (string $href): void {
    $parsed = (new BardParser)->parse(sprintf('<p><a href="%s">Book an appointment</a></p>', $href));

    expect(bardScriptStrippingLinkHrefs($parsed))->toBe([$href])
        ->and(bardScriptStrippingText($parsed))->toBe('Book an appointment');
})->with([
    'https' => ['https://uams.edu/clinics'],
    'http' => ['http://uams.edu/clinics'],
    'root-relative' => ['/clinics/sickle-cell'],
    'mailto' => ['mailto:appointments@uams.edu'],
    'tel' => ['tel:+15016861000'],
]);

/**
 * The executable-node vectors, shared by the two tests above so that every one
 * is asserted both bare and surrounded by content that has to survive.
 */
dataset('bard script stripping executable vectors', [
    'bare script' => ['<script>alert(1)</script>'],
    'script smuggled inside svg' => ['<svg><script>alert(1)</script></svg>'],
    'style block' => ['<style>body{background:url(https://evil.test)}</style>'],
    'iframe' => ['<iframe src="https://evil.test"></iframe>'],
    'object' => ['<object data="https://evil.test"></object>'],
    'embed' => ['<embed src="https://evil.test">'],
]);
