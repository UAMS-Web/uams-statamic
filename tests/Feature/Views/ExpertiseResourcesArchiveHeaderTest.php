<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Cascade;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The Areas of Expertise and Clinical Resources archive heading and introduction,
 * and the Areas of Expertise pager (UAMS-Web/uams-statamic#1584).
 *
 * Each archive opens with a heading and introduction read from the
 * `uams_fad_settings` global, as Find-a-Doc's archive templates read their
 * options; the introduction is omitted when empty and the heading falls back to
 * the archive's name. The expertise pager shows only when there is more than
 * one page (Find-a-Doc hides it at three or fewer results, which at 12 per page
 * is always one page), and each pager link lands on the listing.
 *
 * Built in an isolated Stache so the seeded global and the
 * `collection:clinical_expertise` tag run against real data.
 *
 * @command php artisan test --compact tests/Feature/Views/ExpertiseResourcesArchiveHeaderTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Seed the `uams_fad_settings` global for the default site with the given values.
 *
 * @param  array<string, mixed>  $data
 */
function seedArchiveSettings(array $data): void
{
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('uams_fad_settings');
    $set->title('Find-a-Doc Settings');
    $set->save();

    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();

    /** @var Variables $vars */
    $vars = $set->makeLocalization($defaultSite->handle());
    $vars->data($data);
    $vars->save();
}

/**
 * Render an archive header partial with the cascade's globals in scope, as a
 * live request would hand them to the template.
 */
function renderArchiveHeader(string $partial): string
{
    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();

    /** @var array<string, mixed> $cascade */
    $cascade = Cascade::instance()->withSite($defaultSite)->hydrate()->toArray();

    return (string) Antlers::parse('{{ partial:'.$partial.' }}', $cascade, true);
}

/** Seed the given number of published Areas of Expertise. */
function seedExpertiseEntries(int $count): void
{
    $collection = CollectionFacade::make('clinical_expertise');
    $collection->title('Areas of Expertise');
    $collection->save();

    $blueprint = Blueprint::make('clinical_expertise');
    $blueprint->setNamespace('collections.clinical_expertise');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]],
    ]);
    $blueprint->save();

    foreach (range(1, $count) as $number) {
        /** @var Statamic\Entries\Entry $entry */
        $entry = Entry::make();
        $entry->collection('clinical_expertise');
        $entry->blueprint('clinical_expertise');
        $entry->slug(sprintf('area-%02d', $number));
        $entry->published(true);
        $entry->data(['title' => sprintf('Area %02d', $number)]);
        tap($entry)->saveQuietly();
    }
}

it('shows the expertise archive heading and introduction from settings', function (): void {
    seedArchiveSettings([
        'expertise_archive_headline' => 'Our Specialties',
        'expertise_archive_intro_text' => '<p>Find care across every specialty at UAMS Health.</p>',
    ]);

    $out = renderArchiveHeader('clinical_expertise/archive_header');

    expect($out)->toContain('<h1 class="entry-title" itemprop="headline">Our Specialties</h1>')
        ->and($out)->toContain('class="entry-content clearfix archive-intro"')
        ->and($out)->toContain('Find care across every specialty at UAMS Health.');
});

it('omits the expertise archive introduction when it is empty', function (): void {
    seedArchiveSettings(['expertise_archive_headline' => 'Our Specialties', 'expertise_archive_intro_text' => null]);

    $out = renderArchiveHeader('clinical_expertise/archive_header');

    expect($out)->toContain('Our Specialties')
        ->and($out)->not->toContain('archive-intro');
});

it('falls back to the expertise archive name when the heading setting is empty', function (): void {
    seedArchiveSettings([]);

    $out = renderArchiveHeader('clinical_expertise/archive_header');

    expect($out)->toContain('<h1 class="entry-title" itemprop="headline">Areas of Expertise</h1>')
        ->and($out)->not->toContain('archive-intro');
});

it('escapes the editor-controlled expertise archive heading', function (): void {
    seedArchiveSettings(['expertise_archive_headline' => '<script>alert(1)</script>']);

    $out = renderArchiveHeader('clinical_expertise/archive_header');

    expect($out)->not->toContain('<script>alert(1)</script>')
        ->and($out)->toContain('&lt;script&gt;');
});

it('shows the clinical resources archive heading and introduction from settings', function (): void {
    seedArchiveSettings([
        'clinical_resource_archive_headline' => 'Patient Resources',
        'clinical_resource_archive_intro_text' => '<p>Articles, videos and guides from UAMS Health.</p>',
    ]);

    $out = renderArchiveHeader('clinical_resources/archive_header');

    expect($out)->toContain('<h1 class="entry-title" itemprop="headline">Patient Resources</h1>')
        ->and($out)->toContain('class="entry-content clearfix archive-intro"')
        ->and($out)->toContain('Articles, videos and guides from UAMS Health.');
});

it('omits the clinical resources archive introduction when it is empty', function (): void {
    seedArchiveSettings(['clinical_resource_archive_headline' => 'Patient Resources']);

    $out = renderArchiveHeader('clinical_resources/archive_header');

    expect($out)->toContain('Patient Resources')
        ->and($out)->not->toContain('archive-intro');
});

it('falls back to the clinical resources archive name when the heading setting is empty', function (): void {
    seedArchiveSettings([]);

    $out = renderArchiveHeader('clinical_resources/archive_header');

    expect($out)->toContain('<h1 class="entry-title" itemprop="headline">Clinical Resources</h1>');
});

it('opens each archive index with its header partial', function (string $view, string $partial): void {
    $source = (string) file_get_contents(resource_path("views/$view.antlers.html"));

    expect(substr_count($source, '{{ partial:'.$partial.' }}'))->toBe(1);
})->with([
    'expertise' => ['clinical_expertise/index', 'clinical_expertise/archive_header'],
    'resources' => ['clinical_resources/index', 'clinical_resources/archive_header'],
]);

it('hides the expertise pager when there are three or fewer results', function (): void {
    seedExpertiseEntries(3);

    $out = (string) Antlers::parse('{{ partial:clinical_expertise/listing }}', ['get' => []], true);

    expect($out)->toContain('Area 03')
        ->and($out)->not->toContain('expertise-pagination');
});

it('shows the expertise pager when there is more than one page, with links that land on the listing', function (): void {
    seedExpertiseEntries(13);

    $out = (string) Antlers::parse('{{ partial:clinical_expertise/listing }}', ['get' => []], true);

    expect($out)->toContain('class="expertise-pagination"')
        ->and($out)->toContain('Page 1 of 2')
        ->and($out)->toMatch('#href="[^"]*page[=/]2\#expertise-listing" rel="next"#')
        ->and($out)->toContain('id="expertise-listing"');
});
