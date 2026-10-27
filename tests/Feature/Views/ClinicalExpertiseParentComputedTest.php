<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\ClinicalExpertiseStructureComputedsRegistrar;
use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Tests\Support\StacheIsolatedTestCase;

/**
 * End-to-end coverage for the clinical_expertise structure-tree parent computeds
 * ({@see ClinicalExpertiseStructureComputedsRegistrar},
 * UAMS-Web/uams-statamic-archive#1467): `parent_expertise_name` / `parent_expertise_url` resolve a child Area
 * of Expertise's tree parent, and the index card renders them as the
 * "Part of {parent}" subtitle.
 *
 * Built in an isolated Stache with a real structured collection + tree so the
 * `$entry->parent()` resolution runs against the real structure — the card's
 * rendering from injected scope is covered separately in ClinicalExpertiseCardTest.
 *
 * @command php artisan test --compact tests/Feature/Views/ClinicalExpertiseParentComputedTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Persist a published `clinical_expertise` entry anchored to a controlled term (UAMS-Web/uams-statamic-archive#1066).
 */
function makeExpertiseTreeEntry(string $id, string $slug, string $title): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('clinical_expertise');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint('clinical_expertise');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($slug);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => $title, 'clinical_specializations' => ['207RC0000X']]);
    $entry->save();
}

beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make('clinical_expertise');
    $collection->title('Areas of Expertise');
    $collection->routes('/expertise/{slug}');
    $collection->structureContents(['root' => false]);
    $collection->save();

    // Minimal AoE blueprint omitting the real `featured_image_16_9` assets field, so the
    // card renders its fallback rather than augmenting an assets field with no container.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make('clinical_expertise');
    $blueprint->setNamespace('collections.clinical_expertise');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]],
    ]);
    $blueprint->save();

    // Published AoE entries must be anchored to a controlled term (UAMS-Web/uams-statamic-archive#1066), inert to the
    // parent computeds. The `clinical_expertise` blueprint matches the listing's blueprint:is.
    makeExpertiseTreeEntry('aoe-parent', 'cardiology', 'Cardiology');
    makeExpertiseTreeEntry('aoe-child', 'interventional-cardiology', 'Interventional Cardiology');

    /** @var Collection $expertiseCollection */
    $expertiseCollection = CollectionFacade::find('clinical_expertise');
    /** @var CollectionStructure $structure */
    $structure = $expertiseCollection->structure();
    /** @var CollectionTree $tree */
    $tree = $structure->makeTree('default', [
        ['entry' => 'aoe-parent', 'children' => [
            ['entry' => 'aoe-child'],
        ]],
    ]);
    $tree->save();
});

/** Render the AoE listing partial (no active search). */
function renderExpertiseListingWithParent(): string
{
    return (string) Antlers::parse('{{ partial:clinical_expertise/listing }}', ['get' => []], true);
}

it('renders "Part of {parent}" linking to the parent Area on the child card', function (): void {
    $out = renderExpertiseListingWithParent();
    /** @var Statamic\Entries\Entry $parentEntry */
    $parentEntry = Entry::find('aoe-parent');
    /** @var string $parentUrl */
    $parentUrl = $parentEntry->url();

    expect($out)
        ->toContain('Interventional Cardiology')
        ->toContain('Part of')
        // The parent name resolves to the parent entry's title, linked to its URL.
        ->toContain('<a href="'.$parentUrl.'">Cardiology</a>');
});

it('renders no "Part of" subtitle for the top-level parent Area', function (): void {
    $out = renderExpertiseListingWithParent();

    // Both Areas render, but only the child carries a parent subtitle — the
    // top-level Area resolves null parent computeds, so exactly one card-parent.
    expect(substr_count($out, 'card-parent'))->toBe(1);
});
