<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\ComputedFields\ProviderSpotlight\ProviderSpotlightComputedValues;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;

/**
 * Mirrors a Provider Spotlight's featured provider — and the provider's own
 * ontology — into the spotlight's Find-a-Doc relationships on save (UAMS-Web/uams-statamic-archive#1425).
 *
 * A spotlight's relationships are derived from its featured provider, not curated
 * by editors (the inputs are hidden). The featured provider and the provider's
 * Locations, Areas of Expertise, Conditions, and Treatments are copied onto the
 * spotlight and kept in step, so each of those ontology pages lists the spotlight
 * as related content.
 *
 * Two write semantics:
 *
 * - `clinical_providers` is a **union** — the featured provider is added without
 *   removing any editor-entered provider.
 * - the four ontology relationships are an **overwrite** — an exact copy of the
 *   provider's set (there is no editor-curated value to preserve), cleared when
 *   the spotlight has no featured provider.
 *
 * The bidirectional `stillat/relationships` sync (UAMS-Web/uams-statamic-archive#1360/UAMS-Web/uams-statamic-archive#1407) then propagates
 * each write to the far side's back-reference. Runs on `EntrySaving` so every
 * write is persisted with the entry; no second save is issued. Each field is
 * written only when its value actually changes, so an unrelated save does not
 * needlessly churn the relationships (or the sync).
 */
final class MirrorSpotlightFeaturedProviderRelationships
{
    /**
     * The collection whose provider spotlights carry a featured provider.
     */
    private const string COLLECTION = 'clinical_resources';

    /**
     * The spotlight ontology relationships, each mapped to the featured
     * provider's own source relationship they are overwritten from.
     *
     * @var array<string, string>
     */
    private const array ONTOLOGY_MAP = [
        'clinical_locations' => 'clinical_locations',
        'clinical_expertise' => 'expertise',
        'clinical_conditions' => 'conditions',
        'clinical_services' => 'treatments',
    ];

    /**
     * Mirror the featured provider and its ontology into the spotlight before it
     * is persisted.
     */
    public function handle(EntrySaving $event): void
    {
        $entry = $event->entry;

        if (! $entry instanceof Entry || ! $this->isSpotlight($entry)) {
            return;
        }

        $featured = $this->ids($entry->get('featured_provider'));

        $this->unionFeaturedProvider($entry, $featured);
        $this->mirrorOntology($entry, $featured[0] ?? null);
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
     * Union the featured provider into `clinical_providers`, never removing an
     * editor-entered one.
     *
     * @param  list<string>  $featured
     */
    private function unionFeaturedProvider(Entry $entry, array $featured): void
    {
        if ($featured === []) {
            return;
        }

        $providers = $this->ids($entry->get('clinical_providers'));
        $union = array_values(array_unique([...$providers, ...$featured]));

        if ($union !== $providers) {
            $entry->set('clinical_providers', $union);
        }
    }

    /**
     * Overwrite each ontology relationship with the featured provider's own set.
     * A null provider (none selected, or unresolved) clears the derived
     * relationships; an unresolvable provider id leaves them untouched, so a
     * transient resolve failure never clobbers a good mirror.
     */
    private function mirrorOntology(Entry $entry, ?string $providerId): void
    {
        if ($providerId === null) {
            foreach (array_keys(self::ONTOLOGY_MAP) as $field) {
                $this->setIfChanged($entry, $field, []);
            }

            return;
        }

        $provider = EntryFacade::find($providerId);

        if (! $provider instanceof Entry) {
            return;
        }

        foreach (self::ONTOLOGY_MAP as $field => $source) {
            $this->setIfChanged($entry, $field, $this->ids($provider->get($source)));
        }
    }

    /**
     * Set a relationship field only when its normalized value changes.
     *
     * @param  list<string>  $value
     */
    private function setIfChanged(Entry $entry, string $field, array $value): void
    {
        if ($this->ids($entry->get($field)) !== $value) {
            $entry->set($field, $value);
        }
    }

    /**
     * Normalize a relationship field value to a list of non-empty id strings.
     *
     * @return list<string>
     */
    private function ids(mixed $value): array
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
}
