<?php

declare(strict_types=1);

use App\Modifiers\DigitalSourceTypeValue;
use Statamic\Fields\Value;

/**
 * Behavior of the `digital_source_type_value` Antlers modifier
 * (app/Modifiers/DigitalSourceTypeValue.php): a thin wrapper that pipes the raw
 * `digital_source_type` group payload through IptcDigitalSourceValueResolver and
 * returns the resolved camelCase IPTC type key (or empty string). The resolver's
 * logic is exercised by IptcDigitalSourceValueResolverTest; this covers the
 * modifier's delegation + Value unwrapping.
 *
 * Mutation: the wrapper has no mutable surface (0 mutations — pure delegation);
 * the resolver is mutation-tested under IptcDigitalSourceValueResolverTest.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/DigitalSourceTypeValueTest.php
 */
it('resolves the IPTC type key from a group payload', function (mixed $value, string $expected) {
    expect((new DigitalSourceTypeValue)->index($value, [], []))->toBe($expected);
})->with([
    'preset-only payload resolves to the type key' => [['preset' => 'humanEdits_foo'], 'humanEdits'],
    'a Statamic Value wrapper is unwrapped' => [new Value(['preset' => 'digitized_none']), 'digitized'],
    'null payload yields an empty string' => [null, ''],
]);
