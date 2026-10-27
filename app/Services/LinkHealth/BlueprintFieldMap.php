<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

use Statamic\Fields\Field;
use Statamic\Fields\Fields;

/**
 * Normalize a Statamic fields config into handle => config, at any nesting depth.
 *
 * Blueprints, fieldsets, grid and group fields, and replicator/Bard sets all express their fields
 * as the same list-of-pairs shape, either inline or as a fieldset import. Reducing them to one
 * keyed array is what lets the extractor be fieldtype-driven rather than scraping every string.
 */
final class BlueprintFieldMap
{
    /**
     * Reduce a fields config to handle => field config.
     *
     * @param  mixed  $fieldsConfig  The `fields` value from a blueprint, fieldset, set, or nested
     *                               fieldtype. Anything that is not the expected shape yields an
     *                               empty map rather than throwing — a malformed corner of one
     *                               blueprint must not abort the whole inventory.
     * @return array<string, array<string, mixed>> Field handle => that field's config array.
     */
    public static function normalize(mixed $fieldsConfig): array
    {
        // Removing this early return cannot change the RESULT: `foreach` over a non-array
        // iterates zero times, so the method still returns an empty map for every non-array
        // input. What the guard prevents is the PHP warning, which no return value can show —
        // so no assertion can kill the mutant, and the guard is not dead code.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! \is_array($fieldsConfig)) {
            return [];
        }

        $map = [];

        foreach ($fieldsConfig as $key => $entry) {
            if (! \is_array($entry)) {
                continue;
            }

            // The list form: `- handle: foo` with the config under `field`.
            if (isset($entry['handle'])) {
                $handle = $entry['handle'];

                if (! \is_scalar($handle)) {
                    continue;
                }

                $config = $entry['field'] ?? [];
                /** @var array<string, mixed> $normalized */
                $normalized = \is_array($config) ? $config : [];
                $map[(string) $handle] = $normalized;

                continue;
            }

            // The keyed form: the array key is the handle and the value is the config.
            if (\is_string($key)) {
                /** @var array<string, mixed> $entry */
                $map[$key] = $entry;
            }
        }

        return $map;
    }

    /**
     * Reduce a blueprint's fields to handle => config, flattening tabs and sections.
     *
     * Blueprints in this repository nest their fields under `tabs` -> `sections` -> `fields`, and
     * may import fieldsets rather than declaring fields inline. Reading `contents()['fields']`
     * therefore returns nothing for a real blueprint. Statamic's own Fields collection resolves
     * all of that, so the traversal asks it rather than re-implementing the layout.
     *
     * @param  Fields  $fields  The blueprint's resolved fields.
     * @return array<string, array<string, mixed>> Field handle => that field's config array.
     */
    public static function fromFields(Fields $fields): array
    {
        $map = [];

        foreach ($fields->all() as $handle => $field) {
            if (! \is_string($handle)) {
                continue;
            }

            // larastan requires this narrowing at level 10: `$fields->all()` is typed loosely
            // enough that the `Field` methods below are unresolvable without it. The mutant
            // survives because the guard exists to satisfy the analyser rather than to reject a
            // value this code path can produce — a tension between two gates, not a test gap.
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $field instanceof Field) {
                continue;
            }

            /** @var array<string, mixed> $config */
            $config = $field->config();
            $map[$handle] = $config;
        }

        return $map;
    }

    /**
     * Reduce a replicator or Bard `sets` config to set handle => that set's field map.
     *
     * Sets are grouped a level above the sets themselves (the default group is `main`), and this
     * flattens the grouping away — set handles are unique across groups in practice, and the
     * stored data records only the set handle, so the group is not recoverable from the value
     * anyway.
     *
     * @param  mixed  $setsConfig  The `sets` value from a replicator or Bard field config.
     * @return array<string, array<string, array<string, mixed>>> Set handle => field map.
     */
    public static function sets(mixed $setsConfig): array
    {
        // As in normalize(): foreach over a non-array iterates zero times, so the result is
        // the same empty list; the guard prevents only the PHP warning, which no return value shows.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! \is_array($setsConfig)) {
            return [];
        }

        $sets = [];

        foreach ($setsConfig as $groupHandle => $group) {
            if (! \is_array($group)) {
                continue;
            }

            // A group wraps its sets under `sets`; a set config sits directly under its handle
            // when the blueprint predates grouping.
            $inner = \is_array($group['sets'] ?? null) ? $group['sets'] : [$groupHandle => $group];

            foreach ($inner as $setHandle => $setConfig) {
                if (! \is_array($setConfig)) {
                    continue;
                }

                if (! \is_string($setHandle)) {
                    continue;
                }

                $sets[$setHandle] = self::normalize($setConfig['fields'] ?? []);
            }
        }

        return $sets;
    }
}
