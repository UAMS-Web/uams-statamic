<?php

declare(strict_types=1);

use App\Fieldtypes\Subsection;
use Tests\TestCase;

uses(TestCase::class);

covers(Subsection::class);

/**
 * The `Subsection` fieldtype, the application's replacement for Statamic's
 * deprecated Section: a heading inside a Panel Card that stores no value
 * (UAMS-Web/uams-statamic#312).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/SubsectionFieldtypeTest.php
 */
it('presents as a special subsection field with the section icon', function (): void {
    $fieldtype = new Subsection;

    expect(Subsection::title())->toBe('Subsection')
        ->and($fieldtype->component())->toBe('subsection')
        ->and($fieldtype->icon())->toBe('fieldtype-section')
        ->and($fieldtype->categories())->toBe(['special'])
        ->and($fieldtype->keywords())->toBe([
            'divider',
            'header',
            'heading',
            'section',
            'sub-section',
            'subsection',
            'subtitle',
            'title',
        ]);
});

it('holds no value, so it is neither localizable, validatable nor defaultable', function (): void {
    $fieldtype = new Subsection;

    expect($fieldtype->localizable())->toBeFalse()
        ->and($fieldtype->validatable())->toBeFalse()
        ->and($fieldtype->defaultable())->toBeFalse();
});

it('registers under the subsection handle', function (): void {
    expect(app('statamic.fieldtypes')->get('subsection'))->toBe(Subsection::class);
});
