<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Entries\PersonEntry;
use App\Http\Middleware\ProtectEntry;
use App\Services\OrphanedAncestors\OrphanedAncestorResolver;
use App\Support\Collections\CollectionExtensions;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Stache;
use Statamic\Stache\Indexes\Index;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Store;

/**
 * Registers the `meta_include_in_xml_sitemap` computed on every routable, public
 * collection so mitydigital/sitemapamic excludes non-public entries from each
 * site's XML sitemap.
 *
 * Sitemapamic's loader (`MityDigital\Sitemapamic\Support\Sitemapamic::loadEntries`)
 * only skips *unpublished* entries — it never checks routability, and it maps a
 * kept entry straight to `URL::makeAbsolute($entry->url())`. So a *published* entry
 * that is non-public (private / password-protected), non-routable (a person with no
 * profile page), or reachable only via a redirect (an orphaned knowledge-base
 * descendant) would otherwise emit a bogus, private, or redirecting `<loc>`. This
 * computed is the gate that keeps those out.
 *
 * Two inputs can drop an entry:
 *
 * 1. The **automatic gate** — authoritative, an editor cannot override it. Its shape
 *    varies by collection: private / password-protected status (pages, posts,
 *    clinical_expertise, people; see {@see self::shouldIncludeInSitemap()}), a
 *    non-routable person profile ({@see self::shouldIncludePerson()}), or an
 *    orphaned-ancestor redirect (knowledge_bases; {@see
 *    self::shouldIncludeKnowledgeBaseEntry()}, registered by
 *    {@see KnowledgeBaseCollectionComputedsRegistrar}), or an entry the router
 *    cannot resolve (post_categories, post_tags; see
 *    self::shouldIncludeRoutedEntry()).
 * 2. The **editor toggle** — the per-entry `include_in_xml_sitemap` blueprint field
 *    lets an editor keep an individual entry out of the sitemap without unpublishing
 *    it (default is included).
 *
 * Sitemapamic reads this computed via its `mappings.include` config (see
 * `config/sitemapamic.php`) as `$entry->get($handle) ?? $entry->getComputed($handle)`,
 * preferring a *stored* value. The editor toggle therefore uses the distinct
 * `include_in_xml_sitemap` handle and feeds *into* this computed — a stored field
 * under the mapped `meta_include_in_xml_sitemap` handle would shadow the computed
 * and defeat the automatic gate.
 *
 * @see ProtectEntry the request-time gate the status branch mirrors
 * @see PersonEntry::route() the routability rule the people branch mirrors
 */
final class SitemapInclusionComputedsRegistrar
{
    /**
     * @param  CollectionExtensions  $extensions  Supplies the rules packages register, including the `people` one (UAMS-Web/uams-statamic-archive#3180).
     */
    public function __construct(private readonly CollectionExtensions $extensions)
    {
        //
    }

    /**
     * The computed field handle Sitemapamic consults for per-entry inclusion.
     */
    public const string INCLUDE_HANDLE = 'meta_include_in_xml_sitemap';

    /**
     * The editor-facing blueprint toggle that opts an individual entry out of the
     * sitemap. Deliberately distinct from the mapped self::INCLUDE_HANDLE so a
     * stored value cannot shadow this computed in Sitemapamic's `get() ??
     * getComputed()` read (which would bypass the automatic gate).
     */
    public const string EDITOR_TOGGLE_HANDLE = 'include_in_xml_sitemap';

    /**
     * Collections whose blueprints all carry `status_and_visibility` +
     * `password_protected`, so they share the plain status/password/toggle gate.
     *
     * @var list<string>
     */
    private const array STATUS_GATED_COLLECTIONS = ['pages', 'posts', 'clinical_expertise'];

    /**
     * Structured collections whose blueprints carry no status, password or editor
     * toggle fields, so they are gated on routability alone (UAMS-Web/uams-statamic-archive#2542).
     *
     * @var list<string>
     */
    private const array ROUTE_GATED_COLLECTIONS = ['post_categories', 'post_tags'];

    /**
     * Register the sitemap-inclusion computed on each status/password-gated
     * collection, each route-gated one, and each collection that registers its own
     * rule through {@see CollectionExtensions} (`people`, which adds a routability gate).
     */
    public function register(): void
    {
        // pages / posts / clinical_expertise: the shared status/password/toggle gate.
        // Resolve status/visibility/toggle with value() (not get()) so a
        // localization without its own copy inherits the origin's values and is
        // gated the same way ProtectEntry gates it — otherwise get() returns null
        // and leaks a non-public URL.
        foreach (self::STATUS_GATED_COLLECTIONS as $collection) {
            Collection::computed(
                $collection,
                self::INCLUDE_HANDLE,
                fn (Entry $entry, mixed $_stored): bool => self::shouldIncludeInSitemap(
                    $entry->value('status_and_visibility'),
                    $entry->value('password_protected'),
                    $entry->value(self::EDITOR_TOGGLE_HANDLE),
                )
            );
        }

        // Collections that bring their own rule. `people` registers the shared gate AND
        // exclusion of non-routable profiles (UAMS-Web/uams-statamic-archive#43): a person with `profile_page_query`
        // explicitly false has no URI, so its `url()` is null and Sitemapamic would emit
        // a rootless/duplicate <loc>.
        foreach ($this->extensions->sitemapInclusionRules() as $collection => $rule) {
            Collection::computed($collection, self::INCLUDE_HANDLE, $rule);
        }

        // post_categories / post_tags: only an entry the router can resolve (UAMS-Web/uams-statamic-archive#2542).
        foreach (self::ROUTE_GATED_COLLECTIONS as $collection) {
            Collection::computed(
                $collection,
                self::INCLUDE_HANDLE,
                fn (Entry $entry, mixed $_stored): bool => self::shouldIncludeRoutedEntry(
                    self::indexedUri($entry),
                )
            );
        }
    }

    /**
     * Whether a status/password-gated entry (pages, posts, clinical_expertise) may
     * appear in the public XML sitemap.
     *
     * The automatic gate wins over the editor toggle: `private` and
     * password-protected entries are always excluded (both are published — so
     * Sitemapamic's own `published()` gate lets them through — yet their URLs are
     * deliberately non-public), so an editor cannot force a non-public URL into
     * the sitemap. Beyond the gate, the editor's `include_in_xml_sitemap` toggle
     * excludes an entry when explicitly turned off; absent or `true` means
     * included.
     *
     * @param  mixed  $statusAndVisibility  The entry's `status_and_visibility` value.
     * @param  mixed  $passwordProtected  The entry's `password_protected` toggle value.
     * @param  mixed  $editorInclude  The entry's `include_in_xml_sitemap` toggle value.
     */
    public static function shouldIncludeInSitemap(
        mixed $statusAndVisibility,
        mixed $passwordProtected,
        mixed $editorInclude = true,
    ): bool {
        // A private entry is live but restricted (404 for anonymous visitors).
        if ($statusAndVisibility === 'private') {
            return false;
        }

        // A password-protected entry is gated behind a password; hide its URL.
        if ($passwordProtected) {
            return false;
        }

        // The editor explicitly opted this entry out of the sitemap.
        return $editorInclude !== false;
    }

    /**
     * Whether a `people` entry may appear in the public XML sitemap.
     *
     * Adds a routability gate to the shared status/password/toggle predicate: a
     * person who opted out of a front-end profile page (`profile_page_query`
     * explicitly `false`) is non-routable, so advertising a URL that resolves to a
     * 404 would be wrong. Mirrors {@see PersonEntry::route()}'s `=== false` check
     * exactly — an unset value keeps the routable default and stays eligible.
     *
     * @param  mixed  $statusAndVisibility  The entry's `status_and_visibility` value.
     * @param  mixed  $passwordProtected  The entry's `password_protected` toggle value.
     * @param  mixed  $editorInclude  The entry's `include_in_xml_sitemap` toggle value.
     * @param  mixed  $profilePageQuery  The entry's `profile_page_query` toggle value.
     */
    public static function shouldIncludePerson(
        mixed $statusAndVisibility,
        mixed $passwordProtected,
        mixed $editorInclude,
        mixed $profilePageQuery,
    ): bool {
        // A person opted out of a front-end profile page is non-routable — never
        // advertise a URL that 404s. Explicit false only (unset keeps the default).
        if ($profilePageQuery === false) {
            return false;
        }

        return self::shouldIncludeInSitemap($statusAndVisibility, $passwordProtected, $editorInclude);
    }

    /**
     * Whether an entry in a structured collection gated on routability alone
     * (post_categories, post_tags) may appear in the public XML sitemap.
     *
     * The router resolves a request through the Stache `uri` index, while
     * Sitemapamic emits `$entry->url()`, which recomputes the URI when the index
     * has none. The two disagree for an entry saved without a place in the
     * collection's tree: its index URI is saved as null, so its URL 404s, yet the
     * recomputed URI re-roots it and the sitemap would advertise that URL until
     * the Stache is rebuilt. Reading the index keeps the sitemap to what the
     * router serves.
     *
     * @param  string|null  $indexedUri  The entry's URI in the Stache `uri` index.
     */
    public static function shouldIncludeRoutedEntry(?string $indexedUri): bool
    {
        return $indexedUri !== null && $indexedUri !== '';
    }

    /**
     * The URI the Stache `uri` index holds for an entry, which is what the router
     * matches a request against.
     *
     * @param  Entry  $entry  The entry to look up.
     */
    private static function indexedUri(Entry $entry): ?string
    {
        // Resolve the entry store for the entry's collection
        $entries = Stache::store('entries');
        // A narrowing guard larastan requires at level 10. The Stache cannot hand back
        // another type here, so no fixture reaches the false branch.
        // @pest-mutate-ignore: InstanceOfToTrue
        $store = $entries instanceof AggregateStore ? $entries->store($entry->collectionHandle()) : null;

        // A narrowing guard larastan requires at level 10, which would treat a missing
        // store as unroutable. The Stache resolves one for every configured
        // collection, so no fixture reaches the true branch.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $store instanceof Store) {
            return null;
        }

        $index = $store->index('uri');

        // A narrowing guard larastan requires at level 10. The Stache cannot hand back
        // another type here, so no fixture reaches the false branch.
        // @pest-mutate-ignore: InstanceOfToTrue
        $uri = $index instanceof Index ? $index->get($entry->id()) : null;

        return is_string($uri) ? $uri : null;
    }

    /**
     * Whether a `knowledge_bases` entry may appear in the public XML sitemap.
     *
     * KB blueprints carry no `status_and_visibility` / `password_protected` fields;
     * their non-public case is instead a published descendant under an *unpublished*
     * knowledge_base / topic ancestor, which is redirected away (UAMS-Web/uams-statamic-archive#217, folded onto
     * the universal mechanism in UAMS-Web/uams-statamic-archive#1455). Advertising such an entry's own URL would
     * list a URL that redirects, so an entry with a non-null orphaned-ancestor
     * redirect URL is excluded. The editor toggle still applies. Used by
     * {@see KnowledgeBaseCollectionComputedsRegistrar}, which sources the redirect
     * URL from the universal {@see OrphanedAncestorResolver}.
     *
     * @param  string|null  $orphanedAncestorRedirectUrl  The entry's universal orphaned-ancestor redirect URL (non-null ⇒ orphaned).
     * @param  mixed  $editorInclude  The entry's `include_in_xml_sitemap` toggle value.
     */
    public static function shouldIncludeKnowledgeBaseEntry(
        ?string $orphanedAncestorRedirectUrl,
        mixed $editorInclude = true,
    ): bool {
        // A published descendant under an unpublished ancestor is redirected away;
        // its own URL must not be advertised in the sitemap.
        if ($orphanedAncestorRedirectUrl !== null) {
            return false;
        }

        // The editor explicitly opted this entry out of the sitemap.
        return $editorInclude !== false;
    }
}
