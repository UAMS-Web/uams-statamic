<?php

declare(strict_types=1);

use App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver;
use Illuminate\Support\Facades\Facade;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Entries\EntryRepository;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Stache\Query\EntryQueryBuilder;

uses(MockeryPHPUnitIntegration::class);

// No application is booted here, so nothing else resets a facade a test swaps for a mock.
afterEach(function (): void {
    Facade::clearResolvedInstances();
});

/**
 * @covers \App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver
 *
 * The routed-section resolver is the 404 gate + section map for UAMS-Web/uams-statamic-archive#1058's
 * `/{entry}/{section}` virtual subpages. These tests pin the section enum (which
 * also constrains the route), the blueprint guard, and the populated-vs-empty
 * decision, using a mocked Entry so no Stache is required. The structure-children
 * branch is driven through the protected `hasPublishedExpertiseChildren` seam.
 *
 * Mutation: 100% on App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver (87 mutants, all tested; Windows, #885).
 *
 * @command  php artisan test --compact tests/Unit/Services/ClinicalExpertise/ClinicalExpertiseSectionResolverTest.php
 */

/**
 * Build a mocked clinical_expertise-collection entry.
 *
 * @param  array<string, mixed>  $values  handle => stored value
 */
function fakeExpertiseEntry(string $blueprintHandle, array $values, ?string $id = 'aoe-1'): Entry
{
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->allows('handle')->andReturn($blueprintHandle);

    /** @var Entry&MockInterface $entry */
    $entry = Mockery::mock(Entry::class);
    $entry->allows('blueprint')->andReturn($blueprint);
    $entry->allows('id')->andReturn($id);
    $entry->allows('value')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    return $entry;
}

/**
 * A resolver whose structure-children check is forced, so the `specialties`
 * branch is testable without a live structure tree.
 */
function resolverWithChildren(bool $hasChildren): ClinicalExpertiseSectionResolver
{
    return new class($hasChildren) extends ClinicalExpertiseSectionResolver
    {
        public function __construct(private bool $hasChildren) {}

        protected function hasPublishedExpertiseChildren(Entry $entry): bool
        {
            return $this->hasChildren;
        }
    };
}

it('exposes the five routed section tokens in order', function () {
    expect(ClinicalExpertiseSectionResolver::tokens())
        ->toBe(['providers', 'locations', 'specialties', 'related', 'resources']);
});

it('single-sources the route constraint from the section tokens', function () {
    expect(ClinicalExpertiseSectionResolver::routeConstraint())
        ->toBe('providers|locations|specialties|related|resources');
});

it('recognizes known section tokens and rejects unknown ones', function () {
    $resolver = new ClinicalExpertiseSectionResolver;

    expect($resolver->isKnownSection('providers'))->toBeTrue();
    expect($resolver->isKnownSection('resources'))->toBeTrue();
    expect($resolver->isKnownSection('conditions'))->toBeFalse();
    expect($resolver->isKnownSection('overview'))->toBeFalse();
});

it('404s (null) when the entry is not a clinical_expertise entry', function () {
    $resolver = new ClinicalExpertiseSectionResolver;
    $entry = fakeExpertiseEntry('basic_page', ['clinical_providers' => ['p1']]);

    expect($resolver->resolve($entry, 'providers'))->toBeNull();
});

it('404s (null) for an unknown section token even on an AoE entry', function () {
    $resolver = new ClinicalExpertiseSectionResolver;
    $entry = fakeExpertiseEntry('clinical_expertise', ['clinical_providers' => ['p1']]);

    expect($resolver->resolve($entry, 'conditions'))->toBeNull();
});

it('404s (null) when a relationship section has no related items', function () {
    $resolver = new ClinicalExpertiseSectionResolver;

    expect($resolver->resolve(fakeExpertiseEntry('clinical_expertise', ['clinical_providers' => []]), 'providers'))->toBeNull();
    expect($resolver->resolve(fakeExpertiseEntry('clinical_expertise', ['clinical_locations' => null]), 'locations'))->toBeNull();
    expect($resolver->resolve(fakeExpertiseEntry('clinical_expertise', []), 'resources'))->toBeNull();
});

it('resolves a populated relationship section with its overrides and load id', function () {
    $resolver = new ClinicalExpertiseSectionResolver;
    $entry = fakeExpertiseEntry('clinical_expertise', [
        'clinical_providers' => ['p1', 'p2'],
        'clinical_providers_fpage_intro' => 'Meet the team.',
        'clinical_providers_fpage_excerpt' => 'Short desc.',
        'clinical_providers_fpage_excerpt_query' => true,
        'clinical_providers_fpage_featured_image' => 'img-1',
    ], id: 'cardiology');

    $payload = mustExist($resolver->resolve($entry, 'providers'), 'payload');

    expect($payload)->toMatchArray([
        'section' => 'providers',
        'section_label' => 'Related Clinical Providers',
        'source' => 'clinical_providers',
        'override_prefix' => 'clinical_providers',
        'is_children' => false,
        'load' => 'cardiology',
        'section_intro' => 'Meet the team.',
        'section_excerpt' => 'Short desc.',
        'section_excerpt_query' => true,
        'section_featured_image' => 'img-1',
    ]);

    // Strict identity so a swapped/dropped override-field concat (which yields a
    // null value) cannot pass under toMatchArray's loose comparison.
    expect($payload['section_label'])->toBe('Related Clinical Providers');
    expect($payload['section_intro'])->toBe('Meet the team.');
    expect($payload['section_excerpt'])->toBe('Short desc.');
    expect($payload['section_excerpt_query'])->toBe(true);
    expect($payload['section_featured_image'])->toBe('img-1');
});

it('returns an empty load when the entry has no string id', function () {
    $resolver = new ClinicalExpertiseSectionResolver;
    $entry = fakeExpertiseEntry('clinical_expertise', ['clinical_providers' => ['p1']], id: null);

    $payload = $resolver->resolve($entry, 'providers');

    $payload = mustExist($payload, 'payload');
    expect($payload['load'])->toBe('');
});

it('404s a relationship section whose value is an empty string', function () {
    $resolver = new ClinicalExpertiseSectionResolver;
    $entry = fakeExpertiseEntry('clinical_expertise', ['clinical_resources' => '']);

    expect($resolver->resolve($entry, 'resources'))->toBeNull();
});

it('reads the descendant-prefixed overrides for the structure-children section', function () {
    $resolver = resolverWithChildren(true);
    $entry = fakeExpertiseEntry('clinical_expertise', [
        'clinical_expertise_descendant_fpage_intro' => 'Sub-specialties.',
    ]);

    $payload = $resolver->resolve($entry, 'specialties');

    $payload = mustExist($payload, 'payload');
    expect($payload['source'])->toBe('@children');
    expect($payload['is_children'])->toBeTrue();
    expect($payload['override_prefix'])->toBe('clinical_expertise_descendant');
    expect($payload['section_label'])->toBe('Sub-specialties');
    expect($payload['section_intro'])->toBe('Sub-specialties.');
});

it('resolves each relationship section with its own label and override prefix', function (string $section, string $source, string $label) {
    $entry = fakeExpertiseEntry('clinical_expertise', [
        $source => ['item-1'],
        $source.'_fpage_intro' => 'Intro for '.$section,
    ]);

    $payload = mustExist((new ClinicalExpertiseSectionResolver)->resolve($entry, $section), 'payload');

    expect($payload['section_label'])->toBe($label)
        ->and($payload['source'])->toBe($source)
        ->and($payload['override_prefix'])->toBe($source)
        ->and($payload['section_intro'])->toBe('Intro for '.$section);
})->with([
    'locations' => ['locations', 'clinical_locations', 'Related Clinical Locations'],
    'related' => ['related', 'clinical_expertise_related', 'Related Areas of Expertise'],
    'resources' => ['resources', 'clinical_resources', 'Related Clinical Resources'],
]);

/**
 * Swap the Entry facade's repository for one whose query expects exactly the published-children
 * filter on the given parent id, and answers with the given count.
 *
 * `swap()` rather than `shouldReceive()`: the latter resolves the facade's current root to pick
 * the mock class, and in a worker an earlier booted test has left a container that can no
 * longer build the repository, so it throws `BindingResolutionException` depending on test order.
 */
function expectPublishedChildrenQuery(string $parentId, int $count): void
{
    $query = Mockery::mock(EntryQueryBuilder::class);
    $query->expects('where')->with('collection', 'clinical_expertise')->andReturnSelf();
    $query->expects('where')->with('parent', $parentId)->andReturnSelf();
    $query->expects('where')->with('blueprint', 'clinical_expertise')->andReturnSelf();
    $query->expects('whereStatus')->with('published')->andReturnSelf();
    $query->expects('count')->andReturn($count);

    $repository = Mockery::mock(EntryRepository::class);
    $repository->expects('query')->andReturn($query);

    EntryFacade::swap($repository);
}

it('counts published clinical_expertise children of the entry for the specialties section', function (int $count, bool $populated) {
    expectPublishedChildrenQuery('aoe-1', $count);

    $sections = (new ClinicalExpertiseSectionResolver)->populatedSections(fakeExpertiseEntry('clinical_expertise', []));

    expect(in_array('specialties', $sections, true))->toBe($populated);
})->with([
    'no children' => [0, false],
    'one child' => [1, true],
    'several children' => [3, true],
]);

it('treats an entry with no string id as having no children, without querying', function () {
    $repository = Mockery::mock(EntryRepository::class);
    $repository->shouldNotReceive('query');
    EntryFacade::swap($repository);

    expect((new ClinicalExpertiseSectionResolver)->resolve(fakeExpertiseEntry('clinical_expertise', [], id: null), 'specialties'))
        ->toBeNull();
});

it('404s the specialties section when the entry has no published expertise children', function () {
    $resolver = resolverWithChildren(false);
    $entry = fakeExpertiseEntry('clinical_expertise', []);

    expect($resolver->resolve($entry, 'specialties'))->toBeNull();
});

it('treats a single non-array relationship value as populated', function () {
    $resolver = new ClinicalExpertiseSectionResolver;
    $entry = fakeExpertiseEntry('clinical_expertise', ['clinical_resources' => 'r1']);

    expect($resolver->resolve($entry, 'resources'))->not->toBeNull();
});

it('lists only the populated relationship section tokens, in order', function () {
    // No published children (specialties excluded); providers + resources populated.
    $resolver = resolverWithChildren(false);
    $entry = fakeExpertiseEntry('clinical_expertise', [
        'clinical_providers' => ['p1'],
        'clinical_resources' => ['r1'],
    ]);

    expect($resolver->populatedSections($entry))->toBe(['providers', 'resources']);
});

it('includes the specialties token when the AoE has published children', function () {
    $resolver = resolverWithChildren(true);
    $entry = fakeExpertiseEntry('clinical_expertise', ['clinical_providers' => ['p1']]);

    expect($resolver->populatedSections($entry))->toBe(['providers', 'specialties']);
});

it('lists no populated sections for a non-AoE entry', function () {
    $resolver = resolverWithChildren(false);
    $entry = fakeExpertiseEntry('basic_page', ['clinical_providers' => ['p1']]);

    expect($resolver->populatedSections($entry))->toBe([]);
});
