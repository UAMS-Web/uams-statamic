<?php

declare(strict_types=1);

namespace App\Services\PublishDependencies;

use App\Scopes\ExcludeSuppressedDependents;
use Illuminate\Support\Collection as SupportCollection;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Sites\Site;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Collection-agnostic, opt-in detection of a dependent entry whose declared
 * `publish_depends_on` source entries have gone dark (UAMS-Web/uams-statamic-archive#1593), modeled on the
 * orphaned-ancestor subsystem (UAMS-Web/uams-statamic-archive#1429) but keyed on a *declared relationship*
 * rather than the structure tree.
 *
 * A collection opts in by adding an entries field with the handle
 * {@see self::FIELD_HANDLE} to one of its blueprints; the field points at the
 * source entries this dependent's reachability follows. The tie is directional
 * by construction (the field lives on the dependent, pointing at sources), so
 * acting on a dependent never touches a source, and re-publishing a source
 * restores the dependent automatically on the next request — the mechanism is
 * read-only and never mutates stored status in any direction.
 *
 * A source is "down" when it is unpublished (native status is not `published`)
 * or has been deleted (no entry resolves for its id) — the two are identical
 * inputs, so no distinct delete path is needed. Under the default AND policy the
 * dependent is suppressed only when *every* source is down (the "last source
 * leaves" case); an optional per-field OR policy
 * (`suppression_policy: any`) suppresses when *any* source is down. When
 * suppressed, the redirect destination is the collection mount root, else the
 * site root. Resolution is memoized per request per (site, entry id).
 */
class PublishDependencyResolver
{
    /**
     * The opt-in tether field handle. A collection participates by declaring an
     * entries field with this handle on one of its blueprints.
     */
    public const string FIELD_HANDLE = 'publish_depends_on';

    /**
     * The per-field config key selecting the suppression policy. Its only
     * non-default value is {@see self::POLICY_ANY} (the OR policy); anything else
     * (including a missing key) is the default AND policy.
     */
    private const string POLICY_CONFIG_KEY = 'suppression_policy';

    /**
     * The OR-policy config value — suppress when *any* source is down.
     */
    private const string POLICY_ANY = 'any';

    /**
     * The publish-dependency state for a dependent entry, or null when the entry
     * is not opted in, declares no sources, or is not currently suppressed.
     * Memoized per request per (site, entry id).
     */
    public function resolve(Entry $entry): ?PublishDependencyResult
    {
        $collection = $entry->collection();
        if (! $collection instanceof Collection || ! $this->isEnabledFor($collection)) {
            return null;
        }

        $field = $this->tetherField($entry);
        if (! $field instanceof Field) {
            return null;
        }

        $entryId = $entry->id();
        if (! \is_string($entryId)) {
            return $this->computeResult($entry, $field);
        }

        $result = Blink::once(
            'publish_dependency_'.$this->localeOf($entry).'_'.$entryId,
            fn (): ?PublishDependencyResult => $this->computeResult($entry, $field),
        );

        return $result instanceof PublishDependencyResult ? $result : null;
    }

    /**
     * The ids of every currently-suppressed dependent across all opted-in
     * collections, for the listing-exclusion query scope
     * ({@see ExcludeSuppressedDependents}). Ids are globally unique,
     * so excluding this set from any collection's listing query is safe. Memoized
     * once per request; each collection's entries are scanned only when at least
     * one of its blueprints declares the tether field.
     *
     * @return list<string>
     */
    public function suppressedEntryIds(): array
    {
        /** @var list<string> $ids */
        $ids = Blink::once('publish_dependency_suppressed_ids', function (): array {
            $collected = [];

            foreach ($this->optedInCollectionHandles() as $handle) {
                /** @var EntryQueryBuilder $query */
                $query = EntryFacade::query();
                $query->where('collection', $handle);

                /** @var SupportCollection<int, Entry> $entries */
                $entries = $query->get();

                foreach ($entries as $entry) {
                    if (! $this->resolve($entry) instanceof PublishDependencyResult) {
                        continue;
                    }

                    $id = $entry->id();
                    if (\is_string($id)) {
                        $collected[] = $id;
                    }
                }
            }

            return array_values(array_unique($collected));
        });

        return $ids;
    }

    /**
     * Whether the collection is enabled (not opted out) for publish-dependency
     * handling. The default is enabled; a collection is excluded by listing its
     * handle in `config('publish_dependencies.disabled_collections')`.
     */
    public function isEnabledFor(Collection $collection): bool
    {
        /** @var mixed $disabled */
        $disabled = config('publish_dependencies.disabled_collections', []);

        return ! \is_array($disabled) || ! \in_array($collection->handle(), $disabled, true);
    }

    /**
     * Resolve the dependent's declared sources and apply the suppression policy,
     * returning the redirect + down-source names when suppressed, else null.
     */
    private function computeResult(Entry $entry, Field $field): ?PublishDependencyResult
    {
        $ids = $this->normalizeIds($entry->value(self::FIELD_HANDLE));

        // No declared sources → the dependent is never suppressed. An opted-in
        // blueprint whose entry left the tether empty behaves exactly like a
        // collection that never opted in.
        if ($ids === []) {
            return null;
        }

        // A source is down when unpublished or deleted (find() yields null). The
        // two are identical inputs — a deleted source's id simply resolves to no
        // entry — so there is no separate delete path.
        $downTitles = [];
        foreach ($ids as $id) {
            $source = EntryFacade::find($id);

            // Up only when the source resolves to a published entry.
            if ($source instanceof Entry && $source->status() === 'published') {
                continue;
            }

            $downTitles[] = $this->titleOf($source instanceof Entry ? $source : null, $id);
        }

        $requireAll = $this->policyRequiresAll($field);
        $downCount = \count($downTitles);

        // AND policy: suppressed only when every source is down. OR policy:
        // suppressed when at least one source is down.
        $suppressed = $requireAll
            ? $downCount === \count($ids)
            : $downCount > 0;

        if (! $suppressed) {
            return null;
        }

        $redirectUrl = $this->mountRootUrl($entry) ?? $this->siteRootUrl($entry);

        return new PublishDependencyResult(
            $redirectUrl,
            $downTitles,
            $requireAll,
        );
    }

    /**
     * The tether field on the entry's blueprint, or null when the entry's
     * blueprint does not declare it (the collection has not opted in).
     */
    private function tetherField(Entry $entry): ?Field
    {
        $blueprint = $entry->blueprint();
        if (! $blueprint instanceof Blueprint) {
            return null;
        }

        $field = $blueprint->field(self::FIELD_HANDLE);

        return $field instanceof Field ? $field : null;
    }

    /**
     * Whether the tether field uses the default AND policy (suppress only when
     * *all* sources are down). `suppression_policy: any` selects the OR policy;
     * any other value — including a missing key — is the AND default.
     */
    private function policyRequiresAll(Field $field): bool
    {
        $policy = $field->get(self::POLICY_CONFIG_KEY);

        return ! \is_string($policy) || strtolower(trim($policy)) !== self::POLICY_ANY;
    }

    /**
     * The handles of every enabled collection with at least one blueprint that
     * declares the tether field. Memoized per request.
     *
     * @return list<string>
     */
    private function optedInCollectionHandles(): array
    {
        /** @var list<string> $handles */
        $handles = Blink::once('publish_dependency_opted_in_collections', function (): array {
            $collected = [];

            foreach (CollectionFacade::all() as $collection) {
                if (! $collection instanceof Collection) {
                    continue;
                }

                if (! $this->isEnabledFor($collection)) {
                    continue;
                }

                if (! $this->collectionDeclaresTether($collection)) {
                    continue;
                }

                $handle = $collection->handle();
                if (\is_string($handle)) {
                    $collected[] = $handle;
                }
            }

            return $collected;
        });

        return $handles;
    }

    /**
     * Whether any of the collection's entry blueprints declares the tether field.
     */
    private function collectionDeclaresTether(Collection $collection): bool
    {
        /** @var SupportCollection<int, Blueprint> $blueprints */
        $blueprints = $collection->entryBlueprints();

        foreach ($blueprints as $blueprint) {
            if ($blueprint->hasField(self::FIELD_HANDLE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A down source's display name for the Control Panel banner — the source's
     * title when it has a readable one, else its id (a deleted source resolves to
     * no entry, so only its id remains).
     */
    private function titleOf(?Entry $source, string $id): string
    {
        if ($source instanceof Entry) {
            $title = $source->value('title');
            if (\is_string($title) && $title !== '') {
                return $title;
            }
        }

        return $id;
    }

    /**
     * Normalize a relationship field value to a list of non-empty id strings. The
     * raw (unaugmented) value is read via `value()` so a deleted source's id is
     * preserved — augmentation would silently drop it and lose the "down" signal.
     *
     * @return list<string>
     */
    private function normalizeIds(mixed $value): array
    {
        if (\is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (! \is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (\is_string($item) && $item !== '') {
                $ids[] = $item;
            }
        }

        return $ids;
    }

    /**
     * The URL of the entry collection's mount root, or null when unmounted or
     * when the mount landing is not itself published — an unpublished mount would
     * 302 the visitor onto a 404, so it is skipped in favour of the site root.
     */
    private function mountRootUrl(Entry $entry): ?string
    {
        $collection = $entry->collection();
        if (! $collection instanceof Collection) {
            return null;
        }

        $mount = $collection->mount();
        if (! $mount instanceof Entry || $mount->status() !== 'published') {
            return null;
        }

        $url = $mount->url();

        return \is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * The entry site's root URL, the final redirect fallback.
     */
    private function siteRootUrl(Entry $entry): string
    {
        $site = $entry->site();
        $url = $site instanceof Site ? $site->url() : null;

        return \is_string($url) && $url !== '' ? $url : '/';
    }

    /**
     * The entry's site handle as a string.
     */
    private function localeOf(Entry $entry): string
    {
        $locale = $entry->locale();

        return \is_string($locale) ? $locale : '';
    }
}
