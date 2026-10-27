<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Enums\State;
use App\Services\ComputedFields\ProviderPeople\ClinicalTitleResolver;
use App\Services\Locations\LocationInheritedAddressService;
use App\Services\TitleDisambiguator;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;

/**
 * Composes the SEO `<title>` for the templated `people` (provider profiles) and
 * `clinical_locations` collections (UAMS-Web/uams-statamic-archive#1329), and keeps those rendered titles
 * unique within a collection/site (UAMS-Web/uams-statamic-archive#1330).
 *
 * The `people` title is `Name | Primary Specialty | City, ST | UAMS Health`;
 * `clinical_locations` is `Location Name | City, ST | UAMS Health`. Empty
 * segments are dropped so a missing specialty or city never leaves a dangling
 * `|` separator. The stored `title` field is left untouched — breadcrumbs, the
 * Control Panel, the `<h1>`, and the collection-level disambiguator all read it.
 *
 * "Primary Specialty" is the primary `clinical_specializations` entry's
 * patient-facing title, resolved by {@see ClinicalTitleResolver} (shared with the
 * `clinical_title` computed the Find-a-Doc provider card renders, so the two never
 * drift); "City, ST" comes from the provider's first `clinical_locations` entry
 * (or the location's own address). The brand suffix is the UAMS Health product
 * name, per UAMS-Web/uams-statamic-archive#1329.
 *
 * Uniqueness (UAMS-Web/uams-statamic-archive#1330) is proportionate: because the name is the first segment, two
 * entries can only share a composed title when they share a stored title, so an
 * index-scoped same-title scan (reused from {@see TitleDisambiguator}) gates the
 * work; a deterministic " (2)", " (3)" ordinal is appended only to the actual
 * colliding entries, leaving titles that already differ (by specialty or city)
 * unchanged.
 */
final readonly class DocumentTitle
{
    /**
     * Brand suffix appended to every provider/location title (UAMS-Web/uams-statamic-archive#1329). Hard-coded
     * rather than sourced from the entry's site name: Find-a-Doc provider and
     * location profiles are a UAMS Health product regardless of which multisite
     * section the entry lives in.
     */
    private const string BRAND = 'UAMS Health';

    /**
     * @param  StacheEntryRepository  $entries  Stache entry repository for resolving related location entries.
     * @param  TitleDisambiguator  $titleDisambiguator  Index-scoped same-title lookup reused as the collision gate.
     * @param  ClinicalTitleResolver  $clinicalTitleResolver  Resolves the "Primary Specialty" segment (shared with the `clinical_title` computed).
     */
    public function __construct(
        private StacheEntryRepository $entries,
        private TitleDisambiguator $titleDisambiguator,
        private ClinicalTitleResolver $clinicalTitleResolver,
        private LocationInheritedAddressService $locationAddress,
    ) {}

    /**
     * The unique document `<title>` for a `people` provider-profile entry.
     */
    public function forPeople(Entry $entry): string
    {
        $base = $this->peopleBaseTitle($entry);

        return $this->disambiguate($entry, $base, fn (Entry $peer): string => $this->peopleBaseTitle($peer));
    }

    /**
     * The unique document `<title>` for a `clinical_locations` entry.
     */
    public function forClinicalLocation(Entry $entry): string
    {
        $base = $this->locationBaseTitle($entry);

        return $this->disambiguate($entry, $base, fn (Entry $peer): string => $this->locationBaseTitle($peer));
    }

    /**
     * `Name | Primary Specialty | City, ST | UAMS Health`, empty segments dropped.
     */
    private function peopleBaseTitle(Entry $entry): string
    {
        return $this->assemble([
            $this->stringValue($entry->get('title')),
            $this->clinicalTitleResolver->resolve($entry),
            $this->primaryLocationCityState($entry),
        ]);
    }

    /**
     * `Location Name | City, ST | UAMS Health`, empty segments dropped.
     */
    private function locationBaseTitle(Entry $entry): string
    {
        return $this->assemble([
            $this->stringValue($entry->get('title')),
            $this->cityState($entry),
        ]);
    }

    /**
     * Join the non-empty segments plus the brand with ` | `, so a missing segment
     * never produces a dangling separator. Callers pass segments already narrowed
     * through {@see self::stringValue()} (trimmed), so no re-trim is needed here.
     *
     * @param  list<string>  $segments  The distinguishing title segments, in order.
     */
    private function assemble(array $segments): string
    {
        $segments[] = self::BRAND;

        return implode(' | ', array_filter($segments, static fn (string $segment): bool => $segment !== ''));
    }

    /**
     * "City, ST" from the entry's first related `clinical_locations` entry.
     */
    private function primaryLocationCityState(Entry $entry): string
    {
        $locationIds = $entry->get('clinical_locations');
        if (! \is_array($locationIds) || $locationIds === []) {
            return '';
        }

        $first = $locationIds[0] ?? null;
        if (! \is_string($first) || $first === '') {
            return '';
        }

        $location = $this->entries->find($first);
        if (! $location instanceof Entry) {
            return '';
        }

        return $this->cityState($location);
    }

    /**
     * "City, ST" for a location, as the location resolves them rather than as its own
     * `address` stores them: a child location that inherits its street group takes its
     * parent's city and state, and one inside a facility takes the facility's (UAMS-Web/uams-statamic-archive#2608).
     * The resolver reads the canonical `state_province_region` subfield before each
     * preset's own handle; the value is then normalized to its USPS two-letter code.
     */
    private function cityState(Entry $location): string
    {
        $city = $this->stringValue($this->locationAddress->resolveCity($location));
        $state = $this->normalizeState($this->stringValue($this->locationAddress->resolveState($location)));

        if ($city !== '' && $state !== '') {
            return $city.', '.$state;
        }

        return $city !== '' ? $city : $state;
    }

    /**
     * Normalize a stored region value to its USPS two-letter code for the "City, ST"
     * segment. The Address fieldtype's American preset stores the full state name
     * (e.g. "Arkansas"), so map that to its code; a value already given as a code
     * (e.g. "AR") is kept, and a non-US / unrecognized region passes through as-is.
     */
    private function normalizeState(string $state): string
    {
        if ($state === '') {
            return '';
        }

        // Already a USPS code (the enum is backed by the two-letter code).
        $byCode = State::tryFrom(strtoupper($state));
        if ($byCode !== null) {
            return $byCode->value;
        }

        // Stored as a full state name — map it to the code.
        foreach (State::cases() as $case) {
            if (strcasecmp($case->fullName(), $state) === 0) {
                return $case->value;
            }
        }

        // Non-US / unrecognized region (e.g. an international preset): leave as-is.
        return $state;
    }

    /**
     * Append " (2)", " (3)", … only when another entry in the same collection/site
     * renders an identical composed title.
     *
     * @param  callable(Entry): string  $baseFor  Composes a peer's base title (no
     *                                            disambiguation, so no recursion).
     */
    private function disambiguate(Entry $entry, string $base, callable $baseFor): string
    {
        $name = $this->stringValue($entry->get('title'));
        if ($name === '') {
            return $base;
        }

        // The name is the first segment, so only same-title entries can collide.
        // This exact-title lookup is Stache-index-scoped (UAMS-Web/uams-statamic-archive#862), so the common
        // unique-name case returns immediately without a full-collection scan.
        $peers = $this->titleDisambiguator->entriesWithExactTitle($entry, $name);
        if ($peers->isEmpty()) {
            return $base;
        }

        // Keep only same-name peers whose composed title actually collides; a peer
        // with a different specialty or city already renders a distinct title.
        /** @var Collection<int, Entry> $colliding */
        $colliding = $peers->filter(static fn (Entry $peer): bool => $baseFor($peer) === $base);
        if ($colliding->isEmpty()) {
            return $base;
        }

        // Deterministic ordinal over {self} ∪ {colliding peers} by id: the lowest
        // id keeps the clean title, the rest take " (2)", " (3)", … Every entry in
        // the set computes the same order, so the ordinals are globally consistent.
        $selfId = $this->entryId($entry);

        $ordered = $colliding
            ->push($entry)
            ->unique(fn (Entry $peer): string => $this->entryId($peer))
            ->sortBy(fn (Entry $peer): string => $this->entryId($peer))
            ->values();

        $position = $ordered->search(fn (Entry $peer): bool => $this->entryId($peer) === $selfId);

        return \is_int($position) && $position > 0
            ? $base.' ('.($position + 1).')'
            : $base;
    }

    /**
     * The entry's id narrowed to a string, or '' when it has none.
     */
    private function entryId(Entry $entry): string
    {
        return $this->stringValue($entry->id());
    }

    /**
     * Narrow an arbitrary stored value to a trimmed string, or '' when non-scalar.
     */
    private function stringValue(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }
}
