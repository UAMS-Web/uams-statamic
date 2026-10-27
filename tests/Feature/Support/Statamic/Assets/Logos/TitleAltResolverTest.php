<?php

declare(strict_types=1);

use App\Support\Statamic\Assets\Logos\TitleAltResolver;
use Tests\TestCase;

/**
 * Unit tests for logos container title/alt resolution rules.
 *
 * UAMS `title` expectations use option labels resolved from the live `logos`
 * asset blueprint (see LogosBlueprintOptionLabels).
 *
 * Mutation: 100% on App\Support\Statamic\Assets\Logos\TitleAltResolver (112 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Support/Statamic/Assets/Logos/TitleAltResolverTest.php
 */
uses(TestCase::class);

it('external organization branch sets title and alt formatter', function () {
    // Create the data array for the test
    $data = [
        'uams_logo_query' => false,
        'external_organization_name' => "  Children's Health  ",
    ];

    // Check if the title matches the expected value
    expect(TitleAltResolver::title($data))->toBe("Children's Health");

    // Check if the alt matches the expected value
    expect(TitleAltResolver::alt($data))->toBe("Logo of Children's Health");
});

it('uams branch with special application title and alt', function () {
    // Create the data array for the test
    $data = [
        'uams_logo_query' => true,
        'base_logo' => 'master',
        'application_query' => true,
        'application' => 'For a Better State of Health',
        'orientation' => 'horizontal',
        'color_scheme' => 'dark',
    ];

    // Check if the title matches the expected value
    expect(TitleAltResolver::title($data))->toBe(
        'UAMS — For a Better State of Health — Horizontal — Dark'
    );

    // Check if the alt matches the expected value
    expect(TitleAltResolver::alt($data))->toBe('Logo of UAMS, For a Better State of Health');
});

it('uams branch with major and sub entity title and alt', function () {
    // Create the data array for the test
    $data = [
        'uams_logo_query' => true,
        'base_logo' => 'master-clinical',
        'application_query' => false,
        'major_entity_query' => true,
        'major_entity' => 'College of Medicine',
        'sub_entity_query' => true,
        'sub_entity' => 'Department of Pediatrics',
        'orientation' => 'vertical',
        'color_scheme' => 'light',
    ];

    // Check if the title matches the expected value
    expect(TitleAltResolver::title($data))->toBe(
        'UAMS Health — College of Medicine — Department of Pediatrics — Vertical — Light'
    );

    // Check if the alt matches the expected value
    expect(TitleAltResolver::alt($data))->toBe(
        'Logo of UAMS Health College of Medicine Department of Pediatrics'
    );
});

it('uams branch sub entity without major when flags allow', function () {
    // Create the data array for the test
    $data = [
        'uams_logo_query' => true,
        'base_logo' => 'master',
        'application_query' => false,
        'major_entity_query' => false,
        'sub_entity_query' => true,
        'sub_entity' => 'Only Sub',
        'orientation' => 'both',
        'color_scheme' => 'white',
    ];

    // Check if the title matches the expected value
    expect(TitleAltResolver::title($data))->toBe('UAMS — Only Sub — Both — All White');

    // Check if the alt matches the expected value
    expect(TitleAltResolver::alt($data))->toBe('Logo of UAMS Only Sub');
});

it('uams truthy string and minimal title', function () {
    // Create the data array for the test
    $data = [
        'uams_logo_query' => '1',
        'orientation' => 'horizontal',
        'color_scheme' => 'red',
    ];

    // Check if the title matches the expected value
    expect(TitleAltResolver::title($data))->toBe('Horizontal — All Red');

    // Check if the alt matches the expected value
    expect(TitleAltResolver::alt($data))->toBe('Logo of ');
});
