<?php

declare(strict_types=1);

use App\Support\ContentRoot;
use Tests\TestCase;

uses(TestCase::class);

covers(ContentRoot::class);

/**
 * @covers \App\Support\ContentRoot
 *
 * @command  php artisan test --compact tests/Unit/Support/ContentRootTest.php
 */
it('resolves the testing mirror under storage when the environment is testing', function (): void {
    expect(ContentRoot::path('testing'))->toBe(
        storage_path('framework/testing/content-mirror')
    );
});

it('resolves the content submodule for any non-testing environment', function (string|bool|null $environment): void {
    expect(ContentRoot::path($environment))->toBe(base_path('content'));
})->with([
    'production' => ['production'],
    'local' => ['local'],
    'null' => [null],
    'bool true' => [true],
]);
