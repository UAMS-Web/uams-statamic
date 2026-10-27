<?php

declare(strict_types=1);

use App\Fieldtypes\Audio;
use App\Fieldtypes\Video;
use Statamic\Fieldtypes\Video as BaseVideo;
use Tests\TestCase;

uses(TestCase::class);

covers(Audio::class);

/**
 * Unit tests for {@see Audio} (UAMS-Web/uams-statamic-archive#590).
 *
 * Like {@see Video}, the class only overrides constant CP handles
 * (component/icon) + the keywords list, so Pest reports no covered mutants
 * (--covered-only). The Vue `<audio>` render has no automated coverage — the same
 * gap as the custom Video precedent — so verify it manually in the control panel.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/AudioFieldtypeTest.php
 */
it('extends the statamic video fieldtype for url-storage config parity', function () {
    // Asserted through class_parents() rather than an instance: the parentage is the
    // contract, and an instanceof check over a `new Audio` is one PHPStan can fold
    // away statically, leaving an assertion that cannot fail.
    expect(class_parents(Audio::class))->toContain(BaseVideo::class);
});

it('targets the cp registration with the custom_audio component handle', function () {
    expect((new Audio)->component())->toBe('custom_audio');
});

it('registers a new, additive audio handle (does not supersede a core fieldtype)', function () {
    expect(app('statamic.fieldtypes')->get('audio'))->toBe(Audio::class);
});

it('uses an icon that exists in the control-panel icon set', function () {
    expect((new Audio)->icon())->toBe('media-music-notes');
});

it('is discoverable via audio keywords in the fieldtype selector', function () {
    expect((new Audio)->keywords())->toContain('audio')->toContain('mp3');
});
