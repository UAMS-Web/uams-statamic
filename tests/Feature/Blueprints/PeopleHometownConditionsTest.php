<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\Field;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\StacheIsolatedTestCase;
use UAMSWeb\Aether\IncompleteEntries\Services\EntryValidation\EntryValidator;

// Isolated Stache: the reported-field cases persist a collection, a blueprint and entries
uses(StacheIsolatedTestCase::class);

/**
 * The three hometown fields on the `people` blueprint carry one condition key,
 * `if` on `home_country_query`, and their resident-or-fellow audience comes
 * through `home_country_query`, which keeps its own `if_any` (#528, #533).
 */
const HOMETOWN_COLLECTION = 'people_hometown_conditions';

const HOMETOWN_FIELDS = ['hometown_us', 'hometown_canada', 'hometown_international'];

/**
 * The live `people` blueprint, read from its file: the isolated Stache does not
 * carry the repository's blueprints.
 */
function peopleHometownBlueprint(): StatamicBlueprint
{
    /** @var array<string, mixed> $contents */
    $contents = Yaml::parseFile(base_path('resources/blueprints/collections/people/person.yaml'));

    $blueprint = Blueprint::make('person');
    Assert::assertInstanceOf(StatamicBlueprint::class, $blueprint);
    $blueprint->setNamespace('collections.people');
    $blueprint->setContents($contents);

    return $blueprint;
}

/**
 * The resolved config of one field on the live `people` blueprint.
 *
 * @return array<string, mixed>
 */
function peopleHometownFieldConfig(string $handle): array
{
    $field = peopleHometownBlueprint()->field($handle);
    Assert::assertInstanceOf(Field::class, $field);

    /** @var array<string, mixed> $config */
    $config = $field->config();

    return $config;
}

/**
 * The incomplete-entries widget's validation errors for one published entry of
 * a collection whose blueprint copies the live condition chain.
 *
 * @param  array<string, mixed>  $data
 * @return list<string>
 */
function peopleHometownReportedFields(array $data): array
{
    /** @var StatamicCollection $collection */
    $collection = CollectionFacade::make(HOMETOWN_COLLECTION);
    $collection->title(HOMETOWN_COLLECTION);
    $collection->save();

    // Copy the chain from the live blueprint, so the case follows its conditions
    $handles = ['uams_worker_query', 'resident_query', 'fellow_query', 'home_country_query', ...HOMETOWN_FIELDS];
    $fields = (new Collection($handles))
        ->mapWithKeys(fn (string $handle): array => [$handle => peopleHometownFieldConfig($handle)])
        ->all();

    $blueprint = Blueprint::makeFromFields($fields);
    $blueprint->setHandle(HOMETOWN_COLLECTION);
    $blueprint->setNamespace('collections.'.HOMETOWN_COLLECTION);
    $blueprint->save();

    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection(HOMETOWN_COLLECTION);
    $entry->slug('person');
    $entry->published(true);
    $entry->data(['title' => 'Person', ...$data]);
    $entry->save();

    $entryBlueprint = $entry->blueprint();
    Assert::assertInstanceOf(StatamicBlueprint::class, $entryBlueprint);

    /** @var list<string> $reported */
    $reported = array_keys(app(EntryValidator::class)->validate($entry, $entryBlueprint)->errors->toArray());

    return array_values(array_intersect($reported, HOMETOWN_FIELDS));
}

it('gives each hometown field one condition key, if on home_country_query', function (string $handle, string $country): void {
    $config = peopleHometownFieldConfig($handle);

    expect(array_values(array_intersect(array_keys($config), [
        'if', 'if_any', 'show_when', 'show_when_any', 'unless', 'unless_any', 'hide_when', 'hide_when_any',
    ])))->toBe(['if'])
        ->and($config['if'])->toBe(['home_country_query' => 'equals '.$country]);
})->with([
    'hometown_us' => ['hometown_us', 'us'],
    'hometown_canada' => ['hometown_canada', 'canada'],
    'hometown_international' => ['hometown_international', 'other'],
]);

it('keeps the resident-or-fellow if_any on home_country_query', function (): void {
    expect(peopleHometownFieldConfig('home_country_query')['if_any'] ?? null)->toBe([
        'resident_query' => 'equals true',
        'fellow_query' => 'equals true',
    ]);
});

it('reports an empty hometown_us for a resident whose home country is us', function (): void {
    expect(peopleHometownReportedFields([
        'uams_worker_query' => true,
        'resident_query' => true,
        'home_country_query' => 'us',
    ]))->toBe(['hometown_us']);
});

it('reports no hometown field for a person who is neither a resident nor a fellow', function (): void {
    expect(peopleHometownReportedFields([
        'uams_worker_query' => true,
        'resident_query' => false,
        'fellow_query' => false,
    ]))->toBe([]);
});
