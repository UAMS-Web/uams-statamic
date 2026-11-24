<?php

declare(strict_types=1);

use App\Cache\SchemaTypeAliasCacheWarmer;
use App\Cache\ScopeAliasCache;
use App\Scopes\EntriesBySchemaType;
use Illuminate\Support\Facades\Cache;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Addon;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Statamic\Fields\FieldtypeRepository;
use Tests\TestCase;
use UAMSWeb\Aether\Enums\Generation\Schema\AliasCacheWarmer;
use UAMSWeb\Aether\Enums\Generation\Schema\SchemaTypeEnumGenerator;
use UAMSWeb\Aether\Enums\ServiceProvider as EnumsServiceProvider;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Subsection;
use UAMSWeb\Aether\Fieldtypes\Ownership\Claim;
use UAMSWeb\Aether\Fieldtypes\Ownership\OwnerMap;
use UAMSWeb\Aether\Fieldtypes\Ownership\OwnershipConflict;
use UAMSWeb\Aether\Fieldtypes\ServiceProvider as FieldtypesServiceProvider;

/**
 * Proves the site boots with the shared enums and fieldtype add-ons installed
 * (UAMS-Web/uams-statamic#995), with all four fieldtype families served from the
 * fieldtype add-on (UAMS-Web/uams-statamic#996 to #999).
 *
 * The add-ons' own suites test the moved classes. These pin only what the site
 * contributes: that both packages are registered and their providers ran, that the
 * foundation's `subsection` fieldtype comes from the add-on, which handles the add-on
 * claims, that no fieldtype handle or Control Panel component name has two owners, that
 * no site fieldtype still claims an add-on handle, and that the enum generator reaches
 * the site's alias cache through the binding the site makes.
 *
 * @command  php artisan test --compact tests/Feature/Addons/FieldtypeFoundationAddonsTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

it('registers each foundation add-on and boots its service provider', function (string $package, string $provider): void {
    expect(Addon::get($package))->not->toBeNull()
        ->and(app()->getProviders($provider))->not->toBeEmpty();
})->with([
    'enums' => ['uams-web/aether-enums', EnumsServiceProvider::class],
    'fieldtypes' => ['uams-web/aether-fieldtypes', FieldtypesServiceProvider::class],
]);

it('resolves the subsection handle to the fieldtype add-on\'s class', function (): void {
    $fieldtype = app(FieldtypeRepository::class)->find('subsection');

    expect($fieldtype)->toBeInstanceOf(Subsection::class)
        ->and(get_debug_type($fieldtype))->toBe(Subsection::class);
});

it('leaves every fieldtype handle and component name with one owner across the installed add-ons', function (): void {
    $map = OwnerMap::fromInstalledAddons();

    // The map must have read the fieldtype add-on, or an empty claim list would pass
    $fieldtypeClaims = array_filter(
        $map->claims(),
        fn (Claim $claim): bool => $claim->owner === 'uams-web/aether-fieldtypes' && $claim->kind === Claim::FIELDTYPE,
    );

    expect(array_map(fn (Claim $claim): string => $claim->name, array_values($fieldtypeClaims)))->toBe([
        'address', 'assets', 'audio', 'checkboxes', 'consent', 'css_selector', 'currency', 'date',
        'date_field_select', 'email', 'entries', 'float', 'gps', 'hidden', 'html', 'identifier',
        'integer', 'list', 'mask_field', 'month', 'name', 'option', 'partial_date', 'password',
        'phone', 'product', 'radio', 'replicator', 'section', 'select', 'signature', 'subsection',
        'terms', 'terms_search_index', 'text', 'time', 'total', 'trigger', 'true_false', 'url',
        'username', 'video', 'week', 'xpath', 'zip_code',
    ])
        ->and($map->conflicts())->toBe([]);

    $map->assertSingleOwners();
});

it('serves each add-on handle through a field as the add-on\'s class, not one cached during boot', function (): void {
    $handles = [];

    foreach (OwnerMap::fromInstalledAddons()->claims() as $claim) {
        if ($claim->owner === 'uams-web/aether-fieldtypes' && $claim->kind === Claim::FIELDTYPE) {
            $handles[] = $claim->name;
        }
    }

    expect($handles)->toHaveCount(45);

    // A field resolves through the facade's per-handle cache, which a lookup during
    // provider boot fills before the add-on registers (UAMS-Web/uams-statamic#996)
    $served = [];

    foreach ($handles as $handle) {
        $class = get_debug_type(new Field('choices', ['type' => $handle])->fieldtype());

        if (! str_starts_with($class, 'UAMSWeb\\Aether\\Fieldtypes\\')) {
            $served[] = $handle.' => '.$class;
        }
    }

    expect($served)->toBe([]);
});

it('reports a handle the site and an add-on both register, so the owner test can fail', function (): void {
    $map = new OwnerMap([
        new Claim('uams-web/aether-fieldtypes', Claim::FIELDTYPE, 'subsection'),
        new Claim('uams-statamic', Claim::FIELDTYPE, 'subsection'),
    ]);

    expect(fn () => $map->assertSingleOwners())->toThrow(OwnershipConflict::class);
});

it('registers no site fieldtype under a handle an installed add-on owns', function (): void {
    $addonHandles = [];

    foreach (OwnerMap::fromInstalledAddons()->claims() as $claim) {
        if ($claim->kind === Claim::FIELDTYPE) {
            $addonHandles[$claim->name] = $claim->owner;
        }
    }

    $siteFiles = glob(app_path('Fieldtypes/*.php')) ?: [];
    expect($siteFiles)->not->toBeEmpty();

    $clashes = [];

    foreach ($siteFiles as $file) {
        $class = 'App\\Fieldtypes\\'.basename($file, '.php');

        if (is_subclass_of($class, Fieldtype::class) && ! new ReflectionClass($class)->isAbstract()) {
            $handle = $class::handle();

            if (\is_string($handle) && isset($addonHandles[$handle])) {
                $clashes[] = $handle.' ('.$class.' and '.$addonHandles[$handle].')';
            }
        }
    }

    expect($clashes)->toBe([]);
});

it('hands the schema type enum generator the site\'s alias cache warmer', function (): void {
    expect(get_debug_type(app(AliasCacheWarmer::class)))->toBe(SchemaTypeAliasCacheWarmer::class);

    $generator = app(SchemaTypeEnumGenerator::class);
    $warmer = new ReflectionProperty($generator, 'aliasCacheWarmer');

    expect($warmer->getValue($generator))->toBeInstanceOf(SchemaTypeAliasCacheWarmer::class);
});

it('warms the entries-by-schema-type alias list from the generated type names', function (): void {
    $expected = ['entries_by_schema_type_thing', 'entries_by_schema_type_medical_clinic'];

    // Both stores are mocks: the alias list is shared between test processes
    $store = Mockery::mock(ScopeAliasCache::class);
    $store->shouldReceive('put')->once()->with(EntriesBySchemaType::CACHE_KEY_ALIASES, $expected);
    app()->instance(ScopeAliasCache::class, $store);

    Cache::shouldReceive('put')
        ->once()
        ->withArgs(fn (string $key, array $value): bool => $key === EntriesBySchemaType::CACHE_KEY_ALIASES && $value === $expected);

    new SchemaTypeAliasCacheWarmer()->warm(['Thing', 'MedicalClinic']);
});
