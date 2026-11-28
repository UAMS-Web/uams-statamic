<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The related-entity lists on an Area of Expertise page (UAMS-Web/uams-statamic#1601), which
 * `clinical_expertise/_related` renders through the shared `related_list` partial.
 *
 * Each relation is a real augmented `entries` value over entries in an isolated Stache, so an
 * empty or all-unpublished relation reaches the view as the query builder it is in production,
 * which a bare `{{ if }}` reads as truthy.
 *
 * @command  php artisan test --compact tests/Feature/Views/ExpertiseRelatedListsTest.php
 */
uses(StacheIsolatedTestCase::class);

const EXPERTISE_RELATED_TARGETS = 'related_targets';

beforeEach(function (): void {
    $collection = CollectionFacade::make(EXPERTISE_RELATED_TARGETS);
    $collection->title('Related Targets');
    $collection->routes('/targets/{slug}');
    $collection->save();
});

/**
 * Persist one related entry.
 */
function expertiseRelatedEntry(string $id, string $title, bool $published = true): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(EXPERTISE_RELATED_TARGETS);
    $entry->id($id);
    $entry->slug($id);
    $entry->published($published);
    $entry->data(['title' => $title]);
    $entry->saveQuietly();
}

/**
 * A real augmented `entries` relation over the given ids.
 *
 * @param  list<string>  $ids  The ids the relation stores.
 */
function expertiseRelation(array $ids): mixed
{
    $field = Blueprint::makeFromFields([
        'related' => ['type' => 'entries', 'collections' => [EXPERTISE_RELATED_TARGETS]],
    ])->field('related');
    if (! $field instanceof Field) {
        throw new RuntimeException('The fixture blueprint did not yield a `related` field.');
    }

    $field->setValue($ids);
    $augmented = $field->augment();
    if (! $augmented instanceof Field) {
        throw new RuntimeException('The fixture `related` field did not augment.');
    }

    return $augmented->value();
}

/**
 * Render the expertise related blocks with the given relations, every other one empty.
 *
 * @param  array<string, list<string>>  $relations  Stored ids, keyed by relation handle.
 */
function renderExpertiseRelated(array $relations): string
{
    $scope = ['title' => 'Cardiology', 'url' => '/cardiology'];
    foreach (['clinical_locations', 'clinical_conditions', 'clinical_services'] as $handle) {
        $scope[$handle] = expertiseRelation($relations[$handle] ?? []);
    }

    return (string) Antlers::parse('{{ partial:clinical_expertise/related }}', $scope, true);
}

it('renders no section for an empty or all-unpublished relation', function (string $handle, string $section, array $ids): void {
    expertiseRelatedEntry('draft', 'Draft Entry', published: false);
    expertiseRelatedEntry('shown', 'Shown Entry');

    // Another list is populated, so the wrapper renders and only the gate under test decides.
    $other = $handle === 'clinical_conditions' ? 'clinical_services' : 'clinical_conditions';
    $html = renderExpertiseRelated([$handle => $ids, $other => ['shown']]);

    expect($html)->not->toContain($section)
        ->and($html)->not->toContain('Draft Entry')
        ->and($html)->toContain('Shown Entry');
})->with([
    'locations, empty' => ['clinical_locations', 'aoe-related-locations', []],
    'locations, unpublished' => ['clinical_locations', 'aoe-related-locations', ['draft']],
    'conditions, empty' => ['clinical_conditions', 'aoe-related-conditions', []],
    'conditions, unpublished' => ['clinical_conditions', 'aoe-related-conditions', ['draft']],
    'treatments, empty' => ['clinical_services', 'aoe-related-services', []],
    'treatments, unpublished' => ['clinical_services', 'aoe-related-services', ['draft']],
]);

it('renders no wrapper when every relation is empty', function (): void {
    expertiseRelatedEntry('draft', 'Draft Entry', published: false);

    $html = renderExpertiseRelated(['clinical_locations' => ['draft']]);

    expect($html)->not->toContain('aoe-related');
});

it('renders each populated list with its classes, heading and links', function (): void {
    expertiseRelatedEntry('clinic', 'Heart Clinic');
    expertiseRelatedEntry('afib', 'Atrial Fibrillation');
    expertiseRelatedEntry('ablation', 'Cardiac Ablation');

    $html = renderExpertiseRelated([
        'clinical_locations' => ['clinic'],
        'clinical_conditions' => ['afib'],
        'clinical_services' => ['ablation'],
    ]);

    expect($html)->toContain('<section class="aoe-related-section aoe-related-locations" id="locations">')
        ->and($html)->toContain('<h2 class="aoe-related-title" data-jump-link="Locations">Related Clinical Locations</h2>')
        ->and($html)->toContain('<section class="aoe-related-section aoe-related-conditions">')
        ->and($html)->toContain('<h2 class="aoe-related-title" data-jump-link="Conditions">Related Clinical Conditions</h2>')
        ->and($html)->toContain('<section class="aoe-related-section aoe-related-services">')
        ->and($html)->toContain('<h2 class="aoe-related-title" data-jump-link="Treatments &amp; Procedures">Related Treatments and Procedures</h2>')
        ->and(substr_count($html, '<ul class="aoe-related-list">'))->toBe(3)
        ->and($html)->toContain('<li><a href="/targets/clinic">Heart Clinic</a></li>')
        ->and($html)->toContain('<li><a href="/targets/afib">Atrial Fibrillation</a></li>')
        ->and($html)->toContain('<li><a href="/targets/ablation">Cardiac Ablation</a></li>');
});

it('escapes a title carrying markup in the expertise lists', function (string $handle): void {
    expertiseRelatedEntry('marked', '<img src=x onerror=alert(1)> Heart & "Lung"');

    $html = renderExpertiseRelated([$handle => ['marked']]);

    expect($html)->not->toContain('<img src=x')
        ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt; Heart &amp; &quot;Lung&quot;');
})->with(['clinical_locations', 'clinical_conditions', 'clinical_services']);

it('keeps no hand-written copy of the list block in the expertise view', function (): void {
    $source = File::get(resource_path('views/clinical_expertise/_related.antlers.html'));

    expect(substr_count($source, 'partial:related_list'))->toBe(4)
        ->and($source)->not->toContain('{{ /clinical_locations }}')
        ->and($source)->not->toContain('{{ /clinical_conditions }}')
        ->and($source)->not->toContain('{{ /clinical_services }}')
        ->and($source)->not->toContain('{{ /eligible_providers }}');
});

it('leaves no view carrying the unused card-list-expertise class', function (): void {
    $carrying = [];
    foreach (File::allFiles(resource_path('views')) as $file) {
        if (str_contains($file->getContents(), 'card-list-expertise')) {
            $carrying[] = $file->getRelativePathname();
        }
    }

    expect($carrying)->toBe([]);
});
