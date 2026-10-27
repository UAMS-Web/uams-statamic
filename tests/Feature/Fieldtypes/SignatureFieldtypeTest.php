<?php

declare(strict_types=1);

use App\Fieldtypes\Signature;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for {@see Signature}. The public-form template (UAMS-Web/uams-statamic-archive#674) submits either
 * a base64 PNG data URL (drawn) or a typed-name fallback string; both are plain
 * strings the fieldtype must round-trip, while empty submissions collapse to null
 * so Statamic filters them (and the form `required` rule catches a missing
 * signature server-side).
 *
 * @command php artisan test --compact tests/Feature/Fieldtypes/SignatureFieldtypeTest.php
 */
it('is selectable in form blueprints', function () {
    expect((new Signature)->selectableInForms())->toBeTrue();
});

it('nulls empty submissions on process', function () {
    expect((new Signature)->process(''))->toBeNull();
    expect((new Signature)->process(null))->toBeNull();
});

it('nulls empty submissions on pre-process', function () {
    expect((new Signature)->preProcess(''))->toBeNull();
    expect((new Signature)->preProcess(null))->toBeNull();
});

it('passes drawn and typed submissions through unchanged', function (string $value) {
    expect((new Signature)->process($value))->toBe($value);
    expect((new Signature)->preProcess($value))->toBe($value);
})->with([
    'drawn data URL' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCA',
    'typed fallback name' => 'Jane Q. Public',
    'unicode typed name' => 'José Ángel',
]);

it('identifies itself as a signature pad with no categories, default or config', function () {
    $signature = new Signature;

    expect(Signature::title())->toBe('Signature')
        ->and($signature->component())->toBe('signature')
        ->and($signature->icon())->toBe('text-formatting-input-signature')
        ->and($signature->categories())->toBe([])
        ->and($signature->defaultable())->toBeFalse()
        ->and((new ReflectionMethod(Signature::class, 'configFieldItems'))->invoke($signature))->toBe([]);
});

it('keeps falsy non-empty submissions on process and pre-process', function (mixed $value) {
    // Only the empty string and null collapse; any other value is kept as is
    expect((new Signature)->process($value))->toBe($value)
        ->and((new Signature)->preProcess($value))->toBe($value);
})->with([
    'a zero string' => ['0'],
    'false' => [false],
    'an empty array' => [[]],
]);
