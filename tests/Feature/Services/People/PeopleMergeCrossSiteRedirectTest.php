<?php

declare(strict_types=1);

use App\Services\People\PeopleMergeService;
use App\Support\References\EntryReferenceRepointer;
use App\Tags\FindRedirectRule;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Cross-site serving of people-merge redirects (UAMS-Web/uams-statamic-archive#1088, follow-up to UAMS-Web/uams-statamic-archive#838).
 *
 * UAMS-Web/uams-statamic-archive#838 creates a 301 from each retired localization's URL to the survivor. The
 * UAMS-Web/uams-statamic-archive#838 suite only ever reads the default-site localization, so it never proved a
 * redirect for a loser reachable *only on a non-default site* is actually served
 * there. This test shapes the redirects global like production (registered on ≥2
 * sites, `redirects` field non-localizable) and proves the redirect serves on the
 * non-default site via `find_redirect_rule` — the same per-site read the 404
 * template (`resources/views/errors/_redirects.antlers.html`) performs.
 *
 * Verified mechanism: `find_redirect_rule` reads
 * `GlobalSet::findByHandle('redirects')->in($currentSite)->get('redirects')` — a
 * raw, per-site read that does NOT cascade from the origin localization. So the
 * merge must persist each redirect on the localization of the site it serves
 * (PeopleMergeService::appendRedirects), or it would 404 on that site.
 *
 * @command  php artisan test --compact tests/Feature/Services/People/PeopleMergeCrossSiteRedirectTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Two distinct configured site handles (default first).
 *
 * @return array{0: string, 1: string}
 */
function pmcsTwoSites(): array
{
    /** @var Collection<int|string, Statamic\Sites\Site> $sites */
    $sites = Site::all();
    /** @var array<int, string> $handles */
    $handles = $sites->map(fn (Statamic\Sites\Site $site) => $site->handle())->values()->all();
    Assert::assertGreaterThanOrEqual(2, \count($handles));

    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();
    /** @var string $defaultHandle */
    $defaultHandle = $defaultSite->handle();

    return [$defaultHandle, $handles[0] === $defaultHandle ? $handles[1] : $handles[0]];
}

/**
 * Persist a person entry, optionally rooted in a specific site, bypassing listeners.
 *
 * @param  array<string, mixed>  $data
 */
function pmcsMakePerson(string $id, string $slug, array $data, ?string $site = null): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(PeopleMergeService::peopleCollection());
    $entry->id($id);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);

    if ($site !== null) {
        $entry->locale($site);
    }

    $entry->saveQuietly();

    return $entry;
}

beforeEach(function (): void {
    [$default, $other] = pmcsTwoSites();

    /** @var Collection<int|string, Statamic\Sites\Site> $allSites */
    $allSites = Site::all();
    /** @var array<int, string> $siteHandles */
    $siteHandles = $allSites->map(fn (Statamic\Sites\Site $site) => $site->handle())->values()->all();

    // People collection routed on every site, like production.
    /** @var Statamic\Entries\Collection $peopleCollection */
    $peopleCollection = CollectionFacade::make(PeopleMergeService::peopleCollection());
    $peopleCollection->title('People');
    $peopleCollection->sites($siteHandles);
    $peopleCollection->routes('/people/{slug}');
    $peopleCollection->save();

    $personBlueprint = Blueprint::makeFromFields([
        'bio' => ['type' => 'text', 'display' => 'Biography'],
    ]);
    $personBlueprint->setHandle('person');
    $personBlueprint->setNamespace('collections.'.PeopleMergeService::peopleCollection());
    $personBlueprint->save();

    // The redirects field is non-localizable, mirroring
    // resources/blueprints/globals/redirects.yaml.
    $redirectsBlueprint = Blueprint::makeFromFields([
        'redirects' => ['type' => 'replicator', 'localizable' => false],
    ]);
    $redirectsBlueprint->setHandle('redirects');
    $redirectsBlueprint->setNamespace('globals');
    $redirectsBlueprint->save();

    // Redirects global registered on ≥2 sites with origins null, exactly like the
    // production content/globals/redirects.yaml config.
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->sites([$default => null, $other => null]);
    $set->save();

    foreach ([$default, $other] as $site) {
        /** @var Variables $vars */
        $vars = $set->makeLocalization($site);
        $vars->data(['redirects' => []]);
        $vars->save();
    }
});

function pmcsService(): PeopleMergeService
{
    return new PeopleMergeService(new EntryReferenceRepointer);
}

it('serves a people-merge redirect on the non-default site the loser was reachable in', function (): void {
    [$default, $other] = pmcsTwoSites();

    // Survivor lives on the default site; loser is reachable ONLY on the other site.
    $survivor = pmcsMakePerson('surv', 'jane', ['bio' => 'A'], $default);
    $loser = pmcsMakePerson('lose', 'jane-old', ['bio' => 'B'], $other);

    // Capture the exact URLs the merge will use before the loser is retired.
    /** @var string $loserUrl */
    $loserUrl = $loser->url();
    /** @var string $survivorUrl */
    $survivorUrl = $survivor->url();

    expect($loserUrl)->not->toBe('');
    expect($survivorUrl)->not->toBe('');

    $result = pmcsService()->merge(['surv', 'lose'], 'surv', []);

    expect($result->redirectsCreated)->toBeGreaterThanOrEqual(1);

    // The redirect is persisted on the OTHER site's localization — the one the
    // serving tag reads for a 404 on that site.
    /** @var Statamic\Contracts\Globals\GlobalSet $redirectsGlobal */
    $redirectsGlobal = GlobalSet::findByHandle('redirects');
    /** @var Variables $otherVars */
    $otherVars = $redirectsGlobal->in($other);
    /** @var array<int, array<string, mixed>> $otherRedirects */
    $otherRedirects = $otherVars->get('redirects', []);
    /** @var array{source: string, target_url: string, response: string, enabled: bool} $row */
    $row = collect($otherRedirects)->firstWhere('source', $loserUrl);

    expect($row)->not->toBeNull()
        ->and($row['target_url'])->toBe($survivorUrl)
        ->and($row['response'])->toBe('301')
        ->and($row['enabled'])->toBeTrue();

    // find_redirect_rule, querying the other site for the retired URL, serves it —
    // proving the redirect reaches the 404 path on that site, not just the default.
    $tag = new FindRedirectRule;
    $tag->setContext(['current_uri' => $loserUrl]);
    $tag->setParameters(['in' => $other]);
    $matches = $tag->index();

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['source'])->toBe($loserUrl)
        ->and($matches[0]['target_url'])->toBe($survivorUrl);

    // The loser is fully retired.
    expect(Entry::find('lose'))->toBeNull();
});

it('does not place a non-default-site redirect on the default localization', function (): void {
    [$default, $other] = pmcsTwoSites();

    pmcsMakePerson('surv', 'jane', ['bio' => 'A'], $default);
    $loser = pmcsMakePerson('lose', 'jane-old', ['bio' => 'B'], $other);
    /** @var string $loserUrl */
    $loserUrl = $loser->url();

    pmcsService()->merge(['surv', 'lose'], 'surv', []);

    // The redirect belongs to the other site; the default localization must not
    // carry it (a default 404 for that path would otherwise wrongly match).
    /** @var Statamic\Contracts\Globals\GlobalSet $redirectsGlobal */
    $redirectsGlobal = GlobalSet::findByHandle('redirects');
    /** @var Variables $defaultVars */
    $defaultVars = $redirectsGlobal->in($default);
    /** @var array<int, array<string, mixed>> $defaultRedirects */
    $defaultRedirects = $defaultVars->get('redirects', []);

    expect(collect($defaultRedirects)->pluck('source')->all())->not->toContain($loserUrl);

    // And the default-site tag query does not serve it.
    $tag = new FindRedirectRule;
    $tag->setContext(['current_uri' => $loserUrl]);
    $tag->setParameters(['in' => $default]);

    expect($tag->index())->toBe([]);
});
