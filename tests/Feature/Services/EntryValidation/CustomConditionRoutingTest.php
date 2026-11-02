<?php

declare(strict_types=1);

use App\Services\EntryValidation\CustomConditionEvaluator;
use App\Services\EntryValidation\EntryValidator;
use Illuminate\Support\Facades\Log;
use Statamic\Fields\Field;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

// How the entry validator routes a `custom` field condition to its server-side
// port (#508), and the guard that every condition the blueprints use has one.

it('routes a custom condition through its port, parsing params as the editor does', function (): void {
    $validator = app(EntryValidator::class);

    // Params run from the first colon to the next one, comma-split and trimmed
    expect($validator->evaluateCondition('picks', 'custom selectionCount: lt , 3', ['picks' => ['a', 'b']]))->toBeTrue()
        ->and($validator->evaluateCondition('picks', 'custom selectionCount:lt,2', ['picks' => ['a', 'b']]))->toBeFalse()
        ->and($validator->evaluateCondition('picks', 'custom selectionCount:lt,3:ignored', ['picks' => ['a', 'b']]))->toBeTrue()
        ->and($validator->evaluateCondition('syndication_query', 'custom notSyndicated', ['syndication_query' => false]))->toBeTrue();
});

it('reads an unported custom condition as hidden, like the editor, and logs it', function (): void {
    $log = Log::spy();

    $passes = app(EntryValidator::class)->evaluateCondition('picks', 'custom noSuchCondition:a', ['picks' => 'a']);

    expect($passes)->toBeFalse();

    $log->shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'no server-side port')
            && $context === ['condition' => 'noSuchCondition', 'field' => 'picks']);
});

it('evaluates a custom condition written as the whole condition block, as the editor does', function (string $block, mixed $syndicated, bool $visible): void {
    $field = new Field('teaser', ['type' => 'text', $block => 'custom notSyndicated']);

    expect(app(EntryValidator::class)->fieldPassesConditions($field, ['syndication_query' => $syndicated]))->toBe($visible);
})->with([
    'if shows when it passes' => ['if', false, true],
    'if hides when it fails' => ['if', true, false],
    'unless hides when it passes' => ['unless', false, false],
    'unless shows when it fails' => ['unless', true, true],
    'hide_when hides when it passes' => ['hide_when', false, false],
]);

it('reads a string condition block without the custom prefix as a custom condition', function (): void {
    $field = new Field('teaser', ['type' => 'text', 'if' => 'notSyndicated']);

    expect(app(EntryValidator::class)->fieldPassesConditions($field, ['syndication_query' => false]))->toBeTrue()
        ->and(app(EntryValidator::class)->fieldPassesConditions($field, ['syndication_query' => true]))->toBeFalse();
});

it('hides a field whose string condition block names no ported condition', function (): void {
    $log = Log::spy();

    $field = new Field('teaser', ['type' => 'text', 'if' => 'not a condition']);

    expect(app(EntryValidator::class)->fieldPassesConditions($field, []))->toBeFalse();

    $log->shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context === ['condition' => 'not a condition', 'field' => 'teaser']);
});

/**
 * Collect the custom condition names in every condition block of a parsed YAML
 * tree, whatever its quoting, keyed by name.
 *
 * @param  array<array-key, mixed>  $node
 * @param  array<string, list<string>>  $used  condition name => files using it
 */
function customConditionNamesIn(array $node, string $file, array &$used): void
{
    $conditionKeys = ['if', 'if_any', 'unless', 'unless_any', 'show_when', 'show_when_any', 'hide_when', 'hide_when_any'];

    foreach ($node as $key => $value) {
        // A condition block is one condition string, a custom condition whose
        // `custom ` prefix is optional, or a map of field => condition
        if (\in_array($key, $conditionKeys, true) && \is_string($value)) {
            $used[explode(':', (string) preg_replace('/^custom /', '', $value))[0]][] = $file;
        }

        if (\in_array($key, $conditionKeys, true) && \is_array($value)) {
            foreach ($value as $condition) {
                if (\is_string($condition) && preg_match('/^\s*custom\s+([A-Za-z0-9_]+)/', $condition, $match) === 1) {
                    $used[$match[1]][] = $file;
                }
            }
        }

        if (\is_array($value)) {
            customConditionNamesIn($value, $file, $used);
        }
    }
}

it('ports every custom condition a blueprint or fieldset uses', function (): void {
    /** @var array<string, list<string>> $used condition name => files using it */
    $used = [];

    // Entry, term and global blueprints and the fieldsets they import. Conditions on
    // fieldtype settings (`configFieldItems()` in app/Fieldtypes) are not here on
    // purpose: they drive the blueprint editor, and EntryValidator never sees them
    $files = Finder::create()->files()->name('*.yaml')->in([resource_path('blueprints'), resource_path('fieldsets')]);

    foreach ($files as $file) {
        $parsed = Yaml::parse($file->getContents());

        if (\is_array($parsed)) {
            customConditionNamesIn($parsed, $file->getRelativePathname(), $used);
        }
    }

    // The sweep must see the conditions known to be in use, or its silence proves nothing
    expect(array_keys($used))->toContain('parkingVisible', 'notSyndicated', 'selectionCount', 'notContainsAny');

    $unported = array_diff_key($used, array_flip(CustomConditionEvaluator::SUPPORTED));

    expect($unported)->toBe([]);
});
