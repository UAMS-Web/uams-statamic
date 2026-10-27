<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Asserts WHERE the identifier-uniqueness rules are wired, and just as
 * deliberately where they are not (UAMS-Web/uams-statamic-archive#2152).
 *
 * The behavior tests prove the rules work. They cannot prove the rules are
 * attached to anything -- a correct rule wired to no field passes every one of
 * them, which is close to the shape of the defect this ticket exists to fix.
 * These read the blueprints as data.
 *
 * Two of the assertions are NEGATIVE and are the load-bearing half:
 *
 *   - The MyChart scheduling widget's `dep` field must never carry a uniqueness
 *     rule. It is validated `dep_in_declared_list:epic_dep_ids`, which REQUIRES
 *     it to repeat the location's declared DEP IDs, so a uniqueness rule there
 *     would reject correct data on every location using the widget. It shares a
 *     fieldset with the declared list, so the thing that keeps it safe is that
 *     the rule is wired per blueprint field rather than in the fieldset -- an
 *     easy and invisible thing to get wrong later.
 *   - `clinical_providers` must stay unwired. It holds no entries, is unrouted,
 *     and #803 retires it into `people`.
 *
 * @command  php artisan test --compact tests/Feature/Rules/UniqueIdentifierWiringTest.php
 */
uses(TestCase::class);

/**
 * Parse a blueprint or fieldset under `resources/`.
 *
 * @return array<mixed> The parsed document
 */
function identifierYaml(string $relative): array
{
    $path = base_path($relative);

    expect(file_exists($path))->toBeTrue("missing {$relative}");

    $parsed = Yaml::parseFile($path);

    return \is_array($parsed) ? $parsed : [];
}

/**
 * Collect every `validate` rule string reachable under a parsed document,
 * keyed by the nearest enclosing field handle.
 *
 * @param  array<mixed>  $node  Parsed YAML
 * @param  string  $handle  Handle inherited from the enclosing field
 * @return array<int, array{handle: string, rule: string}>
 */
function identifierRulesIn(array $node, string $handle = ''): array
{
    $found = [];

    $current = \is_string($node['handle'] ?? null) ? $node['handle'] : $handle;

    foreach ($node as $key => $value) {
        if ($key === 'validate' && \is_array($value)) {
            foreach ($value as $rule) {
                if (\is_string($rule)) {
                    $found[] = ['handle' => $current, 'rule' => $rule];
                }
            }

            continue;
        }

        if (\is_array($value)) {
            $found = array_merge($found, identifierRulesIn($value, $current));
        }
    }

    return $found;
}

/**
 * Every rule string attached to one field handle in a document.
 *
 * @return array<int, string>
 */
function identifierRulesFor(string $relative, string $handle): array
{
    $rules = [];

    foreach (identifierRulesIn(identifierYaml($relative)) as $row) {
        if ($row['handle'] === $handle) {
            $rules[] = $row['rule'];
        }
    }

    return $rules;
}

const SEMANTICS = 'compared ignoring capitalization and surrounding spaces';

const PERSON_BLUEPRINT = 'resources/blueprints/collections/people/person.yaml';
const LOCATION_BLUEPRINT = 'resources/blueprints/collections/clinical_locations/location.yaml';
const PROVIDER_BLUEPRINT = 'resources/blueprints/collections/clinical_providers/provider.yaml';

it('reads rules out of a blueprint at all', function (): void {
    // Positive control for the walker: without this, every "no rule found"
    // assertion below would pass just as well on a walker that finds nothing.
    $rules = identifierRulesFor(LOCATION_BLUEPRINT, 'dep');

    expect($rules)->toContain('dep_in_declared_list:epic_dep_ids');
});

it('wires all four Workday fields to one pooled namespace', function (): void {
    $pool = 'unique_identifier:people,{id},uams_workday_id.workday_universal_id,uams_workday_id.workday_employee_id,uams_workday_id.workday_contingent_worker_id,uams_workday_id.workday_student_id';

    foreach ([
        'workday_universal_id',
        'workday_employee_id',
        'workday_contingent_worker_id',
        'workday_student_id',
    ] as $handle) {
        expect(identifierRulesFor(PERSON_BLUEPRINT, $handle))->toContain($pool);
    }
});

it('wires the single-value people identifiers to their own collection', function (): void {
    expect(identifierRulesFor(PERSON_BLUEPRINT, 'npi'))
        ->toContain('unique_identifier:people,{id},npi');

    expect(identifierRulesFor(PERSON_BLUEPRINT, 'epic_ser_id'))
        ->toContain('unique_identifier:people,{id},epic_ser_id');
});

it('keeps the pre-existing rules on the fields it extends', function (): void {
    // A `config.validate` REPLACES the fieldset's own list rather than merging,
    // so adding a rule is exactly where a sibling rule gets dropped silently.
    expect(identifierRulesFor(PERSON_BLUEPRINT, 'workday_employee_id'))
        ->toContain('required_if:uams_worker_subtype,employee');

    expect(identifierRulesFor(PERSON_BLUEPRINT, 'workday_contingent_worker_id'))
        ->toContain('required_if:uams_worker_subtype,contingent_worker');

    expect(identifierRulesFor(PERSON_BLUEPRINT, 'epic_ser_id'))
        ->toContain('regex:/^\d+$/D');
});

it('wires both location list fields with the right row key', function (): void {
    expect(identifierRulesFor(LOCATION_BLUEPRINT, 'epic_dep_ids'))
        ->toContain('unique_identifier_list:clinical_locations,{id},dep');

    expect(identifierRulesFor(LOCATION_BLUEPRINT, 'npi'))
        ->toContain('unique_identifier_list:clinical_locations,{id},item');
});

it('leaves the scheduling widget dep field free of any uniqueness rule', function (): void {
    // The regression guard. This field MUST repeat the declared DEP IDs.
    $rules = identifierRulesFor(LOCATION_BLUEPRINT, 'dep');

    expect($rules)->toContain('dep_in_declared_list:epic_dep_ids');

    foreach ($rules as $rule) {
        expect(str_starts_with($rule, 'unique_identifier'))->toBeFalse(
            'the scheduling widget dep field must not carry a uniqueness rule'
        );
    }
});

it('keeps the uniqueness rule out of the shared fieldset, which is what protects the widget', function (): void {
    // Both the declared list and the widget draw on this one fieldset, so a
    // rule placed here would reach the widget too.
    $rules = identifierRulesIn(identifierYaml('resources/fieldsets/uamsweb_common_fad.yaml'));

    foreach ($rules as $row) {
        expect(str_starts_with($row['rule'], 'unique_identifier'))->toBeFalse();
    }
});

it('wires nothing on clinical_providers', function (): void {
    foreach (identifierRulesIn(identifierYaml(PROVIDER_BLUEPRINT)) as $row) {
        expect(str_starts_with($row['rule'], 'unique_identifier'))->toBeFalse(
            'clinical_providers is unrouted, empty and retiring, and must stay unwired'
        );
    }
});

it('states the comparison semantics where this repository is free to say it', function (): void {
    // The four Workday instructions carry it too since UAMS-Web/uams-statamic-archive#2469. Their agreed text is
    // shared with two sibling repositories (UAMSWP-People-Showcase#56,
    // UAMSWP-Find-a-Doc#819) and pinned character for character by
    // `WorkdayIdentifierFieldsTest`; UAMS-Web/uams-statamic-archive#2469's decision lets this repository append
    // a sentence of its own after it, and that sentence is where the semantics are.
    $fieldset = identifierYaml('resources/fieldsets/uamsweb_common_identifiers.yaml');

    /** @var array<string, string> $seen */
    $seen = [];

    $fields = $fieldset['fields'] ?? [];

    expect($fields)->toBeArray();

    /** @var array<mixed> $fields */
    foreach ($fields as $row) {
        if (! \is_array($row)) {
            continue;
        }

        $handle = $row['handle'] ?? null;

        if (! \is_string($handle)) {
            continue;
        }

        $field = $row['field'] ?? null;
        $instructions = \is_array($field) ? ($field['instructions'] ?? '') : '';
        $seen[$handle] = \is_string($instructions) ? $instructions : '';
    }

    foreach (['npi', 'epic_ser_id'] as $handle) {
        expect($seen)->toHaveKey($handle);
        expect($seen[$handle] ?? '')->toContain(SEMANTICS);
    }

    // The Workday fields state it in the sentence appended after the agreed text
    foreach ([
        'workday_universal_id',
        'workday_employee_id',
        'workday_contingent_worker_id',
        'workday_student_id',
    ] as $handle) {
        expect($seen)->toHaveKey($handle);
        expect($seen[$handle] ?? '')->toContain(SEMANTICS);
    }

    // And the two location list fields say it where an editor meets them
    $location = identifierYaml(LOCATION_BLUEPRINT);

    expect(substr_count(json_encode($location, JSON_THROW_ON_ERROR), SEMANTICS))->toBe(2);
});

it('carries the comparison semantics in every failure message', function (): void {
    // Asserted rather than assumed: an editor who skipped the instructions must
    // still read it at the moment the save is refused.
    $loaded = require base_path('lang/vendor/statamic/en/validation.php');

    expect($loaded)->toBeArray();

    /** @var array<string, mixed> $messages */
    $messages = \is_array($loaded) ? $loaded : [];

    foreach (['unique_identifier', 'unique_identifier_list'] as $key) {
        expect($messages)->toHaveKey($key);

        $group = $messages[$key] ?? null;

        expect($group)->toBeArray();

        /** @var array<string, mixed> $group */
        expect(array_keys($group))->toContain('collision', 'within_entry');

        foreach ($group as $text) {
            expect($text)->toBeString();
            expect(\is_string($text) ? $text : '')->toContain(SEMANTICS);
        }
    }
});
