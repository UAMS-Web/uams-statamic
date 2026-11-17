<?php

declare(strict_types=1);

use App\Fieldtypes\Section;
use Statamic\Fieldtypes\Section as BaseSection;
use Tests\TestCase;

uses(TestCase::class);

covers(Section::class);

/**
 * The application `Section` fieldtype differs from Statamic's in one setting: it
 * can be added to a form blueprint (UAMS-Web/uams-statamic#312).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/SectionFieldtypeTest.php
 */
it('is selectable in form blueprints, where Statamic\'s section is not', function (): void {
    expect((new Section)->selectableInForms())->toBeTrue()
        ->and((new BaseSection)->selectableInForms())->toBeFalse();
});

it('registers the application class as the section handle', function (): void {
    expect(app('statamic.fieldtypes')->get('section'))->toBe(Section::class);
});
