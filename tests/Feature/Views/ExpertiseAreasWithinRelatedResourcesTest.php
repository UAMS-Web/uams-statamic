<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The "Areas Within" cards, the related areas of expertise, and the four newest
 * related clinical resources on an Area of Expertise page (UAMS-Web/uams-statamic#1581),
 * matching Find-a-Doc's single-expertise page.
 *
 * Built in an isolated Stache with a structured `clinical_expertise` collection and
 * its tree, and a dated `clinical_resources` collection, so the child query, the
 * relationship augmentation and the date ordering all run against real entries.
 * Entries are saved quietly, so no search index is touched.
 *
 * @command php artisan test --compact tests/Feature/Views/ExpertiseAreasWithinRelatedResourcesTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Persist a published `clinical_expertise` entry.
 *
 * @param  array<string, mixed>  $data
 */
function makeAreasWithinEntry(string $id, string $slug, string $title, array $data = []): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_expertise');
    $entry->blueprint('clinical_expertise');
    $entry->id($id);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => $title, ...$data]);
    $entry->saveQuietly();
}

/** Persist a published, dated `clinical_resources` article. */
function makeAreasWithinResource(string $id, string $title, string $date): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_resources');
    $entry->blueprint('article');
    $entry->id($id);
    $entry->slug($id);
    $entry->published(true);
    $entry->date(Carbon::parse($date));
    $entry->data(['title' => $title]);
    $entry->saveQuietly();
}

/**
 * Render a partial with the given Area of Expertise entry's augmented data in scope,
 * as the show template hands it to its partials.
 */
function renderAreaOfExpertisePartial(string $partial, string $entryId): string
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::find($entryId);

    /** @var array<string, mixed> $scope */
    $scope = $entry->toAugmentedArray();

    return (string) Antlers::parse('{{ partial:'.$partial.' }}', $scope, true);
}

beforeEach(function (): void {
    /** @var Collection $resources */
    $resources = CollectionFacade::make('clinical_resources');
    $resources->title('Clinical Resources');
    $resources->routes('/resources/{slug}');
    $resources->dated(true);
    $resources->save();

    /** @var Statamic\Fields\Blueprint $articleBlueprint */
    $articleBlueprint = Blueprint::make('article');
    $articleBlueprint->setNamespace('collections.clinical_resources');
    $articleBlueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]],
    ]);
    $articleBlueprint->save();

    /** @var Collection $collection */
    $collection = CollectionFacade::make('clinical_expertise');
    $collection->title('Areas of Expertise');
    $collection->routes('/expertise/{slug}');
    $collection->structureContents(['root' => false]);
    $collection->save();

    // Minimal AoE blueprint: the relationship fields the partials read, plus the toggle,
    // and no `featured_image_16_9` assets field, so the card renders its fallback image.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make('clinical_expertise');
    $blueprint->setNamespace('collections.clinical_expertise');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'excerpt', 'field' => ['type' => 'textarea']],
            ['handle' => 'child_expertise_hidden', 'field' => ['type' => 'toggle']],
            ['handle' => 'clinical_expertise_related', 'field' => ['type' => 'entries', 'collections' => ['clinical_expertise']]],
            ['handle' => 'clinical_resources', 'field' => ['type' => 'entries', 'collections' => ['clinical_resources']]],
        ]]]]],
    ]);
    $blueprint->save();

    foreach ([
        ['res-jan', 'January Guide', '2026-01-15'],
        ['res-mar', 'March Guide', '2026-03-15'],
        ['res-may', 'May Guide', '2026-05-15'],
        ['res-jul', 'July Guide', '2026-07-15'],
        ['res-sep', 'September Guide', '2026-09-15'],
    ] as [$id, $title, $date]) {
        makeAreasWithinResource($id, $title, $date);
    }

    makeAreasWithinEntry('aoe-cardiology', 'cardiology', 'Cardiology', [
        'clinical_expertise_related' => ['aoe-vascular', 'aoe-endocrinology'],
        // Listed oldest-first on purpose: the block must order by date, not by field order.
        'clinical_resources' => ['res-jan', 'res-mar', 'res-may', 'res-jul', 'res-sep'],
    ]);
    makeAreasWithinEntry('aoe-structural', 'structural-heart', 'Structural Heart');
    makeAreasWithinEntry('aoe-electro', 'electrophysiology', 'Electrophysiology');
    makeAreasWithinEntry('aoe-hidden-parent', 'pulmonology', 'Pulmonology', ['child_expertise_hidden' => true]);
    makeAreasWithinEntry('aoe-sleep', 'sleep-medicine', 'Sleep Medicine');
    makeAreasWithinEntry('aoe-vascular', 'vascular-surgery', 'Vascular Surgery', ['excerpt' => 'Care for arteries and veins.']);
    makeAreasWithinEntry('aoe-endocrinology', 'endocrinology', 'Endocrinology', [
        'clinical_resources' => ['res-jan', 'res-mar', 'res-may', 'res-jul'],
    ]);

    /** @var Collection $expertiseCollection */
    $expertiseCollection = CollectionFacade::find('clinical_expertise');
    /** @var CollectionStructure $structure */
    $structure = $expertiseCollection->structure();
    /** @var CollectionTree $tree */
    $tree = $structure->makeTree('default', [
        ['entry' => 'aoe-cardiology', 'children' => [
            // Out of alphabetical order on purpose: the cards sort by title.
            ['entry' => 'aoe-structural'],
            ['entry' => 'aoe-electro'],
        ]],
        ['entry' => 'aoe-hidden-parent', 'children' => [
            ['entry' => 'aoe-sleep'],
        ]],
        ['entry' => 'aoe-vascular'],
        ['entry' => 'aoe-endocrinology'],
    ]);
    $tree->save();

    // Re-save each entry so the Stache's `parent` index, which the collection tag's
    // `parent:is` filter reads, records the tree saved after the entries.
    foreach (['aoe-cardiology', 'aoe-structural', 'aoe-electro', 'aoe-hidden-parent', 'aoe-sleep', 'aoe-vascular', 'aoe-endocrinology'] as $id) {
        /** @var Statamic\Entries\Entry $entry */
        $entry = Entry::find($id);
        $entry->saveQuietly();
    }
});

it('shows a parent area its children as cards, alphabetical, without the "Part of" subtitle', function (): void {
    $out = renderAreaOfExpertisePartial('clinical_expertise/areas_within', 'aoe-cardiology');

    expect($out)->toContain('<span class="title">Areas Within Cardiology</span>')
        ->and(substr_count($out, 'class="card expertise-card'))->toBe(2)
        ->and($out)->toContain('<h3 class="card-title h5">')
        ->and($out)->not->toContain('Part of')
        ->and(strpos($out, 'Electrophysiology'))->toBeLessThan((int) strpos($out, 'Structural Heart'));
});

it('hides the children when the area turns child_expertise_hidden on', function (): void {
    $out = renderAreaOfExpertisePartial('clinical_expertise/areas_within', 'aoe-hidden-parent');

    expect(trim($out))->toBe('');
});

it('shows nothing for an area without children', function (): void {
    $out = renderAreaOfExpertisePartial('clinical_expertise/areas_within', 'aoe-vascular');

    expect(trim($out))->toBe('');
});

it('includes the Areas Within partial on the show template', function (): void {
    $source = (string) file_get_contents(resource_path('views/clinical_expertise/show.antlers.html'));

    expect(substr_count($source, '{{ partial:clinical_expertise/areas_within }}'))->toBe(1);
});

it('lists related areas of expertise alphabetically with their short description', function (): void {
    $out = renderAreaOfExpertisePartial('clinical_expertise/related', 'aoe-cardiology');

    expect($out)->toContain('<h2 class="aoe-related-title" id="related-expertise-title">Related Areas of Expertise</h2>')
        ->and($out)->toContain('>Vascular Surgery</a></h3>')
        ->and($out)->toContain('<p>Care for arteries and veins.</p>')
        ->and(strpos($out, '>Endocrinology</a>'))->toBeLessThan((int) strpos($out, '>Vascular Surgery</a>'));
});

it('omits the related areas block when there are none', function (): void {
    $out = renderAreaOfExpertisePartial('clinical_expertise/related', 'aoe-endocrinology');

    expect($out)->not->toContain('Related Areas of Expertise');
});

it('shows the four newest of more than four resources as cards, with a link to the full list', function (): void {
    $out = renderAreaOfExpertisePartial('clinical_expertise/related', 'aoe-cardiology');

    expect(substr_count($out, 'itemtype="https://schema.org/CreativeWork"'))->toBe(4)
        ->and($out)->toContain('September Guide')
        ->and($out)->toContain('March Guide')
        ->and($out)->not->toContain('January Guide')
        ->and(strpos($out, 'September Guide'))->toBeLessThan((int) strpos($out, 'March Guide'))
        ->and($out)->toContain('Want to find more resources related to Cardiology?')
        ->and($out)->toContain('href="/expertise/cardiology/resources"')
        ->and($out)->toContain('>View the Full List</a>');
});

it('shows four or fewer resources without a link to the full list', function (): void {
    $out = renderAreaOfExpertisePartial('clinical_expertise/related', 'aoe-endocrinology');

    expect(substr_count($out, 'itemtype="https://schema.org/CreativeWork"'))->toBe(4)
        ->and($out)->toContain('January Guide')
        ->and($out)->not->toContain('View the Full List');
});

it('omits the resources block when the area has no resources', function (): void {
    $out = renderAreaOfExpertisePartial('clinical_expertise/related', 'aoe-vascular');

    expect($out)->not->toContain('Related Resources');
});
