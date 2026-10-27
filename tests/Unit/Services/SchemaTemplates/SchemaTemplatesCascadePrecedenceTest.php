<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaBlueprintDefaultsRepository;
use App\Services\SchemaTemplates\SchemaSectionDefaultsRepository;
use App\Services\SchemaTemplates\SchemaSiteDefaultsRepository;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;

/**
 * Regression for the schema-templates cascade precedence (webpage_entry /
 * main_content_entry): the MOST-SPECIFIC non-inheriting level must win
 * (entry > blueprint > section > the site default). A Rector early-return
 * refactor once inverted this so the SHALLOWEST non-inheriting level won — a
 * defect invisible to every other test because none set an `inherit_*` flag,
 * and one that fed a wrong `@id` into rendered JSON-LD output.
 *
 * The resolve*Entry() methods are protected and depend only on their array
 * arguments plus the pure useCustomValue()/singleEntryId() helpers, so they are
 * exercised directly via reflection with stub repositories that are never
 * called.
 */
function cascadeResolver(string $method): Closure
{
    $service = new SchemaTemplatesCascadeService(
        Mockery::mock(SchemaSiteDefaultsRepository::class),
        Mockery::mock(SchemaSectionDefaultsRepository::class),
        Mockery::mock(SchemaBlueprintDefaultsRepository::class),
    );

    $reflection = new ReflectionMethod($service, $method);

    return function (array $site, array $section, array $blueprint, array $entry) use ($reflection, $service): ?string {
        /** @var string|null $result */
        $result = $reflection->invoke($service, $site, $section, $blueprint, $entry);

        return $result;
    };
}

dataset('cascade_methods', [
    // [resolver method, inherit_* flag key, value key]
    'webpage_entry' => ['resolveWebpageEntry', 'inherit_webpage_entry', 'webpage_entry'],
    'main_content_entry' => ['resolveMainContentEntry', 'inherit_main_content_entry', 'main_content_entry'],
]);

it('lets the most-specific non-inheriting level win', function (string $method, string $inheritKey, string $valueKey) {
    $resolve = cascadeResolver($method);

    $site = [$valueKey => 'site-id'];
    $section = [$inheritKey => false, $valueKey => 'section-id'];
    $blueprint = [$inheritKey => false, $valueKey => 'blueprint-id'];
    $entry = [$inheritKey => false, $valueKey => 'entry-id'];

    // Every level overrides at once: the deepest (entry) must win.
    // The inverted bug returned 'section-id' here.
    expect($resolve($site, $section, $blueprint, $entry))->toBe('entry-id');

    // Entry inherits, blueprint overrides: blueprint wins over section.
    // The inverted bug returned 'section-id' here.
    expect($resolve($site, $section, $blueprint, []))->toBe('blueprint-id');

    // Only the section overrides: section wins over the site default.
    expect($resolve($site, $section, [], []))->toBe('section-id');
})->with('cascade_methods');

it('falls back to the site default when every level inherits', function (string $method, string $inheritKey, string $valueKey) {
    $resolve = cascadeResolver($method);

    expect($resolve([$valueKey => 'site-id'], [], [], []))->toBe('site-id');
})->with('cascade_methods');
