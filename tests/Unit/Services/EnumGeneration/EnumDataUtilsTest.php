<?php

declare(strict_types=1);

use App\Services\EnumGeneration\EnumDataUtils;
use Tests\Support\Doubles\EnumDataUtilsIntProbe;
use Tests\Support\Doubles\EnumDataUtilsProbeInfo;
use Tests\Support\Doubles\EnumDataUtilsProbeTag;
use Tests\Support\Doubles\EnumDataUtilsPureProbe;
use Tests\Support\Doubles\EnumDataUtilsStringProbe;

covers(EnumDataUtils::class);

/**
 * `EnumDataUtils` (UAMS-Web/uams-statamic#889): the reflection-built, cached data
 * map an attribute-carrying enum reads its metadata from, the typed field readers
 * over that map, and the accessor closures that combine the two.
 *
 * The map is cached per enum and attribute class for the life of the process, so
 * every test that reads the string probe's `EnumDataUtilsProbeInfo` map builds it
 * with the one extractor below, or its object-typed wrapper, which returns the
 * same rows. Whichever test builds it first, the others read the same map.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/EnumDataUtilsTest.php
 */

/**
 * The extractor every string-probe `EnumDataUtilsProbeInfo` map is built with.
 *
 * @return array<string, mixed>
 */
function enumDataUtilsExtract(EnumDataUtilsProbeInfo $info, string $value): array
{
    return ['label' => $info->label, 'value' => $value, 'primary' => $info->primary];
}

/**
 * The same extractor, typed as the accessor factories declare theirs.
 *
 * @return array<string, mixed>
 */
function enumDataUtilsExtractAny(object $info, string $value): array
{
    if (! $info instanceof EnumDataUtilsProbeInfo) {
        throw new RuntimeException('Expected an EnumDataUtilsProbeInfo, got '.$info::class);
    }

    return enumDataUtilsExtract($info, $value);
}

/**
 * A literal data map for the field readers, one case holding a value of each type.
 *
 * @return array<string, array<string, mixed>>
 */
function enumDataUtilsMap(): array
{
    return ['red' => ['name' => 'Red', 'flag' => true, 'off' => false, 'rank' => 3, 'tags' => ['warm', 'bright'], 'none' => null]];
}

it('maps each backed case with metadata by its backing value, skipping a case without any', function (): void {
    $map = EnumDataUtils::buildLazyDataMap(EnumDataUtilsStringProbe::class, EnumDataUtilsProbeInfo::class, enumDataUtilsExtract(...));

    expect($map)->toBe([
        'red' => ['label' => 'Red', 'value' => 'red', 'primary' => true],
        'blue' => ['label' => 'Blue', 'value' => 'blue', 'primary' => null],
    ]);
});

it('hands an int backing value to the extractor as a string', function (): void {
    $received = [];

    $map = EnumDataUtils::buildLazyDataMap(EnumDataUtilsIntProbe::class, EnumDataUtilsProbeInfo::class, static function (EnumDataUtilsProbeInfo $info, string $value) use (&$received): array {
        $received[] = $value;

        return ['label' => $info->label];
    });

    expect($received)->toBe(['7'])
        ->and($map)->toBe([7 => ['label' => 'Seven']]);
});

it('skips a pure case, which has no backing value to key it by', function (): void {
    $calls = 0;

    $map = EnumDataUtils::buildLazyDataMap(EnumDataUtilsPureProbe::class, EnumDataUtilsProbeInfo::class, static function () use (&$calls): array {
        $calls++;

        return [];
    });

    expect($map)->toBe([])
        ->and($calls)->toBe(0);
});

it('returns the cached map on a later call, without running the new extractor', function (): void {
    $first = EnumDataUtils::buildLazyDataMap(EnumDataUtilsStringProbe::class, EnumDataUtilsProbeInfo::class, enumDataUtilsExtract(...));
    $ran = false;

    $second = EnumDataUtils::buildLazyDataMap(EnumDataUtilsStringProbe::class, EnumDataUtilsProbeInfo::class, static function () use (&$ran): array {
        $ran = true;

        return ['different' => 'map'];
    });

    expect($second)->toBe($first)
        ->and($ran)->toBeFalse();
});

it('caches one map per enum and attribute pair', function (): void {
    EnumDataUtils::buildLazyDataMap(EnumDataUtilsStringProbe::class, EnumDataUtilsProbeInfo::class, enumDataUtilsExtract(...));

    $tags = EnumDataUtils::buildLazyDataMap(EnumDataUtilsStringProbe::class, EnumDataUtilsProbeTag::class, static fn (EnumDataUtilsProbeTag $tag, string $value): array => ['tag' => $tag->tag]);

    expect($tags)->toBe(['red' => ['tag' => 'warm']]);
});

it('reads each typed field from the map', function (): void {
    $map = enumDataUtilsMap();

    expect(EnumDataUtils::getFieldValue($map, 'red', 'name', 'Color'))->toBe('Red')
        ->and(EnumDataUtils::getBooleanFieldValue($map, 'red', 'flag', 'Color'))->toBeTrue()
        ->and(EnumDataUtils::getBooleanFieldValue($map, 'red', 'off', 'Color', true))->toBeFalse()
        ->and(EnumDataUtils::getIntegerFieldValue($map, 'red', 'rank', 'Color'))->toBe(3)
        ->and(EnumDataUtils::getArrayFieldValue($map, 'red', 'tags', 'Color'))->toBe(['warm', 'bright']);
});

it('refuses an unknown case, naming the enum and the case', function (Closure $read): void {
    expect($read)->toThrow(ValueError::class, 'Unknown Color: purple');
})->with([
    'string' => [fn (): string => EnumDataUtils::getFieldValue(enumDataUtilsMap(), 'purple', 'name', 'Color')],
    'boolean' => [fn (): bool => EnumDataUtils::getBooleanFieldValue(enumDataUtilsMap(), 'purple', 'flag', 'Color')],
    'integer' => [fn (): int => EnumDataUtils::getIntegerFieldValue(enumDataUtilsMap(), 'purple', 'rank', 'Color')],
    'array' => [fn (): array => EnumDataUtils::getArrayFieldValue(enumDataUtilsMap(), 'purple', 'tags', 'Color')],
]);

it('refuses a missing or null field, naming the field, the enum and the case', function (Closure $read, string $field): void {
    expect(fn (): mixed => $read($field))->toThrow(ValueError::class, "Unknown field '".$field."' for Color: red");
})->with([
    'string, missing' => [fn (string $field): string => EnumDataUtils::getFieldValue(enumDataUtilsMap(), 'red', $field, 'Color'), 'missing'],
    'string, null' => [fn (string $field): string => EnumDataUtils::getFieldValue(enumDataUtilsMap(), 'red', $field, 'Color'), 'none'],
    'integer' => [fn (string $field): int => EnumDataUtils::getIntegerFieldValue(enumDataUtilsMap(), 'red', $field, 'Color'), 'missing'],
    'array' => [fn (string $field): array => EnumDataUtils::getArrayFieldValue(enumDataUtilsMap(), 'red', $field, 'Color'), 'missing'],
]);

it('refuses a field of the wrong type, naming the field, the enum, the case and the type', function (Closure $read, string $message): void {
    expect($read)->toThrow(ValueError::class, $message);
})->with([
    'string' => [fn (): string => EnumDataUtils::getFieldValue(enumDataUtilsMap(), 'red', 'rank', 'Color'), "Field 'rank' for Color red is not a string"],
    'boolean' => [fn (): bool => EnumDataUtils::getBooleanFieldValue(enumDataUtilsMap(), 'red', 'name', 'Color'), "Field 'name' for Color red is not a boolean"],
    'integer' => [fn (): int => EnumDataUtils::getIntegerFieldValue(enumDataUtilsMap(), 'red', 'name', 'Color'), "Field 'name' for Color red is not a integer"],
    'array' => [fn (): array => EnumDataUtils::getArrayFieldValue(enumDataUtilsMap(), 'red', 'name', 'Color'), "Field 'name' for Color red is not a array"],
]);

it('returns the default for a missing or null boolean field, false unless one is given', function (): void {
    expect(EnumDataUtils::getBooleanFieldValue(enumDataUtilsMap(), 'red', 'missing', 'Color'))->toBeFalse()
        ->and(EnumDataUtils::getBooleanFieldValue(enumDataUtilsMap(), 'red', 'missing', 'Color', true))->toBeTrue()
        ->and(EnumDataUtils::getBooleanFieldValue(enumDataUtilsMap(), 'red', 'none', 'Color', true))->toBeTrue();
});

it('builds a field accessor over the cached map', function (): void {
    $label = EnumDataUtils::createFieldAccessor(EnumDataUtilsStringProbe::class, EnumDataUtilsProbeInfo::class, enumDataUtilsExtractAny(...), 'label', 'Color');

    expect($label('blue'))->toBe('Blue')
        ->and(fn (): string => $label('bare'))->toThrow(ValueError::class, 'Unknown Color: bare');
});

it('builds a boolean field accessor whose default is false unless one is given', function (): void {
    $primary = EnumDataUtils::createBooleanFieldAccessor(EnumDataUtilsStringProbe::class, EnumDataUtilsProbeInfo::class, enumDataUtilsExtractAny(...), 'primary', 'Color');
    $primaryOrTrue = EnumDataUtils::createBooleanFieldAccessor(EnumDataUtilsStringProbe::class, EnumDataUtilsProbeInfo::class, enumDataUtilsExtractAny(...), 'primary', 'Color', true);

    expect($primary('red'))->toBeTrue()
        ->and($primary('blue'))->toBeFalse()
        ->and($primaryOrTrue('blue'))->toBeTrue()
        ->and(fn (): bool => $primary('bare'))->toThrow(ValueError::class, 'Unknown Color: bare');
});
