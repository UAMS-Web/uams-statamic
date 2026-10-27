<?php

declare(strict_types=1);

use App\Statamic\Eloquent\Assets\Asset;
use Tests\TestCase;

/**
 * Mutation: 100% on App\Statamic\Eloquent\Assets\Asset (27 mutants, --covered-only).
 */
uses(TestCase::class);

it('returns normalized integer when meta contains bitrate', function (): void {
    // Build an asset test double with controllable meta values.
    $asset = new class extends Asset
    {
        public mixed $metaBitrate = null;

        /**
         * @param  mixed  $key
         * @return mixed
         */
        public function meta($key = null)
        {
            // Return configured bitrate for bitrate key requests.
            if ($key === 'bitrate') {
                return $this->metaBitrate;
            }

            // Return an empty array for full metadata requests.
            return [];
        }
    };

    // Set floating-point bitrate to verify normalization.
    $asset->metaBitrate = 191999.6;

    // Read normalized bitrate from the asset API.
    $bitrate = $asset->bitrate();

    // Assert bitrate is normalized to integer bits per second.
    expect($bitrate)->toBe(192000);
});

it('returns null when meta bitrate is missing or invalid', function (): void {
    // Build an asset test double with controllable meta values.
    $asset = new class extends Asset
    {
        public mixed $metaBitrate = null;

        /**
         * @param  mixed  $key
         * @return mixed
         */
        public function meta($key = null)
        {
            // Return configured bitrate for bitrate key requests.
            if ($key === 'bitrate') {
                return $this->metaBitrate;
            }

            // Return an empty array for full metadata requests.
            return [];
        }
    };

    // Set an invalid bitrate value and assert null.
    $asset->metaBitrate = 0;
    expect($asset->bitrate())->toBeNull();

    // Set a non-numeric bitrate value and assert null.
    $asset->metaBitrate = 'invalid';
    expect($asset->bitrate())->toBeNull();
});

it('prefers the audio bitrate key when available', function (): void {
    // Build an asset test double that exposes the protected bitrate resolver.
    $asset = new class extends Asset
    {
        /**
         * @param  array<string, mixed>  $analysis
         */
        public function resolve(array $analysis): ?int
        {
            // Delegate to the protected resolver under test.
            return $this->resolveBitrateFromAnalysis($analysis);
        }
    };

    // Build analysis data with both bitrate keys populated.
    $analysis = [
        'audio' => [
            'bitrate' => 192000,
        ],
        'bitrate' => 160000,
    ];

    // Resolve bitrate from analysis data.
    $bitrate = $asset->resolve($analysis);

    // Assert the audio-specific bitrate value is preferred.
    expect($bitrate)->toBe(192000);
});

it('uses the generic bitrate key when the audio key is missing', function (): void {
    // Build an asset test double that exposes the protected bitrate resolver.
    $asset = new class extends Asset
    {
        /**
         * @param  array<string, mixed>  $analysis
         */
        public function resolve(array $analysis): ?int
        {
            // Delegate to the protected resolver under test.
            return $this->resolveBitrateFromAnalysis($analysis);
        }
    };

    // Build analysis data with only the generic bitrate key.
    $analysis = [
        'bitrate' => 128000,
    ];

    // Resolve bitrate from analysis data.
    $bitrate = $asset->resolve($analysis);

    // Assert the generic bitrate value is returned.
    expect($bitrate)->toBe(128000);
});

it('returns null when resolver values are missing or invalid', function (): void {
    // Build an asset test double that exposes the protected bitrate resolver.
    $asset = new class extends Asset
    {
        /**
         * @param  array<string, mixed>  $analysis
         */
        public function resolve(array $analysis): ?int
        {
            // Delegate to the protected resolver under test.
            return $this->resolveBitrateFromAnalysis($analysis);
        }
    };

    // Build analysis data without a valid bitrate value.
    $analysis = [
        'audio' => [
            'bitrate' => 'not-a-number',
        ],
        'bitrate' => 0,
    ];

    // Resolve bitrate from analysis data.
    $bitrate = $asset->resolve($analysis);

    // Assert that no bitrate is returned.
    expect($bitrate)->toBeNull();
});
