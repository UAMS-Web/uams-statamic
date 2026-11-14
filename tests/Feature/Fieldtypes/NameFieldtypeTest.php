<?php

declare(strict_types=1);

use App\Fieldtypes\Name;
use Tests\TestCase;

uses(TestCase::class);

covers(Name::class);

/**
 * How the `Name` fieldtype presents itself: the Vue component, the icon, and that
 * it can be added to a form blueprint (UAMS-Web/uams-statamic#312). Its subfields
 * belong to the `HasSubfields` trait's tests.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/NameFieldtypeTest.php
 */
it('presents as the name component with the users icon, selectable in forms', function (): void {
    $fieldtype = new Name;

    expect($fieldtype->component())->toBe('name')
        ->and($fieldtype->icon())->toBe('fieldtype-users')
        ->and($fieldtype->selectableInForms())->toBeTrue();
});

it('registers under the name handle', function (): void {
    expect(app('statamic.fieldtypes')->get('name'))->toBe(Name::class);
});
