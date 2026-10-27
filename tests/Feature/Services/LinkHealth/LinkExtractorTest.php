<?php

declare(strict_types=1);

use App\Services\LinkHealth\ContentItemType;
use App\Services\LinkHealth\LinkClassifier;
use App\Services\LinkHealth\LinkExtractor;
use App\Services\LinkHealth\LinkKind;
use App\Services\LinkHealth\LinkOccurrence;
use Statamic\Entries\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Globals\Variables;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for {@see LinkExtractor} — the fieldtype-driven walk that builds the link inventory
 * (UAMS-Web/uams-statamic-archive#1764).
 *
 * Fixtures persist real entries, a global set and a term into an isolated Stache and are written
 * with `saveQuietly()` so no save listener rewrites them. The blueprint deliberately mixes
 * link-bearing fieldtypes with a `code` field, because excluding the latter is an acceptance
 * criterion rather than an incidental detail.
 *
 * @command  php artisan test --compact tests/Feature/Services/LinkHealth/LinkExtractorTest.php
 */
uses(StacheIsolatedTestCase::class);

const LX_COLLECTION = 'lx_pages';
const LX_TAXONOMY = 'lx_topics';
const LX_KNOWN_ID = 'lx-known-entry-1';

/**
 * Build the extractor under test, with one known entry id and one served host.
 */
function lxExtractor(): LinkExtractor
{
    return new LinkExtractor(new LinkClassifier(['www.uams.edu'], [LX_KNOWN_ID => true]));
}

/**
 * Index occurrences by their dotted location, for readable assertions.
 *
 * @param  list<LinkOccurrence>  $occurrences
 * @return array<string, LinkOccurrence>
 */
function lxByLocation(array $occurrences): array
{
    $out = [];

    foreach ($occurrences as $occurrence) {
        $out[$occurrence->location()] = $occurrence;
    }

    return $out;
}

/**
 * Persist a fixture entry, bypassing save listeners.
 *
 * The per-assignment annotations are what the Statamic facades require under larastan: each
 * builder method is declared as returning the contract rather than the concrete class.
 *
 * @param  array<string, mixed>  $data
 */
function lxMakeEntry(string $id, array $data): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection(LX_COLLECTION);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);
    $entry->saveQuietly();
}

beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make(LX_COLLECTION);
    /** @var Collection $collection */
    $collection = $collection->title('LX Pages');
    $collection->save();

    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make(LX_TAXONOMY);
    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = $taxonomy->title('LX Topics');
    $taxonomy->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make('lx_page');
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setNamespace('collections.'.LX_COLLECTION);
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'intro', 'field' => ['type' => 'text']],
        ['handle' => 'cta_url', 'field' => ['type' => 'text']],
        ['handle' => 'snippet', 'field' => ['type' => 'code']],
        ['handle' => 'related', 'field' => ['type' => 'entries']],
        ['handle' => 'blurb', 'field' => ['type' => 'html']],
        ['handle' => 'body_md', 'field' => ['type' => 'markdown']],
        ['handle' => 'notes', 'field' => ['type' => 'textarea']],
        ['handle' => 'cta_link', 'field' => ['type' => 'link']],
        ['handle' => 'go', 'field' => ['type' => 'redirect']],
        ['handle' => 'files', 'field' => ['type' => 'assets']],
        ['handle' => 'topics', 'field' => ['type' => 'terms']],
        ['handle' => 'owners', 'field' => ['type' => 'users']],
        ['handle' => 'meta', 'field' => ['type' => 'group', 'fields' => [
            ['handle' => 'more_url', 'field' => ['type' => 'text']],
        ]]],
        ['handle' => 'rows', 'field' => ['type' => 'grid', 'fields' => [
            ['handle' => 'row_url', 'field' => ['type' => 'text']],
        ]]],
        ['handle' => 'page_builder', 'field' => ['type' => 'replicator', 'sets' => [
            'main' => ['sets' => [
                'plain' => ['fields' => [['handle' => 'heading', 'field' => ['type' => 'text']]]],
                'cta' => ['fields' => [['handle' => 'url', 'field' => ['type' => 'text']]]],
            ]],
        ]]],
        ['handle' => 'body', 'field' => ['type' => 'bard', 'sets' => [
            'main' => ['sets' => [
                'embed' => ['fields' => [['handle' => 'embed_url', 'field' => ['type' => 'text']]]],
                // A numerically-named set. PHP canonicalizes the key to the int 5, which is what
                // makes the `is_string()` half of the Bard set guard observable.
                '5' => ['fields' => [['handle' => 'embed_url', 'field' => ['type' => 'text']]]],
            ]],
        ]]],
    ]]]]]]);
    $blueprint->save();

    /** @var Statamic\Fields\Blueprint $globalBlueprint */
    $globalBlueprint = Blueprint::make('lx_footer');
    /** @var Statamic\Fields\Blueprint $globalBlueprint */
    $globalBlueprint = $globalBlueprint->setNamespace('globals');
    /** @var Statamic\Fields\Blueprint $globalBlueprint */
    $globalBlueprint = $globalBlueprint->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'footer_url', 'field' => ['type' => 'text']],
    ]]]]]]);
    $globalBlueprint->save();

    /** @var Statamic\Fields\Blueprint $termBlueprint */
    $termBlueprint = Blueprint::make(LX_TAXONOMY);
    /** @var Statamic\Fields\Blueprint $termBlueprint */
    $termBlueprint = $termBlueprint->setNamespace('taxonomies.'.LX_TAXONOMY);
    /** @var Statamic\Fields\Blueprint $termBlueprint */
    $termBlueprint = $termBlueprint->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'term_url', 'field' => ['type' => 'text']],
    ]]]]]]);
    $termBlueprint->save();
});

it('extracts links from every nesting depth with an actionable location', function (): void {
    lxMakeEntry('lx-1', [
        'intro' => 'Cardiology Clinic',
        'cta_url' => 'https://example.com/cta',
        'snippet' => '<a href="https://example.com/in-code">not a link occurrence</a>',
        'related' => [LX_KNOWN_ID],
        'blurb' => '<p>See <a href="https://uamshealth.com/find">this</a>.</p>',
        'meta' => ['more_url' => '/more'],
        'rows' => [['row_url' => 'mailto:x@uams.edu']],
        'page_builder' => [
            ['type' => 'plain', 'heading' => 'Nothing here'],
            ['type' => 'plain', 'heading' => 'Still nothing'],
            ['type' => 'cta', 'url' => 'https://www.uams.edu/deep'],
        ],
    ]);

    $found = lxByLocation(lxExtractor()->extract());

    // The third set is distinguishable from the first two, which is the whole point of the path.
    expect($found)->toHaveKey('page_builder.2.cta.url')
        ->and($found['page_builder.2.cta.url']->kind)->toBe(LinkKind::Internal)
        ->and($found['page_builder.2.cta.url']->itemType)->toBe(ContentItemType::Entry)
        ->and($found['page_builder.2.cta.url']->container)->toBe(LX_COLLECTION)
        ->and($found['page_builder.2.cta.url']->id)->toBe('lx-1')
        // `site` completes the address. Without it the entry-side locale guard is unpinned.
        ->and($found['page_builder.2.cta.url']->site)->toBe('default');

    expect($found)->toHaveKey('cta_url')
        ->and($found['cta_url']->kind)->toBe(LinkKind::External)
        ->and($found)->toHaveKey('meta.more_url')
        ->and($found['meta.more_url']->kind)->toBe(LinkKind::Internal)
        ->and($found)->toHaveKey('rows.0.row_url')
        ->and($found['rows.0.row_url']->kind)->toBe(LinkKind::NonHttp)
        ->and($found)->toHaveKey('blurb')
        ->and($found['blurb']->kind)->toBe(LinkKind::FirstPartyOther)
        ->and($found)->toHaveKey('related.0')
        ->and($found['related.0']->kind)->toBe(LinkKind::EntryRelation);
});

// The acceptance criterion is explicit that a URL in a code field is never an occurrence: this
// repository has 34 of them and every false positive there is permanent.
it('never emits an occurrence from a code field', function (): void {
    lxMakeEntry('lx-2', [
        'snippet' => 'curl https://example.com/in-code',
        'cta_url' => 'https://example.com/real',
    ]);

    $values = array_map(static fn (LinkOccurrence $o): string => $o->value, lxExtractor()->extract());

    // The exact set, so the `code` exclusion is pinned from both sides at once: the URL in the
    // code field is absent AND nothing else appeared in its place.
    expect($values)->toBe(['https://example.com/real']);
});

it('never emits prose that merely mentions a domain', function (): void {
    lxMakeEntry('lx-3', [
        'intro' => 'Visit uams.edu for details',
    ]);

    expect(lxExtractor()->extract())->toBe([]);
});

it('extracts a link mark and an embedded set from a Bard field', function (): void {
    lxMakeEntry('lx-4', [
        'body' => [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'See ', 'marks' => [
                    ['type' => 'link', 'attrs' => ['href' => 'https://example.org/bard']],
                ]],
            ]],
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'embed', 'embed_url' => '/embedded']]],
        ],
    ]);

    $found = lxByLocation(lxExtractor()->extract());

    expect($found)->toHaveKey('body.0.0')
        ->and($found['body.0.0']->kind)->toBe(LinkKind::External)
        ->and($found)->toHaveKey('body.1.embed.embed_url')
        ->and($found['body.1.embed.embed_url']->kind)->toBe(LinkKind::Internal);
});

// The scraper claims to handle both quote styles, `src` as well as `href`, and markdown targets.
// None of that was pinned by the walk tests, which only ever fed it a double-quoted `href`.
it('scrapes both quote styles, src attributes and markdown targets', function (): void {
    lxMakeEntry('lx-6', [
        'blurb' => '<a href="https://example.com/dq">d</a> '
            ."<a href='https://example.com/sq'>s</a> "
            .'<img src="https://example.com/img.png"> '
            .'[md](https://example.com/md) '
            .'![img](https://example.com/mdimg.png)',
    ]);

    $values = array_map(static fn (LinkOccurrence $o): string => $o->value, lxExtractor()->extract());
    sort($values);

    // Asserted as the EXACT set, not with `toContain`. A needles-only assertion cannot fail on a
    // mutant that emits something EXTRA — a scraper whose alternation, empty-href guard or
    // markdown pass over-matches still contains all five needles (UAMS-Web/uams-statamic-archive#2274).
    expect($values)->toBe([
        'https://example.com/dq',
        'https://example.com/img.png',
        'https://example.com/md',
        'https://example.com/mdimg.png',
        'https://example.com/sq',
    ]);
});

// An empty attribute is markup noise, not a link, and emitting it would put an unactionable row
// in front of an editor for every placeholder anchor in the corpus.
it('emits nothing for an empty href and nothing for empty markup', function (): void {
    lxMakeEntry('lx-7', ['blurb' => '<a href="">empty</a><a href=\'\'>also empty</a>']);
    expect(lxExtractor()->extract())->toBe([]);

    lxMakeEntry('lx-8', ['blurb' => '']);
    expect(lxExtractor()->extract())->toBe([]);
});

it('walks global set localizations and terms, not only entries', function (): void {
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('lx_footer');
    /** @var Statamic\Globals\GlobalSet $set */
    $set = $set->title('LX Footer');
    $set->save();

    /** @var Variables $localization */
    $localization = $set->makeLocalization('default');
    /** @var Variables $localization */
    $localization = $localization->data(['footer_url' => 'https://example.net/footer']);
    $localization->save();

    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    /** @var Statamic\Taxonomies\Term $term */
    $term = $term->taxonomy(LX_TAXONOMY);
    /** @var LocalizedTerm $term */
    $term = $term->inDefaultLocale();
    /** @var LocalizedTerm $term */
    $term = $term->slug('lx-topic');
    $term->data(['term_url' => '/topic']);
    $term->saveQuietly();

    $byType = [];

    foreach (lxExtractor()->extract() as $occurrence) {
        $byType[$occurrence->itemType->value][] = [
            $occurrence->container,
            $occurrence->site,
            $occurrence->value,
        ];
    }

    // `container` and `site` are asserted, not just the value. They are what makes an occurrence
    // ACTIONABLE — which global set, which taxonomy, which localization to open — and nothing
    // pinned them, so the `is_string(...) ? ... : ''` guards that populate them could be negated
    // without failing a test (UAMS-Web/uams-statamic-archive#2274).
    expect($byType[ContentItemType::Global->value] ?? [])->toBe([['lx_footer', 'default', 'https://example.net/footer']])
        ->and($byType[ContentItemType::Term->value] ?? [])->toBe([[LX_TAXONOMY, 'default', '/topic']]);
});

it('performs no HTTP and returns the same result twice', function (): void {
    lxMakeEntry('lx-5', [
        'cta_url' => 'https://unreachable.invalid/x',
    ]);

    $first = lxExtractor()->extract();
    $second = lxExtractor()->extract();

    expect($first)->toEqual($second)
        ->and($first)->toHaveCount(1)
        ->and($first[0]->kind)->toBe(LinkKind::External);
});

// The dispatch table in `walkField` had four arms no fixture reached: both MARKUP_TYPES beyond
// `html`, and both LINK_VALUE_TYPES. An arm nothing exercises is an arm whose removal no test
// notices, so each of these pins one (UAMS-Web/uams-statamic-archive#2274).
it('scrapes markdown and textarea fields, not only html', function (): void {
    lxMakeEntry('lx-markup', [
        'body_md' => '[a](https://example.com/from-markdown)',
        'notes' => '<a href="https://example.com/from-textarea">t</a>',
    ]);

    $values = array_map(static fn (LinkOccurrence $o): string => $o->value, lxExtractor()->extract());
    sort($values);

    expect($values)->toBe([
        'https://example.com/from-markdown',
        'https://example.com/from-textarea',
    ]);
});

it('collects a link and a redirect fieldtype value whole', function (): void {
    lxMakeEntry('lx-link-value', [
        'cta_link' => 'https://example.com/from-link',
        'go' => '/from-redirect',
    ]);

    $byLocation = lxByLocation(lxExtractor()->extract());
    ksort($byLocation);

    expect(array_keys($byLocation))->toBe(['cta_link', 'go'])
        ->and($byLocation['cta_link']->kind)->toBe(LinkKind::External)
        ->and($byLocation['go']->kind)->toBe(LinkKind::Internal);
});

// `RELATION_TYPES` maps each fieldtype to whether a BARE id in it means an entry relation, and
// only `entries` says true. Nothing tested the false side, so flipping any of those values —
// or dropping the flag as it is threaded through `collectRelation` into `record` — survived.
it('treats a bare entry id as a relation only inside an entries field', function (): void {
    lxMakeEntry('lx-relation', [
        'related' => [LX_KNOWN_ID],
        'files' => [LX_KNOWN_ID],
        'topics' => [LX_KNOWN_ID],
        'owners' => [LX_KNOWN_ID],
    ]);

    $byLocation = lxByLocation(lxExtractor()->extract());

    // The same bare id in the other three fieldtypes classifies as nothing at all and is dropped.
    expect(array_keys($byLocation))->toBe(['related.0'])
        ->and($byLocation['related.0']->kind)->toBe(LinkKind::EntryRelation);
});

// The Bard walk skips a set node whose handle the blueprint does not declare, and then CONTINUES
// to the remaining nodes. Both halves were unpinned: the previous fixture put its only set node
// last, so `continue` and `break` were indistinguishable, and no fixture carried an unknown
// handle — under which the guard's `&&` short-circuit is the only thing preventing a lookup of
// an absent key (UAMS-Web/uams-statamic-archive#2274).
it('skips an undeclared Bard set and keeps walking the nodes after it', function (): void {
    lxMakeEntry('lx-bard-2', [
        'body' => [
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'not_in_blueprint', 'embed_url' => '/never']]],
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'embed', 'embed_url' => '/declared']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'after', 'marks' => [
                    ['type' => 'link', 'attrs' => ['href' => 'https://example.org/after-the-set']],
                ]],
            ]],
        ],
    ]);

    $found = lxByLocation(lxExtractor()->extract());
    ksort($found);

    // The undeclared set contributes nothing; the node AFTER both sets is still reached.
    expect(array_keys($found))->toBe(['body.1.embed.embed_url', 'body.2.0']);
});

// `record()` is called with `$fromRelationField: false` from every non-relation path. Nothing
// asserted that, so flipping any of those literals to `true` — which would promote a bare id in
// ordinary prose to an entry relation — survived at four separate call sites (UAMS-Web/uams-statamic-archive#2274).
it('never treats a bare entry id as a relation outside a relation field', function (): void {
    lxMakeEntry('lx-bare-id', [
        'cta_url' => LX_KNOWN_ID,
        'blurb' => '<a href="'.LX_KNOWN_ID.'">m</a> ['.LX_KNOWN_ID.']('.LX_KNOWN_ID.')',
    ]);

    // A known entry id carries no scheme and no leading slash, so outside a relation field it
    // classifies as nothing at all. Any of these emitting a row is the mutant.
    expect(lxExtractor()->extract())->toBe([]);
});

// `scalars()` pairs each string with its LIST INDEX, and the index is what puts a row in the
// right place. A single-valued fixture cannot tell a preserved index from a dropped one.
it('keeps the list index of each value in the occurrence path', function (): void {
    lxMakeEntry('lx-list', ['cta_url' => ['/first', '/second']]);

    $found = lxByLocation(lxExtractor()->extract());
    ksort($found);

    expect(array_keys($found))->toBe(['cta_url.0', 'cta_url.1'])
        ->and($found['cta_url.0']->value)->toBe('/first')
        ->and($found['cta_url.1']->value)->toBe('/second');
});

// Stored data is not guaranteed to match its fieldtype — a field retyped in a blueprint leaves
// the old shape behind. Both guards that absorb this were unpinned, and both mutants reach a
// `preg_match_all()` or a `record()` call with a non-string and raise a TypeError (UAMS-Web/uams-statamic-archive#2274).
it('tolerates a non-string stored in a markup field and in a list', function (): void {
    lxMakeEntry('lx-shape', [
        'blurb' => ['not' => 'a string'],
        'cta_url' => ['/kept', 42, null],
    ]);

    $found = lxByLocation(lxExtractor()->extract());

    // The array markup yields nothing rather than throwing, and the list keeps only its strings.
    expect(array_keys($found))->toBe(['cta_url.0'])
        ->and($found['cta_url.0']->value)->toBe('/kept');
});

// A blueprint may name a set `5`, and PHP canonicalizes that key to the int 5 -- so a Bard node
// carrying the int 5 looks like it could match the set by key alone. It cannot, because
// `BlueprintFieldMap::sets()` drops a non-string handle before the map is built. This pins that
// the two guards compose end to end. It is NOT a mutation kill and is not claimed as one: it
// passes with the `&&` and with the `||`, which is what identified the equivalence (UAMS-Web/uams-statamic-archive#2274).
it('drops a Bard set whose type is not a string, even when a key would match', function (): void {
    lxMakeEntry('lx-bard-3', [
        'body' => [
            ['type' => 'set', 'attrs' => ['values' => ['type' => 5, 'embed_url' => '/by-key-alone']]],
        ],
    ]);

    expect(lxExtractor()->extract())->toBe([]);
});

// --- scoped extraction (UAMS-Web/uams-statamic-archive#1766) ----------------------------------------------
//
// The criterion is that `--site` and `--collection` narrow the WORK, not the output. Those two
// are indistinguishable from the returned list — both yield the same occurrences — so these
// assert `entriesLoaded()`, which counts what was actually pulled out of the Stache. With 255
// sites, filtering after loading pays the full multisite hydration cost while looking, from the
// rows alone, as though it had narrowed anything.

it('loads only the scoped collection entries, not every entry', function (): void {
    /** @var Collection $other */
    $other = CollectionFacade::make('lx_other');
    /** @var Collection $other */
    $other = $other->title('LX Other');
    $other->save();

    lxMakeEntry('lx-in-scope', ['cta_url' => '/in']);

    /** @var Statamic\Entries\Entry $outside */
    $outside = Entry::make();
    /** @var Statamic\Entries\Entry $outside */
    $outside = $outside->collection('lx_other');
    /** @var Statamic\Entries\Entry $outside */
    $outside = $outside->id('lx-out-of-scope');
    /** @var Statamic\Entries\Entry $outside */
    $outside = $outside->slug('out');
    $outside->saveQuietly();

    $extractor = lxExtractor();
    $extractor->extractScoped(null, LX_COLLECTION);

    // One entry loaded, not two. A count of two here would mean the scope narrowed the report
    // while the scan still paid for everything.
    expect($extractor->entriesLoaded())->toBe(1);
});

it('loads every entry when nothing is scoped', function (): void {
    lxMakeEntry('lx-a', ['cta_url' => '/a']);
    lxMakeEntry('lx-b', ['cta_url' => '/b']);

    $extractor = lxExtractor();
    $extractor->extractScoped();

    // The control: without it, a scoped count of 1 is equally consistent with a broken loader
    // that never loads more than one entry.
    expect($extractor->entriesLoaded())->toBe(2);
});

it('scopes to a single entry, which is what an on-publish scan needs', function (): void {
    // UAMS-Web/uams-statamic-archive#1772: publishing one entry must not re-probe its whole collection. Scoped to the
    // collection alone, a publish in `posts` would crawl every post and rewrite findings
    // this save has nothing to do with.
    lxMakeEntry('lx-a', ['cta_url' => '/a']);
    lxMakeEntry('lx-b', ['cta_url' => '/b']);

    $extractor = lxExtractor();
    $extractor->extractScoped(null, LX_COLLECTION, 'lx-a');

    // Paired with the collection-scoped test above, which loads both: without that control,
    // 1 here is equally consistent with a loader that never loads more than one entry.
    expect($extractor->entriesLoaded())->toBe(1);
});

it('scopes by site as well as collection', function (): void {
    lxMakeEntry('lx-a', ['cta_url' => '/a']);

    $extractor = lxExtractor();
    $extractor->extractScoped('health', LX_COLLECTION);

    // The fixture entry lives on the default site only.
    expect($extractor->entriesLoaded())->toBe(0);
});

it('scopes by site alone', function (): void {
    lxMakeEntry('lx-a', ['cta_url' => '/a']);

    $extractor = lxExtractor();
    $occurrences = $extractor->extractScoped('health');

    // The fixture entry lives on the default site only, so a site-only scope loads and reports
    // nothing. Falling back to the unscoped walk would load it.
    expect($extractor->entriesLoaded())->toBe(0)
        ->and($occurrences)->toBe([]);
});

it('scopes to an entry id alone and returns that entry\'s links', function (): void {
    lxMakeEntry('lx-a', ['cta_url' => '/a']);
    lxMakeEntry('lx-b', ['cta_url' => '/b']);

    $extractor = lxExtractor();
    $values = array_map(
        static fn (LinkOccurrence $occurrence): string => $occurrence->value,
        $extractor->extractScoped(null, null, 'lx-a'),
    );

    // One entry loaded and its link returned. Falling back to the unscoped walk would load both.
    expect($extractor->entriesLoaded())->toBe(1)
        ->and($values)->toBe(['/a']);
});

it('walks globals and terms when nothing is scoped', function (): void {
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('lx_footer');
    /** @var Statamic\Globals\GlobalSet $set */
    $set = $set->title('LX Footer');
    $set->save();

    /** @var Variables $localization */
    $localization = $set->makeLocalization('default');
    /** @var Variables $localization */
    $localization = $localization->data(['footer_url' => 'https://example.net/footer']);
    $localization->save();

    $values = array_map(
        static fn (LinkOccurrence $occurrence): string => $occurrence->value,
        lxExtractor()->extractScoped(),
    );

    // An unscoped run is the full extract, globals included; a walk of entries alone misses it.
    expect($values)->toContain('https://example.net/footer');
});
