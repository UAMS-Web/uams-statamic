<?php

declare(strict_types=1);

use App\Services\ClinicalExpertise\ClinicalExpertiseComputedFieldsService;
use App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Coverage for the AoE subsite-chrome computed service (UAMS-Web/uams-statamic-archive#1057): the nearest-
 * expertise-ancestor walk (self first) and the primary-nav section-link builder.
 * Booted (the ancestor walk memoizes through Blink); the ancestor chain is driven
 * through the protected `ancestorIds` seam so no structure tree is required.
 *
 * @covers \App\Services\ClinicalExpertise\ClinicalExpertiseComputedFieldsService
 *
 * @command  php artisan test --compact tests/Feature/Services/ClinicalExpertise/ClinicalExpertiseComputedFieldsServiceTest.php
 */

/**
 * A mocked clinical_expertise-collection entry.
 */
function computedEntry(string $blueprintHandle, string $id, ?string $url = null): Entry
{
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn($blueprintHandle);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('id')->andReturn($id);

    if ($url !== null) {
        $entry->shouldReceive('url')->andReturn($url);
    }

    return $entry;
}

/**
 * A mocked entry stubbing only value()/id() — enough for the CTA resolution,
 * which never inspects the blueprint.
 *
 * @param  array<string, mixed>  $values
 */
function ctaEntry(array $values, string $id = 'cta-entry'): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('value')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    return $entry;
}

it('does not throw resolving inheritance for an unpersisted entry (CP store path)', function () {
    // The CP resolves computeds before persistence, so the entry id is not yet
    // findable; the ancestor walk must guard rather than findOrFail-throw.
    EntryFacade::shouldReceive('find')->with('unsaved-cta')->andReturnNull();
    $service = new ClinicalExpertiseComputedFieldsService(Mockery::mock(ClinicalExpertiseSectionResolver::class));

    expect($service->resolveEffectiveCta(ctaEntry(['cta' => []], 'unsaved-cta')))->toBeNull();
});

it('reports an unpersisted non-AoE entry as outside a subsite without throwing', function () {
    EntryFacade::shouldReceive('find')->with('unsaved-page')->andReturnNull();
    $service = new ClinicalExpertiseComputedFieldsService(Mockery::mock(ClinicalExpertiseSectionResolver::class));

    expect($service->isWithinExpertiseSubsite(computedEntry('basic_page', 'unsaved-page')))->toBeFalse();
});

it('returns the entry own CTA when it has bars', function () {
    $bars = [['type' => 'uamsweb_block_cta']];
    $service = new ClinicalExpertiseComputedFieldsService(Mockery::mock(ClinicalExpertiseSectionResolver::class));

    expect($service->resolveEffectiveCta(ctaEntry(['cta' => $bars])))->toBe($bars);
});

it('inherits the nearest ancestor CTA when the entry has none', function () {
    $bars = [['type' => 'uamsweb_block_cta']];
    EntryFacade::shouldReceive('find')->with('anc-with-cta')->andReturn(ctaEntry(['cta' => $bars], 'anc-with-cta'));

    $service = new class(Mockery::mock(ClinicalExpertiseSectionResolver::class)) extends ClinicalExpertiseComputedFieldsService
    {
        protected function ancestorIds(int|string $entryId): array
        {
            return ['anc-with-cta'];
        }
    };

    expect($service->resolveEffectiveCta(ctaEntry(['cta' => []], 'leaf-a')))->toBe($bars);
});

it('skips a stale ancestor id that no longer resolves when inheriting a CTA', function () {
    $bars = [['type' => 'uamsweb_block_cta']];
    EntryFacade::shouldReceive('find')->with('stale-cta')->andReturnNull();
    EntryFacade::shouldReceive('find')->with('anc-cta-2')->andReturn(ctaEntry(['cta' => $bars], 'anc-cta-2'));

    $service = new class(Mockery::mock(ClinicalExpertiseSectionResolver::class)) extends ClinicalExpertiseComputedFieldsService
    {
        protected function ancestorIds(int|string $entryId): array
        {
            return ['stale-cta', 'anc-cta-2'];
        }
    };

    expect($service->resolveEffectiveCta(ctaEntry(['cta' => []], 'leaf-d')))->toBe($bars);
});

it('suppresses the inherited CTA when the entry opts out', function () {
    // An ancestor DOES define a CTA — the opt-out must still suppress it (so this
    // differs from the fall-through result, pinning the opt-out branch).
    $bars = [['type' => 'uamsweb_block_cta']];
    EntryFacade::shouldReceive('find')->with('anc-optout')->andReturn(ctaEntry(['cta' => $bars], 'anc-optout'));

    $service = new class(Mockery::mock(ClinicalExpertiseSectionResolver::class)) extends ClinicalExpertiseComputedFieldsService
    {
        protected function ancestorIds(int|string $entryId): array
        {
            return ['anc-optout'];
        }
    };

    expect($service->resolveEffectiveCta(ctaEntry(['cta' => [], 'cta_inherit_disabled' => true], 'leaf-optout')))->toBeNull();
});

it('still renders the own CTA even when inheritance is opted out', function () {
    $bars = [['type' => 'uamsweb_block_cta']];
    $service = new ClinicalExpertiseComputedFieldsService(Mockery::mock(ClinicalExpertiseSectionResolver::class));

    expect($service->resolveEffectiveCta(ctaEntry(['cta' => $bars, 'cta_inherit_disabled' => true])))->toBe($bars);
});

it('returns null when neither the entry nor an ancestor defines a CTA', function () {
    $service = new class(Mockery::mock(ClinicalExpertiseSectionResolver::class)) extends ClinicalExpertiseComputedFieldsService
    {
        protected function ancestorIds(int|string $entryId): array
        {
            return [];
        }
    };

    expect($service->resolveEffectiveCta(ctaEntry(['cta' => []], 'leaf-c')))->toBeNull();
});

it('resolves an AoE entry as its own nearest expertise ancestor', function () {
    $service = new ClinicalExpertiseComputedFieldsService(Mockery::mock(ClinicalExpertiseSectionResolver::class));
    $aoe = computedEntry('clinical_expertise', 'cardiology');

    expect($service->findNearestExpertiseAncestor($aoe))->toBe($aoe);
});

it('reports an AoE entry as within an expertise subsite', function () {
    $service = new ClinicalExpertiseComputedFieldsService(Mockery::mock(ClinicalExpertiseSectionResolver::class));

    expect($service->isWithinExpertiseSubsite(computedEntry('clinical_expertise', 'cardiology')))->toBeTrue();
});

it('reports a non-AoE page with no expertise ancestor as outside any subsite', function () {
    $service = new class(Mockery::mock(ClinicalExpertiseSectionResolver::class)) extends ClinicalExpertiseComputedFieldsService
    {
        protected function ancestorIds(int|string $entryId): array
        {
            return [];
        }
    };

    expect($service->isWithinExpertiseSubsite(computedEntry('basic_page', 'plain')))->toBeFalse();
});

it('walks to the nearest AoE ancestor for a descendant content page', function () {
    $ancestor = computedEntry('clinical_expertise', 'cardiology');
    EntryFacade::shouldReceive('find')->with('cardiology')->andReturn($ancestor);

    $service = new class(Mockery::mock(ClinicalExpertiseSectionResolver::class)) extends ClinicalExpertiseComputedFieldsService
    {
        protected function ancestorIds(int|string $entryId): array
        {
            return ['cardiology'];
        }
    };

    expect($service->findNearestExpertiseAncestor(computedEntry('basic_page', 'child-1')))->toBe($ancestor);
});

it('skips a non-AoE ancestor and resolves the nearest AoE above it', function () {
    $nonAoe = computedEntry('basic_page', 'sub-page');
    $aoe = computedEntry('clinical_expertise', 'cardiology');
    EntryFacade::shouldReceive('find')->with('sub-page')->andReturn($nonAoe);
    EntryFacade::shouldReceive('find')->with('cardiology')->andReturn($aoe);

    $service = new class(Mockery::mock(ClinicalExpertiseSectionResolver::class)) extends ClinicalExpertiseComputedFieldsService
    {
        protected function ancestorIds(int|string $entryId): array
        {
            return ['sub-page', 'cardiology'];
        }
    };

    expect($service->findNearestExpertiseAncestor(computedEntry('basic_page', 'leaf-1')))->toBe($aoe);
});

it('skips a stale ancestor id that no longer resolves to an entry', function () {
    $aoe = computedEntry('clinical_expertise', 'cardiology');
    EntryFacade::shouldReceive('find')->with('gone')->andReturnNull();
    EntryFacade::shouldReceive('find')->with('cardiology')->andReturn($aoe);

    $service = new class(Mockery::mock(ClinicalExpertiseSectionResolver::class)) extends ClinicalExpertiseComputedFieldsService
    {
        protected function ancestorIds(int|string $entryId): array
        {
            return ['gone', 'cardiology'];
        }
    };

    expect($service->findNearestExpertiseAncestor(computedEntry('basic_page', 'leaf-2')))->toBe($aoe);
});

it('builds Overview plus one nav link per populated section of the nearest ancestor', function () {
    $sections = Mockery::mock(ClinicalExpertiseSectionResolver::class);
    $root = computedEntry('clinical_expertise', 'cardiology', 'https://uams.test/aoe/cardiology');
    $sections->shouldReceive('populatedSections')->with($root)->andReturn(['providers', 'specialties']);

    $service = new ClinicalExpertiseComputedFieldsService($sections);

    expect($service->resolveSectionNav($root))->toBe([
        ['label' => 'Overview', 'url' => 'https://uams.test/aoe/cardiology'],
        ['label' => 'Providers', 'url' => 'https://uams.test/aoe/cardiology/providers'],
        ['label' => 'Sub-specialties', 'url' => 'https://uams.test/aoe/cardiology/specialties'],
    ]);
});

it('returns an empty nav when the page is not within an AoE subsite', function () {
    $service = new class(Mockery::mock(ClinicalExpertiseSectionResolver::class)) extends ClinicalExpertiseComputedFieldsService
    {
        protected function ancestorIds(int|string $entryId): array
        {
            return [];
        }
    };

    expect($service->resolveSectionNav(computedEntry('basic_page', 'orphan-1')))->toBe([]);
});

it('returns an empty nav when the nearest ancestor has no URL', function () {
    $root = computedEntry('clinical_expertise', 'cardiology', '');
    $service = new ClinicalExpertiseComputedFieldsService(Mockery::mock(ClinicalExpertiseSectionResolver::class));

    expect($service->resolveSectionNav($root))->toBe([]);
});

it('does not double the slash when the ancestor URL ends in a slash', function () {
    $sections = Mockery::mock(ClinicalExpertiseSectionResolver::class);
    $root = computedEntry('clinical_expertise', 'cardiology', 'https://uams.test/aoe/cardiology/');
    $sections->shouldReceive('populatedSections')->with($root)->andReturn(['providers']);

    $service = new ClinicalExpertiseComputedFieldsService($sections);

    expect($service->resolveSectionNav($root))->toBe([
        ['label' => 'Overview', 'url' => 'https://uams.test/aoe/cardiology/'],
        ['label' => 'Providers', 'url' => 'https://uams.test/aoe/cardiology/providers'],
    ]);
});
