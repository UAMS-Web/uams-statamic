<?php

declare(strict_types=1);

namespace App\Support\Statamic;

use App\Services\Posts\NewsSpringboardService;
use App\Tags\NewsHome;
use App\Tags\NewsSpringboard;
use Illuminate\Support\Collection;
use Statamic\Fields\Value;
use Statamic\Fields\Values;

/**
 * Normalizes a news-magazine `sections` grid — however Antlers augments it — to an
 * ordered list of plain section configs, shared by the {@see NewsSpringboard}
 * (UAMS-Web/uams-statamic-archive#1158) and {@see NewsHome} (UAMS-Web/uams-statamic-archive#1612) tags so the Value/Values row
 * unwrapping lives in one place.
 *
 * Each grid row is a category slot (heading + a `post_categories` category + item
 * count); the output rows are consumed by {@see NewsSpringboardService}.
 */
final class NewsSectionGrid
{
    /**
     * Normalize the raw `sections` grid value to an ordered list of section configs.
     *
     * @return array<int, array{heading: ?string, category: array<int, string>, count: int}>
     */
    public static function normalize(mixed $raw): array
    {
        // Unwrap a Statamic field value, then a collection, to the row list.
        if ($raw instanceof Value) {
            $raw = $raw->value();
        }

        if ($raw instanceof Collection) {
            $raw = $raw->all();
        }

        if (! \is_array($raw)) {
            return [];
        }

        $sections = [];

        foreach ($raw as $row) {
            $sections[] = [
                'heading' => self::scalarString(self::rowField($row, 'section_heading')),
                'category' => AugmentedEntryIds::fromValue(self::rowField($row, 'category')),
                'count' => self::positiveInt(self::rowField($row, 'count')) ?: NewsSpringboardService::DEFAULT_COUNT,
            ];
        }

        unset($row);

        return $sections;
    }

    /**
     * Read a field from a grid row (a `Values` set or a plain array).
     *
     * A `Values` set is read via {@see Values::toArray()} rather than array access:
     * its `ArrayAccess` returns null for an entries subfield (whose augmented value
     * is a query builder, not a scalar), whereas `toArray()` preserves every
     * subfield as its {@see Value} wrapper — which the callers below unwrap uniformly.
     */
    private static function rowField(mixed $row, string $key): mixed
    {
        if ($row instanceof Values) {
            return $row->toArray()[$key] ?? null;
        }

        if (\is_array($row)) {
            return $row[$key] ?? null;
        }

        return null;
    }

    /**
     * Coerce a (possibly Value-wrapped) field to a non-empty string, or null.
     */
    private static function scalarString(mixed $value): ?string
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        return \is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * Coerce a (possibly Value-wrapped) field to a non-negative integer (0 when
     * absent/non-numeric).
     */
    private static function positiveInt(mixed $value): int
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        return \is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
