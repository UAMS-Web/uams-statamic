<?php

declare(strict_types=1);

use App\Support\Statamic\Assets\Logos\TitleAltResolver;

covers(TitleAltResolver::class);

/**
 * Pure unit coverage for {@see TitleAltResolver} with injected option label maps.
 *
 * The live `logos` blueprint fallback (`$selectOptionLabelMaps === null`) is covered
 * by `tests/Feature/Support/Statamic/Assets/Logos/TitleAltResolverTest.php`.
 *
 * @command  php artisan test --compact tests/Unit/Support/Statamic/Assets/Logos/TitleAltResolverTest.php
 */
const LOGO_LABEL_MAPS = [
    'orientation' => [
        'horizontal' => 'Horizontal',
        'vertical' => 'Vertical',
        'both' => 'Both',
    ],
    'color_scheme' => [
        'dark' => 'Dark',
        'light' => 'Light',
        'white' => 'All White',
        'red' => 'All Red',
    ],
];

it('resolves external organization title and alt when uams_logo_query is falsy', function (mixed $flag): void {
    $data = [
        'uams_logo_query' => $flag,
        'external_organization_name' => "  Children's Health  ",
    ];

    expect(TitleAltResolver::title($data, LOGO_LABEL_MAPS))->toBe("Children's Health")
        ->and(TitleAltResolver::alt($data))->toBe("Logo of Children's Health");
})->with([
    'false' => [false],
    'null' => [null],
    'int 0' => [0],
    'int 2' => [2],
    'string 0' => ['0'],
    'string false' => ['false'],
    'string no' => ['no'],
]);

it('returns null title and alt for external organization when name is missing, blank, or non-string', function (mixed $name): void {
    $data = [
        'uams_logo_query' => false,
        'external_organization_name' => $name,
    ];

    expect(TitleAltResolver::title($data, LOGO_LABEL_MAPS))->toBeNull()
        ->and(TitleAltResolver::alt($data))->toBeNull();
})->with([
    'null' => [null],
    'empty string' => [''],
    'whitespace only' => ['   '],
    'integer' => [123],
    'array' => [['not', 'string']],
]);

it('recognizes all truthy uams_logo_query encodings', function (mixed $flag): void {
    $data = [
        'uams_logo_query' => $flag,
        'base_logo' => 'master',
        'orientation' => 'horizontal',
        'color_scheme' => 'red',
    ];

    expect(TitleAltResolver::title($data, LOGO_LABEL_MAPS))->toBe('UAMS — Horizontal — All Red')
        ->and(TitleAltResolver::alt($data))->toBe('Logo of UAMS');
})->with([
    'bool true' => [true],
    'int 1' => [1],
    'string 1' => ['1'],
    'string true' => ['true'],
    'string yes' => ['yes'],
]);

it('uses injected option label maps rather than loading the blueprint', function (): void {
    $customMaps = [
        'orientation' => ['horizontal' => 'Custom Horiz'],
        'color_scheme' => ['dark' => 'Custom Dark'],
    ];

    $data = [
        'uams_logo_query' => true,
        'base_logo' => 'master',
        'orientation' => 'horizontal',
        'color_scheme' => 'dark',
    ];

    expect(TitleAltResolver::title($data, $customMaps))->toBe('UAMS — Custom Horiz — Custom Dark');
});

it('returns null title and bare prefix alt when every UAMS title segment is empty', function (): void {
    $data = [
        'uams_logo_query' => true,
    ];

    expect(TitleAltResolver::title($data, LOGO_LABEL_MAPS))->toBeNull()
        ->and(TitleAltResolver::alt($data))->toBe('Logo of ');
});

it('filters empty string labels returned from option maps', function (): void {
    $maps = [
        'orientation' => ['horizontal' => ''],
        'color_scheme' => ['dark' => 'Dark'],
    ];

    $data = [
        'uams_logo_query' => true,
        'orientation' => 'horizontal',
        'color_scheme' => 'dark',
    ];

    expect(TitleAltResolver::title($data, $maps))->toBe('Dark');
});

it('ignores non-string, empty, and unknown option map keys', function (mixed $key): void {
    /** @var array<string, array<string, string>> $maps */
    $maps = [
        'orientation' => [
            '' => 'Should Not Match Empty',
            '1' => 'Should Not Match Int',
        ],
        'color_scheme' => ['dark' => 'Dark'],
    ];

    $data = [
        'uams_logo_query' => true,
        'orientation' => $key,
        'color_scheme' => 'dark',
    ];

    expect(TitleAltResolver::title($data, $maps))->toBe('Dark');
})->with([
    'empty string' => [''],
    'integer 1' => [1],
    'array' => [['horizontal']],
    'unknown key' => ['diagonal'],
]);

it('resolves special application branch and suppresses major and sub entities even when their toggles are set', function (): void {
    $data = [
        'uams_logo_query' => true,
        'base_logo' => 'master',
        'application_query' => true,
        'application' => '  For a Better State of Health  ',
        'major_entity_query' => true,
        'major_entity' => 'Ignored Major',
        'sub_entity_query' => true,
        'sub_entity' => 'Ignored Sub',
        'orientation' => 'horizontal',
        'color_scheme' => 'dark',
    ];

    expect(TitleAltResolver::title($data, LOGO_LABEL_MAPS))
        ->toBe('UAMS — For a Better State of Health — Horizontal — Dark')
        ->and(TitleAltResolver::alt($data))
        ->toBe('Logo of UAMS, For a Better State of Health');
});

it('omits application text when application_query is falsy or application text is blank', function (): void {
    $disabled = [
        'uams_logo_query' => true,
        'base_logo' => 'master',
        'application_query' => false,
        'application' => 'Ignored App',
    ];

    expect(TitleAltResolver::title($disabled, LOGO_LABEL_MAPS))->toBe('UAMS')
        ->and(TitleAltResolver::alt($disabled))->toBe('Logo of UAMS');

    $blank = [
        'uams_logo_query' => true,
        'base_logo' => 'master',
        'application_query' => true,
        'application' => '   ',
    ];

    expect(TitleAltResolver::title($blank, LOGO_LABEL_MAPS))->toBe('UAMS')
        ->and(TitleAltResolver::alt($blank))->toBe('Logo of UAMS');
});

it('resolves clinical master base logo with major and sub entities', function (): void {
    $data = [
        'uams_logo_query' => true,
        'base_logo' => 'master-clinical',
        'application_query' => false,
        'major_entity_query' => true,
        'major_entity' => '  College of Medicine  ',
        'sub_entity_query' => true,
        'sub_entity' => '  Department of Pediatrics  ',
        'orientation' => 'vertical',
        'color_scheme' => 'light',
    ];

    expect(TitleAltResolver::title($data, LOGO_LABEL_MAPS))
        ->toBe('UAMS Health — College of Medicine — Department of Pediatrics — Vertical — Light')
        ->and(TitleAltResolver::alt($data))
        ->toBe('Logo of UAMS Health College of Medicine Department of Pediatrics');
});

it('omits major and sub entity text when their toggles are off or values are blank', function (): void {
    $togglesOff = [
        'uams_logo_query' => true,
        'base_logo' => 'master',
        'application_query' => false,
        'major_entity_query' => false,
        'major_entity' => 'Ignored Major',
        'sub_entity_query' => false,
        'sub_entity' => 'Ignored Sub',
    ];

    expect(TitleAltResolver::title($togglesOff, LOGO_LABEL_MAPS))->toBe('UAMS')
        ->and(TitleAltResolver::alt($togglesOff))->toBe('Logo of UAMS');

    $blankEntities = [
        'uams_logo_query' => true,
        'base_logo' => 'master',
        'application_query' => false,
        'major_entity_query' => true,
        'major_entity' => '   ',
        'sub_entity_query' => true,
        'sub_entity' => '',
    ];

    expect(TitleAltResolver::title($blankEntities, LOGO_LABEL_MAPS))->toBe('UAMS')
        ->and(TitleAltResolver::alt($blankEntities))->toBe('Logo of UAMS');
});
