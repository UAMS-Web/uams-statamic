<?php

declare(strict_types=1);

use App\Support\References\EntryReferenceRepointer;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Collection;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\YAML;
use Statamic\Globals\Variables;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for {@see EntryReferenceRepointer} — the value-shape walk that
 * re-points inbound references when a `people` entry is merged away (UAMS-Web/uams-statamic-archive#838).
 *
 * Fixtures persist real entries/terms into an isolated Stache
 * (StacheIsolatedTestCase) and are written with `saveQuietly()` so no
 * entry-saved listener mutates them. The survivor/loser ids are deliberately
 * chosen so one is a prefix of the value space to pin the link boundary guard.
 *
 * Mutation: 100% on App\Support\References\EntryReferenceRepointer (147 mutants), measured on
 * macOS on 2026-11-25 without --covered-only, with one equivalent annotated in rewriteString():
 * its early return for a string with no entry link saves only the work of patterns that could
 * not match. Every killable mutant is dead.
 */
uses(StacheIsolatedTestCase::class);

const REPOINT_PEOPLE = 'repoint_people';
const REPOINT_REFERRERS = 'repoint_referrers';

const REPOINT_LOSER = 'loser-1111';
const REPOINT_SURVIVOR = 'survivor-2222';

/**
 * Persist a referrer entry holding arbitrary data, bypassing save listeners.
 *
 * @param  array<string, mixed>  $data
 */
function repointMakeReferrer(string $slug, array $data): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection(REPOINT_REFERRERS);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($slug);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($slug);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);
    $entry->saveQuietly();
}

beforeEach(function (): void {
    /** @var Collection $people */
    $people = CollectionFacade::make(REPOINT_PEOPLE);
    /** @var Collection $people */
    $people = $people->title('People');
    $people->save();

    /** @var Collection $referrers */
    $referrers = CollectionFacade::make(REPOINT_REFERRERS);
    /** @var Collection $referrers */
    $referrers = $referrers->title('Referrers');
    $referrers->save();

    // The two people entries the rest of the suite re-points between.
    /** @var Statamic\Entries\Entry $loser */
    $loser = Entry::make();
    /** @var Statamic\Entries\Entry $loser */
    $loser = $loser->collection(REPOINT_PEOPLE);
    /** @var Statamic\Entries\Entry $loser */
    $loser = $loser->id(REPOINT_LOSER);
    /** @var Statamic\Entries\Entry $loser */
    $loser = $loser->slug('loser');
    $loser->saveQuietly();

    /** @var Statamic\Entries\Entry $survivor */
    $survivor = Entry::make();
    /** @var Statamic\Entries\Entry $survivor */
    $survivor = $survivor->collection(REPOINT_PEOPLE);
    /** @var Statamic\Entries\Entry $survivor */
    $survivor = $survivor->id(REPOINT_SURVIVOR);
    /** @var Statamic\Entries\Entry $survivor */
    $survivor = $survivor->slug('survivor');
    $survivor->saveQuietly();
});

/**
 * Re-read a referrer entry's stored data by slug/id.
 *
 * @return array<string, mixed>
 */
function repointReferrerData(string $id): array
{
    /** @var Statamic\Entries\Entry|null $entry */
    $entry = Entry::find($id);
    Assert::assertNotNull($entry, "Referrer {$id} should persist.");

    /** @var Illuminate\Support\Collection<string, mixed> $data */
    $data = $entry->data();

    return $data->all();
}

it('rewrites a single-id relationship value to the survivor', function (): void {
    repointMakeReferrer('single', ['person' => REPOINT_LOSER]);

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    expect(repointReferrerData('single')['person'])->toBe(REPOINT_SURVIVOR)
        ->and($summary->entries)->toBe(1)
        ->and($summary->occurrences)->toBe(1);
});

it('rewrites and de-duplicates a multi-id relationship list', function (): void {
    // References both the loser and the survivor plus an unrelated id.
    repointMakeReferrer('multi', ['people' => [REPOINT_LOSER, REPOINT_SURVIVOR, 'other-9999']]);

    (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    // The loser collapses onto the survivor; the duplicate survivor is dropped.
    expect(repointReferrerData('multi')['people'])->toBe([REPOINT_SURVIVOR, 'other-9999']);
});

it('preserves legitimate duplicate values when re-pointing a reference in the same list', function (): void {
    // A list that references the loser AND legitimately repeats unrelated values.
    repointMakeReferrer('mixed-dupes', ['tags' => [REPOINT_LOSER, 'red', 'red', 'blue']]);

    (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    // Only the loser is re-pointed; the duplicate 'red' values are untouched —
    // a blanket array_unique would have destroyed them.
    expect(repointReferrerData('mixed-dupes')['tags'])->toBe([REPOINT_SURVIVOR, 'red', 'red', 'blue']);
});

it('collapses the survivor id even when the loser appears more than once', function (): void {
    // Both the loser (twice) and the survivor are referenced.
    repointMakeReferrer('multi-loser', ['people' => [REPOINT_LOSER, REPOINT_SURVIVOR, REPOINT_LOSER]]);

    (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    // All re-pointed survivor occurrences collapse to a single entry, order kept.
    expect(repointReferrerData('multi-loser')['people'])->toBe([REPOINT_SURVIVOR]);
});

it('rewrites references nested deep inside replicator sets', function (): void {
    repointMakeReferrer('nested', [
        'blocks' => [
            ['type' => 'card', 'author' => REPOINT_LOSER],
            ['type' => 'group', 'inner' => ['lead' => REPOINT_LOSER]],
        ],
    ]);

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    /** @var array{blocks: array{array{author: string}, array{inner: array{lead: string}}}} $data */
    $data = repointReferrerData('nested');

    expect($data['blocks'][0]['author'])->toBe(REPOINT_SURVIVOR)
        ->and($data['blocks'][1]['inner']['lead'])->toBe(REPOINT_SURVIVOR)
        ->and($summary->occurrences)->toBe(2);
});

it('rewrites statamic entry links inside bard prosemirror marks', function (): void {
    repointMakeReferrer('bard', [
        'body' => [
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'See profile',
                        'marks' => [
                            ['type' => 'link', 'attrs' => ['href' => 'statamic://entry::'.REPOINT_LOSER]],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    /** @var array{body: array{array{content: array{array{marks: array{array{attrs: array{href: string}}}}}}}} $bardData */
    $bardData = repointReferrerData('bard');
    $href = $bardData['body'][0]['content'][0]['marks'][0]['attrs']['href'];

    expect($href)->toBe('statamic://entry::'.REPOINT_SURVIVOR)
        ->and($summary->occurrences)->toBe(1);
});

it('does not partial-match a longer id sharing the loser id as a prefix', function (): void {
    // A link to a DIFFERENT entry whose id begins with the loser id must survive.
    $longerId = REPOINT_LOSER.'-extra';

    repointMakeReferrer('boundary', [
        'body' => 'before statamic://entry::'.$longerId.' after',
    ]);

    (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    expect(repointReferrerData('boundary')['body'])->toContain($longerId)
        ->and(repointReferrerData('boundary')['body'])->not->toContain(REPOINT_SURVIVOR);
});

it('does not exact-match a string that merely contains the loser id', function (): void {
    // A plain text field whose value contains but does not equal the loser id.
    repointMakeReferrer('contains', ['note' => 'ref:'.REPOINT_LOSER.' is legacy']);

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    expect(repointReferrerData('contains')['note'])->toBe('ref:'.REPOINT_LOSER.' is legacy')
        ->and($summary->occurrences)->toBe(0);
});

it('skips the retired entries themselves', function (): void {
    // The loser entry references itself; it must not be counted or saved.
    /** @var Statamic\Entries\Entry $loser */
    $loser = Entry::find(REPOINT_LOSER);
    /** @var Statamic\Entries\Entry $loser */
    $loser = $loser->data(['self' => REPOINT_LOSER]);
    $loser->saveQuietly();

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    /** @var Statamic\Entries\Entry $reloadedLoser */
    $reloadedLoser = Entry::find(REPOINT_LOSER);
    /** @var Illuminate\Support\Collection<string, mixed> $reloadedLoserData */
    $reloadedLoserData = $reloadedLoser->data();

    expect($summary->entries)->toBe(0)
        ->and($reloadedLoserData->all()['self'])->toBe(REPOINT_LOSER);
});

it('preview counts references without writing them', function (): void {
    repointMakeReferrer('preview', ['person' => REPOINT_LOSER]);

    $summary = (new EntryReferenceRepointer)->preview([REPOINT_LOSER => REPOINT_SURVIVOR]);

    expect($summary->occurrences)->toBe(1)
        ->and($summary->entries)->toBe(1)
        // Nothing was written — the reference still points at the loser.
        ->and(repointReferrerData('preview')['person'])->toBe(REPOINT_LOSER);
});

it('reports the same occurrences from preview and repoint on one instance', function (): void {
    repointMakeReferrer('reused', ['person' => REPOINT_LOSER]);
    $repointer = new EntryReferenceRepointer;

    $preview = $repointer->preview([REPOINT_LOSER => REPOINT_SURVIVOR]);
    $repoint = $repointer->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    expect($preview->occurrences)->toBe(1)
        ->and($repoint->occurrences)->toBe(1)
        ->and(repointReferrerData('reused')['person'])->toBe(REPOINT_SURVIVOR);
});

it('reports no occurrences from a second repoint on one instance once nothing is left to change', function (): void {
    repointMakeReferrer('repeated', ['person' => REPOINT_LOSER]);
    $repointer = new EntryReferenceRepointer;

    $first = $repointer->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);
    $second = $repointer->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    expect($first->occurrences)->toBe(1)
        ->and($second->occurrences)->toBe(0)
        ->and($second->itemsChanged())->toBe(0);
});

it('treats a no-op id map (id mapped to itself) as nothing to do', function (): void {
    repointMakeReferrer('noop', ['person' => REPOINT_LOSER]);

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_LOSER]);

    expect($summary->itemsChanged())->toBe(0)
        ->and($summary->occurrences)->toBe(0)
        ->and(repointReferrerData('noop')['person'])->toBe(REPOINT_LOSER);
});

it('rewrites references held by taxonomy terms', function (): void {
    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make('repoint_tax');
    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = $taxonomy->title('Repoint Tax');
    $taxonomy->save();

    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    /** @var Statamic\Taxonomies\Term $term */
    $term = $term->taxonomy('repoint_tax');
    /** @var LocalizedTerm $term */
    $term = $term->inDefaultLocale();
    /** @var LocalizedTerm $term */
    $term = $term->slug('contact-group');
    $term->data(['people' => [REPOINT_LOSER]]);
    $term->saveQuietly();

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    /** @var Statamic\Taxonomies\Term|null $reloaded */
    $reloaded = Term::find('repoint_tax::contact-group');
    Assert::assertNotNull($reloaded, 'Term should persist into the isolated Stache.');

    /** @var LocalizedTerm $reloadedLocalized */
    $reloadedLocalized = $reloaded->inDefaultLocale();
    /** @var Illuminate\Support\Collection<string, mixed> $reloadedData */
    $reloadedData = $reloadedLocalized->data();

    expect($reloadedData->all()['people'])->toBe([REPOINT_SURVIVOR])
        ->and($summary->terms)->toBe(1);
});

it('rewrites references held by global set localizations', function (): void {
    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();
    /** @var string $site */
    $site = $defaultSite->handle();

    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('repoint_global');
    /** @var Statamic\Globals\GlobalSet $set */
    $set = $set->title('Repoint Global');
    $set->save();

    /** @var Variables $variables */
    $variables = $set->makeLocalization($site);
    $variables->data(['founder' => [REPOINT_LOSER]]);
    $variables->save();

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    /** @var Statamic\Globals\GlobalSet|null $reloaded */
    $reloaded = GlobalSet::findByHandle('repoint_global');
    Assert::assertNotNull($reloaded, 'Global set should persist.');

    /** @var Variables $reloadedVariables */
    $reloadedVariables = $reloaded->in($site);
    /** @var Illuminate\Support\Collection<string, mixed> $reloadedGlobalData */
    $reloadedGlobalData = $reloadedVariables->data();

    expect($reloadedGlobalData->all()['founder'])->toBe([REPOINT_SURVIVOR])
        ->and($summary->globals)->toBe(1);
});

/**
 * A global set localized into each given site, with the data for each.
 *
 * @param  array<string, array<string, mixed>>  $localizations  Site handle => data
 */
function repointMakeGlobals(string $handle, array $localizations): void
{
    $set = mustBe(Statamic\Globals\GlobalSet::class, GlobalSet::make($handle), 'the global set');
    $set->title($handle);
    $set->sites(array_keys($localizations));
    $set->save();

    foreach ($localizations as $site => $data) {
        $variables = mustBe(Variables::class, $set->makeLocalization($site), 'the localization');
        $variables->data($data);
        $variables->save();
    }
}

/**
 * The data a global set's localization holds on disk.
 *
 * @return array<mixed>
 */
function repointGlobalOnDisk(string $handle, string $site): array
{
    $set = mustBe(Statamic\Globals\GlobalSet::class, GlobalSet::findByHandle($handle), 'the global set');
    $variables = mustBe(Variables::class, $set->in($site), 'the localization');

    return YAML::file($variables->path())->parse();
}

/**
 * Save a term holding arbitrary data, bypassing save listeners.
 *
 * @param  array<string, mixed>  $data
 */
function repointMakeTerm(string $slug, array $data): void
{
    $term = mustBe(Statamic\Taxonomies\Term::class, Term::make(), 'the term');
    $term->taxonomy('repoint_tax');
    $localized = mustBe(LocalizedTerm::class, $term->inDefaultLocale(), 'the localized term');
    $localized->slug($slug);
    $localized->data($data);
    $localized->saveQuietly();
}

it('reads no content when there is nothing to re-point', function (string $new): void {
    Entry::shouldReceive('all')->never();

    $summary = (new EntryReferenceRepointer)->repoint($new === '' ? [] : [REPOINT_LOSER => $new]);

    expect([$summary->entries, $summary->globals, $summary->terms, $summary->occurrences])->toBe([0, 0, 0, 0]);
})->with([
    'an empty map' => [''],
    'an id mapped to itself' => [REPOINT_LOSER],
]);

it('counts each item and occurrence it re-points, and no item without a reference', function (): void {
    // Numbers, booleans and null are never references, beside one or not
    repointMakeReferrer('without', ['count' => 5, 'flag' => true, 'none' => null, 'ratio' => 1.5, 'list' => [1, false, null]]);
    repointMakeReferrer('with', ['count' => 5, 'flag' => true, 'none' => null, 'people' => [REPOINT_LOSER, 7]]);

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    expect([$summary->entries, $summary->globals, $summary->terms, $summary->occurrences])->toBe([1, 0, 0, 1])
        ->and(repointReferrerData('with'))->toBe(['count' => 5, 'flag' => true, 'none' => null, 'people' => [REPOINT_SURVIVOR, 7]]);
});

it('tells ids apart exactly, even where PHP reads two of them as the same number', function (): void {
    // '1e3' and '1000' are equal numbers and different ids
    repointMakeReferrer('1000', ['person' => '1e3']);

    $summary = (new EntryReferenceRepointer)->repoint(['1e3' => REPOINT_SURVIVOR]);

    expect(repointReferrerData('1000')['person'])->toBe(REPOINT_SURVIVOR)
        ->and($summary->entries)->toBe(1);
});

it('collapses only a survivor id, even where another value reads as the same number', function (): void {
    repointMakeReferrer('numeric', ['people' => [REPOINT_LOSER, '1e3', '1e3']]);
    repointMakeReferrer('both', ['people' => [REPOINT_LOSER, 'loser-3333']]);

    (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => '1000', 'loser-3333' => '1.0e3']);

    expect(repointReferrerData('numeric')['people'])->toBe(['1000', '1e3', '1e3'])
        ->and(repointReferrerData('both')['people'])->toBe(['1000', '1.0e3']);
});

it('leaves a list it changed nothing in as it was, duplicates and all', function (): void {
    repointMakeReferrer('untouched', ['person' => REPOINT_LOSER, 'others' => [REPOINT_SURVIVOR, REPOINT_SURVIVOR]]);

    (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    expect(repointReferrerData('untouched'))->toBe(['person' => REPOINT_SURVIVOR, 'others' => [REPOINT_SURVIVOR, REPOINT_SURVIVOR]]);
});

it('collapses no duplicates in a list that holds anything but strings, or in a keyed map', function (): void {
    repointMakeReferrer('structured', [
        'mixed' => [REPOINT_LOSER, REPOINT_SURVIVOR, 5],
        'nodes' => [REPOINT_LOSER, REPOINT_SURVIVOR, ['type' => 'set']],
        'keyed' => ['a' => REPOINT_LOSER, 'b' => REPOINT_SURVIVOR],
    ]);

    (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    expect(repointReferrerData('structured'))->toBe([
        'mixed' => [REPOINT_SURVIVOR, REPOINT_SURVIVOR, 5],
        'nodes' => [REPOINT_SURVIVOR, REPOINT_SURVIVOR, ['type' => 'set']],
        'keyed' => ['a' => REPOINT_SURVIVOR, 'b' => REPOINT_SURVIVOR],
    ]);
});

it('rewrites every inline link in a string, to each survivor', function (): void {
    repointMakeReferrer('links', ['body' => sprintf(
        '[a](statamic://entry::%1$s) [b](statamic://entry::loser-3333) [c](statamic://entry::%1$s)',
        REPOINT_LOSER
    )]);

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR, 'loser-3333' => 'survivor-4444']);

    expect(repointReferrerData('links')['body'])->toBe(sprintf(
        '[a](statamic://entry::%1$s) [b](statamic://entry::survivor-4444) [c](statamic://entry::%1$s)',
        REPOINT_SURVIVOR
    ))
        ->and($summary->occurrences)->toBe(3);
});

it('saves each global localization that holds a reference, whatever sites come before it', function (): void {
    Site::setSites([
        'first' => ['name' => 'First', 'url' => '/', 'locale' => 'en_US'],
        'second' => ['name' => 'Second', 'url' => '/second/', 'locale' => 'en_US'],
    ]);
    [$first, $second] = ['first', 'second'];

    // Not localized into the first site at all
    repointMakeGlobals('repoint_later_only', [$second => ['founder' => REPOINT_LOSER]]);
    // Localized into the first site, with no reference there
    repointMakeGlobals('repoint_later_reference', [$first => ['founder' => 'someone'], $second => ['founder' => REPOINT_LOSER]]);

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    expect(repointGlobalOnDisk('repoint_later_only', $second))->toBe(['founder' => REPOINT_SURVIVOR])
        ->and(repointGlobalOnDisk('repoint_later_reference', $second))->toBe(['founder' => REPOINT_SURVIVOR])
        ->and(repointGlobalOnDisk('repoint_later_reference', $first))->toBe(['founder' => 'someone'])
        ->and([$summary->globals, $summary->occurrences])->toBe([2, 2]);
});

it('re-points each term that holds a reference, among terms that do not', function (): void {
    $taxonomy = mustBe(Statamic\Taxonomies\Taxonomy::class, Taxonomy::make('repoint_tax'), 'the taxonomy');
    $taxonomy->save();
    repointMakeTerm('a-without', ['lead' => 'someone']);
    repointMakeTerm('b-with', ['lead' => REPOINT_LOSER]);
    repointMakeTerm('c-without', ['lead' => 'someone']);
    repointMakeTerm('d-with', ['lead' => REPOINT_LOSER]);

    $summary = (new EntryReferenceRepointer)->repoint([REPOINT_LOSER => REPOINT_SURVIVOR]);

    $leads = [];
    foreach (['a-without', 'b-with', 'c-without', 'd-with'] as $slug) {
        $term = Term::find('repoint_tax::'.$slug);
        $leads[$slug] = mustBe(LocalizedTerm::class, $term, 'the term')->get('lead');
    }

    expect($leads)->toBe(['a-without' => 'someone', 'b-with' => REPOINT_SURVIVOR, 'c-without' => 'someone', 'd-with' => REPOINT_SURVIVOR])
        ->and([$summary->terms, $summary->occurrences])->toBe([2, 2]);
});
