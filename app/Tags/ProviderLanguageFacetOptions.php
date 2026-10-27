<?php

declare(strict_types=1);

namespace App\Tags;

use App\Enums\LanguageBCP47;
use App\Support\Collections\PeopleCollection;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Tags\Tags;

/**
 * Antlers tag: the distinct BCP-47 languages spoken across the `people` collection,
 * as `{ value, label }` rows for the Find-a-Doc provider directory's Language facet
 * (UAMS-Web/uams-statamic-archive#570).
 *
 * The directory's `language` facet needs a `<select>` of code → display-name options,
 * but the `language` fieldtype is an ~8,000-case BCP-47 `select` (no taxonomy to loop)
 * — so rendering the whole registry and pruning it client-side is untenable. This tag
 * instead loops the collection once and returns only the codes providers actually
 * carry, resolved to their display names via `LanguageBCP47::description()`.
 *
 * Scope is the whole `people` collection, not just clinical-provider-eligible /
 * searchable entries: the `fadProviderDirectory` engine's `pruneOptions()` removes any
 * option no rendered (eligible) card carries, so this only has to be a superset. That
 * keeps the tag from re-deriving the computed eligibility rules the listing owns.
 *
 * Usage:
 *   {{ provider_language_facet_options }}
 *     <option value="{{ value }}">{{ label | sanitize }}</option>
 *   {{ /provider_language_facet_options }}
 */
class ProviderLanguageFacetOptions extends Tags
{
    /**
     * Registered Antlers handle `{{ provider_language_facet_options }}`.
     *
     * @var string
     */
    protected static $handle = 'provider_language_facet_options';

    /**
     * The distinct provider languages as select options, sorted by display label.
     *
     * @return list<array{value: string, label: string}>
     */
    public function index(): array
    {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();

        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', PeopleCollection::handle());

        /** @var Collection<int, EntryModel> $entries */
        $entries = $query->get();

        // Collect every stored language code across the collection, de-duplicated.
        /** @var list<string> $codes */
        $codes = $entries
            ->flatMap(function (EntryModel $entry): array {
                $stored = $entry->get('language');

                return \is_array($stored) ? array_values($stored) : [];
            })
            ->map(static fn (mixed $code): string => \is_string($code) ? trim($code) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->optionsFromCodes($codes);
    }

    /**
     * Resolve raw BCP-47 codes to `{ value, label }` option rows, sorted by label.
     *
     * @param  list<string>  $codes
     * @return list<array{value: string, label: string}>
     */
    private function optionsFromCodes(array $codes): array
    {
        // Map each code to its display name, falling back to the code itself.
        $options = array_map(
            static function (string $code): array {
                $language = LanguageBCP47::tryFrom($code);

                return [
                    'value' => $code,
                    'label' => $language?->description() ?? $code,
                ];
            },
            $codes,
        );

        // Order alphabetically by the reader-facing label.
        usort(
            $options,
            static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']),
        );

        return $options;
    }
}
