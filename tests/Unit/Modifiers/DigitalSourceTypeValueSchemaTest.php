<?php

declare(strict_types=1);

use App\Modifiers\DigitalSourceTypeValueSchema;
use Statamic\Fields\Value;

/**
 * Behavior of the `digital_source_type_value_schema` Antlers modifier
 * (app/Modifiers/DigitalSourceTypeValueSchema.php): a thin wrapper that pipes the
 * raw `digital_source_type` group payload through IptcDigitalSourceValueResolver
 * and returns the schema.org DigitalSource subtype URL (or empty string). The
 * resolver's logic is exercised by IptcDigitalSourceValueResolverTest; this
 * covers the modifier's delegation + Value unwrapping.
 *
 * Mutation: the wrapper has no mutable surface (0 mutations — pure delegation);
 * the resolver is mutation-tested under IptcDigitalSourceValueResolverTest.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/DigitalSourceTypeValueSchemaTest.php
 */
it('resolves the schema.org URL from a group payload', function (mixed $value, string $expected) {
    expect((new DigitalSourceTypeValueSchema)->index($value, [], []))->toBe($expected);
})->with([
    'preset-only payload resolves to the schema URL' => [['preset' => 'humanEdits_foo'], 'https://schema.org/MinorHumanEditsDigitalSource'],
    'a Statamic Value wrapper is unwrapped' => [new Value(['preset' => 'humanEdits_foo']), 'https://schema.org/MinorHumanEditsDigitalSource'],
    'null payload yields an empty string' => [null, ''],
]);
