<?php

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Tiptap\SetMarkupNonce;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\BardParser;

/**
 * @covers \UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Tiptap\Nodes\Set
 *
 * Pins that the importer no longer trusts `data-set-*` markup arriving in
 * WordPress post content (UAMS-Web/uams-statamic-archive#1742, fixed in UAMS-Web/wordpress-importer#494).
 *
 * {@see BardParserScriptStrippingTest} pins the node-and-mark allowlist. That
 * allowlist is real but narrower than it reads: `Tiptap\Core\Schema::apply()`
 * filters node *types* and *marks*, and nothing else. Node *attributes* are
 * never filtered — which was moot for every vanilla node, and not moot for the
 * importer's custom `set` node. It parsed `<div data-set-type="…">` out of
 * source HTML and copied each `data-set-attr-*` value into `attrs.values`
 * verbatim, so a post carrying that markup planted its attribute values
 * straight into an entry where a set partial such as
 * `resources/views/sets/_pull_quote.antlers.html` renders them raw.
 *
 * The importer closed it by trusting only markup carrying a nonce it mints per
 * import process, which content being imported cannot know. The tag selector
 * matches that nonce, so author-typed set markup is not a `set` node at all —
 * it parses as an ordinary div and the author's own text survives.
 *
 * **This file used to assert the passthrough as a known gap.** It was written
 * to be flipped when the fix landed, and this is that flip: every expectation
 * below now asserts the payload is dropped. A green run here IS a safety claim,
 * unlike the version it replaces.
 *
 * @command  php artisan test --compact tests/Unit/WordPressImport/BardParserSetAttributePassthroughTest.php
 */

/**
 * The `values` array of the first `set` node in a Bard tree.
 *
 * @param  array<int|string, mixed>  $content  Bard (ProseMirror JSON) content
 * @return array<mixed> The set's stored values, or an empty array if absent
 */
function bardSetPassthroughValues(array $content): array
{
    foreach ($content as $node) {
        if (! is_array($node) || ($node['type'] ?? null) !== 'set') {
            continue;
        }

        $attrs = $node['attrs'] ?? null;
        $values = is_array($attrs) ? ($attrs['values'] ?? null) : null;

        if (is_array($values)) {
            return $values;
        }
    }

    return [];
}

afterEach(function (): void {
    // The nonce is process-global; do not leak one run's token into the next
    SetMarkupNonce::reset();
});

it('still discards the surrounding executable markup', function (): void {
    // The node-and-mark allowlist is a separate mechanism from the set-attribute
    // trust boundary. Keep it pinned so a failure here is not misread as the
    // set fix regressing.
    $parsed = (new BardParser)->parse(
        '<div data-set-type="pull_quote"'.SetMarkupNonce::attribute()
        .' data-set-attr-quote="hello"></div><script>alert(1)</script>'
    );

    expect(json_encode($parsed))->not->toContain('alert(1)')
        ->and(bardSetPassthroughValues($parsed))->toHaveKey('quote', 'hello');
});

it('drops a data-set-attr payload that arrived in post content', function (string $payload): void {
    $parsed = (new BardParser)->parse(sprintf(
        '<div data-set-type="pull_quote" data-set-attr-quote="%s" data-set-attr-author="Uhura"></div>',
        htmlspecialchars($payload, ENT_QUOTES)
    ));

    // No set is minted from author-typed markup, so nothing is stored…
    expect(bardSetPassthroughValues($parsed))->toBe([]);

    // …and the payload survives nowhere in the tree. JSON_UNESCAPED_SLASHES is
    // load-bearing: the default encoding writes `</script>` as `<\/script>`, so
    // a plain toContain() on a closing tag silently never matches.
    expect(json_encode($parsed, JSON_UNESCAPED_SLASHES))->not->toContain($payload);
})->with([
    'script tag' => ['<script>alert(1)</script>'],
    'img onerror' => ['<img src=x onerror=alert(1)>'],
    'anchor with javascript scheme' => ['<a href="javascript:alert(1)">x</a>'],
]);

it('accepts set markup only from the importer, not from arbitrary source HTML', function (): void {
    // The importer emits `data-set-*` itself (ShortcodeProcessor::generateTiptapHtml).
    // What changed is that the same markup typed into a WordPress post is now
    // distinguishable from it, which is the whole of the fix.
    $parsed = (new BardParser)->parse(
        '<p>Ordinary copy.</p><div data-set-type="gallery" data-set-attr-heading="&lt;script&gt;alert(1)&lt;/script&gt;"></div>'
    );

    expect(bardSetPassthroughValues($parsed))->toBe([])
        ->and(json_encode($parsed, JSON_UNESCAPED_SLASHES))->not->toContain('<script>alert(1)</script>');
});

it('keeps the author copy that surrounded the rejected markup', function (): void {
    // Rejecting at the tag selector rather than inside getAttrs is what makes
    // this hold: the div is parsed as ordinary content instead of becoming an
    // attribute-less set with the author's words stranded inside it.
    $parsed = (new BardParser)->parse(
        '<p>Ordinary copy.</p>'
        .'<div data-set-type="pull_quote" data-set-attr-quote="x">Author wrote this</div>'
    );

    $encoded = json_encode($parsed);

    expect($encoded)->toContain('Ordinary copy.')
        ->and($encoded)->toContain('Author wrote this');
});

it('still imports set markup the importer generated', function (): void {
    $parsed = (new BardParser)->parse(
        '<div data-set-type="pull_quote"'.SetMarkupNonce::attribute()
        .' data-set-attr-quote="A real quote" data-set-attr-author="Uhura"></div>'
    );

    expect(bardSetPassthroughValues($parsed))
        ->toHaveKey('quote', 'A real quote')
        ->toHaveKey('author', 'Uhura');
});
