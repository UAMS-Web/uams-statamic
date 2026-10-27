<?php

declare(strict_types=1);

use App\Support\Statamic\Assets\Concerns\ComputesAssetCreditText;
use App\Support\Statamic\Assets\Concerns\ComputesAssetIptcDigitalSourceValues;
use App\Support\Statamic\Assets\GenericAugmentedAssetWithCredit;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

uses(TestCase::class);

covers(ComputesAssetCreditText::class, ComputesAssetIptcDigitalSourceValues::class);

/**
 * The computed fields the fallback augmented asset adds: `credit_text` and the IPTC digital
 * source value and schema keys for each digital source group (#3343 coverage).
 *
 * Each asset is made in memory on an unsaved container, so nothing is written to disk.
 *
 * @command  php artisan test --compact tests/Feature/Support/Statamic/Assets/GenericAugmentedAssetWithCreditTest.php
 */

/**
 * An in-memory asset carrying the given blueprint data.
 *
 * @param  array<array-key, mixed>  $data
 */
function genericCreditAsset(array $data): StatamicAsset
{
    $container = mustBe(StatamicAssetContainer::class, AssetContainer::make('generic_credit_probe'), 'the probe container');
    $container->disk('local');

    $asset = mustBe(StatamicAsset::class, $container->makeAsset('probe.jpg'), 'the probe asset');
    $asset->data($data);

    return $asset;
}

it('computes the credit line from the creator and the creator organization', function (array $data, ?string $expected): void {
    $augmented = new GenericAugmentedAssetWithCredit(genericCreditAsset($data));

    $creditText = (fn (): ?string => $this->credit_text())->call($augmented);

    expect($creditText)->toBe($expected);
})->with([
    'both' => [['creator' => 'Ann Lee', 'creator_organization' => 'UAMS'], 'Ann Lee / UAMS'],
    'the creator alone' => [['creator' => 'Ann Lee'], 'Ann Lee'],
    'the organization alone' => [['creator_organization' => 'UAMS'], 'UAMS'],
    'neither' => [[], null],
]);

it('does not yet reach the credit line through augmentation', function (): void {
    // Statamic dispatches `credit_text` to a `creditText()` method; fixing that is #3358
    $augmented = new GenericAugmentedAssetWithCredit(genericCreditAsset(['creator' => 'Ann Lee', 'creator_organization' => 'UAMS']));

    expect($augmented->get('credit_text')->value())->toBeNull();
});

it('lists the computed digital source keys after the asset\'s own keys, once each', function (): void {
    // `digital_source_type_value` is also stored on this asset, so it must not be listed twice
    $keys = (new GenericAugmentedAssetWithCredit(genericCreditAsset(['alt' => 'A photo', 'digital_source_type_value' => 'stale'])))->keys();

    $computed = [
        'digital_source_type_value',
        'digital_source_type_value_schema',
        'digital_source_type_text_digital_source_type_value',
        'digital_source_type_text_digital_source_type_value_schema',
        'digital_source_type_portrait_digital_source_type_value',
        'digital_source_type_portrait_digital_source_type_value_schema',
        'digital_source_type_image_digital_source_type_value',
        'digital_source_type_image_digital_source_type_value_schema',
        'digital_source_type_audio_digital_source_type_value',
        'digital_source_type_audio_digital_source_type_value_schema',
        'digital_source_type_video_digital_source_type_value',
        'digital_source_type_video_digital_source_type_value_schema',
    ];

    expect($keys)->toBe(array_values(array_unique($keys)))
        ->and(array_slice($keys, 0, 2))->toBe(['alt', 'digital_source_type_value'])
        ->and(array_slice($keys, -11))->toBe(array_slice($computed, 1));
});

it('resolves each digital source group\'s value and schema from that group alone', function (string $group, string $valueKey, string $schemaKey): void {
    // Every other group carries a different preset, so reading the wrong group gives a different answer
    $data = [
        'digital_source_type' => ['preset' => 'print_document_scanned'],
        'digital_source_type_text' => ['preset' => 'print_document_scanned'],
        'digital_source_type_portrait' => ['preset' => 'print_document_scanned'],
        'digital_source_type_image' => ['preset' => 'print_document_scanned'],
        'digital_source_type_audio' => ['preset' => 'print_document_scanned'],
        'digital_source_type_video' => ['preset' => 'print_document_scanned'],
    ];
    $data[$group] = ['preset' => 'trainedAlgorithmicMedia_document_ai_raw'];

    $augmented = new GenericAugmentedAssetWithCredit(genericCreditAsset($data));

    expect($augmented->get($valueKey)->value())->toBe('trainedAlgorithmicMedia')
        ->and($augmented->get($schemaKey)->value())->toBe('https://schema.org/TrainedAlgorithmicMediaDigitalSource');
})->with([
    'the primary group' => ['digital_source_type', 'digital_source_type_value', 'digital_source_type_value_schema'],
    'text' => ['digital_source_type_text', 'digital_source_type_text_digital_source_type_value', 'digital_source_type_text_digital_source_type_value_schema'],
    'portrait' => ['digital_source_type_portrait', 'digital_source_type_portrait_digital_source_type_value', 'digital_source_type_portrait_digital_source_type_value_schema'],
    'image' => ['digital_source_type_image', 'digital_source_type_image_digital_source_type_value', 'digital_source_type_image_digital_source_type_value_schema'],
    'audio' => ['digital_source_type_audio', 'digital_source_type_audio_digital_source_type_value', 'digital_source_type_audio_digital_source_type_value_schema'],
    'video' => ['digital_source_type_video', 'digital_source_type_video_digital_source_type_value', 'digital_source_type_video_digital_source_type_value_schema'],
]);

it('resolves an empty value and schema for a group the asset does not carry', function (): void {
    $augmented = new GenericAugmentedAssetWithCredit(genericCreditAsset([]));

    expect($augmented->get('digital_source_type_value')->value())->toBe('')
        ->and($augmented->get('digital_source_type_value_schema')->value())->toBe('');
});
