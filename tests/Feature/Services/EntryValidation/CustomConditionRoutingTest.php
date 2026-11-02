<?php

declare(strict_types=1);

use App\Services\EntryValidation\CustomConditionEvaluator;
use App\Services\EntryValidation\EntryValidator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Finder\Finder;
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

it('ports every custom condition a blueprint or fieldset uses', function (): void {
    /** @var array<string, list<string>> $used condition name => files using it */
    $used = [];

    $files = Finder::create()->files()->name('*.yaml')->in([resource_path('blueprints'), resource_path('fieldsets')]);

    foreach ($files as $file) {
        // A condition value is quoted YAML: 'custom <name>' or 'custom <name>:<params>'
        preg_match_all("/['\"]custom\\s+([A-Za-z0-9_]+)/", $file->getContents(), $matches);

        foreach ($matches[1] as $name) {
            $used[$name][] = $file->getRelativePathname();
        }
    }

    // The sweep must see the conditions known to be in use, or its silence proves nothing
    expect(array_keys($used))->toContain('parkingVisible', 'notSyndicated', 'selectionCount');

    $unported = array_diff_key($used, array_flip(CustomConditionEvaluator::SUPPORTED));

    expect($unported)->toBe([]);
});
