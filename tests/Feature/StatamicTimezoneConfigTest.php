<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Aligns with Statamic 6 guidance: UTC for Laravel, display zone in Statamic.
 *
 * No mutable PHP surface under test: this asserts framework/package
 * configuration values only, so there is no App\ class to target for mutation.
 */
it('keeps the app timezone at UTC and the Statamic display timezone at Chicago', function (): void {
    expect(config('app.timezone'))->toBe('UTC');
    expect(config('statamic.system.display_timezone'))->toBe('America/Chicago');
});
