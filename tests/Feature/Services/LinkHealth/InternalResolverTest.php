<?php

declare(strict_types=1);

use App\Services\LinkHealth\ContentItemType;
use App\Services\LinkHealth\InternalResolver;
use App\Services\LinkHealth\LinkKind;
use App\Services\LinkHealth\LinkOccurrence;
use App\Services\LinkHealth\ResolutionState;
use App\Support\Redirects\RedirectRuleMatcher;
use App\Tags\FindRedirectRule;
use Illuminate\Support\Facades\File;
use Statamic\Entries\Collection;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Globals\Variables;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\StacheIsolatedTestCase;
use Tests\Support\ThrowawayRoot;

/**
 * Coverage for {@see InternalResolver} — resolving internal targets by lookup rather than by
 * fetching them (UAMS-Web/uams-statamic-archive#1765).
 *
 * The distinctions under test are the point of the slice. An HTTP probe collapses `missing`,
 * `unpublished` and `redirected` into one `404`, and cannot see a dangling `entries` relation at
 * all. Every case here is decided from the content repositories, so the file runs with the
 * network unavailable.
 *
 * @command  php artisan test --compact tests/Feature/Services/LinkHealth/InternalResolverTest.php
 */
uses(StacheIsolatedTestCase::class);

const IR_COLLECTION = 'ir_pages';
const IR_TAXONOMY = 'ir_topics';

function irResolver(): InternalResolver
{
    return new InternalResolver(new RedirectRuleMatcher);
}

/**
 * An occurrence carrying one value of one kind, originating on one site.
 */
function irOccurrence(string $value, LinkKind $kind, string $site = 'default'): LinkOccurrence
{
    return new LinkOccurrence(
        ContentItemType::Entry,
        IR_COLLECTION,
        'ir-origin',
        $site,
        'body',
        [],
        $value,
        $kind,
    );
}

function irMakeEntry(string $id, string $slug, bool $published): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection(IR_COLLECTION);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($slug);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published($published);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => $slug]);
    $entry->saveQuietly();
}

beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make(IR_COLLECTION);
    /** @var Collection $collection */
    $collection = $collection->title('IR Pages');
    /** @var Collection $collection */
    $collection = $collection->routes('/ir/{slug}');
    $collection->save();

    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make(IR_TAXONOMY);
    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = $taxonomy->title('IR Topics');
    $taxonomy->save();
});

// --- entry references and relations ----------------------------------------
//
// AC1. `missing` and `unpublished` are different editorial problems: the first needs the link
// repointed, the second needs the content published. A 404 cannot tell them apart, which is the
// whole reason this slice looks the target up instead of fetching it.

it('resolves a live entry reference as ok', function (): void {
    irMakeEntry('ir-live', 'live', true);

    expect(irResolver()->resolve(irOccurrence('entry::ir-live', LinkKind::EntryReference))->state)
        ->toBe(ResolutionState::Ok);
});

it('resolves a deleted entry reference as missing, naming the id', function (): void {
    $resolution = irResolver()->resolve(irOccurrence('entry::ir-gone', LinkKind::EntryReference));

    expect($resolution->state)->toBe(ResolutionState::Missing)
        ->and($resolution->detail)->toBe('ir-gone')
        ->and($resolution->isBroken())->toBeTrue();
});

it('separates an unpublished entry from a missing one', function (): void {
    irMakeEntry('ir-draft', 'draft', false);

    $resolution = irResolver()->resolve(irOccurrence('entry::ir-draft', LinkKind::EntryReference));

    expect($resolution->state)->toBe(ResolutionState::Unpublished)
        ->and($resolution->state)->not->toBe(ResolutionState::Missing)
        ->and($resolution->isBroken())->toBeTrue();
});

it('resolves the statamic:// scheme the same as a bare reference', function (): void {
    irMakeEntry('ir-live', 'live', true);

    expect(irResolver()->resolve(irOccurrence('statamic://entry::ir-live', LinkKind::EntryReference))->state)
        ->toBe(ResolutionState::Ok);
});

// A bare id from an `entries` fieldtype has no URL to probe, so an HTTP check cannot see it at
// all. This is the case the ticket names as only reachable by lookup.
it('resolves a bare entries-relation id, which has no URL to probe', function (): void {
    irMakeEntry('ir-live', 'live', true);

    expect(irResolver()->resolve(irOccurrence('ir-live', LinkKind::EntryRelation))->state)
        ->toBe(ResolutionState::Ok);

    expect(irResolver()->resolve(irOccurrence('ir-nope', LinkKind::EntryRelation))->state)
        ->toBe(ResolutionState::Missing);
});

// --- term references --------------------------------------------------------

it('resolves a live term reference as ok and a dangling one as missing', function (): void {
    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    /** @var Statamic\Taxonomies\Term $term */
    $term = $term->taxonomy(IR_TAXONOMY);
    /** @var LocalizedTerm $term */
    $term = $term->inDefaultLocale();
    /** @var LocalizedTerm $term */
    $term = $term->slug('ir-topic');
    $term->data(['title' => 'IR Topic']);
    $term->saveQuietly();

    expect(irResolver()->resolve(irOccurrence('term::'.IR_TAXONOMY.'::ir-topic', LinkKind::TermReference))->state)
        ->toBe(ResolutionState::Ok);

    expect(irResolver()->resolve(irOccurrence('term::'.IR_TAXONOMY.'::nope', LinkKind::TermReference))->state)
        ->toBe(ResolutionState::Missing);
});

// --- kinds this slice does not own -----------------------------------------
//
// Reported as NotChecked rather than dropped: a caller must be able to tell "not examined" from
// "examined and healthy", and an omitted row cannot express that.
it('reports kinds outside this slice as not checked, rather than omitting them', function (LinkKind $kind): void {
    expect(irResolver()->resolve(irOccurrence('https://example.com/x', $kind))->state)
        ->toBe(ResolutionState::NotChecked);
})->with([
    'external' => [LinkKind::External],
    'first-party elsewhere' => [LinkKind::FirstPartyOther],
    'non-http' => [LinkKind::NonHttp],
    'fragment only' => [LinkKind::FragmentOnly],
]);

it('never reports an unchecked or redirected occurrence as broken', function (): void {
    // `Redirected` resolves today; recommending a new target is #1767's job.
    expect(irResolver()->resolve(irOccurrence('mailto:x@y.z', LinkKind::NonHttp))->isBroken())
        ->toBeFalse();
});

// --- root-relative URLs -----------------------------------------------------
//
// AC3. Resolved against the routable index rather than fetched, keyed through
// `RedirectSource::collisionKey()` so this and the redirect rules agree on what counts as the
// same path — otherwise `/foo` and `/foo/` would resolve differently here than when served.

it('resolves a root-relative URL that matches a routable entry', function (): void {
    irMakeEntry('ir-live', 'live', true);

    expect(irResolver()->resolve(irOccurrence('/ir/live', LinkKind::Internal))->state)
        ->toBe(ResolutionState::Ok);
});

it('resolves a root-relative URL that matches nothing as missing', function (): void {
    irMakeEntry('ir-live', 'live', true);

    $resolution = irResolver()->resolve(irOccurrence('/ir/never-existed', LinkKind::Internal));

    expect($resolution->state)->toBe(ResolutionState::Missing)
        ->and($resolution->detail)->toBe('/ir/never-existed');
});

// An unpublished entry is not routable, so a URL pointing at one is not `ok`. This is the URL-side
// counterpart of the reference-side distinction above.
it('does not treat an unpublished entry as a routable URL', function (): void {
    irMakeEntry('ir-draft', 'draft', false);

    expect(irResolver()->resolve(irOccurrence('/ir/draft', LinkKind::Internal))->state)
        ->not->toBe(ResolutionState::Ok);
});

it('agrees on trailing-slash variants, because both sides use the same normalization', function (): void {
    irMakeEntry('ir-live', 'live', true);

    expect(irResolver()->resolve(irOccurrence('/ir/live/', LinkKind::Internal))->state)
        ->toBe(ResolutionState::Ok);
});

it('resolves the path component of an absolute same-host URL', function (): void {
    irMakeEntry('ir-live', 'live', true);

    expect(irResolver()->resolve(irOccurrence('https://www.uams.edu/ir/live', LinkKind::Internal))->state)
        ->toBe(ResolutionState::Ok);
});

// --- redirect awareness -----------------------------------------------------
//
// AC4. A path served by a redirect rule is not broken. The rules are selected through the same
// `RedirectRuleMatcher` the `find_redirect_rule` tag serves from, extracted in UAMS-Web/uams-statamic-archive#1765 for exactly
// this reason — re-deriving the selection here would let the report and the served behavior
// disagree, which is the outcome the criterion rules out.

/**
 * Persist a `redirects` global carrying the given rules.
 *
 * @param  list<array<string, mixed>>  $rules  Rules on the default site.
 * @param  list<array<string, mixed>>  $siteRules  Rules belonging to the non-default site.
 */
function irMakeRedirects(array $rules, array $siteRules = []): void
{
    // Shaped like production: the `redirects` field is non-localizable and the global is
    // registered on both sites with null origins, so there is no cascade and a per-site read
    // returns only that site's own list. Without this the per-site write below does not persist,
    // and a test asserting per-site behavior would pass for the wrong reason.
    $blueprint = Blueprint::makeFromFields([
        'redirects' => ['type' => 'replicator', 'localizable' => false],
    ]);
    $blueprint->setHandle('redirects');
    $blueprint->setNamespace('globals');
    $blueprint->save();

    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->sites(['default' => null, 'health' => null]);
    $set->save();

    foreach (['default' => $rules, 'health' => $siteRules] as $site => $list) {
        /** @var Variables $localization */
        $localization = $set->makeLocalization($site);
        /** @var Variables $localization */
        $localization = $localization->data(['redirects' => $list]);
        $localization->save();
    }
}

it('resolves a path served only by a redirect rule as redirected, not missing', function (): void {
    irMakeRedirects([
        ['source' => '/ir/old-path', 'target_url' => '/ir/live', 'enabled' => true, 'order' => 10],
    ]);

    $resolution = irResolver()->resolve(irOccurrence('/ir/old-path', LinkKind::Internal));

    expect($resolution->state)->toBe(ResolutionState::Redirected)
        ->and($resolution->detail)->toBe('/ir/live')
        // It works today, so it is not something to hand an editor as broken.
        ->and($resolution->isBroken())->toBeFalse();
});

// The filters are the tag's, not a re-derivation. A disabled rule does not serve the path, so the
// resolver must not report it as redirected either.
it('does not treat a disabled rule as serving the path', function (): void {
    irMakeRedirects([
        ['source' => '/ir/old-path', 'target_url' => '/ir/live', 'enabled' => false, 'order' => 10],
    ]);

    expect(irResolver()->resolve(irOccurrence('/ir/old-path', LinkKind::Internal))->state)
        ->toBe(ResolutionState::Missing);
});

// A live path is served directly and never consults the rules, which mirrors the front end.
it('prefers a routable URL over a redirect rule that also covers it', function (): void {
    irMakeEntry('ir-live', 'live', true);
    irMakeRedirects([
        ['source' => '/ir/live', 'target_url' => '/ir/elsewhere', 'enabled' => true, 'order' => 10],
    ]);

    expect(irResolver()->resolve(irOccurrence('/ir/live', LinkKind::Internal))->state)
        ->toBe(ResolutionState::Ok);
});

// The criterion states agreement with what the TAG serves, so this asserts it directly rather
// than asserting each side against a fixture and trusting they meet. Both go through the same
// `RedirectRuleMatcher`, so this is a check that the seam is actually shared — it would fail if a
// future edit re-derived either side.
it('agrees with what find_redirect_rule serves for the same path', function (string $path): void {
    irMakeEntry('ir-live', 'live', true);
    irMakeRedirects([
        ['source' => '/ir/old-path', 'target_url' => '/ir/live', 'enabled' => true, 'order' => 10],
        ['source' => '/ir/disabled', 'target_url' => '/ir/live', 'enabled' => false, 'order' => 20],
    ]);

    $context = Mockery::mock(Context::class);
    $context->shouldReceive('get')->andReturnUsing(fn (string $key) => $key === 'current_uri' ? $path : null);
    $parameters = Mockery::mock(Parameters::class);
    $parameters->shouldReceive('get')->andReturnUsing(fn (string $key, mixed $default = null) => $default);

    $tag = new FindRedirectRule;
    $reflection = new ReflectionClass($tag);
    $reflection->getProperty('context')->setValue($tag, $context);
    $reflection->getProperty('params')->setValue($tag, $parameters);

    $tagRedirects = $tag->index() !== [];
    $resolverRedirects = irResolver()->resolve(irOccurrence($path, LinkKind::Internal))->state
        === ResolutionState::Redirected;

    expect($resolverRedirects)->toBe($tagRedirects);
})->with([
    'a path a rule serves' => ['/ir/old-path'],
    'a path only a DISABLED rule covers' => ['/ir/disabled'],
    'a path no rule mentions' => ['/ir/unmentioned'],
    'a live path, which is served rather than redirected' => ['/ir/live'],
]);

// --- asset references -------------------------------------------------------
//
// AC2. The file is confirmed on disk rather than the reference trusted. A well-formed reference
// whose file is gone is exactly what a shape-based check cannot see, and it is the reason this
// slice touches the filesystem at all.

const IR_DISK = 'ir_assets';
const IR_CONTAINER = 'ir_files';

function irMakeContainer(): void
{
    config(['filesystems.disks.'.IR_DISK => [
        'driver' => 'local',
        'root' => storage_path('framework/testing/'.IR_DISK.'/'.ThrowawayRoot::segment()),
        'url' => '/ir-assets',
        'visibility' => 'public',
    ]]);

    File::ensureDirectoryExists(storage_path('framework/testing/'.IR_DISK.'/'.ThrowawayRoot::segment()));

    /** @var Statamic\Assets\AssetContainer $container */
    $container = AssetContainer::make(IR_CONTAINER);
    $container->disk(IR_DISK);
    $container->title('IR Files');
    $container->save();
}

it('resolves an asset whose file is on disk as ok', function (): void {
    irMakeContainer();
    File::put(storage_path('framework/testing/'.IR_DISK.'/'.ThrowawayRoot::segment().'/present.pdf'), '%PDF-1.4 probe');

    /** @var Statamic\Assets\AssetContainer $container */
    $container = AssetContainer::find(IR_CONTAINER);
    $container->makeAsset('present.pdf')->save();

    expect(irResolver()->resolve(irOccurrence('asset::'.IR_CONTAINER.'::present.pdf', LinkKind::AssetReference))->state)
        ->toBe(ResolutionState::Ok);
});

// The distinction the criterion names: the reference is well-formed and the container resolves,
// but the file is not there. `missing` would send an editor looking for a broken reference; the
// reference is fine and the FILE is what is gone.
it('separates a missing file from a missing reference', function (): void {
    irMakeContainer();
    File::put(storage_path('framework/testing/'.IR_DISK.'/'.ThrowawayRoot::segment().'/vanishing.pdf'), '%PDF-1.4 probe');

    /** @var Statamic\Assets\AssetContainer $container */
    $container = AssetContainer::find(IR_CONTAINER);
    $container->makeAsset('vanishing.pdf')->save();

    // The asset record survives; the file underneath it does not.
    File::delete(storage_path('framework/testing/'.IR_DISK.'/'.ThrowawayRoot::segment().'/vanishing.pdf'));

    $resolution = irResolver()->resolve(
        irOccurrence('asset::'.IR_CONTAINER.'::vanishing.pdf', LinkKind::AssetReference)
    );

    expect($resolution->state)->toBe(ResolutionState::MissingFile)
        ->and($resolution->state)->not->toBe(ResolutionState::Missing)
        ->and($resolution->isBroken())->toBeTrue();
});

it('resolves an asset whose container is gone as missing, naming the container', function (): void {
    irMakeContainer();

    $resolution = irResolver()->resolve(
        irOccurrence('asset::ir_no_such_container::x.pdf', LinkKind::AssetReference)
    );

    expect($resolution->state)->toBe(ResolutionState::Missing)
        ->and($resolution->detail)->toBe('ir_no_such_container');
});

// --- site awareness ---------------------------------------------------------
//
// AC5. An occurrence is resolved in the context of its ORIGIN site. A path that resolves on one
// site may not exist on another, and an entry live on one may be absent from the next — so the
// same stored value is legitimately healthy in one place and broken in another. A resolver that
// ignored the origin would report one of those two wrongly, every time.

it('resolves the same path differently depending on the occurrence origin site', function (): void {
    irMakeEntry('ir-live', 'live', true);

    // The entry exists only on the default site, so its URL is routable there and nowhere else.
    expect(irResolver()->resolve(irOccurrence('/ir/live', LinkKind::Internal, 'default'))->state)
        ->toBe(ResolutionState::Ok);

    expect(irResolver()->resolve(irOccurrence('/ir/live', LinkKind::Internal, 'health'))->state)
        ->toBe(ResolutionState::Missing);
});

it('resolves the same entry reference differently depending on the origin site', function (): void {
    irMakeEntry('ir-live', 'live', true);

    expect(irResolver()->resolve(irOccurrence('entry::ir-live', LinkKind::EntryReference, 'default'))->state)
        ->toBe(ResolutionState::Ok);

    // No localization on that site: the reference is not resolvable from there.
    expect(irResolver()->resolve(irOccurrence('entry::ir-live', LinkKind::EntryReference, 'health'))->state)
        ->toBe(ResolutionState::Missing);
});

// The per-site caches must not leak between sites — a single resolver instance answering for two
// origins is the normal case when walking a whole inventory.
it('keeps its per-site indexes separate within one resolver instance', function (): void {
    irMakeEntry('ir-live', 'live', true);
    $resolver = irResolver();

    // Warm the non-default site FIRST, so a leaking cache would make the default answer wrong.
    expect($resolver->resolve(irOccurrence('/ir/live', LinkKind::Internal, 'health'))->state)
        ->toBe(ResolutionState::Missing);
    expect($resolver->resolve(irOccurrence('/ir/live', LinkKind::Internal, 'default'))->state)
        ->toBe(ResolutionState::Ok);
});

// --- shapes the walk produces that the happy path does not ------------------
//
// An occurrence carries whatever the extractor found, and `LinkExtractor` writes an empty site
// when an item's locale is not a string. These cases exist because the resolver has branches for
// that and for a container-less asset reference, and neither was reachable from the tests above.

it('falls back to the default site when the occurrence carries no site', function (): void {
    irMakeEntry('ir-live', 'live', true);

    // Empty site: the entry is used as-is rather than localized, and the routable index is built
    // without a site filter.
    expect(irResolver()->resolve(irOccurrence('entry::ir-live', LinkKind::EntryReference, ''))->state)
        ->toBe(ResolutionState::Ok);

    expect(irResolver()->resolve(irOccurrence('/ir/live', LinkKind::Internal, ''))->state)
        ->toBe(ResolutionState::Ok);
});

it('reads redirect rules for the default site when the occurrence carries no site', function (): void {
    irMakeRedirects([
        ['source' => '/ir/old-path', 'target_url' => '/ir/live', 'enabled' => true, 'order' => 10],
    ]);

    expect(irResolver()->resolve(irOccurrence('/ir/old-path', LinkKind::Internal, ''))->state)
        ->toBe(ResolutionState::Redirected);
});

it('resolves an asset reference that names no container', function (): void {
    irMakeContainer();
    File::put(storage_path('framework/testing/'.IR_DISK.'/'.ThrowawayRoot::segment().'/loose.pdf'), '%PDF-1.4 probe');
    /** @var Statamic\Assets\AssetContainer $container */
    $container = AssetContainer::find(IR_CONTAINER);
    $container->makeAsset('loose.pdf')->save();

    // No `container::` segment. The reference is passed through whole rather than split, so a
    // resolver that always split it would look up the wrong thing.
    expect(irResolver()->resolve(irOccurrence('asset::loose.pdf', LinkKind::AssetReference))->state)
        ->not->toBe(ResolutionState::Ok);
});

// --- reference and path parsing --------------------------------------------

it('rejects a reference whose body is empty, reporting the value it could not parse', function (string $value, LinkKind $kind): void {
    $resolution = irResolver()->resolve(irOccurrence($value, $kind));

    // The DETAIL is asserted, not just the state. A malformed reference and a well-formed one
    // pointing at nothing both resolve `missing`, so the state alone cannot tell them apart —
    // and the detail is what an editor needs to know which of the two they are looking at.
    expect($resolution->state)->toBe(ResolutionState::Missing)
        ->and($resolution->detail)->toBe($value);
})->with([
    'empty asset body' => ['asset::', LinkKind::AssetReference],
    'empty term body' => ['term::', LinkKind::TermReference],
    'asset with no prefix at all' => ['just-a-string', LinkKind::AssetReference],
    'term with no prefix at all' => ['just-a-string', LinkKind::TermReference],
]);

it('treats an internal value carrying no path as nothing to check', function (): void {
    // A bare fragment or query reaches `Internal` only through an odd stored value; there is no
    // path to resolve, and reporting it missing would be a false positive.
    expect(irResolver()->resolve(irOccurrence('?a=b', LinkKind::Internal))->state)
        ->toBe(ResolutionState::NotChecked);
});

it('resolves a root-relative path and an absolute URL to the same outcome', function (): void {
    irMakeEntry('ir-live', 'live', true);

    // Pins the `/`-prefixed short circuit against the parse_url branch: both must agree.
    $relative = irResolver()->resolve(irOccurrence('/ir/live', LinkKind::Internal))->state;
    $absolute = irResolver()->resolve(irOccurrence('https://www.uams.edu/ir/live', LinkKind::Internal))->state;

    expect($relative)->toBe($absolute)->and($relative)->toBe(ResolutionState::Ok);
});

it('splits an asset reference on the FIRST separator only, so a path may contain one', function (): void {
    irMakeContainer();
    File::ensureDirectoryExists(storage_path('framework/testing/'.IR_DISK.'/'.ThrowawayRoot::segment().'/sub'));
    File::put(storage_path('framework/testing/'.IR_DISK.'/'.ThrowawayRoot::segment().'/sub/deep.pdf'), '%PDF-1.4 probe');
    /** @var Statamic\Assets\AssetContainer $container */
    $container = AssetContainer::find(IR_CONTAINER);
    $container->makeAsset('sub/deep.pdf')->save();

    // `container::sub/deep.pdf` must keep the whole remainder as the path. A split that took more
    // than two pieces would drop everything after the second separator.
    expect(irResolver()->resolve(irOccurrence('asset::'.IR_CONTAINER.'::sub/deep.pdf', LinkKind::AssetReference))->state)
        ->toBe(ResolutionState::Ok);
});

it('keeps walking a collection past an unpublished entry', function (): void {
    // The index skips unpublished entries. Stopping at the first one instead of continuing would
    // silently drop every routable URL after it, which no single-entry fixture can detect.
    irMakeEntry('ir-aaa-draft', 'aaa-draft', false);
    irMakeEntry('ir-zzz-live', 'zzz-live', true);

    expect(irResolver()->resolve(irOccurrence('/ir/zzz-live', LinkKind::Internal))->state)
        ->toBe(ResolutionState::Ok);
});

it('carries a query string through on a root-relative path', function (): void {
    irMakeEntry('ir-live', 'live', true);

    // Pins today's behavior: the `/`-prefixed short circuit returns the value whole, so a query
    // string is part of the key and the path does not match the bare routable URL.
    expect(irResolver()->resolve(irOccurrence('/ir/live?utm=x', LinkKind::Internal))->state)
        ->not->toBe(ResolutionState::Ok);
});

// The reference prefix must match `kind::` and not merely `kind`, and the `statamic://` scheme is
// stripped BEFORE that test. Each case below reports `missing` either way — what separates a
// correct parse from a sloppy one is the DETAIL, which is why these assert it.
it('requires the full `kind::` prefix, not merely the kind', function (): void {
    // `asset-not-a-reference` starts with `asset` but is not an `asset::` reference. Matching on the kind alone
    // would strip seven characters off an unrelated string and look up the remainder.
    $resolution = irResolver()->resolve(irOccurrence('asset-not-a-reference', LinkKind::AssetReference));

    expect($resolution->state)->toBe(ResolutionState::Missing)
        ->and($resolution->detail)->toBe('asset-not-a-reference');
});

it('reports the whole stored value when a prefixed reference does not parse', function (): void {
    // The scheme is stripped first, so a sloppy parse would report the stripped remainder and an
    // editor would search for a value that is not what is stored.
    $resolution = irResolver()->resolve(irOccurrence('statamic://just-a-string', LinkKind::AssetReference));

    expect($resolution->state)->toBe(ResolutionState::Missing)
        ->and($resolution->detail)->toBe('statamic://just-a-string');
});

it('rejects a bare scheme with nothing after it', function (): void {
    // `statamic://` strips to the empty string. An entry relation accepts a bare id, so the
    // emptiness check is the only thing standing between this and a lookup of ''.
    $resolution = irResolver()->resolve(irOccurrence('statamic://', LinkKind::EntryReference));

    expect($resolution->state)->toBe(ResolutionState::Missing)
        ->and($resolution->detail)->toBe('statamic://');
});

// The rules are read for the OCCURRENCE's site, not for the default one. A resolver that always
// read the default site's list would miss a per-site rule entirely — and per-site rules are how
// the people-merge writes land, so this is a real shape rather than a contrived one.
it('reads a per-site redirect rule for the occurrence origin site', function (): void {
    irMakeRedirects(
        [['source' => '/ir/default-only', 'target_url' => '/ir/a', 'enabled' => true, 'order' => 10]],
        [['source' => '/ir/health-only', 'target_url' => '/ir/b', 'enabled' => true, 'order' => 10]],
    );

    // The per-site rule serves its own site.
    expect(irResolver()->resolve(irOccurrence('/ir/health-only', LinkKind::Internal, 'health'))->state)
        ->toBe(ResolutionState::Redirected);

    // And does not leak onto the default site.
    expect(irResolver()->resolve(irOccurrence('/ir/health-only', LinkKind::Internal, 'default'))->state)
        ->toBe(ResolutionState::Missing);

    // The default list still merges beneath, so it serves the non-default site too.
    expect(irResolver()->resolve(irOccurrence('/ir/default-only', LinkKind::Internal, 'health'))->state)
        ->toBe(ResolutionState::Redirected);
});

// The resolver has no request behind it, so it matches a stored PATH with no query. A rule whose
// source carries one therefore does not serve a bare path, and pinning that stops the empty query
// argument from being quietly replaced with something that changes what matches.
it('does not match a query-bearing rule against a bare path', function (): void {
    irMakeRedirects([
        ['source' => '/ir/q?utm=x', 'target_url' => '/ir/a', 'enabled' => true, 'order' => 10],
    ]);

    expect(irResolver()->resolve(irOccurrence('/ir/q', LinkKind::Internal))->state)
        ->toBe(ResolutionState::Missing);
});

// An entry in a collection with no route has no URL. It must be skipped rather than keyed, and a
// guard that admitted it would index a null.
it('skips an entry that has no URL when building the routable index', function (): void {
    /** @var Collection $routeless */
    $routeless = CollectionFacade::make('ir_routeless');
    /** @var Collection $routeless */
    $routeless = $routeless->title('IR Routeless');
    $routeless->save();

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('ir_routeless');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id('ir-routeless');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug('routeless');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    $entry->saveQuietly();

    irMakeEntry('ir-live', 'live', true);

    // The routed entry still resolves, so the routeless one was skipped rather than fatal.
    expect(irResolver()->resolve(irOccurrence('/ir/live', LinkKind::Internal))->state)
        ->toBe(ResolutionState::Ok);
});
