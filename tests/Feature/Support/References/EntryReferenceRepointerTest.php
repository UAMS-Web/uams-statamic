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
