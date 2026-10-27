<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Asset;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Support\SchemaFixtureStacheTestCase;

/**
 * A person's subjects in the Person `knowsAbout` JSON-LD after UAMS-Web/uams-statamic-archive#2822 made
 * `subject_matter` a collection.
 *
 * The Person / ContactPoint type base used to branch on `taxonomy == 'subject_matter'`
 * inside its `knows_about_terms` array. The subjects now arrive through
 * `knows_about_entries`, directly after the clinical specializations, so the base's
 * entries array renders each one with the same Subject Matter DefinedTerm node
 * (`f15f2efa…`): the subject's name and its sameAs URLs.
 *
 * Rendered over {@see SchemaFixtureStacheTestCase}'s Star-Trek graph, whose Stache is
 * a throwaway copy, so the subject entries and Kirk's re-save stay disposable.
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdSubjectMatterKnowsAboutTest.php
 */
uses(SchemaFixtureStacheTestCase::class, MockeryPHPUnitIntegration::class);

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();

    foreach (['subject_matter', 'clinical_specializations'] as $handle) {
        /** @var Statamic\Entries\Collection $collection */
        $collection = Collection::make($handle);
        $collection->sites(['default']);
        $collection->save();
    }
});

const SUBJECT_LD_KIRK_ID = 'aaaa1111-0000-4000-8000-000000000001';

const SUBJECT_LD_KIRK_URL = 'http://uams-statamic.test/people/james-kirk';

/**
 * Persist a published entry in a collection.
 *
 * @param  array<string, mixed>  $data
 */
function subjectLdEntry(string $collection, string $blueprint, string $slug, array $data): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->blueprint($blueprint);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => $slug, ...$data]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Give Kirk the fields, then render his profile's JSON-LD and return his Person
 * node's `knowsAbout` as a list.
 *
 * @param  array<string, mixed>  $fields
 * @return list<mixed>
 */
function subjectLdKirkKnowsAbout(array $fields): array
{
    /** @var EntryInstance $kirk */
    $kirk = Entry::find(SUBJECT_LD_KIRK_ID);

    foreach ($fields as $handle => $value) {
        $kirk->set($handle, $value);
    }

    $kirk->saveQuietly();

    app()->instance('request', Request::create(SUBJECT_LD_KIRK_URL, 'GET'));
    Asset::shouldReceive('find')->andReturn(null);

    $script = app(SchemaOutputService::class)->outputForCurrentContext();
    if (preg_match('/<script[^>]*application\/ld\+json[^>]*>(.*?)<\/script>/is', $script, $matches) !== 1) {
        Assert::fail('Expected a JSON-LD script tag in the rendered output.');
    }

    /** @var array<string, mixed> $document */
    $document = json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);

    $person = subjectLdFindNode($document, SUBJECT_LD_KIRK_URL);
    Assert::assertIsArray($person, 'Expected a full Person node for Kirk.');

    $knowsAbout = $person['knowsAbout'] ?? [];

    return \is_array($knowsAbout) && array_is_list($knowsAbout) ? $knowsAbout : [$knowsAbout];
}

/**
 * The first full node (more keys than `@id`) with the given `@id`, anywhere in the tree.
 *
 * @return array<string, mixed>|null
 */
function subjectLdFindNode(mixed $value, string $atId): ?array
{
    if (! \is_array($value)) {
        return null;
    }

    if (($value['@id'] ?? null) === $atId && \count($value) > 1) {
        /** @var array<string, mixed> $value */
        return $value;
    }

    foreach ($value as $child) {
        $found = subjectLdFindNode($child, $atId);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * Every DefinedTerm in a `knowsAbout` list, keyed by name.
 *
 * @param  list<mixed>  $knowsAbout
 * @return array<string, array<string, mixed>>
 */
function subjectLdDefinedTerms(array $knowsAbout): array
{
    $terms = [];

    foreach ($knowsAbout as $item) {
        if (! \is_array($item) || ($item['@type'] ?? null) !== 'DefinedTerm') {
            continue;
        }

        $name = $item['name'] ?? null;

        if (\is_string($name)) {
            /** @var array<string, mixed> $item */
            $terms[$name] = $item;
        }
    }

    return $terms;
}

it('lists a research-interest subject in knowsAbout as a DefinedTerm with its sameAs', function (): void {
    $subject = subjectLdEntry('subject_matter', 'subject_matter', 'xenobiology', [
        'title' => 'xenobiology',
        'same_as' => [['id' => 'sa1', 'url' => 'https://www.wikidata.org/wiki/Q1155395', 'type' => 'new_set', 'enabled' => true]],
    ]);

    $terms = subjectLdDefinedTerms(subjectLdKirkKnowsAbout([
        'research_interests' => [['type' => 'controlled_vocabulary', 'subject_matter' => [$subject->id()]]],
    ]));

    expect($terms)->toHaveKey('xenobiology')
        ->and($terms['xenobiology']['sameAs'] ?? null)->toContain('https://www.wikidata.org/wiki/Q1155395');
});

it('emits a subject and a specialization side by side, each once', function (): void {
    $subject = subjectLdEntry('subject_matter', 'subject_matter', 'exobiology', ['title' => 'exobiology']);
    $thoracic = subjectLdEntry('clinical_specializations', 'clinical_specialization', '208g00000x', [
        'code_query' => true,
        'code' => '208G00000X',
        'code_name' => 'Thoracic Surgery',
        'extension_query' => false,
    ]);

    // The two groups share one array; a comma placed wrongly would make the JSON-LD unparseable
    $knowsAbout = subjectLdKirkKnowsAbout([
        'clinical_specializations' => [$thoracic->id()],
        'research_interests' => [['type' => 'controlled_vocabulary', 'subject_matter' => [$subject->id()]]],
    ]);

    $codes = array_values(array_filter(array_map(fn (mixed $item): mixed => \is_array($item) ? ($item['termCode'] ?? null) : null, $knowsAbout)));
    $names = array_values(array_filter(array_map(fn (mixed $item): mixed => \is_array($item) ? ($item['name'] ?? null) : null, $knowsAbout)));

    // Counted over the raw list, so a duplicated node would show as a second occurrence
    expect(array_count_values(array_filter($names, 'is_string'))['exobiology'] ?? 0)->toBe(1)
        ->and($codes)->toBe(['208G00000X']);
});

it('emits no subject DefinedTerm for a person without one', function (): void {
    expect(subjectLdDefinedTerms(subjectLdKirkKnowsAbout([])))->not->toHaveKey('xenobiology');
});

it('lists a clinical specialization chosen in a subject field in knowsAbout (#2823)', function (): void {
    // Since UAMS-Web/uams-statamic-archive#2823 a clinical concept is chosen from the clinical specializations, so a
    // research interest can point at one; it must reach knowsAbout as the code-set node
    $coded = subjectLdEntry('clinical_specializations', 'clinical_specialization', '207sg0202x', [
        'title' => 'Clinical Biochemical Genetics Physician',
        'code_query' => true,
        'code' => '207SG0202X',
        'code_name' => 'Clinical Biochemical Genetics',
        'extension_query' => false,
    ]);
    $subject = subjectLdEntry('subject_matter', 'subject_matter', 'exobiology', ['title' => 'exobiology']);

    $knowsAbout = subjectLdKirkKnowsAbout([
        'research_interests' => [['type' => 'controlled_vocabulary', 'subject_matter' => [$coded->id(), $subject->id()]]],
    ]);

    $codes = array_values(array_filter(array_map(fn (mixed $item): mixed => \is_array($item) ? ($item['termCode'] ?? null) : null, $knowsAbout)));

    expect($codes)->toBe(['207SG0202X'])
        ->and(subjectLdDefinedTerms($knowsAbout))->toHaveKey('exobiology');
});
