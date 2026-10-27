<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Support\People\PersonKnowsAboutComputer;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Asset;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Support\SchemaFixtureStacheTestCase;

/**
 * A provider's clinical specializations in the Person `knowsAbout` JSON-LD after
 * UAMS-Web/uams-statamic-archive#2596 made them `clinical_specializations` collection entries.
 *
 * The Person / ContactPoint type base (`1a834c0c…`) used to branch on
 * `taxonomy == 'clinical_specializations'` inside its `knows_about_terms` array. The
 * entries now arrive through `knows_about_entries`, so the base's second array
 * renders them with the same two DefinedTerm nodes: the canonical Health Care
 * Provider Taxonomy node (`1cd75e43…`) for a code-set specialization, and the base
 * node (`ab9c43d8…`) for an extension.
 *
 * Rendered over {@see SchemaFixtureStacheTestCase}'s Star-Trek graph, whose Stache is
 * a throwaway copy, so the specialization entries and Kirk's re-save stay disposable.
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdClinicalSpecializationsKnowsAboutTest.php
 */
uses(SchemaFixtureStacheTestCase::class, MockeryPHPUnitIntegration::class);

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    /** @var Statamic\Entries\Collection $specializations */
    $specializations = Collection::make('clinical_specializations');
    $specializations->sites(['default']);
    $specializations->save();
});

const SPECIALIZATION_LD_KIRK_ID = 'aaaa1111-0000-4000-8000-000000000001';

const SPECIALIZATION_LD_KIRK_URL = 'http://uams-statamic.test/people/james-kirk';

/**
 * Persist a clinical specialization entry.
 *
 * @param  array<string, mixed>  $data
 */
function specializationLdEntry(string $slug, array $data): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('clinical_specializations');
    $entry->blueprint('clinical_specialization');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => $slug, ...$data]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Give Kirk the specializations, then render his profile's JSON-LD and return his
 * Person node's `knowsAbout` as a list.
 *
 * @param  list<string>  $specializationIds
 * @return list<mixed>
 */
function specializationLdKirkKnowsAbout(array $specializationIds): array
{
    /** @var EntryInstance $kirk */
    $kirk = Entry::find(SPECIALIZATION_LD_KIRK_ID);
    $kirk->set('clinical_specializations', $specializationIds);
    $kirk->saveQuietly();

    app()->instance('request', Request::create(SPECIALIZATION_LD_KIRK_URL, 'GET'));
    Asset::shouldReceive('find')->andReturn(null);

    $script = app(SchemaOutputService::class)->outputForCurrentContext();
    if (preg_match('/<script[^>]*application\/ld\+json[^>]*>(.*?)<\/script>/is', $script, $matches) !== 1) {
        Assert::fail('Expected a JSON-LD script tag in the rendered output.');
    }

    /** @var array<string, mixed> $document */
    $document = json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);

    $person = specializationLdFindNode($document, SPECIALIZATION_LD_KIRK_URL);
    Assert::assertIsArray($person, 'Expected a full Person node for Kirk.');

    $knowsAbout = $person['knowsAbout'] ?? [];

    return \is_array($knowsAbout) && array_is_list($knowsAbout) ? $knowsAbout : [$knowsAbout];
}

/**
 * The first full node (more keys than `@id`) with the given `@id`, anywhere in the tree.
 *
 * @return array<string, mixed>|null
 */
function specializationLdFindNode(mixed $value, string $atId): ?array
{
    if (! \is_array($value)) {
        return null;
    }

    if (($value['@id'] ?? null) === $atId && \count($value) > 1) {
        /** @var array<string, mixed> $value */
        return $value;
    }

    foreach ($value as $child) {
        $found = specializationLdFindNode($child, $atId);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * The `termCode` of every DefinedTerm in a `knowsAbout` list.
 *
 * @param  list<mixed>  $knowsAbout
 * @return list<mixed>
 */
function specializationLdTermCodes(array $knowsAbout): array
{
    $codes = [];

    foreach ($knowsAbout as $item) {
        if (\is_array($item) && ($item['@type'] ?? null) === 'DefinedTerm' && isset($item['termCode'])) {
            $codes[] = $item['termCode'];
        }
    }

    return $codes;
}

it('lists a code-set specialization in knowsAbout as a DefinedTerm with its code', function (): void {
    $thoracic = specializationLdEntry('208g00000x', [
        'code_query' => true,
        'code' => '208G00000X',
        'code_name' => 'Thoracic Surgery',
        'extension_query' => false,
    ]);

    // The entry reaches the template through knows_about_entries, not knows_about_terms.
    /** @var EntryInstance $kirk */
    $kirk = Entry::find(SPECIALIZATION_LD_KIRK_ID);
    $kirk->set('clinical_specializations', [$thoracic->id()]);
    expect((new PersonKnowsAboutComputer)->computeEntries($kirk))->toContain($thoracic->id());

    expect(specializationLdTermCodes(specializationLdKirkKnowsAbout([mustBeString($thoracic->id(), 'the specialization id')])))
        ->toContain('208G00000X');
});

it('emits no specialization DefinedTerm for a provider without one', function (): void {
    expect(specializationLdTermCodes(specializationLdKirkKnowsAbout([])))->not->toContain('208G00000X');
});
