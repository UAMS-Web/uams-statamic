<?php

declare(strict_types=1);

namespace App\Search;

use App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Query\ContainsQueryableValues;
use Statamic\Contracts\Search\Result as ResultContract;
use Statamic\Contracts\Search\Searchable;
use Statamic\Data\HasAugmentedData;
use Statamic\Search\Result;

/**
 * A synthetic search document for one populated Area-of-Expertise routed section
 * subpage (`/{entry}/{section}`, UAMS-Web/uams-statamic-archive#1058) — e.g. "Cancer Care — Related Clinical
 * Locations" at `/cancer-care/locations`.
 *
 * The routed subpages are not entries (they are derived at request time by
 * {@see ClinicalExpertiseSectionResolver}), so the
 * `local` search driver never sees them. {@see AreaOfExpertiseSectionProvider}
 * synthesizes one of these per populated section and wraps it here so it can be
 * indexed and rendered like any other hit (UAMS-Web/uams-statamic-archive#1527).
 *
 * The interactive `{{ search:results }}` tag runs the default `withData(true)` path,
 * which re-hydrates each hit through the provider's `find()` and wraps it in a
 * {@see Result}. That wrapper delegates augmentation to this object
 * ({@see Augmentable}) and resolves the tag's `status`/`site` filters through it
 * ({@see ContainsQueryableValues}), so a published, current-site subpage survives
 * filtering and renders `{{ url }}` / `{{ title }}` from {@see self::augmentedArrayData()}.
 */
final class AreaOfExpertiseSectionDocument implements Augmentable, ContainsQueryableValues, Searchable
{
    use HasAugmentedData;

    /**
     * Reference prefix. Must equal {@see AreaOfExpertiseSectionProvider::referencePrefix()}
     * so the driver routes this document's reference back to that provider, and it becomes
     * the augmented `result_type` the results template dispatches on.
     */
    public const string REFERENCE_PREFIX = 'clinical_expertise_section';

    /**
     * Queryable field holding the parent Area of Expertise's entry id, so a search query
     * can drop the subpages of a private Area of Expertise (UAMS-Web/uams-statamic-archive#2634). An entry hit resolves
     * it to null, so a condition on it leaves entries alone.
     */
    public const string AREA_OF_EXPERTISE_ID_FIELD = 'area_of_expertise_id';

    /**
     * @param  string  $aoeId  The parent Area-of-Expertise entry id.
     * @param  string  $section  The routed section token (`providers`, `locations`, …).
     * @param  string  $title  The reader-facing + searchable title (`{AoE} — {section label}`).
     * @param  string  $url  The subpage URL (`/{aoe-uri}/{section}`).
     * @param  string  $site  The AoE entry's site handle (only consulted on multi-site installs).
     */
    public function __construct(
        private readonly string $aoeId,
        private readonly string $section,
        private readonly string $title,
        private readonly string $url,
        private readonly string $site,
    ) {}

    /**
     * Build the stable reference for an AoE section, shared by the document, the
     * provider's `provide()`/`find()`, and the reindex listener.
     */
    public static function reference(string $aoeId, string $section): string
    {
        return self::REFERENCE_PREFIX.'::'.$aoeId.'::'.$section;
    }

    /**
     * The augmented data the results template reads (`{{ url }}`, `{{ title }}`) and the
     * index extracts searchable field values from. Only the fields a consumer actually reads
     * are exposed — the entry id and section token live in the reference, not here.
     *
     * @return array<string, string>
     */
    public function augmentedArrayData(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
        ];
    }

    /**
     * The reference the driver stores and groups by (prefix → provider).
     */
    public function getSearchReference(): string
    {
        return self::reference($this->aoeId, $this->section);
    }

    /**
     * The value indexed for a given search field. Only `title` carries text; any other
     * configured field (e.g. the default index's `page_builder`) is absent here.
     */
    public function getSearchValue(string $field): mixed
    {
        return $this->augmentedArrayData()[$field] ?? null;
    }

    /**
     * Wrap this document in a search {@see Result}, typed by the reference prefix so the
     * results template can dispatch it to the Area-of-Expertise section card.
     */
    public function toSearchResult(): ResultContract
    {
        return new Result($this, self::REFERENCE_PREFIX);
    }

    /**
     * Resolve the values the `{{ search:results }}` tag filters on. A subpage represents a
     * published AoE section, so it is always `published` (matching Statamic's `PlainResult`
     * default); `site` is consulted only on multi-site installs. The parent Area of
     * Expertise's id lets a query drop the subpages of a private one (UAMS-Web/uams-statamic-archive#2634).
     */
    public function getQueryableValue(string $field): mixed
    {
        return match ($field) {
            'status' => 'published',
            'site' => $this->site,
            self::AREA_OF_EXPERTISE_ID_FIELD => $this->aoeId,
            default => $this->augmentedArrayData()[$field] ?? null,
        };
    }
}
