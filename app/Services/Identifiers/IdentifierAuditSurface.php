<?php

declare(strict_types=1);

namespace App\Services\Identifiers;

use App\Support\Collections\PeopleCollection;
use Symfony\Component\Yaml\Yaml;

/**
 * Derives the set of identifier fields to audit from the blueprints themselves
 * (UAMS-Web/uams-statamic-archive#2395).
 *
 * The audit and the save-time rule (UAMS-Web/uams-statamic-archive#2152) must not disagree about what is
 * covered, and the cheapest way to guarantee that is to stop maintaining a
 * second list: this reads the wired `unique_identifier` and
 * `unique_identifier_list` rules out of the blueprints and audits exactly what
 * they enforce.
 *
 * Two of the ticket's requirements then hold by CONSTRUCTION rather than by a
 * skip-list that has to be kept in step:
 *
 *   - The MyChart scheduling widget's `dep` field is not scanned, because it
 *     carries `dep_in_declared_list` and no uniqueness rule -- it is REQUIRED to
 *     repeat the location's declared DEP IDs.
 *   - `clinical_providers` is not scanned, because nothing there is wired.
 *
 * The cost, stated because it is a real trade: removing a rule's wiring silently removes
 * that field from the audit too. That is the intended direction -- the audit
 * follows the policy rather than asserting a policy of its own -- but it means
 * a blueprint edit is the only place this surface can be changed.
 */
class IdentifierAuditSurface
{
    /**
     * Blueprints to read, as collection handle => path under `resources/`.
     *
     * Only collections that wire a rule appear here; a collection with no
     * uniqueness rules contributes nothing and needs no entry.
     *
     * @return array<string, string>
     */
    private static function blueprints(): array
    {
        return [
            PeopleCollection::handle() => 'resources/blueprints/collections/people/person.yaml',
            'clinical_locations' => 'resources/blueprints/collections/clinical_locations/location.yaml',
        ];
    }

    /**
     * Rule name for a single-value identifier, pooled across one or more paths.
     *
     * @var string
     */
    private const string RULE_SINGLE = 'unique_identifier';

    /**
     * Rule name for a replicator list of identifiers.
     *
     * @var string
     */
    private const string RULE_LIST = 'unique_identifier_list';

    /**
     * Every audited field, derived from the wired rules.
     *
     * A pooled group appears once, keyed by its collection and its ordered path
     * list, however many fields carry the same rule string -- the four Workday
     * fields share one rule and are one uniqueness namespace, not four.
     *
     * @return list<array{collection: string, paths: list<string>, rowKey: string|null, label: string}>
     */
    public function all(): array
    {
        // Accumulate the audited groups, de-duplicated by their rule identity
        $groups = [];

        // Walk each blueprint that might wire a rule
        foreach (self::blueprints() as $collection => $relative) {
            // Read every validate rule in the document
            foreach ($this->rulesIn($relative) as $rule) {
                // Parse the rule into a group, or skip a rule of another kind
                $group = $this->parse($collection, $rule);

                // Rules that are not uniqueness rules contribute nothing
                if ($group === null) {
                    // Move to the next rule
                    continue;
                }

                // A pooled rule appears once per field it is wired to; keep one
                $groups[$group['label']] = $group;
            }
        }

        return array_values($groups);
    }

    /**
     * Parse one rule string into an audited group.
     *
     * @param  string  $collection  Collection handle the blueprint belongs to
     * @param  string  $rule  A `validate` entry, e.g. `unique_identifier:people,{id},npi`
     * @return array{collection: string, paths: list<string>, rowKey: string|null, label: string}|null
     */
    private function parse(string $collection, string $rule): ?array
    {
        // Split the rule name from its parameters
        $parts = explode(':', $rule, 2);

        // A rule with no parameters is not one of ours
        if (\count($parts) !== 2) {
            // Report no group
            return null;
        }

        [$name, $parameters] = $parts;

        // Only the two uniqueness rules describe an audited field
        if ($name !== self::RULE_SINGLE && $name !== self::RULE_LIST) {
            // Report no group
            return null;
        }

        // Parameters are collection, the entry-id placeholder, then the rest
        $arguments = array_map(trim(...), explode(',', $parameters));

        // A usable rule names at least a collection and one path or row key
        if (\count($arguments) < 3) {
            // Report no group
            return null;
        }

        // The rule's own collection argument wins over the file it was found in
        $ruleCollection = $arguments[0] !== '' ? $arguments[0] : $collection;

        // Everything past the id placeholder is the pool, or the row key
        $tail = array_values(array_filter(\array_slice($arguments, 2), static fn (string $a): bool => $a !== ''));

        // A rule naming nothing to read describes no field
        if ($tail === []) {
            // Report no group
            return null;
        }

        // A list rule names one row subfield; a single rule names a pool of paths
        $isList = $name === self::RULE_LIST;

        return [
            'collection' => $ruleCollection,
            'paths' => $isList ? [] : $tail,
            'rowKey' => $isList ? $tail[0] : null,
            'label' => $ruleCollection.' '.($isList ? 'list:'.$tail[0] : implode(' + ', $tail)),
        ];
    }

    /**
     * Every `validate` rule string in a blueprint, with the handle it sits on.
     *
     * A list rule is attached to the replicator field itself, so the handle is
     * needed to know which field to read on an entry; a single rule names its
     * paths outright and the handle is redundant there.
     *
     * @param  string  $relative  Path under the application root
     * @return list<array{handle: string, rule: string}>
     */
    public function rulesWithHandles(string $relative): array
    {
        // Parse the blueprint document
        $parsed = Yaml::parseFile(base_path($relative));

        // A document that is not a map contributes nothing
        if (! \is_array($parsed)) {
            // Return no rules
            return [];
        }

        // Walk the document collecting rules against their nearest handle
        return $this->walk($parsed, '');
    }

    /**
     * Every `validate` rule string in a blueprint.
     *
     * @param  string  $relative  Path under the application root
     * @return list<string>
     */
    private function rulesIn(string $relative): array
    {
        // Reduce the handle-carrying rows to their rule strings
        return array_map(
            static fn (array $row): string => $row['rule'],
            $this->rulesWithHandles($relative)
        );
    }

    /**
     * Recursively collect `validate` entries, carrying the enclosing handle.
     *
     * @param  array<mixed>  $node  A parsed fragment
     * @param  string  $handle  Handle inherited from the enclosing field
     * @return list<array{handle: string, rule: string}>
     */
    private function walk(array $node, string $handle): array
    {
        // Track the handle this fragment sits under
        $current = \is_string($node['handle'] ?? null) ? $node['handle'] : $handle;

        // Accumulate the rules found beneath here
        $found = [];

        // Walk every key in the fragment
        foreach ($node as $key => $value) {
            // A validate list holds the rule strings
            if ($key === 'validate' && \is_array($value)) {
                // Keep each rule string against the enclosing handle
                foreach ($value as $rule) {
                    // Only a string is a rule
                    if (\is_string($rule)) {
                        // Record the pair
                        $found[] = ['handle' => $current, 'rule' => $rule];
                    }
                }

                // Nothing nested under validate needs walking
                continue;
            }

            // Descend into any nested structure
            if (\is_array($value)) {
                // Merge what the subtree contributes
                $found = array_merge($found, $this->walk($value, $current));
            }
        }

        return $found;
    }
}
