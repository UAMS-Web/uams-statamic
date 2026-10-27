<?php

declare(strict_types=1);

use App\Services\LinkHealth\BlueprintFieldMap;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;

/**
 * `BlueprintFieldMap::normalize()` reduces the two shapes a blueprint's `fields` key can take —
 * a LIST of `['handle' => …, 'field' => …]` pairs, and a KEYED map of handle => config — to one
 * keyed form.
 *
 * The class had no dedicated test: it was exercised only incidentally, through whichever blueprint
 * shapes `LinkExtractorTest` happens to build. Mutation testing named the cost precisely — six
 * survivors, four of them clustered in the list-of-pairs branch, each one a fixture nobody had
 * written rather than a defect. Every case below was chosen to kill a specific surviving mutant,
 * and the mutant it targets is named on it (UAMS-Web/uams-statamic-archive#2274).
 *
 * @command  php artisan test --compact tests/Feature/Services/LinkHealth/BlueprintFieldMapTest.php
 */
it('keeps a list entry config, rather than discarding it for an empty array', function (): void {
    // Kills CoalesceRemoveLeft on `$entry['field'] ?? []`: with the left side removed every
    // entry maps to `[]`, which no previous fixture could distinguish because none carried a
    // config through this branch.
    $map = BlueprintFieldMap::normalize([
        ['handle' => 'body', 'field' => ['type' => 'bard', 'sets' => ['x' => []]]],
    ]);

    expect($map)->toHaveKey('body')
        ->and($map['body'])->toBe(['type' => 'bard', 'sets' => ['x' => []]]);
});

it('replaces a non-array list entry config with an empty array', function (): void {
    // Kills TernaryNegated on `is_array($config) ? $config : []`. A `field` key holding a string
    // is what a hand-edited blueprint produces; negated, the string would be stored as the config
    // and every consumer expecting an array would fault far from here.
    $map = BlueprintFieldMap::normalize([
        ['handle' => 'body', 'field' => 'not-an-array'],
    ]);

    expect($map)->toBe(['body' => []]);
});

it('casts a non-string handle so the map is keyed by string', function (): void {
    // Kills RemoveStringCast on `(string) $handle`, and the input has to be a FLOAT to do it.
    // An int handle cannot: PHP converts the numeric string key `'2024'` straight back to
    // int `2024`, so cast and no-cast are indistinguishable — the first version of this test
    // asserted `['2024']` and failed for exactly that reason. A float is the discriminator:
    // `(string) 1.5` keys `'1.5'`, while `$map[1.5]` truncates to int `1` and emits a
    // deprecation. `is_scalar(1.5)` is true, so the value reaches this line.
    $map = BlueprintFieldMap::normalize([
        ['handle' => 1.5, 'field' => ['type' => 'text']],
    ]);

    expect(array_keys($map))->toBe(['1.5'])
        ->and($map['1.5'])->toBe(['type' => 'text']);
});

it('continues past a list entry rather than stopping at the first', function (): void {
    // Kills ContinueToBreak on the `continue` that ends the handle branch. Every earlier fixture
    // had a single entry in this shape, so `break` and `continue` were indistinguishable.
    $map = BlueprintFieldMap::normalize([
        ['handle' => 'first', 'field' => ['type' => 'text']],
        ['handle' => 'second', 'field' => ['type' => 'link']],
    ]);

    expect(array_keys($map))->toBe(['first', 'second']);
});

it('skips a list entry whose handle is not scalar', function (): void {
    $map = BlueprintFieldMap::normalize([
        ['handle' => ['nested'], 'field' => ['type' => 'text']],
        ['handle' => 'kept', 'field' => ['type' => 'text']],
    ]);

    expect(array_keys($map))->toBe(['kept']);
});

it('reads the keyed form, where the key is the handle', function (): void {
    $map = BlueprintFieldMap::normalize([
        'body' => ['type' => 'bard'],
    ]);

    expect($map)->toBe(['body' => ['type' => 'bard']]);
});

it('returns an empty map for a non-array config', function (): void {
    expect(BlueprintFieldMap::normalize('not-an-array'))->toBe([])
        ->and(BlueprintFieldMap::normalize(null))->toBe([]);
});

it('skips a non-array entry in either form', function (): void {
    $map = BlueprintFieldMap::normalize([
        'scalar-entry',
        ['handle' => 'kept', 'field' => ['type' => 'text']],
    ]);

    expect(array_keys($map))->toBe(['kept']);
});

it('reads a grouped set, where the group wraps its sets under `sets`', function (): void {
    $sets = BlueprintFieldMap::sets([
        'content' => ['sets' => ['quote' => ['fields' => [['handle' => 'cite', 'field' => ['type' => 'link']]]]]],
    ]);

    expect($sets)->toBe(['quote' => ['cite' => ['type' => 'link']]]);
});

it('reads an ungrouped set, where the config sits directly under its handle', function (): void {
    // Kills RemoveArrayItem on the `[$groupHandle => $group]` fallback. Blueprints predating
    // grouping carry a set config with no `sets` wrapper; with the item removed the fallback
    // yields an empty array and every such set vanishes from the map silently. No fixture had
    // this shape, so the branch was executed and its result never observed (UAMS-Web/uams-statamic-archive#2274).
    $sets = BlueprintFieldMap::sets([
        'quote' => ['fields' => [['handle' => 'cite', 'field' => ['type' => 'link']]]],
    ]);

    expect($sets)->toBe(['quote' => ['cite' => ['type' => 'link']]]);
});

it('skips a set whose handle is not a string', function (): void {
    $sets = BlueprintFieldMap::sets([
        'content' => ['sets' => [['fields' => []], 'kept' => ['fields' => []]]],
    ]);

    expect(array_keys($sets))->toBe(['kept']);
});

it('maps each field by handle to its config', function (): void {
    $fields = new Fields([
        ['handle' => 'body', 'field' => ['type' => 'bard', 'display' => 'Body']],
        ['handle' => 'link', 'field' => ['type' => 'link']],
    ]);

    $map = BlueprintFieldMap::fromFields($fields);

    expect(array_keys($map))->toBe(['body', 'link'])
        ->and($map['body']['type'])->toBe('bard')
        ->and($map['link']['type'])->toBe('link');
});

it('skips a field whose handle is not a string', function (): void {
    $field = new Field('body', ['type' => 'bard']);

    $fields = Mockery::mock(Fields::class);
    $fields->shouldReceive('all')->andReturn(collect([7 => $field, 'body' => $field]));

    expect(array_keys(BlueprintFieldMap::fromFields($fields)))->toBe(['body']);
});

it('returns no sets for a sets config that is not a list', function (mixed $config): void {
    expect(BlueprintFieldMap::sets($config))->toBe([]);
})->with(['null' => [null], 'text' => ['sets']]);

it('skips a set group that is not a list and a set config that is not a list', function (): void {
    expect(BlueprintFieldMap::sets([
        'broken_group' => 'not a group',
        'main' => ['sets' => [
            'quote' => 'not a set',
            'callout' => ['fields' => [['handle' => 'text', 'field' => ['type' => 'text']]]],
        ]],
    ]))->toBe(['callout' => ['text' => ['type' => 'text']]]);
});
