<?php

declare(strict_types=1);

use App\Modifiers\StripTagsArray;
use Illuminate\Support\Str;
use Statamic\Fields\Value;

/**
 * Behavior of the `strip_tags_array` Antlers modifier
 * (app/Modifiers/StripTagsArray.php): strip HTML tags from each string in a list
 * (array, Collection, or a single scalar wrapped as one element), unwrapping Value
 * items, with the same tag-argument rules as core strip_tags — pass nothing to
 * strip all, tag names to strip only those, or a context variable holding the tag
 * list (unless Antlers runtime method syntax is active).
 *
 * Mutation: every killable mutant is dead (score ~84%). The residual survivors are
 * equivalents — the tagKey `(string)` cast / `: ''` fallback (Arr::get with an empty or
 * non-existent key both miss the context and fall back to params), the redundant
 * `(string)` casts on the already-string item/stripped values (Str::stripTags both takes
 * and returns a string), and the `array_values` reindexing on the Collection/array paths
 * (the foreach + `$result[]` already reindexes). Left clean rather than annotated with
 * `@pest-mutate-ignore`.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/StripTagsArrayTest.php
 */
it('strips tags from each item of a list', function (mixed $value, array $params, array $context, array $expected) {
    expect((new StripTagsArray)->index($value, $params, $context))->toBe($expected);
})->with([
    // ── Original cases ──
    'strips all tags from each string' => [['<p>One</p>', '<div>Two</div>'], [], [], ['One', 'Two']],
    'a Collection is accepted' => [collect(['<em>x</em>', '<strong>y</strong>']), [], [], ['x', 'y']],
    'a scalar wraps as a single element' => ['<a href="#">Link</a>', [], [], ['Link']],
    'null is an empty list' => [null, [], [], []],
    'false is an empty list' => [false, [], [], []],
    'empty string is an empty list' => ['', [], [], []],
    'tag-list parameter matches core strip_tags' => [['<p>Hello <b>world</b></p>'], ['p'], [], ['Hello <b>world</b>']],
    'resolves the tag list from context' => [['<p>a</p><b>b</b>'], ['tag_config'], ['tag_config' => ['p']], ['a<b>b</b>']],
    'skips context resolution under runtime method syntax' => [['<p>stay</p>'], ['tag_config'], ['{__method_args}' => true, 'tag_config' => ['p']], ['<p>stay</p>']],
    'unwraps Statamic Value items' => [[new Value('<i>x</i>')], [], [], ['x']],
    'a Value wrapping a non-scalar unwraps to an empty string' => [[new Value(['nested'])], [], [], ['']],

    // ── Added: reindexing, non-scalar items, multi-item lists ──
    'associative array keys are reindexed' => [['a' => '<p>One</p>', 'b' => '<p>Two</p>'], [], [], ['One', 'Two']],
    'a non-scalar item becomes an empty string' => [[['nested'], '<p>x</p>'], [], [], ['', 'x']],
    'a Stringable item is stripped' => [[Str::of('<b>bold</b>')], [], [], ['bold']],
]);
