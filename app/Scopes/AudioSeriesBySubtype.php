<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Support\AudioSeries;
use Illuminate\Support\Arr;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\TermQueryBuilder;

/**
 * Term query scope selecting `audio_series` terms whose multi-select `subtype`
 * contains a requested value (UAMS-Web/uams-statamic-archive#1611) — e.g. "list the podcast series."
 *
 * Mirrors the `entries_by_schema_type_*` precedent: a stored multi-value field is
 * matched with `whereJsonContains`, and the requested value is encoded in the
 * scope alias so a blueprint field or tag can pin it without a param —
 * `audio_series_subtype_podcast`, `audio_series_subtype_radio`. A bare
 * `audio_series_subtype:<value>` tag param is also honored for ad-hoc listings.
 */
class AudioSeriesBySubtype extends Scope
{
    /**
     * Unique scope handle; per-subtype aliases cover the encoded picks.
     *
     * @var string
     */
    protected static $handle = 'audio_series_subtype';

    /**
     * Prefix joining the scope handle to an encoded `subtype` option.
     */
    private const string ALIAS_PREFIX = 'audio_series_subtype_';

    /**
     * The `subtype` options that back an encoded alias.
     *
     * @var array<int, string>
     */
    private const array ALIASED_SUBTYPES = [
        AudioSeries::SUBTYPE_PODCAST,
        AudioSeries::SUBTYPE_RADIO,
    ];

    /**
     * Register one alias per `subtype` option (`audio_series_subtype_podcast`, …).
     *
     * @return array<int, string> Registered alias handles.
     */
    public static function aliases(): array
    {
        return array_map(
            static fn (string $subtype): string => self::ALIAS_PREFIX.$subtype,
            self::ALIASED_SUBTYPES
        );
    }

    /**
     * Apply the scope to the term query.
     *
     * @param  mixed  $query  Term index query (see {@see applyWithTypedTermQuery}).
     * @param  mixed  $values  `Scope::apply` context; reads the encoded alias from
     *                         `queryScopes` or a bare `audio_series_subtype` param.
     */
    public function apply(
        $query,
        $values
    ): void {
        // Only a term index Builder this scope can filter
        if (! $query instanceof Builder) {
            return;
        }

        // Only a map of request-style values
        if (! \is_array($values)) {
            return;
        }

        /** @var array<string, mixed> $values */
        $this->applyWithTypedTermQuery(
            $query,
            $values
        );
    }

    /**
     * Apply the scope with a known term query and param array.
     *
     * @param  Builder|TermQueryBuilder  $query  Index query for taxonomy terms.
     * @param  array<string, mixed>  $values  `Scope::apply` context.
     */
    private function applyWithTypedTermQuery(
        Builder|TermQueryBuilder $query,
        array $values
    ): void {
        // Resolve the requested subtype from an encoded alias or a bare param
        $subtype = $this->subtypeFromValues($values);

        // Nothing to filter when no known subtype was requested
        if ($subtype === null) {
            return;
        }

        // Constrain to the audio_series taxonomy so a shared `subtype` handle on
        // any future taxonomy cannot leak into the result set
        $query->where(
            'taxonomy',
            AudioSeries::TAXONOMY
        );

        // Match terms whose stored multi-select `subtype` array contains the value
        $query->whereJsonContains(
            AudioSeries::SUBTYPE_FIELD,
            $subtype
        );

        // Unset local variables to free up memory
        unset(
            $subtype
        );
    }

    /**
     * Read the requested `subtype` from an encoded `queryScopes` alias, or a bare
     * `audio_series_subtype` tag param.
     *
     * @param  array<string, mixed>  $values  `Scope::apply` context.
     * @return string|null A known `subtype` option, or null when none is requested.
     */
    private function subtypeFromValues(array $values): ?string
    {
        // Prefer an encoded alias in queryScopes (blueprint field config path)
        foreach (Arr::wrap($values['queryScopes'] ?? []) as $handle) {
            if (! \is_string($handle)) {
                continue;
            }

            if (! str_starts_with($handle, self::ALIAS_PREFIX)) {
                continue;
            }

            $subtype = substr($handle, \strlen(self::ALIAS_PREFIX));

            // Its values are non-numeric strings, which loose and strict in_array() compare alike.
            // @pest-mutate-ignore: TrueToFalse
            if (\in_array($subtype, self::ALIASED_SUBTYPES, true)) {
                return $subtype;
            }
        }

        // Fall back to a bare tag param (`audio_series_subtype:podcast`)
        $param = $values[self::$handle] ?? null;

        // Its values are non-numeric strings, which loose and strict in_array() compare alike.
        // @pest-mutate-ignore: TrueToFalse
        if (\is_string($param) && \in_array($param, self::ALIASED_SUBTYPES, true)) {
            return $param;
        }

        // No known subtype requested
        return null;
    }
}
