<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
use App\Services\SchemaTemplates\SchemaTemplateNodeBuildStack;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;
use Statamic\Contracts\Query\Builder as StatamicQueryBuilderContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Data;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Link\ArrayableLink;
use Statamic\Sites\Site;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\Support\Doubles\SchemaOutputServiceTitledTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for SchemaOutputService (effective schema inheritance).
 *
 * Mutation: not recorded. An earlier figure here (100% on App\Services\SchemaTemplates\SchemaOutputService,
 * 1150 mutants, --covered-only) named no command or revision and was removed (UAMS-Web/uams-statamic-archive#2915).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceTest.php
 */

/**
 * Invoke the protected resolvePropertyValue method with emitter arrays by reference.
 *
 * @param  array<int, string>  $graphEntryIds  Entry ids already in the JSON-LD graph
 * @param  array<int, string>  $visitedIds  Entry ids currently visited while building nodes
 * @param  array<int, string>  $emittedEntryIds  Entry ids that already received full nodes (by reference)
 * @param  array<string, bool>  $emittedNodeIds  Node @id values already emitted (by reference)
 * @param  array<string, bool>  $materializedFullNodeAtIds  Full nodes known after shared dedupe (by reference)
 * @param  array<string, mixed>  $antlersContext  Extra variables merged into Antlers scope
 * @return mixed Resolved fragment suitable for JSON-LD output (reflection-typed)
 */
function schemaOutputServiceInvokeResolvePropertyValue(
    SchemaOutputService $service,
    mixed $value,
    mixed $contextEntity,
    array $graphEntryIds,
    array $visitedIds,
    array &$emittedEntryIds,
    array &$emittedNodeIds,
    array &$materializedFullNodeAtIds,
    array $antlersContext
): mixed {
    // Obtain the protected resolvePropertyValue method via reflection
    $method = (new ReflectionClass($service))->getMethod('resolvePropertyValue');

    // Invoke resolvePropertyValue with emitter arrays passed by reference
    return $method->invokeArgs(
        $service,
        [
            $value,
            $contextEntity,
            $graphEntryIds,
            $visitedIds,
            &$emittedEntryIds,
            &$emittedNodeIds,
            &$materializedFullNodeAtIds,
            $antlersContext,
            null,
        ]
    );
}

/**
 * Invoke the protected getEffectiveSchema method on the service.
 *
 * @param  array<int, string>  $visitedIds  Entry ids already visited (cycle detection)
 * @return mixed Effective type and properties array, or null when invalid (reflection-typed)
 */
function schemaOutputServiceInvokeGetEffectiveSchema(
    SchemaOutputService $service,
    EntryModel $entry,
    array $visitedIds
): mixed {
    $method = (new ReflectionClass($service))->getMethod('getEffectiveSchema');

    return $method->invoke($service, $entry, $visitedIds);
}

/**
 * Invoke the protected resolveGraphEntryIdsForEntity method on the service.
 *
 * @return array<array-key, mixed>
 */
function schemaOutputServiceInvokeResolveGraphEntryIdsForEntity(
    SchemaOutputService $service,
    EntryModel $entity
): array {
    // Get the method
    $method = (new ReflectionClass($service))->getMethod('resolveGraphEntryIdsForEntity');

    // Invoke the method
    $result = $method->invoke($service, $entity);

    // Return an empty array when the result is not an array
    if (! is_array($result)) {
        return [];
    }

    return $result;
}

/**
 * Invoke the protected resolveSingleReplicatorRowToValue method by reference.
 *
 * @param  array<string, mixed>  $row  One replicator row to resolve
 * @param  array<int, string>  $graphEntryIds  Entry ids already in the graph
 * @param  array<int, string>  $visitedIds  Entry ids currently being visited
 * @param  array<int, string>  $emittedEntryIds  Entry ids with full nodes emitted (by reference)
 * @param  array<string, bool>  $emittedNodeIds  Node @id values already emitted (by reference)
 * @param  array<string, bool>  $materializedFullNodeAtIds  Full nodes known after shared dedupe (by reference)
 * @param  array<string, mixed>  $antlersContext  Optional antlers context overrides
 * @return mixed Parsed row value, decoded JSON, or null (reflection-typed)
 */
function schemaOutputServiceInvokeResolveSingleReplicatorRowToValue(
    SchemaOutputService $service,
    array $row,
    mixed $contextEntity,
    array $graphEntryIds,
    array $visitedIds,
    array &$emittedEntryIds,
    array &$emittedNodeIds,
    array &$materializedFullNodeAtIds,
    array $antlersContext
): mixed {
    // Get the method
    $method = (new ReflectionClass($service))->getMethod('resolveSingleReplicatorRowToValue');

    // Invoke the method with emitter arrays passed by reference
    return $method->invokeArgs(
        $service,
        [
            $row,
            $contextEntity,
            $graphEntryIds,
            $visitedIds,
            &$emittedEntryIds,
            &$emittedNodeIds,
            &$materializedFullNodeAtIds,
            $antlersContext,
            null,
        ]
    );
}

/**
 * Mock taxonomy term used as the sole result of the credentials query.
 *
 * @return TermContract&SchemaOutputServiceTitledTerm&MockInterface Mock term whose `title()` returns M.D.
 */
function schemaOutputServiceTermsHydrationTermDouble(): TermContract&SchemaOutputServiceTitledTerm&MockInterface
{
    // Create a stand-in taxonomy term for the credentials query result
    $term = Mockery::mock(TermContract::class, SchemaOutputServiceTitledTerm::class);

    // Stub title() so Antlers can read an honorific-style suffix string
    $term->shouldReceive('title')->andReturn('M.D.');

    return $term;
}

/**
 * Mock Statamic query builder returning the given term from `get()`.
 *
 * @return StatamicQueryBuilderContract&MockInterface Mock builder for augmented credentials resolution
 */
function schemaOutputServiceTermsHydrationQueryDouble(
    TermContract $term
): StatamicQueryBuilderContract&MockInterface {
    // Create a stand-in Statamic query builder for augmented credentials
    $query = Mockery::mock(StatamicQueryBuilderContract::class);

    // Stub get() to mirror OrderedQueryBuilder hydration with one term
    $query->shouldReceive('get')->once()->andReturn(collect([$term]));

    return $query;
}

/**
 * Mock Statamic Value whose augmented `value()` is the term query builder.
 *
 * @return Value&MockInterface Mock field Value for the credentials handle
 */
function schemaOutputServiceTermsHydrationCredentialsValueDouble(
    StatamicQueryBuilderContract $query
): Value&MockInterface {
    // Create a stand-in Statamic field Value for the credentials handle
    $value = Mockery::mock(Value::class);

    // Stub value() to yield the term query builder, matching field augmentation
    $value->shouldReceive('value')->once()->andReturn($query);

    return $value;
}

/**
 * Mock blueprint field definition for a `terms_search_index` credentials field.
 *
 * @return Field&MockInterface Mock with handle `credentials` and type `terms_search_index`
 */
function schemaOutputServiceTermsHydrationCredentialsFieldDouble(): Field&MockInterface
{
    // Create a stand-in blueprint field row for credentials
    $field = Mockery::mock(Field::class);

    // Stub handle so hydration targets the credentials field name
    $field->shouldReceive('handle')->andReturn('credentials');

    // Stub type so hydration treats the field as terms_search_index
    $field->shouldReceive('type')->andReturn('terms_search_index');

    return $field;
}

/**
 * Mock Fields bag whose `all()` returns only the credentials field mock.
 *
 * @return Fields&MockInterface Mock fields collection for blueprint->fields()
 */
function schemaOutputServiceTermsHydrationFieldsDouble(
    Field $credentialsField
): Fields&MockInterface {
    // Create a stand-in Fields bag for blueprint->fields()
    $fields = Mockery::mock(Fields::class);

    // Stub all() for hydration walk plus overlay walk (same field list)
    $fields->shouldReceive('all')->twice()->andReturn(collect([$credentialsField]));

    return $fields;
}

/**
 * Mock blueprint whose `fields()` yields the credentials field setup.
 *
 * @return Blueprint&MockInterface Mock blueprint wired to fields and credentials field doubles
 */
function schemaOutputServiceTermsHydrationBlueprintDouble(): Blueprint&MockInterface
{
    // Create a stand-in blueprint for the entry stub
    $blueprint = Mockery::mock(Blueprint::class);

    // Stub fields() for hydration plus overlay passes (same Fields instance)
    $blueprint->shouldReceive('fields')->twice()->andReturn(
        schemaOutputServiceTermsHydrationFieldsDouble(
            schemaOutputServiceTermsHydrationCredentialsFieldDouble()
        )
    );

    // The podcast-episode signal (UAMS-Web/uams-statamic-archive#1611) reads `field('series')` off every entry
    // blueprint; a null field marks this a non-audio-series entry, so
    // `is_podcast_episode` stays false without a `Term::find` lookup.
    $blueprint->shouldReceive('field')->with('series')->andReturnNull();

    return $blueprint;
}

/**
 * Mock entry combining values(), blueprint, and augmented credentials.
 *
 * @param  string|list<string>  $credentialsRaw  Stored field value before hydration
 * @return EntryModel&MockInterface Partial mock with URL stubs for schema context merge
 */
function schemaOutputServiceTermsHydrationEntryDouble(
    Blueprint $blueprint,
    Value $credentialsValue,
    string|array $credentialsRaw = ['md']
): EntryModel&MockInterface {
    // Create a stand-in entry matching values()->all() shape before hydration
    $entry = Mockery::mock(EntryModel::class);

    // Stub values() with raw credential storage plus a distinct entry title
    $entry->shouldReceive('values')->once()->andReturn(collect([
        'credentials' => $credentialsRaw,
        'title' => 'Dr. Jane Q. Public',
    ]));

    // Stub blueprint() for terms hydration and overlay passes
    $entry->shouldReceive('blueprint')->andReturn($blueprint);

    // Stub augmentedValue for credentials with the provided Value double
    $entry->shouldReceive('augmentedValue')->with('credentials')->once()->andReturn($credentialsValue);

    // Stub absoluteUrl so mergeCanonicalUrlKeysIntoSchemaAntlersContext skips permalink
    $entry->shouldReceive('absoluteUrl')->andReturn(null);

    // Stub url so mergeCanonicalUrlKeysIntoSchemaAntlersContext skips relative url
    $entry->shouldReceive('url')->andReturn(null);

    return $entry;
}

/**
 * Build a mock entry for tests of schema Antlers terms-field hydration.
 *
 * @return EntryModel&MockInterface Partial mock with raw `credentials` slug list
 */
function schemaOutputServiceEntryMockForTermsHydration(): EntryModel&MockInterface
{
    // Wire blueprint, augmented Value, and term query doubles into one entry
    return schemaOutputServiceTermsHydrationEntryDouble(
        schemaOutputServiceTermsHydrationBlueprintDouble(),
        schemaOutputServiceTermsHydrationCredentialsValueDouble(
            schemaOutputServiceTermsHydrationQueryDouble(
                schemaOutputServiceTermsHydrationTermDouble()
            )
        ),
        ['md']
    );
}

/**
 * Same hydration wiring but credentials stored as one scalar slug string.
 *
 * @return EntryModel&MockInterface Entry mock with `credentials` as a scalar string
 */
function schemaOutputServiceEntryMockForTermsHydrationWithScalarSlug(): EntryModel&MockInterface
{
    // Wire the same doubles while augmented context uses a string slug
    return schemaOutputServiceTermsHydrationEntryDouble(
        schemaOutputServiceTermsHydrationBlueprintDouble(),
        schemaOutputServiceTermsHydrationCredentialsValueDouble(
            schemaOutputServiceTermsHydrationQueryDouble(
                schemaOutputServiceTermsHydrationTermDouble()
            )
        ),
        'md'
    );
}

/**
 * Replicator field double for nested `job_title` augmentation tests.
 */
function schemaOutputServiceNestedJobTitleReplicatorFieldDouble(): Field&MockInterface
{
    $field = Mockery::mock(Field::class);
    $field->shouldReceive('handle')->andReturn('job_title');
    $field->shouldReceive('type')->andReturn('replicator');

    return $field;
}

/**
 * schema_object field double that must be skipped during overlay augmentation.
 */
function schemaOutputServiceNestedJobTitleSchemaObjectFieldDouble(): Field&MockInterface
{
    $field = Mockery::mock(Field::class);
    $field->shouldReceive('handle')->andReturn('subject_schema');
    $field->shouldReceive('type')->andReturn('schema_object');

    return $field;
}

/**
 * Fields bag double listing the replicator and schema_object fields.
 */
function schemaOutputServiceNestedJobTitleOverlayFieldsDouble(): Fields&MockInterface
{
    $fields = Mockery::mock(Fields::class);
    $fields->shouldReceive('all')->twice()->andReturn(
        collect([
            schemaOutputServiceNestedJobTitleReplicatorFieldDouble(),
            schemaOutputServiceNestedJobTitleSchemaObjectFieldDouble(),
        ])
    );

    return $fields;
}

/**
 * Blueprint double wired to the nested job_title overlay fields bag.
 */
function schemaOutputServiceNestedJobTitleOverlayBlueprintDouble(): Blueprint&MockInterface
{
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('fields')->twice()->andReturn(
        schemaOutputServiceNestedJobTitleOverlayFieldsDouble()
    );

    // The podcast-episode signal (UAMS-Web/uams-statamic-archive#1611) reads `field('series')` off every entry
    // blueprint; a null field marks this a non-audio-series entry, so
    // `is_podcast_episode` stays false without a `Term::find` lookup.
    $blueprint->shouldReceive('field')->with('series')->andReturnNull();

    return $blueprint;
}

/**
 * Augmented job_title Value double yielding a composed role_title row.
 */
function schemaOutputServiceNestedJobTitleOverlayAugmentedJobTitleValueDouble(): Value&MockInterface
{
    $nestedTitle = [
        'value' => 'Chancellor',
        'configuration' => 'institutional_leadership',
    ];
    $jobTitleAugmented = [
        [
            'id' => 'set-id-1',
            'type' => 'institutional_leadership_role',
            'title' => $nestedTitle,
        ],
    ];

    $value = Mockery::mock(Value::class);
    $value->shouldReceive('value')->once()->andReturn($jobTitleAugmented);

    return $value;
}

/**
 * Raw values() collection for the nested job_title overlay entry double.
 *
 * @return Collection<string, mixed>
 */
function schemaOutputServiceNestedJobTitleEntryValuesCollection(): Collection
{
    /** @var Collection<string, mixed> $values */
    $values = collect([
        'job_title' => [['id' => 'set-id-1', 'type' => 'institutional_leadership_role']],
        'subject_schema' => ['schema_objects_entry' => '00000000-0000-0000-0000-000000000099'],
        'title' => 'Dr. Example',
    ]);

    return $values;
}

/**
 * Entry double combining values(), blueprint, and augmented job_title.
 *
 * @param  Collection<string, mixed>  $values
 */
function schemaOutputServiceNestedJobTitleStubEntry(
    Blueprint $blueprint,
    Value $jobTitleValue,
    Collection $values
): EntryModel&MockInterface {
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('values')->once()->andReturn($values);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('augmentedValue')->with('job_title')->once()->andReturn($jobTitleValue);
    $entry->shouldNotReceive('augmentedValue')->with('subject_schema');
    $entry->shouldReceive('absoluteUrl')->andReturn(null);
    $entry->shouldReceive('url')->andReturn(null);

    return $entry;
}

/**
 * Entry double for nested `job_title` augmentation + `schema_object` skip tests.
 */
function schemaOutputServiceNestedJobTitleOverlayEntryDouble(): EntryModel&MockInterface
{
    return schemaOutputServiceNestedJobTitleStubEntry(
        schemaOutputServiceNestedJobTitleOverlayBlueprintDouble(),
        schemaOutputServiceNestedJobTitleOverlayAugmentedJobTitleValueDouble(),
        schemaOutputServiceNestedJobTitleEntryValuesCollection()
    );
}

it('hydrates terms fields for loops in the antlers context', function () {
    // Resolve the schema output service from the container
    $service = app(SchemaOutputService::class);

    // Reflect the private buildAntlersContextFromEntity method for invocation
    $method = (new ReflectionClass($service))->getMethod('buildAntlersContextFromEntity');

    // Build the Antlers variable bag using the hydrated entry double
    $context = $method->invoke($service, schemaOutputServiceEntryMockForTermsHydration());

    // Assert credentials were hydrated from slugs to a collection instance
    $credentials = data_get($context, 'credentials');
    expect($credentials)->toBeInstanceOf(Collection::class);

    // Assert the slug hydrated into a term object (not the raw slug or entry title)
    $first = $credentials instanceof Collection ? $credentials->first() : null;
    expect($first)->toBeInstanceOf(TermContract::class);

    // Pin the hydrated term's title to the credential term (not the entry title)
    expect($first instanceof SchemaOutputServiceTitledTerm ? $first->title() : null)->toBe('M.D.');

    // Assert the flat entry title from merged values stayed unchanged
    expect(data_get($context, 'title'))->toBe('Dr. Jane Q. Public');
});

it('hydrates a single term slug string', function () {
    // Resolve the schema output service from the container
    $service = app(SchemaOutputService::class);

    // Reflect the private buildAntlersContextFromEntity method for invocation
    $method = (new ReflectionClass($service))->getMethod('buildAntlersContextFromEntity');

    // Build the Antlers bag using a scalar credentials slug instead of a list
    $context = $method->invoke(
        $service,
        schemaOutputServiceEntryMockForTermsHydrationWithScalarSlug()
    );

    // Assert credentials were hydrated from a string slug to a collection
    $credentials = data_get($context, 'credentials');
    expect($credentials)->toBeInstanceOf(Collection::class);

    // Assert the slug hydrated into a term object, not a raw string
    $first = $credentials instanceof Collection ? $credentials->first() : null;
    expect($first)->toBeInstanceOf(TermContract::class);

    // Pin the hydrated term's title to the credential term
    expect($first instanceof SchemaOutputServiceTitledTerm ? $first->title() : null)->toBe('M.D.');
});

it('wraps a single augmented term instance in a collection', function () {
    // Build a term stand-in matching Statamic single-select augmentation
    $term = Mockery::mock(TermContract::class, SchemaOutputServiceTitledTerm::class);

    // Stub title so hydrated loop output is distinguishable from entry title
    $term->shouldReceive('title')->andReturn('M.D.');

    // Augmented Value yields the term directly (see Fieldtypes\Terms::augment)
    $credentialsValue = Mockery::mock(Value::class);

    // Expect value() once with no query builder indirection
    $credentialsValue->shouldReceive('value')->once()->andReturn($term);

    // Resolve the schema output service from the container
    $service = app(SchemaOutputService::class);

    // Reflect the private buildAntlersContextFromEntity method for invocation
    $method = (new ReflectionClass($service))->getMethod('buildAntlersContextFromEntity');

    // Build context from entry double with scalar slug storage
    $context = $method->invoke(
        $service,
        schemaOutputServiceTermsHydrationEntryDouble(
            schemaOutputServiceTermsHydrationBlueprintDouble(),
            $credentialsValue,
            'md'
        )
    );

    // Assert credentials became a one-element collection for Antlers loops
    $credentials = data_get($context, 'credentials');
    expect($credentials)->toBeInstanceOf(Collection::class);
    expect($credentials)->toHaveCount(1);
    $first = $credentials instanceof Collection ? $credentials->first() : null;
    expect($first)->toBeInstanceOf(TermContract::class);

    // Pin that the wrapped element is the augmented term (its stubbed title)
    expect($first instanceof SchemaOutputServiceTitledTerm ? $first->title() : null)->toBe('M.D.');
});

it('overlays the augmented link value', function () {
    // Build a blueprint field row for a Link-type academic_profile_url
    $linkField = Mockery::mock(Field::class);

    // Stub handle and type so overlay targets this field
    $linkField->shouldReceive('handle')->andReturn('academic_profile_url');
    $linkField->shouldReceive('type')->andReturn('link');

    // Build a Fields bag listing only the link field
    $fields = Mockery::mock(Fields::class);
    $fields->shouldReceive('all')->twice()->andReturn(collect([$linkField]));

    // Build a blueprint double wired to the fields bag (hydration + overlay)
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('fields')->twice()->andReturn($fields);

    // The podcast-episode signal (UAMS-Web/uams-statamic-archive#1611) reads `field('series')`; a null field
    // marks this a non-audio-series entry.
    $blueprint->shouldReceive('field')->with('series')->andReturnNull();

    // Mock augmented Value for the link handle
    $linkValue = Mockery::mock(Value::class);
    $linkValue->shouldReceive('value')->once()->andReturn('https://profiles.example.test/person');

    // Build a partial entry double with raw link storage in values()
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('values')->once()->andReturn(
        collect([
            'academic_profile_url' => 'entry::00000000-0000-0000-0000-000000000001',
            'title' => 'Example',
        ])
    );
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('augmentedValue')->with('academic_profile_url')->once()->andReturn($linkValue);

    // Stub URL methods so mergeCanonicalUrlKeysIntoSchemaAntlersContext is safe
    $entry->shouldReceive('absoluteUrl')->andReturn(null);
    $entry->shouldReceive('url')->andReturn(null);

    // Resolve the schema output service from the container
    $service = app(SchemaOutputService::class);

    // Reflect the private buildAntlersContextFromEntity method for invocation
    $method = (new ReflectionClass($service))->getMethod('buildAntlersContextFromEntity');

    // Build the Antlers variable bag from the entry double
    $context = $method->invoke($service, $entry);

    expect(data_get($context, 'academic_profile_url'))
        ->toBe('https://profiles.example.test/person');
});

it('overlays an arrayable link as an absolute url', function () {
    // Build a blueprint field row for a Link-type academic_profile_url
    $linkField = Mockery::mock(Field::class);
    $linkField->shouldReceive('handle')->andReturn('academic_profile_url');
    $linkField->shouldReceive('type')->andReturn('link');

    // Build a Fields bag listing only the link field
    $fields = Mockery::mock(Fields::class);
    $fields->shouldReceive('all')->twice()->andReturn(collect([$linkField]));

    // Build a blueprint double wired to the fields bag (hydration + overlay)
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('fields')->twice()->andReturn($fields);

    // The podcast-episode signal (UAMS-Web/uams-statamic-archive#1611) reads `field('series')`; a null field
    // marks this a non-audio-series entry.
    $blueprint->shouldReceive('field')->with('series')->andReturnNull();

    // Mock the linked entry inside ArrayableLink (relative url vs absolute)
    $linked = Mockery::mock(EntryModel::class);
    $linked->shouldReceive('absoluteUrl')->once()->andReturn('https://site.example.test/welcome-to-uams/about-uams');
    $linked->shouldReceive('url')->andReturn('/welcome-to-uams/about-uams');

    $arrayableLink = new ArrayableLink($linked);

    $linkValue = Mockery::mock(Value::class);
    $linkValue->shouldReceive('value')->once()->andReturn($arrayableLink);

    // Build a partial entry double with raw link storage in values()
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('values')->once()->andReturn(
        collect([
            'academic_profile_url' => 'entry::00000000-0000-0000-0000-000000000001',
            'title' => 'Example',
        ])
    );
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('augmentedValue')->with('academic_profile_url')->once()->andReturn($linkValue);
    $entry->shouldReceive('absoluteUrl')->andReturn(null);
    $entry->shouldReceive('url')->andReturn(null);

    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod('buildAntlersContextFromEntity');

    $context = $method->invoke($service, $entry);

    expect(data_get($context, 'academic_profile_url'))
        ->toBe('https://site.example.test/welcome-to-uams/about-uams');
});

it('overlays the augmented nested job title row title value', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod('buildAntlersContextFromEntity');

    $context = $method->invoke($service, schemaOutputServiceNestedJobTitleOverlayEntryDouble());

    $jt = data_get($context, 'job_title');
    expect($jt)->toBeArray();
    expect($jt)->toHaveKey(0);
    expect(data_get($jt, '0'))->toBeArray();
    expect(data_get($jt, '0.title.value'))->toBe('Chancellor');
    expect(data_get($context, 'subject_schema'))
        ->toBe(['schema_objects_entry' => '00000000-0000-0000-0000-000000000099']);
});

it('fills missing template variables including dot path roots', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))
        ->getMethod('fillMissingSimpleTemplateVariablesWithNull');
    $method->setAccessible(true);

    $template = '{{ if first_name.text && last_name.phonetic_text }}ok{{ /if }}';
    $context = [
        'unrelated' => 'x',
    ];

    $out = $method->invoke(
        $service,
        $template,
        $context
    );

    expect($out)->toHaveKey('first_name');
    expect(data_get($out, 'first_name'))->toBe('');
    expect($out)->toHaveKey('last_name');
    expect(data_get($out, 'last_name'))->toBe('');
});

it('ignores handles inside a schema_primary_related_taxonomy_term pair', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))
        ->getMethod('fillMissingSimpleTemplateVariablesWithNull');
    $method->setAccessible(true);

    $template = <<<'ANTLERS'
{{ schema_primary_related_taxonomy_term }}
  {{ audience }}
    {{ title }}
  {{ /audience }}
{{ /schema_primary_related_taxonomy_term }}
ANTLERS;
    $context = [
        'unrelated' => 'x',
    ];

    $out = $method->invoke(
        $service,
        $template,
        $context
    );

    expect($out)->not->toHaveKey('audience');
    expect($out)->not->toHaveKey('title');
});

it('ignores handles inside a schema_primary_related_taxonomy_term dot field pair', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))
        ->getMethod('fillMissingSimpleTemplateVariablesWithNull');
    $method->setAccessible(true);

    $template = <<<'ANTLERS'
{{ schema_primary_related_taxonomy_term.audience }}
  {{ title }}
{{ /schema_primary_related_taxonomy_term.audience }}
ANTLERS;
    $context = [
        'unrelated' => 'x',
    ];

    $out = $method->invoke(
        $service,
        $template,
        $context
    );

    expect($out)->not->toHaveKey('title');
});

it('fills missing simple conditional variables', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))
        ->getMethod('fillMissingSimpleTemplateVariablesWithNull');
    $method->setAccessible(true);

    $template = '{{ if email }}a{{ /if }}{{ unless telephone }}b{{ /unless }}{{ elseif fax }}c{{ /if }}';
    $context = [
        'unrelated' => 'x',
    ];

    $out = $method->invoke(
        $service,
        $template,
        $context
    );

    expect($out)->toHaveKey('email');
    expect(data_get($out, 'email'))->toBeNull();
    expect($out)->toHaveKey('telephone');
    expect(data_get($out, 'telephone'))->toBeNull();
    expect($out)->toHaveKey('fax');
    expect(data_get($out, 'fax'))->toBeNull();
});

it('fills missing relationship field handle params', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))
        ->getMethod('fillMissingSimpleTemplateVariablesWithNull');
    $method->setAccessible(true);

    $template = <<<'ANTLERS'
{{ schema_thing_from_relationship
  mode="direct"
  current_field="credentials|pronouns"
  direct_scope="credentials"
}}
ANTLERS;
    $context = [
        'unrelated' => 'x',
    ];

    $out = $method->invoke(
        $service,
        $template,
        $context
    );

    expect($out)->toHaveKey('credentials');
    expect(data_get($out, 'credentials'))->toBeFalse();
    expect($out)->toHaveKey('pronouns');
    expect(data_get($out, 'pronouns'))->toBeFalse();
});

it('clears leaked relationship field values when undefined', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))
        ->getMethod('fillMissingSimpleTemplateVariablesWithNull');
    $method->setAccessible(true);

    // Mock the current context entity so `get('credentials')` returns null.
    $entity = Mockery::mock(EntryModel::class);
    $entity->shouldReceive('get')->with('credentials')->andReturn(null);
    Data::shouldReceive('find')->with('kaitlin-id')->andReturn($entity);

    $template = '{{ schema_thing_from_relationship mode="direct" current_field="credentials" }}';
    $context = [
        'id' => 'kaitlin-id',
        // Simulate an ambient leak where credentials already exist.
        'credentials' => ['leaked'],
    ];

    $out = $method->invoke(
        $service,
        $template,
        $context
    );

    expect($out)->toHaveKey('credentials');
    expect(data_get($out, 'credentials'))->toBeFalse();
});

it('returns entry schema when there is no structure parent', function () {
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn('entry-1');
    $entry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Organization',
        'properties' => ['name' => 'Acme'],
    ]);
    $entry->shouldReceive('get')->with('inherit_from')->andReturn(null);
    $entry->shouldReceive('hasStructure')->andReturn(false);

    $service = app(SchemaOutputService::class);
    $effective = schemaOutputServiceInvokeGetEffectiveSchema($service, $entry, []);

    expect($effective)->not->toBeNull();
    expect(data_get($effective, 'type'))->toBe('Organization');
    expect(data_get($effective, 'properties'))->toBe(['name' => 'Acme']);
});

it('inherits properties from the parent', function () {
    $parentPage = Mockery::mock(Page::class);
    $parentPage->shouldReceive('parent')->andReturn(null);
    $parentPage->shouldReceive('isRoot')->andReturn(true);

    $parentEntry = Mockery::mock(EntryModel::class);
    $parentEntry->shouldReceive('id')->andReturn('parent-1');
    $parentEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Organization',
        'properties' => [
            'name' => 'Parent Org',
            'url' => 'https://parent.example',
        ],
    ]);
    $parentEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);
    $parentEntry->shouldReceive('hasStructure')->andReturn(true);
    $parentEntry->shouldReceive('page')->andReturn($parentPage);

    $childParentPage = Mockery::mock(Page::class);
    $childParentPage->shouldReceive('isRoot')->andReturn(false);
    $childParentPage->shouldReceive('id')->andReturn('parent-1');

    $childEntry = Mockery::mock(EntryModel::class);
    $childEntry->shouldReceive('id')->andReturn('child-1');
    $childEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Organization',
        'properties' => [
            'name' => 'Child Org',
            'url' => '',  // empty -> inherit from parent
        ],
    ]);
    $childEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);
    $childEntry->shouldReceive('hasStructure')->andReturn(true);
    $childPage = Mockery::mock(Page::class);
    $childPage->shouldReceive('parent')->andReturn($childParentPage);
    $childEntry->shouldReceive('page')->andReturn($childPage);

    EntryFacade::shouldReceive('find')->with('child-1')->andReturn($childEntry);
    EntryFacade::shouldReceive('find')->with('parent-1')->andReturn($parentEntry);

    $service = app(SchemaOutputService::class);
    $effective = schemaOutputServiceInvokeGetEffectiveSchema($service, $childEntry, []);

    expect($effective)->not->toBeNull();
    expect(data_get($effective, 'type'))->toBe('Organization');
    expect(data_get($effective, 'properties.name'))->toBe('Child Org');
    expect(data_get($effective, 'properties.url'))->toBe('https://parent.example');
});

it('attaches the parent base for inherit_custom', function () {
    // Mock the parent entry's Page at the structure root
    $parentPage = Mockery::mock(Page::class);

    // Stub no grandparent above the parent page
    $parentPage->shouldReceive('parent')->andReturn(null);

    // Mark the parent page as the tree root
    $parentPage->shouldReceive('isRoot')->andReturn(true);

    // Mock the parent entry that owns the inherited sameAs URL
    $parentEntry = Mockery::mock(EntryModel::class);

    // Fix a stable id for parent lookups in the structure walk
    $parentEntry->shouldReceive('id')->andReturn('parent-1');

    // Provide parent schema with a scalar sameAs for the snapshot base
    $parentEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Organization',
        'properties' => [
            'sameAs' => 'https://parent.example/profile',
        ],
    ]);

    // Stub no extra inherit_from sources on the parent
    $parentEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);

    // Indicate the parent entry participates in collection structure
    $parentEntry->shouldReceive('hasStructure')->andReturn(true);

    // Attach the root page to the parent entry
    $parentEntry->shouldReceive('page')->andReturn($parentPage);

    // Mock the page that acts as the child's structure parent
    $childParentPage = Mockery::mock(Page::class);

    // Place the child's parent page below the site root
    $childParentPage->shouldReceive('isRoot')->andReturn(false);

    // Return the parent entry id as the structure parent reference
    $childParentPage->shouldReceive('id')->andReturn('parent-1');

    // Mock the child entry that opts into inherit_custom for sameAs
    $childEntry = Mockery::mock(EntryModel::class);

    // Fix a stable id for the child during recursion guards
    $childEntry->shouldReceive('id')->andReturn('child-1');

    // Provide child schema with inherit_custom and no local replicator rows
    $childEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Organization',
        'properties' => [
            'sameAs' => [
                'mode' => 'inherit_custom',
                'value' => [],
            ],
        ],
    ]);

    // Stub no inherit_from entries on the child
    $childEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);

    // Indicate the child entry participates in collection structure
    $childEntry->shouldReceive('hasStructure')->andReturn(true);

    // Mock the child's page linked to the structure parent
    $childPage = Mockery::mock(Page::class);

    // Point the child's page at its parent in the tree
    $childPage->shouldReceive('parent')->andReturn($childParentPage);

    // Attach the child page to the child entry
    $childEntry->shouldReceive('page')->andReturn($childPage);

    // Allow Entry::find when resolving the child by id (cycle guard path)
    EntryFacade::shouldReceive('find')->with('child-1')->andReturn($childEntry);

    // Allow Entry::find when loading the structure parent entry
    EntryFacade::shouldReceive('find')->with('parent-1')->andReturn($parentEntry);

    // Resolve SchemaOutputService from the container
    $service = app(SchemaOutputService::class);

    // Compute effective schema for the child with parent merge rules applied
    $effective = schemaOutputServiceInvokeGetEffectiveSchema($service, $childEntry, []);

    // Expect a valid merged schema array
    expect($effective)->not->toBeNull();

    // Read the merged sameAs property from effective properties
    $sameAs = data_get($effective, 'properties.sameAs');

    // Expect a group-shaped value produced by inherit_custom merge
    expect($sameAs)->toBeArray();

    // Expect the mode preserved for downstream resolution
    expect(data_get($sameAs, 'mode'))->toBe('inherit_custom');

    // Expect no additive replicator rows in this fixture
    expect(data_get($sameAs, 'value'))->toBe([]);

    // Expect the parent's sameAs copied as the resolution base snapshot
    expect(data_get($sameAs, '_inherit_base'))->toBe('https://parent.example/profile');
});

it('merges inherit_custom base and rows when resolving the property value', function () {
    // Resolve SchemaOutputService from the container
    $service = app(SchemaOutputService::class);

    // Initialize emitted-entry tracking passed by reference into resolution
    $emittedEntryIds = [];

    // Initialize emitted-node tracking passed by reference into resolution
    $emittedNodeIds = [];

    $materializedFullNodeAtIds = [];

    // Build a synthetic inherit_custom group as produced after effective-schema merge
    $group = [
        'mode' => 'inherit_custom',
        '_inherit_base' => 'https://a.test',
        'value' => [
            [
                '_id' => 'rowone',
                'type' => 'text_short',
                'value' => 'https://b.test',
                'enabled' => true,
            ],
        ],
    ];

    // Resolve the group with no front-end context entity
    $resolved = schemaOutputServiceInvokeResolvePropertyValue(
        $service,
        $group,
        null,
        [],
        [],
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        []
    );

    // Expect inherited base URL first, then the row URL, as a two-item list
    expect($resolved)->toBe(['https://a.test', 'https://b.test']);
});

it('deduplicates identical scalar rows', function () {
    // Resolve SchemaOutputService from the container
    $service = app(SchemaOutputService::class);

    // Initialize emitted-entry tracking passed by reference into resolution
    $emittedEntryIds = [];

    // Initialize emitted-node tracking passed by reference into resolution
    $emittedNodeIds = [];

    $materializedFullNodeAtIds = [];

    // Build two enabled rows that resolve to the same string (e.g. alternateName)
    $rows = [
        [
            '_id' => 'row-a',
            'type' => 'text_short',
            'value' => 'rel-match-day-2026.pdf',
            'enabled' => true,
        ],
        [
            '_id' => 'row-b',
            'type' => 'text_short',
            'value' => 'rel-match-day-2026.pdf',
            'enabled' => true,
        ],
    ];

    // Resolve the replicator list with no context entity
    $resolved = schemaOutputServiceInvokeResolvePropertyValue(
        $service,
        $rows,
        null,
        [],
        [],
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        []
    );

    // Expect a single scalar so JSON-LD does not repeat the same value
    expect($resolved)->toBe('rel-match-day-2026.pdf');
});

it('deduplicates inherit_custom when base matches an additive row', function () {
    // Resolve SchemaOutputService from the container
    $service = app(SchemaOutputService::class);

    // Initialize emitted-entry tracking passed by reference into resolution
    $emittedEntryIds = [];

    // Initialize emitted-node tracking passed by reference into resolution
    $emittedNodeIds = [];

    $materializedFullNodeAtIds = [];

    // Build inherit_custom where the row repeats the inherited URL
    $group = [
        'mode' => 'inherit_custom',
        '_inherit_base' => 'https://same.example/profile',
        'value' => [
            [
                '_id' => 'rowone',
                'type' => 'text_short',
                'value' => 'https://same.example/profile',
                'enabled' => true,
            ],
        ],
    ];

    // Resolve the group with no front-end context entity
    $resolved = schemaOutputServiceInvokeResolvePropertyValue(
        $service,
        $group,
        null,
        [],
        [],
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        []
    );

    // Expect one URL, not a two-element list with duplicates
    expect($resolved)->toBe('https://same.example/profile');
});

it('inherits the type from the parent when the child type is empty', function () {
    $parentPage = Mockery::mock(Page::class);
    $parentPage->shouldReceive('parent')->andReturn(null);
    $parentPage->shouldReceive('isRoot')->andReturn(true);

    $parentEntry = Mockery::mock(EntryModel::class);
    $parentEntry->shouldReceive('id')->andReturn('parent-1');
    $parentEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'CollegeOrUniversity',
        'properties' => ['name' => 'Parent'],
    ]);
    $parentEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);
    $parentEntry->shouldReceive('hasStructure')->andReturn(true);
    $parentEntry->shouldReceive('page')->andReturn($parentPage);

    $childParentPage = Mockery::mock(Page::class);
    $childParentPage->shouldReceive('isRoot')->andReturn(false);
    $childParentPage->shouldReceive('id')->andReturn('parent-1');

    $childPage = Mockery::mock(Page::class);
    $childPage->shouldReceive('parent')->andReturn($childParentPage);

    $childEntry = Mockery::mock(EntryModel::class);
    $childEntry->shouldReceive('id')->andReturn('child-1');
    $childEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => '',
        'properties' => ['name' => 'Child'],
    ]);
    $childEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);
    $childEntry->shouldReceive('hasStructure')->andReturn(true);
    $childEntry->shouldReceive('page')->andReturn($childPage);

    EntryFacade::shouldReceive('find')->with('parent-1')->andReturn($parentEntry);

    $service = app(SchemaOutputService::class);
    $effective = schemaOutputServiceInvokeGetEffectiveSchema($service, $childEntry, []);

    expect($effective)->not->toBeNull();
    expect(data_get($effective, 'type'))->toBe('CollegeOrUniversity');
    expect(data_get($effective, 'properties.name'))->toBe('Child');
});

it('inherits properties from inherit_from entries', function () {
    $otherEntry = Mockery::mock(EntryModel::class);
    $otherEntry->shouldReceive('id')->andReturn('other-1');
    $otherEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Thing',
        'properties' => [
            'description' => 'From other',
            'url' => 'https://other.example',
        ],
    ]);
    $otherEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);
    $otherEntry->shouldReceive('hasStructure')->andReturn(false);

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn('entry-1');
    $entry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Organization',
        'properties' => [
            'name' => 'Acme',
            'description' => '',  // empty -> take from inherit_from
        ],
    ]);
    $entry->shouldReceive('get')
        ->with('inherit_from')
        ->andReturnUsing(
            fn (): array => ['other-1']
        );
    $entry->shouldReceive('hasStructure')->andReturn(false);

    EntryFacade::shouldReceive('find')->with('other-1')->andReturn($otherEntry);

    $service = app(SchemaOutputService::class);
    $effective = schemaOutputServiceInvokeGetEffectiveSchema($service, $entry, []);

    expect($effective)->not->toBeNull();
    expect(data_get($effective, 'type'))->toBe('Organization');
    expect(data_get($effective, 'properties.name'))->toBe('Acme');
    expect(data_get($effective, 'properties.description'))->toBe('From other');
    expect(data_get($effective, 'properties.url'))->toBe('https://other.example');
});

it('filters parent properties by the child type', function () {
    // Mock the parent entry's Page at the structure root
    $parentPage = Mockery::mock(Page::class);

    // Stub no grandparent above the parent page
    $parentPage->shouldReceive('parent')->andReturn(null);

    // Mark the parent page as the tree root
    $parentPage->shouldReceive('isRoot')->andReturn(true);

    // Mock the parent entry that supplies Person-shaped schema
    $parentEntry = Mockery::mock(EntryModel::class);

    // Fix a stable id for parent lookups in Entry::find
    $parentEntry->shouldReceive('id')->andReturn('parent-1');

    // Provide Person schema including jobTitle (invalid on Organization)
    $parentEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Person',
        'properties' => [
            'name' => 'Parent Person',
            'jobTitle' => 'CEO',
        ],
    ]);

    // Stub no extra inherit_from sources on the parent
    $parentEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);

    // Indicate the parent entry participates in collection structure
    $parentEntry->shouldReceive('hasStructure')->andReturn(true);

    // Attach the root page to the parent entry
    $parentEntry->shouldReceive('page')->andReturn($parentPage);

    // Mock the page that acts as the child's structure parent
    $childParentPage = Mockery::mock(Page::class);

    // Place the child's parent page below the site root
    $childParentPage->shouldReceive('isRoot')->andReturn(false);

    // Return the parent entry id as the structure parent reference
    $childParentPage->shouldReceive('id')->andReturn('parent-1');

    // Mock the child's page linked to the structure parent
    $childPage = Mockery::mock(Page::class);

    // Point the child's page at its parent in the tree
    $childPage->shouldReceive('parent')->andReturn($childParentPage);

    // Mock the child entry typed as Organization
    $childEntry = Mockery::mock(EntryModel::class);

    // Fix a stable id for the child during recursion guards
    $childEntry->shouldReceive('id')->andReturn('child-1');

    // Leave name and jobTitle empty so merge would copy from the parent
    $childEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Organization',
        'properties' => [
            'name' => '',
            'jobTitle' => '',
        ],
    ]);

    // Stub no inherit_from entries on the child
    $childEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);

    // Indicate the child entry participates in collection structure
    $childEntry->shouldReceive('hasStructure')->andReturn(true);

    // Attach the child page to the child entry
    $childEntry->shouldReceive('page')->andReturn($childPage);

    // Allow Entry::find when resolving the child by id
    EntryFacade::shouldReceive('find')->with('child-1')->andReturn($childEntry);

    // Allow Entry::find when loading the structure parent entry
    EntryFacade::shouldReceive('find')->with('parent-1')->andReturn($parentEntry);

    // Resolve SchemaOutputService from the container
    $service = app(SchemaOutputService::class);

    // Compute effective schema for the child with parent merge rules
    $effective = schemaOutputServiceInvokeGetEffectiveSchema($service, $childEntry, []);

    // Expect a merged schema array from getEffectiveSchema
    expect($effective)->not->toBeNull();

    // Expect the child's Organization type
    expect(data_get($effective, 'type'))->toBe('Organization');

    // Expect shared name to inherit from the parent Person
    expect(data_get($effective, 'properties.name'))->toBe('Parent Person');

    // Expect Person-only jobTitle to be omitted for Organization
    expect(data_get($effective, 'properties'))->not->toHaveKey('jobTitle');
});

it('filters inherit_from properties by the recipient type', function () {
    // Mock the inherit_from source entry with Person schema
    $otherEntry = Mockery::mock(EntryModel::class);

    // Fix a stable id for the other entry
    $otherEntry->shouldReceive('id')->andReturn('other-1');

    // Include jobTitle so filtering must drop it on Organization
    $otherEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Person',
        'properties' => [
            'name' => 'Other Person',
            'jobTitle' => 'Dr',
            'description' => 'From other person',
        ],
    ]);

    // Stub no inherit_from chain on the other entry
    $otherEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);

    // Keep the other entry outside the collection structure
    $otherEntry->shouldReceive('hasStructure')->andReturn(false);

    // Mock the recipient Organization entry under test
    $entry = Mockery::mock(EntryModel::class);

    // Fix a stable id for the recipient entry
    $entry->shouldReceive('id')->andReturn('entry-1');

    // Set name locally and leave description empty for inherit_from fill
    $entry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Organization',
        'properties' => [
            'name' => 'Acme',
            'description' => '',
        ],
    ]);

    // Point inherit_from at the Person entry id
    $entry->shouldReceive('get')
        ->with('inherit_from')
        ->andReturnUsing(
            fn (): array => ['other-1']
        );

    // Keep the recipient outside the collection structure
    $entry->shouldReceive('hasStructure')->andReturn(false);

    // Allow Entry::find when resolving the inherit_from entry
    EntryFacade::shouldReceive('find')->with('other-1')->andReturn($otherEntry);

    // Resolve SchemaOutputService from the container
    $service = app(SchemaOutputService::class);

    // Compute effective schema including inherit_from merge
    $effective = schemaOutputServiceInvokeGetEffectiveSchema($service, $entry, []);

    // Expect a merged schema array from getEffectiveSchema
    expect($effective)->not->toBeNull();

    // Expect Organization type on the recipient
    expect(data_get($effective, 'type'))->toBe('Organization');

    // Expect local name to override the other entry's name
    expect(data_get($effective, 'properties.name'))->toBe('Acme');

    // Expect shared description to merge from inherit_from
    expect(data_get($effective, 'properties.description'))->toBe('From other person');

    // Expect Person-only jobTitle to stay out of the merged properties
    expect(data_get($effective, 'properties'))->not->toHaveKey('jobTitle');
});

it('breaks the inheritance cycle', function () {
    $parentPage = Mockery::mock(Page::class);
    $parentPage->shouldReceive('isRoot')->andReturn(false);
    $parentPage->shouldReceive('id')->andReturn('entry-1');

    $entryPage = Mockery::mock(Page::class);
    $entryPage->shouldReceive('parent')->andReturn($parentPage);

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn('entry-1');
    $entry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Organization',
        'properties' => ['name' => 'Self'],
    ]);
    $entry->shouldReceive('get')->with('inherit_from')->andReturn(null);
    $entry->shouldReceive('hasStructure')->andReturn(true);
    $entry->shouldReceive('page')->andReturn($entryPage);

    EntryFacade::shouldReceive('find')->with('entry-1')->andReturn($entry);

    $service = app(SchemaOutputService::class);
    $effective = schemaOutputServiceInvokeGetEffectiveSchema($service, $entry, []);

    expect($effective)->not->toBeNull();
    expect(data_get($effective, 'type'))->toBe('Organization');
    expect(data_get($effective, 'properties'))->toBe(['name' => 'Self']);
});

it('uses the inherited url for the canonical @id', function () {
    $parentPage = Mockery::mock(Page::class);
    $parentPage->shouldReceive('parent')->andReturn(null);
    $parentPage->shouldReceive('isRoot')->andReturn(true);

    $parentEntry = Mockery::mock(EntryModel::class);
    $parentEntry->shouldReceive('id')->andReturn('parent-1');
    $parentEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'WebPage',
        'properties' => [
            'url' => 'https://parent.example/page',
        ],
    ]);
    $parentEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);
    $parentEntry->shouldReceive('hasStructure')->andReturn(true);
    $parentEntry->shouldReceive('page')->andReturn($parentPage);

    $childParentPage = Mockery::mock(Page::class);
    $childParentPage->shouldReceive('isRoot')->andReturn(false);
    $childParentPage->shouldReceive('id')->andReturn('parent-1');

    $childPage = Mockery::mock(Page::class);
    $childPage->shouldReceive('parent')->andReturn($childParentPage);

    $childEntry = Mockery::mock(EntryModel::class);
    $childEntry->shouldReceive('id')->andReturn('child-1');
    $childEntry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'WebPage',
        'properties' => [
            'url' => ['mode' => 'inherit', 'value' => []],
        ],
    ]);
    $childEntry->shouldReceive('get')->with('inherit_from')->andReturn(null);
    $childEntry->shouldReceive('hasStructure')->andReturn(true);
    $childEntry->shouldReceive('page')->andReturn($childPage);

    EntryFacade::shouldReceive('find')->with('parent-1')->andReturn($parentEntry);

    $service = app(SchemaOutputService::class);
    $atId = $service->getCanonicalAtIdForSchemaEntry($childEntry);

    expect($atId)->toBe('https://parent.example/page#child-1');
});

it('uses the site absolute url fallback for the canonical @id', function () {
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('absoluteUrl')->andReturn('https://uams.example');

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn('entry-1');
    $entry->shouldReceive('get')->with('schema')->andReturn([
        'type' => 'Organization',
        'properties' => [],
    ]);
    $entry->shouldReceive('get')->with('inherit_from')->andReturn(null);
    $entry->shouldReceive('hasStructure')->andReturn(false);
    $entry->shouldReceive('site')->andReturn($site);

    $service = app(SchemaOutputService::class);
    $atId = $service->getCanonicalAtIdForSchemaEntry($entry);

    expect($atId)->toBe('https://uams.example#entry-1');
});

it('resolves graph entry ids for the entity returning slot entry ids', function () {
    // Mock the entity
    $entity = Mockery::mock(EntryModel::class);

    // Mock the cascade service
    $cascadeService = Mockery::mock(SchemaTemplatesCascadeService::class);

    // Expect the resolve method to be called once with the entity
    $cascadeService->shouldReceive('resolve')
        ->once()
        ->with($entity)
        ->andReturnUsing(
            fn (): array => [
                'subject_entry' => 'subject-entry-id',
                'website_entry' => ['website-entry-id'],
                'webpage_entry' => '',
                'main_content_entry' => 'main-content-entry-id',
            ]
        );

    // Set the cascade service instance
    app()->instance(SchemaTemplatesCascadeService::class, $cascadeService);

    // Get the service
    $service = app(SchemaOutputService::class);

    // Invoke the resolve graph entry ids for entity method
    $graphEntryIds = schemaOutputServiceInvokeResolveGraphEntryIdsForEntity($service, $entity);

    // Assert that the graph entry ids are the expected values
    expect($graphEntryIds)->toBe([
        'subject-entry-id',
        'website-entry-id',
        'main-content-entry-id',
    ]);
});

it('dedupes a node by @id replacing repeated full nodes', function () {
    // Resolve the shared JSON-LD @id deduplication helper
    $deduper = app(SchemaJsonLdAtIdDeduplication::class);

    // Initialize the emitted node ids array
    $emittedNodeIds = [];

    // Build the first node
    $firstNode = [
        '@id' => 'http://example.test/assets/example.jpg',
        '@type' => 'ImageObject',
        'name' => 'Example image',
    ];

    // Build the second node
    $secondNode = [
        '@id' => 'http://example.test/assets/example.jpg',
        '@type' => 'ImageObject',
        'name' => 'Example image',
    ];

    // Resolve the first node
    $firstResolved = $deduper->dedupeNodeByAtId($firstNode, $emittedNodeIds);

    // Resolve the second node
    $secondResolved = $deduper->dedupeNodeByAtId($secondNode, $emittedNodeIds);

    // Assert that the first resolved node is the expected value
    expect(data_get($firstResolved, '@type'))->toBe('ImageObject');

    // Assert that the second resolved node is the expected value
    expect($secondResolved)->toBe(['@id' => 'http://example.test/assets/example.jpg']);
});

it('collapses a second emit of the same canonical @id to a reference', function () {
    $entryId = '58185f50-22a4-48e7-9575-b86a1fdffef9';

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($entryId);
    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');
    $entry->shouldReceive('get')->andReturnUsing(static function (string $key): mixed {
        return match ($key) {
            'schema' => [
                'type' => 'ContactPoint',
                'properties' => [],
            ],
            'inherit_from' => null,
            default => null,
        };
    });
    $entry->shouldReceive('hasStructure')->andReturn(false);
    $entry->shouldReceive('blueprint')->andReturn(null);

    $site = Mockery::mock(Site::class);
    $site->shouldReceive('absoluteUrl')->andReturn('https://example.test');
    $entry->shouldReceive('site')->andReturn($site);

    EntryFacade::shouldReceive('find')->with($entryId)->andReturn($entry);

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    $service = app(SchemaOutputService::class);
    $firstJson = $service->buildSchemaTemplateNodeJsonForTag(
        [$entryId],
        $emitContext,
        []
    );
    $secondJson = $service->buildSchemaTemplateNodeJsonForTag(
        [$entryId],
        $emitContext,
        []
    );

    $firstDecoded = json_decode($firstJson, true);
    $secondDecoded = json_decode($secondJson, true);

    expect($firstDecoded)->toBeArray();
    expect($firstDecoded)->toHaveKey('@type');

    $expectedAtId = 'https://example.test#'.$entryId;
    expect(data_get($firstDecoded, '@id'))->toBe($expectedAtId);

    expect($secondDecoded)->toBeArray();
    expect($secondDecoded)->not->toHaveKey('@type');
    expect($secondDecoded)->toBe(['@id' => $expectedAtId]);
});

it('applies a compatible type_override for @type', function () {
    $entryId = '58185f50-22a4-48e7-9575-b86a1fdffef9';

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($entryId);
    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');
    $entry->shouldReceive('get')->andReturnUsing(static function (string $key): mixed {
        return match ($key) {
            'schema' => [
                'type' => 'Person',
                'properties' => [],
            ],
            'inherit_from' => null,
            default => null,
        };
    });
    $entry->shouldReceive('hasStructure')->andReturn(false);
    $entry->shouldReceive('blueprint')->andReturn(null);

    $site = Mockery::mock(Site::class);
    $site->shouldReceive('absoluteUrl')->andReturn('https://example.test');
    $entry->shouldReceive('site')->andReturn($site);

    EntryFacade::shouldReceive('find')->with($entryId)->andReturn($entry);

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    $service = app(SchemaOutputService::class);
    $json = $service->buildSchemaTemplateNodeJsonForTag(
        [$entryId],
        $emitContext,
        [
            SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE => ['Patient'],
        ]
    );

    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray();
    expect(data_get($decoded, '@type'))->toBe('Patient');
});

it('keeps the entry @type when type_override is incompatible', function () {
    $entryId = '58185f50-22a4-48e7-9575-b86a1fdffef9';

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($entryId);
    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');
    $entry->shouldReceive('get')->andReturnUsing(static function (string $key): mixed {
        return match ($key) {
            'schema' => [
                'type' => 'Person',
                'properties' => [],
            ],
            'inherit_from' => null,
            default => null,
        };
    });
    $entry->shouldReceive('hasStructure')->andReturn(false);
    $entry->shouldReceive('blueprint')->andReturn(null);

    $site = Mockery::mock(Site::class);
    $site->shouldReceive('absoluteUrl')->andReturn('https://example.test');
    $entry->shouldReceive('site')->andReturn($site);

    EntryFacade::shouldReceive('find')->with($entryId)->andReturn($entry);

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    $service = app(SchemaOutputService::class);
    $json = $service->buildSchemaTemplateNodeJsonForTag(
        [$entryId],
        $emitContext,
        [
            SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE => ['ContactPoint'],
        ]
    );

    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray();
    expect(data_get($decoded, '@type'))->toBe('Person');
});

it('uses the full type_override list for @type when the gate passes', function () {
    $entryId = '58185f50-22a4-48e7-9575-b86a1fdffef9';

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($entryId);
    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');
    $entry->shouldReceive('get')->andReturnUsing(static function (string $key): mixed {
        return match ($key) {
            'schema' => [
                'type' => 'Person',
                'properties' => [],
            ],
            'inherit_from' => null,
            default => null,
        };
    });
    $entry->shouldReceive('hasStructure')->andReturn(false);
    $entry->shouldReceive('blueprint')->andReturn(null);

    $site = Mockery::mock(Site::class);
    $site->shouldReceive('absoluteUrl')->andReturn('https://example.test');
    $entry->shouldReceive('site')->andReturn($site);

    EntryFacade::shouldReceive('find')->with($entryId)->andReturn($entry);

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    $service = app(SchemaOutputService::class);
    $json = $service->buildSchemaTemplateNodeJsonForTag(
        [$entryId],
        $emitContext,
        [
            SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE => ['Patient', 'Organization'],
        ]
    );

    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray();
    expect(data_get($decoded, '@type'))->toBe(['Patient', 'Organization']);
});

it('prefers a reference when the @id is pre-registered', function () {
    $entryId = '58185f50-22a4-48e7-9575-b86a1fdffef9';
    $expectedAtId = 'https://example.test#'.$entryId;

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($entryId);
    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');
    $entry->shouldReceive('get')->andReturnUsing(static function (string $key): mixed {
        return match ($key) {
            'schema' => [
                'type' => 'ContactPoint',
                'properties' => [],
            ],
            'inherit_from' => null,
            default => null,
        };
    });
    $entry->shouldReceive('hasStructure')->andReturn(false);
    $entry->shouldReceive('blueprint')->andReturn(null);

    $site = Mockery::mock(Site::class);
    $site->shouldReceive('absoluteUrl')->andReturn('https://example.test');
    $entry->shouldReceive('site')->andReturn($site);

    EntryFacade::shouldReceive('find')->with($entryId)->once()->andReturn($entry);

    $emittedEntryIds = [];
    $emittedNodeIds = [$expectedAtId => true];
    $materializedFullNodeAtIds = [$expectedAtId => true];
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    $service = app(SchemaOutputService::class);
    $json = $service->buildSchemaTemplateNodeJsonForTag(
        [$entryId],
        $emitContext,
        []
    );

    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray();
    expect($decoded)->not->toHaveKey('@type');
    expect($decoded)->toBe(['@id' => $expectedAtId]);
});

it('keeps the first full nested body for a pre-registered @id', function () {
    $entryId = '58185f50-22a4-48e7-9575-b86a1fdffef9';

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($entryId);
    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');
    $entry->shouldReceive('get')->andReturnUsing(static function (string $key): mixed {
        return match ($key) {
            'schema' => [
                'type' => 'Person',
                'properties' => [
                    'hasCredential' => [
                        [
                            'type' => 'antlers',
                            'enabled' => true,
                            'value' => [
                                'mode' => 'html',
                                'code' => '{"@id":"https://example.test/#cred","@type":"Credential","name":"MD"}',
                            ],
                        ],
                        [
                            'type' => 'antlers',
                            'enabled' => true,
                            'value' => [
                                'mode' => 'html',
                                'code' => '{"@id":"https://example.test/#cred"}',
                            ],
                        ],
                    ],
                ],
            ],
            'inherit_from' => null,
            default => null,
        };
    });
    $entry->shouldReceive('hasStructure')->andReturn(false);
    $entry->shouldReceive('blueprint')->andReturn(null);

    $site = Mockery::mock(Site::class);
    $site->shouldReceive('absoluteUrl')->andReturn('https://example.test');
    $entry->shouldReceive('site')->andReturn($site);

    EntryFacade::shouldReceive('find')->with($entryId)->andReturn($entry);

    $emittedEntryIds = [];
    $emittedNodeIds = [
        'https://example.test/#cred' => true,
    ];
    $materializedFullNodeAtIds = [
        'https://example.test/#cred' => true,
    ];
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    $service = app(SchemaOutputService::class);
    $json = $service->buildSchemaTemplateNodeJsonForTag(
        [$entryId],
        $emitContext,
        []
    );

    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray();
    expect(data_get($decoded, 'hasCredential'))->toBeArray();
    expect(data_get($decoded, 'hasCredential.@type'))->toBe('Credential');
    expect(data_get($decoded, 'hasCredential.@id'))->toBe('https://example.test/#cred');
});

it('expands the node when the @id is pre-emitted', function () {
    $entryId = '58185f50-22a4-48e7-9575-b86a1fdffef9';
    $expectedAtId = 'https://example.test#'.$entryId;

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($entryId);
    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');
    $entry->shouldReceive('get')->andReturnUsing(static function (string $key): mixed {
        return match ($key) {
            'schema' => [
                'type' => 'ContactPoint',
                'properties' => [
                    'name' => 'must-not-expand',
                ],
            ],
            'inherit_from' => null,
            default => null,
        };
    });
    $entry->shouldReceive('hasStructure')->andReturn(false);
    $entry->shouldReceive('blueprint')->andReturn(null);

    $site = Mockery::mock(Site::class);
    $site->shouldReceive('absoluteUrl')->andReturn('https://example.test');
    $entry->shouldReceive('site')->andReturn($site);

    EntryFacade::shouldReceive('find')->with($entryId)->once()->andReturn($entry);

    $emittedEntryIds = [];
    $emittedNodeIds = [$expectedAtId => true];
    $materializedFullNodeAtIds = [];
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod('schemaObjectEntryToNode');
    $method->setAccessible(true);
    $node = $method->invokeArgs(
        $service,
        [
            $entryId,
            null,
            [],
            [],
            &$emittedEntryIds,
            &$emittedNodeIds,
            &$materializedFullNodeAtIds,
            [],
            null,
        ]
    );

    expect($node)->toBeArray();
    expect(data_get($node, '@id'))->toBe($expectedAtId);
    expect(data_get($node, '@type'))->toBe('ContactPoint');
    expect($node)->toHaveKey('name');
});

it('returns an @id reference only when re-entrant', function () {
    $entryId = '58185f50-22a4-48e7-9575-b86a1fdffef9';

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');

    $site = Mockery::mock(Site::class);
    $site->shouldReceive('absoluteUrl')->andReturn('https://example.test');
    $entry->shouldReceive('site')->andReturn($site);

    EntryFacade::shouldReceive('find')->with($entryId)->andReturn($entry);

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];
    $emitContext = new SchemaTemplateEmitContext(
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [],
        null,
        new SchemaTemplateInstanceWatermarkBag
    );

    $service = app(SchemaOutputService::class);

    SchemaTemplateNodeBuildStack::push($entryId);

    try {
        $json = $service->buildSchemaTemplateNodeJsonForTag(
            [$entryId],
            $emitContext,
            []
        );
    } finally {
        SchemaTemplateNodeBuildStack::pop();
    }

    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray();
    expect($decoded)->not->toHaveKey('@type');
    expect($decoded)->toBe(['@id' => 'https://example.test#'.$entryId]);
});

it('suffixes the canonical fragment with the template instance index', function () {
    // Resolve the schema output service from the container
    $service = app(SchemaOutputService::class);

    // Reflect the protected append helper for direct invocation
    $method = (new ReflectionClass($service))->getMethod(
        'appendTemplateInstanceIndexToCanonicalAtId'
    );

    // Assert the first index suffix is applied to a canonical @id
    expect($method->invoke(
        $service,
        'https://example.test/#5357dd18-9469-4a1d-a745-e2b57cd69bb5',
        0
    ))->toBe('https://example.test/#5357dd18-9469-4a1d-a745-e2b57cd69bb5-0');

    // Assert a later index suffix preserves the base URL and fragment stem
    expect($method->invoke(
        $service,
        'https://example.test/#5357dd18-9469-4a1d-a745-e2b57cd69bb5',
        2
    ))->toBe('https://example.test/#5357dd18-9469-4a1d-a745-e2b57cd69bb5-2');
});

it('parses the template instance index from the antlers context', function () {
    // Resolve the schema output service from the container
    $service = app(SchemaOutputService::class);

    // Reflect the protected parser for direct invocation
    $method = (new ReflectionClass($service))->getMethod(
        'parseTemplateInstanceIndexFromAntlersContext'
    );

    $key = SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX;

    // Assert integer indices pass through
    expect($method->invoke($service, [$key => 1]))->toBe(1);

    // Assert numeric strings normalize to integers
    expect($method->invoke($service, [$key => '3']))->toBe(3);

    // Assert missing keys return null
    expect($method->invoke($service, []))->toBeNull();

    // Assert negative integers are rejected
    expect($method->invoke($service, [$key => -1]))->toBeNull();

    // Assert non-numeric strings are rejected
    expect($method->invoke($service, [$key => 'x']))->toBeNull();
});

it('peeks then commits the effective template instance index', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod(
        'resolveEffectiveTemplateInstanceIndexForWatermark'
    );
    $method->setAccessible(true);

    $bag = new SchemaTemplateInstanceWatermarkBag;
    $ctx = [
        SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => 0,
    ];
    $org = Mockery::mock(EntryModel::class);
    $org->shouldReceive('id')->andReturn('org-uuid');
    $tid = 'd4f58c4f-ee5c-4536-af1c-6e3dc3f2da4f';

    expect($method->invoke($service, $tid, $org, [], $ctx, $bag, false))->toBe(0);
    expect($method->invoke($service, $tid, $org, [], $ctx, $bag, false))->toBe(0);
    expect($bag->lastIssuedByKey)->toBe([]);

    expect($method->invoke($service, $tid, $org, [], $ctx, $bag, true))->toBe(0);
    expect(data_get($bag->lastIssuedByKey, "$tid\0org-uuid"))->toBe(0);

    expect($method->invoke($service, $tid, $org, [], $ctx, $bag, true))->toBe(1);
    expect(data_get($bag->lastIssuedByKey, "$tid\0org-uuid"))->toBe(1);
});

it('shares one watermark series under the same graph anchor', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod(
        'resolveEffectiveTemplateInstanceIndexForWatermark'
    );
    $method->setAccessible(true);

    $bag = new SchemaTemplateInstanceWatermarkBag;
    $ctx = [
        SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => 0,
    ];
    $tid = 'd4f58c4f-ee5c-4536-af1c-6e3dc3f2da4f';
    $graphAnchor = '9073d347-8121-4ad9-b00b-43355da14300';
    $graphEntryIds = [$graphAnchor];

    $personA = Mockery::mock(EntryModel::class);
    $personA->shouldReceive('id')->andReturn('d31c56e1-fb9e-4960-9fbb-27ff6d1acda1');

    $personB = Mockery::mock(EntryModel::class);
    $personB->shouldReceive('id')->andReturn('59ec0edb-3030-41f0-a90d-f38e0de91b51');

    expect($method->invoke($service, $tid, $personA, $graphEntryIds, $ctx, $bag, false))->toBe(0);
    expect($method->invoke($service, $tid, $personA, $graphEntryIds, $ctx, $bag, true))->toBe(0);
    expect($method->invoke($service, $tid, $personB, $graphEntryIds, $ctx, $bag, false))->toBe(1);
    expect($method->invoke($service, $tid, $personB, $graphEntryIds, $ctx, $bag, true))->toBe(1);
    expect(data_get($bag->lastIssuedByKey, "$tid\0$graphAnchor"))->toBe(1);
});

it('decodes a json object output from a single replicator row', function () {
    // Get the service
    $service = app(SchemaOutputService::class);

    // Build an antlers row that emits a JSON object
    $row = [
        'type' => 'antlers',
        'value' => [
            'code' => '{{ listen_action_json }}',
            'mode' => 'html',
        ],
    ];

    // Initialize emitted entry ids and emitted node ids
    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];

    // Resolve the row value
    $resolved = schemaOutputServiceInvokeResolveSingleReplicatorRowToValue(
        $service,
        $row,
        null,
        [],
        [],
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        [
            'listen_action_json' => '{"@type":"https://schema.org/ListenAction","target":"https://example.test/#segment-1"}',
        ]
    );

    // Assert that the parsed value is a decoded object array
    expect($resolved)->toBeArray();
    expect(data_get($resolved, '@type'))->toBe('https://schema.org/ListenAction');
    expect(data_get($resolved, 'target'))->toBe('https://example.test/#segment-1');
});

it('uses merged values for the computed reading time', function () {
    // Get the service
    $service = app(SchemaOutputService::class);

    // Mock the entry
    $entry = Mockery::mock(EntryModel::class);

    // Expect the values() method to be called once and return the expected values
    $entry->shouldReceive('values')->once()->andReturn(
        collect([
            'reading_time' => 360,
        ])
    );

    // Stub blueprint so terms-field hydration skips (partial mock)
    $entry->shouldReceive('blueprint')->andReturn(null);

    // Stub URL methods so schema Antlers context merge does not error on the mock
    $entry->shouldReceive('absoluteUrl')->andReturn(null);
    $entry->shouldReceive('url')->andReturn(null);

    // Build the antlers row
    $row = [
        'type' => 'antlers',
        'value' => [
            'code' => '{{ reading_time | format_duration }}',
            'mode' => 'html',
        ],
    ];

    // Initialize the emitted entry ids
    $emittedEntryIds = [];

    // Initialize the emitted node ids
    $emittedNodeIds = [];

    $materializedFullNodeAtIds = [];

    // Resolve the row value
    $resolved = schemaOutputServiceInvokeResolveSingleReplicatorRowToValue(
        $service,
        $row,
        $entry,
        [],
        [],
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        []
    );

    // Assert that the resolved value is the expected value
    expect($resolved)->toBe('PT6M');
});

it('injects the permalink for schema antlers', function () {
    // Get the service
    $service = app(SchemaOutputService::class);

    // Mock entry: values() omits augmented permalink (typical Statamic behavior)
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('values')->once()->andReturn(
        collect([
            'title' => 'Example',
        ])
    );

    // Stub blueprint so terms-field hydration skips (partial mock)
    $entry->shouldReceive('blueprint')->andReturn(null);

    $entry->shouldReceive('absoluteUrl')->once()->andReturn(
        'https://uams-statamic.test/people/example'
    );
    $entry->shouldReceive('url')->once()->andReturn('/people/example');

    // Build an antlers row that outputs permalink
    $row = [
        'type' => 'antlers',
        'value' => [
            'code' => '{{ permalink }}',
            'mode' => 'html',
        ],
    ];

    // Initialize the emitted entry ids
    $emittedEntryIds = [];

    // Initialize the emitted node ids
    $emittedNodeIds = [];

    $materializedFullNodeAtIds = [];

    // Resolve the row value
    $resolved = schemaOutputServiceInvokeResolveSingleReplicatorRowToValue(
        $service,
        $row,
        $entry,
        [],
        [],
        $emittedEntryIds,
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        []
    );

    // Assert permalink resolved for JSON-LD url / sameAs fragments
    expect($resolved)->toBe('https://uams-statamic.test/people/example');
});

it('prefers the lexicographic overlay key for the primary related taxonomy term', function () {
    $termAlpha = Mockery::mock(TaxonomyTerm::class);
    $termZeta = Mockery::mock(TaxonomyTerm::class);

    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod(
        'resolveSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay'
    );
    $method->setAccessible(true);

    $context = [
        'zebra_terms' => collect([$termZeta]),
        'alpha_terms' => collect([$termAlpha]),
    ];
    $antlersContext = [
        'zebra_terms' => true,
        'alpha_terms' => true,
    ];

    $winner = $method->invoke($service, $context, $antlersContext);

    expect($winner)->toBe($termAlpha);
});

it('accepts a bare term value for the primary related taxonomy term', function () {
    $term = Mockery::mock(TaxonomyTerm::class);

    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod(
        'resolveSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay'
    );
    $method->setAccessible(true);

    $context = ['overlay_slot' => $term];
    $antlersContext = ['overlay_slot' => true];

    $winner = $method->invoke($service, $context, $antlersContext);

    expect($winner)->toBe($term);
});

it('sets the reserved context key when injecting the primary related taxonomy term', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $localized = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('inDefaultLocale')->once()->andReturn($localized);

    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod(
        'injectSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay'
    );
    $method->setAccessible(true);

    $context = [
        'overlay_slot' => collect([$term]),
    ];
    $antlersContext = ['overlay_slot' => true];

    $out = $method->invoke($service, $context, $antlersContext);

    expect(data_get($out, SchemaOutputService::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM))
        ->toBe($localized);
});

it('hydrates terms-like fields on a real certification fixture', function () {
    $term = TermFacade::find('certifications::american-board-of-orthopaedic-surgery-orthopaedic-surgery');

    if ($term === null) {
        Assert::markTestSkipped('Fixture certification term missing from content.');
    }

    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod(
        'injectSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay'
    );
    $method->setAccessible(true);

    $context = [
        'activity' => collect([$term]),
    ];
    $antlersContext = ['activity' => true];

    $out = $method->invoke($service, $context, $antlersContext);

    $injected = data_get($out, SchemaOutputService::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM);

    expect($injected)->toBeInstanceOf(Fluent::class);

    $audience = $injected instanceof Fluent ? $injected->get('audience') : null;
    expect($audience)->toBeInstanceOf(Collection::class);
    expect($audience instanceof Collection ? $audience->isNotEmpty() : false)->toBeTrue();
    $firstAudienceTerm = $audience instanceof Collection ? $audience->first() : null;
    expect($firstAudienceTerm)->toBeInstanceOf(LocalizedTerm::class);
    expect($firstAudienceTerm instanceof LocalizedTerm ? $firstAudienceTerm->slug() : null)->toBe('orthopaedic-surgeons');
});

it('keeps only grouped handles referenced in the template', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod(
        'filterGroupedTermsLikeBlueprintFieldsByAntlersTemplateReferences'
    );
    $method->setAccessible(true);

    $organizationField = Mockery::mock(Field::class);
    $specialtyField = Mockery::mock(Field::class);

    $grouped = [
        'organization' => [$organizationField],
        'specialty' => [$specialtyField],
    ];

    $template = '{{ organization }}{{ schema_object }}{{ /organization }}';

    $filtered = $method->invoke($service, $grouped, $template);

    expect($filtered)->toHaveKey('organization');
    expect($filtered)->not->toHaveKey('specialty');
});

it('keeps the full grouped map when the template has no matching handles', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod(
        'filterGroupedTermsLikeBlueprintFieldsByAntlersTemplateReferences'
    );
    $method->setAccessible(true);

    $organizationField = Mockery::mock(Field::class);

    $grouped = [
        'organization' => [$organizationField],
    ];

    $template = '{{ something_else entirely }}';

    $filtered = $method->invoke($service, $grouped, $template);

    expect($filtered)->toBe($grouped);
});

it('leaves unreferenced overlay slugs raw', function () {
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod(
        'hydrateAntlersOverlayTermSlugValuesIfRaw'
    );
    $method->setAccessible(true);

    $rawSlugList = [
        'taxonomy-term-slug-a',
        'taxonomy-term-slug-b',
    ];
    $context = [
        'noise_terms' => $rawSlugList,
    ];
    $antlersContext = [
        'noise_terms' => $rawSlugList,
    ];
    $template = '{{ unrelated_field }}';

    $out = $method->invoke(
        $service,
        $context,
        $antlersContext,
        $template
    );

    expect(data_get($out, 'noise_terms'))->toBe($rawSlugList);
});
