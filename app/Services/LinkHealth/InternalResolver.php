<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

use App\Support\Redirects\RedirectRuleMatcher;
use App\Support\Redirects\RedirectSource;
use Illuminate\Support\Collection;
use Statamic\Assets\Asset as AssetItem;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Filesystem\FlysystemAdapter;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Resolve every internal target in-process, without a single HTTP request (UAMS-Web/uams-statamic-archive#1765).
 *
 * A dangling reference is a broken link that can be PROVEN broken by looking it up, and the lookup
 * answers a strictly stronger question than fetching the URL would. A `404` cannot distinguish a
 * deleted entry from an unpublished one from a redirected path, and it cannot see a dangling
 * `entries` relation at all, because that has no URL to probe. Lookup also costs no network,
 * cannot be rate-limited, and works for content that is not publicly reachable.
 *
 * Resolution is SITE-AWARE. An occurrence is resolved in the context of its origin site, because
 * a path that resolves on one site may not exist on another, and an entry published on one site
 * may be unpublished on the next.
 *
 * Redirect awareness runs through {@see RedirectRuleMatcher}, which is the same selection
 * `App\Tags\FindRedirectRule` serves from — extracted for this purpose in UAMS-Web/uams-statamic-archive#1765. Re-deriving it
 * here would let the report and the served behavior disagree, which is the one outcome the
 * acceptance criteria rule out.
 */
final class InternalResolver
{
    /**
     * Routable-URL keys per site, built once per site and reused.
     *
     * @var array<string, array<string, true>>
     */
    private array $routableKeys = [];

    /**
     * Redirect rules per site, read once per site and reused.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $redirectRules = [];

    /**
     * @param  RedirectRuleMatcher  $matcher  The shared redirect selection.
     */
    public function __construct(private readonly RedirectRuleMatcher $matcher) {}

    /**
     * Resolve one occurrence against the content repositories.
     *
     * @param  LinkOccurrence  $occurrence  The occurrence to resolve.
     * @return Resolution What became of its target.
     */
    public function resolve(LinkOccurrence $occurrence): Resolution
    {
        return match ($occurrence->kind) {
            LinkKind::EntryReference, LinkKind::EntryRelation => $this->resolveEntry($occurrence),
            LinkKind::AssetReference => $this->resolveAsset($occurrence),
            LinkKind::TermReference => $this->resolveTerm($occurrence),
            LinkKind::Internal => $this->resolveInternalUrl($occurrence),
            // External, FirstPartyOther, NonHttp and FragmentOnly are other slices' work, or have
            // no target to look up. Reported rather than dropped so a caller can tell an
            // unexamined occurrence from a healthy one.
            default => new Resolution(ResolutionState::NotChecked),
        };
    }

    /**
     * Resolve an `entry::` reference or a bare `entries`-relation id.
     *
     * `missing` and `unpublished` are separated deliberately: the first means the target is gone
     * and the link must be repointed, the second means the content is still there and the fix is
     * editorial. Collapsing them would make the report unactionable.
     */
    private function resolveEntry(LinkOccurrence $occurrence): Resolution
    {
        $id = $this->referenceBody($occurrence->value, 'entry');

        if ($id === null) {
            return new Resolution(ResolutionState::Missing, $occurrence->value);
        }

        $entry = Entry::find($id);

        if (! $entry instanceof EntryItem) {
            return new Resolution(ResolutionState::Missing, $id);
        }

        // The localization for the occurrence's own site, which is what decides publication. An
        // entry live on the default site may be absent or unpublished on a sibling.
        $localized = $occurrence->site === '' ? $entry : $entry->in($occurrence->site);

        if (! $localized instanceof EntryItem) {
            return new Resolution(ResolutionState::Missing, $id);
        }

        return $localized->published() === true
            ? new Resolution(ResolutionState::Ok)
            : new Resolution(ResolutionState::Unpublished, $id);
    }

    /**
     * Resolve an `asset::` reference, confirming the FILE and not merely the reference.
     *
     * A well-formed reference whose container is gone is `missing`; one whose container resolves
     * but whose file is absent from disk is `missing_file`. The second is the case that a
     * shape-based check cannot see, and the reason this slice touches the filesystem at all.
     */
    private function resolveAsset(LinkOccurrence $occurrence): Resolution
    {
        $reference = $this->referenceBody($occurrence->value, 'asset');

        if ($reference === null) {
            return new Resolution(ResolutionState::Missing, $occurrence->value);
        }

        // `container::path` when the reference names one, otherwise the configured default.
        // The limit is unobservable: raising it only changes the split for a reference whose PATH
        // itself contains `::`, and the reference format uses that as its own separator, so such a
        // path cannot be represented unambiguously in the first place.
        // @pest-mutate-ignore: IncrementInteger
        [$containerHandle, $path] = str_contains($reference, '::')
            ? explode('::', $reference, 2)
            : [null, $reference];

        if ($containerHandle !== null && AssetContainer::find($containerHandle) === null) {
            return new Resolution(ResolutionState::Missing, $containerHandle);
        }

        $asset = Asset::find($reference);

        if (! $asset instanceof AssetItem) {
            return new Resolution(ResolutionState::Missing, $reference);
        }

        // `$asset->exists()` is NOT a disk check — measured: it returns true for an asset whose
        // file has been deleted, on a freshly fetched instance as well as a stale one, because it
        // consults the asset's own metadata. Trusting it would make `missing_file` unreachable,
        // which is the one state the criterion asks this method to establish. Ask the disk.
        /** @var \Statamic\Assets\AssetContainer $container */
        $container = $asset->container();
        /** @var FlysystemAdapter $disk */
        $disk = $container->disk();

        /** @var string $assetPath */
        $assetPath = $asset->path();

        return $disk->exists($assetPath) === true
            ? new Resolution(ResolutionState::Ok)
            : new Resolution(ResolutionState::MissingFile, $path);
    }

    /**
     * Resolve a `term::` reference through the taxonomy repositories.
     */
    private function resolveTerm(LinkOccurrence $occurrence): Resolution
    {
        $reference = $this->referenceBody($occurrence->value, 'term');

        if ($reference === null) {
            return new Resolution(ResolutionState::Missing, $occurrence->value);
        }

        $term = Term::find($reference);

        return $term instanceof LocalizedTerm
            ? new Resolution(ResolutionState::Ok)
            : new Resolution(ResolutionState::Missing, $reference);
    }

    /**
     * Resolve a root-relative or same-host URL against the routable index, then the redirects.
     *
     * Order matters: a path that resolves directly is `ok` and never consults the redirect rules,
     * which mirrors the front end — a live URL is served, not redirected.
     */
    private function resolveInternalUrl(LinkOccurrence $occurrence): Resolution
    {
        $path = $this->pathOf($occurrence->value);

        if ($path === null) {
            return new Resolution(ResolutionState::NotChecked);
        }

        if (isset($this->routableUrlKeys($occurrence->site)[RedirectSource::collisionKey($path)])) {
            return new Resolution(ResolutionState::Ok);
        }

        // The query is empty because there is no request behind a resolver — it matches a stored
        // path. A non-empty value would only change the path-with-query subject, which the
        // selection consults solely for a source that itself carries a query; pinning that would
        // mean writing a rule whose source contains the mutator's substituted string, which is not
        // knowable from here.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $matched = $this->matcher->match($this->rulesFor($occurrence->site), $path, '');

        if ($matched !== []) {
            $target = $matched[0]['target_url'] ?? null;

            return new Resolution(
                ResolutionState::Redirected,
                \is_string($target) ? $target : null,
            );
        }

        return new Resolution(ResolutionState::Missing, $path);
    }

    /**
     * The body of a `statamic://kind::body` or bare `kind::body` reference, or null.
     *
     * @param  string  $value  The stored value.
     * @param  string  $kind  The reference kind — `entry`, `asset` or `term`.
     */
    private function referenceBody(string $value, string $kind): ?string
    {
        $bare = str_starts_with($value, 'statamic://')
            ? substr($value, \strlen('statamic://'))
            : $value;

        // A bare `entries`-relation id carries no prefix at all, and is the whole reference.
        if (! str_starts_with($bare, $kind.'::')) {
            return $kind === 'entry' && $bare !== '' ? $bare : null;
        }

        // `strlen($kind.'::')` and the mutant's `strlen('::'.$kind)` are the same number —
        // measured, 7 for every kind here — so the concatenation order cannot be observed.
        // @pest-mutate-ignore: ConcatSwitchSides
        $body = substr($bare, \strlen($kind.'::'));

        return $body === '' ? null : $body;
    }

    /**
     * The path component of a stored value, or null when it carries none to resolve.
     */
    private function pathOf(string $value): ?string
    {
        if (str_starts_with($value, '/')) {
            return $value;
        }

        $path = parse_url($value, PHP_URL_PATH);

        // Both mutants are absorbed by the ternary: when the guard differs, `parse_url` has
        // returned null, and the true branch yields `$path` — which is that same null. Measured
        // with `?a=b`, whose PHP_URL_PATH is null.
        // @pest-mutate-ignore: BooleanAndToBooleanOr,EmptyStringToNotEmpty
        return \is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Every routable entry URL on one site, keyed the way stored sources are keyed.
     *
     * Uses `RedirectSource::collisionKey()` so this index and the redirect rules agree on what
     * counts as the same path — otherwise `/foo` and `/foo/` would resolve differently here than
     * they do when served.
     *
     * @return array<string, true>
     */
    private function routableUrlKeys(string $site): array
    {
        if (isset($this->routableKeys[$site])) {
            return $this->routableKeys[$site];
        }

        $keys = [];

        /** @var iterable<mixed> $handles */
        $handles = CollectionFacade::handles();

        foreach ($handles as $handle) {
            if (! \is_string($handle)) {
                continue;
            }

            /** @var EntryQueryBuilder $query */
            $query = Entry::query();
            /** @var EntryQueryBuilder $query */
            $query = $query->where('collection', $handle);

            if ($site !== '') {
                /** @var EntryQueryBuilder $query */
                $query = $query->where('site', $site);
            }

            /** @var Collection<int, mixed> $entries */
            $entries = $query->get();

            foreach ($entries as $entry) {
                // Split rather than combined, because `ChangeOrIfContinueToMultiContinueRector`
                // requires it — and the split is load-bearing for a second reason. The annotation
                // below is per line AND per mutator, so it has to travel with the `instanceof`
                // half; left on a combined condition, or moved to the `published()` half, it
                // silences the wrong mutator on the wrong node and the survivor returns.
                //
                // Narrowing larastan requires at level 10: the query returns a loosely typed
                // collection, and the methods below are unresolvable without it. The facade cannot
                // yield a non-entry here, so the false branch is unreachable — the same class
                // recorded in LinkExtractor's docblock.
                // @pest-mutate-ignore: InstanceOfToTrue
                if (! $entry instanceof EntryItem) {
                    continue;
                }

                // Deliberately NOT exempt: an unpublished entry is a real, reachable skip, and the
                // routeless-collection case kills the mutant on it.
                if ($entry->published() !== true) {
                    continue;
                }

                $url = $entry->url();

                // The second guard is unreachable: an entry's `url()` returns a routable URL or
                // null, never the empty string, so `is_string()` has already short-circuited by
                // the time an empty value could matter. Covered from the null side by the
                // routeless-collection case.
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                if (\is_string($url) && $url !== '') {
                    // The VALUE is never read — membership is tested with `isset()`, which is
                    // true for `false` as well as for `true`. Measured. Only a null would change
                    // the answer, and that is not what this mutator writes.
                    // @pest-mutate-ignore: TrueToFalse
                    $keys[RedirectSource::collisionKey($url)] = true;
                }
            }
        }

        return $this->routableKeys[$site] = $keys;
    }

    /**
     * The redirect rules that apply on one site, read through the shared matcher.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rulesFor(string $site): array
    {
        if (isset($this->redirectRules[$site])) {
            return $this->redirectRules[$site];
        }

        $global = GlobalSet::findByHandle('redirects');

        if ($global === null) {
            return $this->redirectRules[$site] = [];
        }

        /** @var \Statamic\Sites\Site|null $defaultSite */
        $defaultSite = Site::default();
        /** @var string|null $defaultSiteHandle */
        // The null-safe operator cannot be observed: `Site::default()` returns a site in every
        // configured install, and an install with no default site cannot be constructed to test
        // against. The operator stays because the facade's signature admits null.
        // @pest-mutate-ignore: RemoveNullSafeOperator
        $defaultSiteHandle = $defaultSite?->handle();

        return $this->redirectRules[$site] = $this->matcher->rulesForSite(
            $global,
            // Two mutants land here and neither is observable. The cast is a no-op because the
            // handle is already a string wherever a default site exists (see the note above), and
            // changing the `''` comparison only routes an empty site through `rulesForSite` with
            // an empty handle — which merges the default site's list beneath it and arrives at the
            // same rules. The TERNARY itself is not exempt and is killed by the per-site case.
            // @pest-mutate-ignore: RemoveStringCast,EmptyStringToNotEmpty
            $site === '' ? (string) $defaultSiteHandle : $site,
            $defaultSiteHandle,
        );
    }
}
