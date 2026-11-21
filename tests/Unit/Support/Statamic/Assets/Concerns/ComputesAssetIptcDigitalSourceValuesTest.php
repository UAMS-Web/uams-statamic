<?php

declare(strict_types=1);

use App\Services\IptcDigitalSourceValueResolver;
use App\Support\Statamic\Assets\Concerns\ComputesAssetIptcDigitalSourceValues;
use Tests\Support\AugmentedKeysStub;

covers(ComputesAssetIptcDigitalSourceValues::class);

/**
 * Pure unit coverage for {@see ComputesAssetIptcDigitalSourceValues}.
 *
 * Exercises `keys()` merging, deduplication, reindexing, and non-array parent
 * fallback, plus all 12 computed value/schema methods against isolated group
 * payloads.
 *
 * @command  php artisan test --compact tests/Unit/Support/Statamic/Assets/Concerns/ComputesAssetIptcDigitalSourceValuesTest.php
 */

/**
 * Build a minimal test harness using `ComputesAssetIptcDigitalSourceValues`.
 *
 * @param  array<string, mixed>  $data  Raw blueprint group payloads keyed by handle
 * @param  mixed  $parentKeys  Value returned by `parent::keys()`
 * @param  list<string>  $creditKeys  Value returned by `creditTextKeys()`
 */
function makeIptcDigitalSourceHarness(
    array $data = [],
    mixed $parentKeys = ['title'],
    array $creditKeys = ['credit_text'],
): AugmentedKeysStub {
    return new class($parentKeys, $data, $creditKeys) extends AugmentedKeysStub
    {
        use ComputesAssetIptcDigitalSourceValues;

        /**
         * @param  array<string, mixed>  $data
         * @param  list<string>  $creditKeys
         */
        public function __construct(
            mixed $parentKeys,
            private readonly array $data,
            private readonly array $creditKeys,
        ) {
            parent::__construct($parentKeys);
        }

        /**
         * @return list<string>
         */
        protected function creditTextKeys(): array
        {
            return $this->creditKeys;
        }

        protected function getFromData(string $handle): mixed
        {
            return $this->data[$handle] ?? null;
        }

        public function callComputed(string $method): string
        {
            return match ($method) {
                'digitalSourceTypeValue' => $this->digitalSourceTypeValue(),
                'digitalSourceTypeValueSchema' => $this->digitalSourceTypeValueSchema(),
                'digitalSourceTypeTextDigitalSourceTypeValue' => $this->digitalSourceTypeTextDigitalSourceTypeValue(),
                'digitalSourceTypeTextDigitalSourceTypeValueSchema' => $this->digitalSourceTypeTextDigitalSourceTypeValueSchema(),
                'digitalSourceTypePortraitDigitalSourceTypeValue' => $this->digitalSourceTypePortraitDigitalSourceTypeValue(),
                'digitalSourceTypePortraitDigitalSourceTypeValueSchema' => $this->digitalSourceTypePortraitDigitalSourceTypeValueSchema(),
                'digitalSourceTypeImageDigitalSourceTypeValue' => $this->digitalSourceTypeImageDigitalSourceTypeValue(),
                'digitalSourceTypeImageDigitalSourceTypeValueSchema' => $this->digitalSourceTypeImageDigitalSourceTypeValueSchema(),
                'digitalSourceTypeAudioDigitalSourceTypeValue' => $this->digitalSourceTypeAudioDigitalSourceTypeValue(),
                'digitalSourceTypeAudioDigitalSourceTypeValueSchema' => $this->digitalSourceTypeAudioDigitalSourceTypeValueSchema(),
                'digitalSourceTypeVideoDigitalSourceTypeValue' => $this->digitalSourceTypeVideoDigitalSourceTypeValue(),
                'digitalSourceTypeVideoDigitalSourceTypeValueSchema' => $this->digitalSourceTypeVideoDigitalSourceTypeValueSchema(),
                default => throw new InvalidArgumentException("Unknown method: {$method}"),
            };
        }
    };
}

it('merges parent, credit text, and all digital source keys without duplicates and reindexed as a list', function (): void {
    $expectedDigitalSourceKeys = [];

    foreach (IptcDigitalSourceValueResolver::digitalSourceTypeGroupHandles() as $handle) {
        $pair = IptcDigitalSourceValueResolver::computedKeysForGroupHandle($handle);
        $expectedDigitalSourceKeys[] = $pair['value'];
        $expectedDigitalSourceKeys[] = $pair['schema'];
    }

    // Include duplicates across parent, credit, and digital source keys so both
    // `array_unique()` and `array_values()` are load-bearing.
    $harness = makeIptcDigitalSourceHarness(
        parentKeys: ['title', 'credit_text', 'digital_source_type_value', 'alt'],
        creditKeys: ['credit_text', 'credit_source'],
    );

    $expected = array_values(array_unique([
        'title',
        'credit_text',
        'digital_source_type_value',
        'alt',
        'credit_source',
        ...$expectedDigitalSourceKeys,
    ]));

    expect($harness->keys())->toBe($expected);
});

it('falls back to an empty parent key list when parent::keys() is not an array', function (): void {
    $expectedDigitalSourceKeys = [];

    foreach (IptcDigitalSourceValueResolver::digitalSourceTypeGroupHandles() as $handle) {
        $pair = IptcDigitalSourceValueResolver::computedKeysForGroupHandle($handle);
        $expectedDigitalSourceKeys[] = $pair['value'];
        $expectedDigitalSourceKeys[] = $pair['schema'];
    }

    $harness = makeIptcDigitalSourceHarness(
        parentKeys: 'not-an-array',
        creditKeys: ['credit_text'],
    );

    expect($harness->keys())->toBe(['credit_text', ...$expectedDigitalSourceKeys]);
});

it('resolves the value key and schema URL for each digital source group handle', function (
    string $groupHandle,
    string $valueMethod,
    string $schemaMethod,
): void {
    $harness = makeIptcDigitalSourceHarness(
        data: [
            $groupHandle => ['preset' => 'digitalCapture_none'],
        ],
    );

    expect(method_exists($harness, 'callComputed'))->toBeTrue();

    /** @var callable(string): string $invoke */
    $invoke = [$harness, 'callComputed'];

    expect($invoke($valueMethod))->toBe('digitalCapture')
        ->and($invoke($schemaMethod))->toBe('https://schema.org/DigitalCaptureDigitalSource');
})->with([
    'primary' => ['digital_source_type', 'digitalSourceTypeValue', 'digitalSourceTypeValueSchema'],
    'text' => ['digital_source_type_text', 'digitalSourceTypeTextDigitalSourceTypeValue', 'digitalSourceTypeTextDigitalSourceTypeValueSchema'],
    'portrait' => ['digital_source_type_portrait', 'digitalSourceTypePortraitDigitalSourceTypeValue', 'digitalSourceTypePortraitDigitalSourceTypeValueSchema'],
    'image' => ['digital_source_type_image', 'digitalSourceTypeImageDigitalSourceTypeValue', 'digitalSourceTypeImageDigitalSourceTypeValueSchema'],
    'audio' => ['digital_source_type_audio', 'digitalSourceTypeAudioDigitalSourceTypeValue', 'digitalSourceTypeAudioDigitalSourceTypeValueSchema'],
    'video' => ['digital_source_type_video', 'digitalSourceTypeVideoDigitalSourceTypeValue', 'digitalSourceTypeVideoDigitalSourceTypeValueSchema'],
]);
