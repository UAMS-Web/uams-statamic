<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\ComputedFields\ProviderSpotlight\ProviderSpotlightComputedValues;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Sites\Site;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Derives a Provider Spotlight's entry title and slug from its featured provider
 * on save (UAMS-Web/uams-statamic-archive#1629), alongside {@see MirrorSpotlightFeaturedProviderRelationships}.
 *
 * A spotlight is *about* one featured provider, so its title is provider-derived
 * ("Get to Know {provider name}") rather than editor-maintained, and tracks the
 * provider on every save. The slug is derived from the prefix/degree-free
 * provider name while the entry is a draft, then frozen once it is published so
 * a live URL is never rewritten by a later provider change.
 *
 * Imported spotlights carry their own WordPress title and slug
 * (`UAMS-Web/wordpress-importer#419`), so a WordPress console import is bypassed
 * and its titles/slugs preserved; derivation applies to CP-authored spotlights
 * (and any later CP re-save), mirroring the clinical-collection validation
 * listeners' `import:wordpress` bypass.
 *
 * Runs on `EntrySaving` so the derived values persist with the entry and no
 * second save is issued, after `SyncEntryPublishedStatus` so the published
 * state the slug freeze reads is already reconciled.
 */
final class DeriveSpotlightTitleAndSlug
{
    /**
     * The collection whose provider spotlights carry a featured provider.
     */
    private const string COLLECTION = 'clinical_resources';

    public function __construct(
        private readonly ProviderSpotlightComputedValues $spotlight,
    ) {}

    /**
     * Derive the title (always) and slug (frozen once published) before the
     * spotlight is persisted.
     */
    public function handle(EntrySaving $event): void
    {
        $entry = $event->entry;

        if (! $entry instanceof Entry || ! $this->isSpotlight($entry)) {
            return;
        }

        // Imported spotlights keep their WordPress title/slug; only CP saves derive.
        if ($this->isRunningWordPressImport()) {
            return;
        }

        $this->deriveTitle($entry);
        $this->deriveSlug($entry);
    }

    /**
     * Set the title from the featured provider, tracking a provider change on
     * every save. Written only when it actually changes.
     */
    private function deriveTitle(Entry $entry): void
    {
        $title = $this->spotlight->autoTitle($entry);

        if ($title !== null && $entry->get('title') !== $title) {
            $entry->set('title', $title);
        }
    }

    /**
     * Set the slug from the featured provider while the entry is a draft, then
     * freeze it once published so a live URL is never rewritten. A published
     * entry that has no slug yet is still filled, so a spotlight always
     * has a slug.
     */
    private function deriveSlug(Entry $entry): void
    {
        $current = $entry->slug();
        $current = \is_string($current) ? $current : '';

        if ($entry->published() && $current !== '') {
            return;
        }

        $base = $this->spotlight->autoSlug($entry);

        if ($base === null || $base === '') {
            return;
        }

        $slug = $this->uniqueSlug($entry, $base);

        if ($slug !== $current) {
            $entry->slug($slug);
        }
    }

    /**
     * Make the derived base slug unique within the collection and site by
     * appending a numeric suffix on collision (`…-2`, `…-3`), mirroring the
     * title disambiguator. Statamic's CP slug-uniqueness rule runs before this
     * `EntrySaving` write, so two spotlights whose providers share a display
     * name — or the same provider featured twice — would otherwise persist the
     * same slug and collide on one front-end URL. The entry's own slug is
     * excluded so a re-save never bumps its suffix.
     */
    private function uniqueSlug(Entry $entry, string $base): string
    {
        /** @var Site|null $entrySite */
        $entrySite = $entry->site();
        $siteHandle = $entrySite?->handle();
        $site = \is_string($siteHandle) ? $siteHandle : null;

        $selfId = $entry->id();
        $selfId = \is_string($selfId) ? $selfId : null;

        $slug = $base;
        $suffix = 1;

        while ($this->slugTaken($slug, $site, $selfId)) {
            $suffix++;
            $slug = sprintf('%s-%d', $base, $suffix);
        }

        return $slug;
    }

    /**
     * Whether another entry in the collection (and site) already uses the slug.
     * The `slug` where-clause narrows via the Stache slug index; the value is
     * re-checked defensively so a broadened index match cannot report a false
     * collision.
     */
    private function slugTaken(string $slug, ?string $site, ?string $selfId): bool
    {
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', self::COLLECTION);
        /** @var EntryQueryBuilder $query */
        $query = $query->where('slug', $slug);
        /** @var \Illuminate\Support\Collection<int, Entry> $candidates */
        $candidates = $query->get();

        foreach ($candidates as $other) {
            if ($other->slug() !== $slug) {
                continue;
            }

            if ($selfId !== null && $other->id() === $selfId) {
                continue;
            }

            /** @var Site|null $otherSite */
            $otherSite = $other->site();
            if ($site !== null && $otherSite?->handle() !== $site) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Whether the entry is a `clinical_resources` provider spotlight.
     */
    private function isSpotlight(Entry $entry): bool
    {
        $collection = $entry->collection();
        if (! $collection instanceof Collection || $collection->handle() !== self::COLLECTION) {
            return false;
        }

        $blueprint = $entry->blueprint();

        return $blueprint instanceof Blueprint
            && $blueprint->handle() === ProviderSpotlightComputedValues::BLUEPRINT;
    }

    /**
     * Whether the current process is a WordPress console import (`import:wordpress`).
     *
     * Mirrors the clinical-collection validation listeners' bypass: only a
     * console invocation whose argv carries the import command preserves the
     * imported title/slug rather than deriving.
     */
    private function isRunningWordPressImport(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        if (isset($_SERVER['argv']) && \is_array($_SERVER['argv'])) {
            foreach ($_SERVER['argv'] as $arg) {
                if (\is_string($arg) && str_contains($arg, 'import:wordpress')) {
                    return true;
                }
            }
        }

        return false;
    }
}
