<?php

declare(strict_types=1);

use App\Fieldtypes\Video;
use Statamic\Fieldtypes\Video as BaseVideo;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for {@see Video}.
 *
 * Mutation: no mutable surface on App\Fieldtypes\Video — the class only
 * overrides component() to return the constant 'custom_video' CP handle, so
 * Pest reports no covered mutants (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/VideoFieldtypeTest.php
 */
it('extends statamic video fieldtype for config parity', function () {
    // Asserted through class_parents() rather than an instance: the parentage is the
    // contract, and an instanceof check over a `new Video` is one PHPStan can fold
    // away statically, leaving an assertion that cannot fail.
    expect(class_parents(Video::class))->toContain(BaseVideo::class);
});

it('targets the cp registration with the custom_video component handle', function () {
    expect((new Video)->component())->toBe('custom_video');
});

it('resolves the video handle to the app class', function () {
    expect(app('statamic.fieldtypes')->get('video'))->toBe(Video::class);
});
