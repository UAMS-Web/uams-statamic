<?php

declare(strict_types=1);

use App\Support\Multisite\DepartmentHubResolver;
use Illuminate\Support\Carbon;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryObject;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * End-to-end coverage for the {{ department_hub }} tag (UAMS-Web/uams-statamic-archive#1602): it resolves the
 * current site's `department_organization` (UAMS-Web/uams-statamic-archive#1556) and returns the org-filtered
 * magazine (lead + type sections) plus the derived leadership zone (UAMS-Web/uams-statamic-archive#1557), or a
 * degrade flag when the site is not a hub.
 *
 * The site→org bridge is stubbed (a resolver subclass) so the tag is exercised
 * against real `posts` / `people` / `organizations` in an isolated Stache without
 * standing up the multisite global. Helpers are prefixed `hub*`.
 *
 * @covers \App\Tags\DepartmentHub
 *
 * @command  php artisan test --compact tests/Feature/Tags/DepartmentHubTagTest.php
 */
uses(StacheIsolatedTestCase::class);

/** Bind a resolver stub that resolves every site to the given organization (or null). */
function hubStubResolver(?EntryObject $organization): void
{
    $stub = new class extends DepartmentHubResolver
    {
        public ?EntryObject $stubbed = null;

        public function organization(string $siteHandle): ?EntryObject
        {
            return $this->stubbed;
        }
    };
    $stub->stubbed = $organization;

    app()->instance(DepartmentHubResolver::class, $stub);
}

/** Create an `organizations` entry with the given id/role. */
function hubOrg(string $id, string $role): EntryObject
{
    /** @var EntryObject $entry */
    $entry = Entry::make();
    /** @var EntryObject $entry */
    $entry = $entry->collection('organizations');
    /** @var EntryObject $entry */
    $entry = $entry->id($id);
    /** @var EntryObject $entry */
    $entry = $entry->slug($id);
    /** @var EntryObject $entry */
    $entry = $entry->locale('default');
    /** @var EntryObject $entry */
    $entry = $entry->published(true);
    /** @var EntryObject $entry */
    $entry = $entry->data(['title' => 'Org '.$id, 'organization_role' => $role]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Create a published news post associated with the given orgs.
 *
 * @param  array<int, string>  $orgs
 */
function hubPost(string $id, string $blueprint, string $date, array $orgs): void
{
    /** @var EntryObject $entry */
    $entry = Entry::make();
    /** @var EntryObject $entry */
    $entry = $entry->collection('posts');
    /** @var EntryObject $entry */
    $entry = $entry->id($id);
    /** @var EntryObject $entry */
    $entry = $entry->slug($id);
    /** @var EntryObject $entry */
    $entry = $entry->locale('news');
    /** @var EntryObject $entry */
    $entry = $entry->blueprint($blueprint);
    /** @var EntryObject $entry */
    $entry = $entry->published(true);
    /** @var EntryObject $entry */
    $entry = $entry->date(Carbon::parse($date));
    /** @var EntryObject $entry */
    $entry = $entry->data(['title' => 'Post '.$id, 'related_organizations' => $orgs]);
    $entry->saveQuietly();
}

/**
 * Create a `people` entry with the given job_title rows.
 *
 * @param  array<int, array<string, mixed>>  $jobTitle
 */
function hubPerson(string $id, array $jobTitle): void
{
    /** @var EntryObject $entry */
    $entry = Entry::make();
    /** @var EntryObject $entry */
    $entry = $entry->collection('people');
    /** @var EntryObject $entry */
    $entry = $entry->id($id);
    /** @var EntryObject $entry */
    $entry = $entry->slug($id);
    /** @var EntryObject $entry */
    $entry = $entry->locale('default');
    /** @var EntryObject $entry */
    $entry = $entry->published(true);
    /** @var EntryObject $entry */
    $entry = $entry->data(['title' => 'Person '.$id, 'job_title' => $jobTitle]);
    $entry->saveQuietly();
}

beforeEach(function (): void {
    foreach ([['organizations', ['default']], ['people', ['default']], ['pages', ['default', 'news']], ['posts', ['default', 'news']]] as [$handle, $sites]) {
        /** @var Collection $collection */
        $collection = CollectionFacade::make($handle);
        /** @var Collection $collection */
        $collection = $collection->title(ucfirst($handle));
        /** @var Collection $collection */
        $collection = $collection->sites($sites);

        if ($handle === 'posts') {
            /** @var Collection $collection */
            $collection = $collection->dated(true);
        }

        $collection->save();
    }

    foreach (['post', 'video'] as $handle) {
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = Blueprint::makeFromFields([
            'title' => ['type' => 'text'],
            'related_organizations' => ['type' => 'entries', 'collections' => ['organizations']],
            'sticky_query' => ['type' => 'toggle'],
        ]);
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = $blueprint->setHandle($handle);
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = $blueprint->setNamespace('collections.posts');
        $blueprint->save();
    }

    // The hub page's own blueprint, so a real entry augments `lead_count` the way
    // the shipped template sees it.
    /** @var Statamic\Fields\Blueprint $hubBlueprint */
    $hubBlueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'lead_count' => ['type' => 'integer'],
    ]);
    /** @var Statamic\Fields\Blueprint $hubBlueprint */
    $hubBlueprint = $hubBlueprint->setHandle('department_hub');
    /** @var Statamic\Fields\Blueprint $hubBlueprint */
    $hubBlueprint = $hubBlueprint->setNamespace('collections.pages');
    $hubBlueprint->save();
});

it('renders the org-filtered magazine and leadership for a hub site', function (): void {
    $org = hubOrg('org-anesthesiology', 'department_academic');
    hubStubResolver($org);

    hubPost('mine', 'post', '2024-05-01', ['org-anesthesiology']);
    hubPost('theirs', 'post', '2024-06-01', ['org-cardio']);
    hubPerson('chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);

    $template = '{{ department_hub lead_count="1" }}{{ if is_hub }}HUB lead:{{ lead }}[{{ id }}]{{ /lead }} people:{{ leadership }}[{{ id }}]{{ /leadership }}{{ else }}DEGRADE{{ /if }}{{ /department_hub }}';

    $out = (string) Antlers::parse($template, []);

    expect($out)->toContain('HUB')
        ->toContain('lead:[mine]')
        ->toContain('people:[chair]')
        ->and($out)->not->toContain('[theirs]');
});

it('offsets a type section past the lead for a hub site', function (): void {
    $org = hubOrg('org-anesthesiology', 'department_academic');
    hubStubResolver($org);

    hubPost('p1', 'post', '2024-05-01', ['org-anesthesiology']);
    hubPost('p2', 'post', '2024-04-01', ['org-anesthesiology']);
    hubPost('p3', 'post', '2024-03-01', ['org-anesthesiology']);

    // lead_count 1 → lead=[p1]; the post section offsets past p1 → p2, p3.
    $template = '{{ department_hub lead_count="1" }}lead:{{ lead }}[{{ id }}]{{ /lead }} {{ sections }}{{ type }}:{{ posts }}[{{ id }}]{{ /posts }};{{ /sections }}{{ /department_hub }}';

    $out = (string) Antlers::parse($template, []);

    expect($out)->toContain('lead:[p1]')
        ->toContain('post:[p2][p3];')
        ->and($out)->not->toContain('post:[p1]');
});

it('honors the lead_count field from a real augmented hub entry', function (): void {
    $org = hubOrg('org-anesthesiology', 'department_academic');
    hubStubResolver($org);

    hubPost('p1', 'post', '2024-05-01', ['org-anesthesiology']);
    hubPost('p2', 'post', '2024-04-01', ['org-anesthesiology']);
    hubPost('p3', 'post', '2024-03-01', ['org-anesthesiology']);

    // The shipped template renders `{{ department_hub }}` with NO lead_count param,
    // so production always takes the CONTEXT branch — where an entry field arrives
    // augmented as a Value, not a scalar. Render against a real entry so a
    // regression that drops the Value unwrap (and silently falls back to the
    // default 5) fails here.
    /** @var EntryObject $hub */
    $hub = Entry::make();
    /** @var EntryObject $hub */
    $hub = $hub->collection('pages');
    /** @var EntryObject $hub */
    $hub = $hub->id('hub-page');
    /** @var EntryObject $hub */
    $hub = $hub->slug('news');
    /** @var EntryObject $hub */
    $hub = $hub->locale('default');
    /** @var EntryObject $hub */
    $hub = $hub->published(true);
    /** @var EntryObject $hub */
    $hub = $hub->blueprint('department_hub');
    /** @var EntryObject $hub */
    $hub = $hub->data(['title' => 'Department News', 'lead_count' => 1]);
    $hub->saveQuietly();

    /** @var array<string, mixed> $context */
    $context = $hub->toAugmentedArray();

    $out = (string) Antlers::parse(
        '{{ department_hub }}lead:{{ lead }}[{{ id }}]{{ /lead }} {{ sections }}{{ type }}:{{ posts }}[{{ id }}]{{ /posts }};{{ /sections }}{{ /department_hub }}',
        $context
    );

    // The entry's lead_count of 1 must win: a 5-post default would pull all three
    // posts into the lead and leave the post section empty.
    expect($out)->toContain('lead:[p1]')
        ->toContain('post:[p2][p3];')
        ->and($out)->not->toContain('lead:[p1][p2]');
});

it('degrades to the base rung when the site is not a hub', function (): void {
    hubStubResolver(null);

    hubPost('any', 'post', '2024-05-01', ['org-anesthesiology']);

    $template = '{{ department_hub }}{{ if is_hub }}HUB{{ else }}DEGRADE{{ /if }}{{ /department_hub }}';

    $out = (string) Antlers::parse($template, []);

    expect($out)->toContain('DEGRADE')
        ->and($out)->not->toContain('HUB');
});
