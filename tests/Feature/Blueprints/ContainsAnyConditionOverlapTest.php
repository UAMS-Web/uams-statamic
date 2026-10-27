<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Fields\Fieldtype;
use Tests\Support\ContainsAnyOverlapAudit;
use Tests\TestCase;
use UAMSWeb\FormSettings\CpForms\ConfirmationSettingsFieldBuilder;

uses(TestCase::class);

/**
 * Guards every `contains_any` field condition against a candidate that is part of an option the
 * rule does not list (UAMS-Web/uams-statamic-archive#2891).
 *
 * Against a single string, `contains_any` is a substring test, so `contains_any date` on a field
 * whose options include `datetime-local` also shows the field for `datetime-local`. Nothing fails
 * when that happens: a field simply appears for an option its author did not name. The audit
 * compares each rule with the target field's FULL option list, read from the blueprint, the
 * fieldset, the fieldtype's config fields, or the fieldtype itself for a path into a
 * `schema_type` or `ctdl_class` value. A rule that needs membership should use `includes_any`.
 *
 * The corpus covers `resources/blueprints`, `resources/fieldsets`, the config fields of every
 * fieldtype in `app/Fieldtypes`, and the confirmation fields built for CP forms. Blueprints under
 * `content/` are not read: none carries a `contains_any` rule.
 *
 * @command php artisan test --compact tests/Feature/Blueprints/ContainsAnyConditionOverlapTest.php
 */
function containsAnyOverlapAudit(): ContainsAnyOverlapAudit
{
    return new ContainsAnyOverlapAudit(resource_path('fieldsets'));
}

/**
 * A blueprint's contents holding the given fields in one section.
 *
 * @param  list<array<mixed>>  $fields
 * @return array<string, mixed>
 */
function containsAnyBlueprint(array $fields): array
{
    return ['tabs' => ['main' => ['sections' => [['fields' => $fields]]]]];
}

/**
 * The audit over the whole corpus. Built once per process: it only reads files and fieldtype
 * definitions, so nothing a test does can change it.
 */
function containsAnyCorpusAudit(): ContainsAnyOverlapAudit
{
    static $audit = null;

    if ($audit instanceof ContainsAnyOverlapAudit) {
        return $audit;
    }

    return $audit = containsAnyOverlapAudit()
        ->auditYamlDirectories([
            'resources/blueprints' => resource_path('blueprints'),
            'resources/fieldsets' => resource_path('fieldsets'),
        ])
        ->auditFieldtypeClasses(app_path('Fieldtypes'), 'App\\Fieldtypes')
        ->auditItems(array_values((new ConfirmationSettingsFieldBuilder)->build('confirmation_settings_default')), ConfirmationSettingsFieldBuilder::class);
}

/**
 * How many findings each source produced.
 *
 * @return array<string, int>
 */
function containsAnyFindingsBySource(): array
{
    $counts = [];

    foreach (containsAnyCorpusAudit()->findings() as $finding) {
        $counts[$finding['source']] = ($counts[$finding['source']] ?? 0) + 1;
    }

    return $counts;
}

it('finds no contains_any candidate that is part of an option its rule does not list', function (): void {
    expect(containsAnyCorpusAudit()->overlaps())->toBe([]);
});

it('reads every contains_any rule written in blueprint and fieldset YAML', function (): void {
    $found = containsAnyFindingsBySource();
    $missed = [];
    $written = 0;

    foreach (['resources/blueprints', 'resources/fieldsets'] as $directory) {
        foreach (File::allFiles(base_path($directory)) as $file) {
            if ($file->getExtension() !== 'yaml') {
                continue;
            }

            $source = $directory.'/'.str_replace('\\', '/', $file->getRelativePathname());
            $count = preg_match_all('/contains_any/', $file->getContents());
            $written += $count;

            // At least as many: a fieldset's rule on a referenced field is also read in each
            // blueprint that references it.
            if (($found[$source] ?? 0) < $count) {
                $missed[$source] = [$count, $found[$source] ?? 0];
            }
        }
    }

    expect($written)->toBeGreaterThan(0);
    expect($missed)->toBe([]);
});

it('reads every contains_any rule a fieldtype emits in its config fields', function (): void {
    $found = containsAnyFindingsBySource();
    $mismatched = [];
    $emitted = 0;

    foreach (glob(app_path('Fieldtypes').'/*.php') ?: [] as $path) {
        $class = 'App\\Fieldtypes\\'.basename($path, '.php');

        if (! is_subclass_of($class, Fieldtype::class) || (new ReflectionClass($class))->isAbstract()) {
            continue;
        }

        $fieldtype = app($class);
        $count = $fieldtype instanceof Fieldtype
            ? substr_count((string) json_encode($fieldtype->configFields()->items()), '"contains_any ')
            : 0;
        $emitted += $count;

        if (($found[$class] ?? 0) !== $count) {
            $mismatched[$class] = [$count, $found[$class] ?? 0];
        }
    }

    expect($emitted)->toBeGreaterThan(0);
    expect($mismatched)->toBe([]);
});

it('resolves every rule to a target whose options it can read', function (): void {
    $unmodeled = [];

    foreach (containsAnyCorpusAudit()->findings() as $finding) {
        if (in_array($finding['status'], ['unresolved', 'no-static-options'], true)) {
            $unmodeled[] = $finding['source'].' '.$finding['field'].' -> '.$finding['target'].' ('.$finding['status'].')';
        }
    }

    // An `entries` field compared with two entry UUIDs, which cannot be part of one another.
    expect($unmodeled)->toBe([
        'resources/blueprints/collections/clinical_locations/location.yaml va_substation_id -> brand_organization (no-static-options)',
    ]);
});

it('reports a planted overlap on each kind of target', function (array $target, string $path, string $candidate, string $overlap): void {
    $audit = containsAnyOverlapAudit()->auditContents(containsAnyBlueprint([
        $target,
        ['handle' => 'shown', 'field' => ['type' => 'text', 'if' => [$path => 'contains_any '.$candidate]]],
    ]), 'planted');

    expect($audit->overlaps())->toHaveCount(1);
    expect($audit->overlaps()[0]['overlaps'])->toContain($overlap);
})->with([
    'select options' => [
        ['handle' => 'kind', 'field' => ['type' => 'select', 'options' => ['date' => 'Date', 'datetime-local' => 'Date and time']]],
        'kind',
        'date',
        'date -> datetime-local',
    ],
    'key and value options' => [
        ['handle' => 'kind', 'field' => ['type' => 'button_group', 'options' => [['key' => 'title', 'value' => 'Title'], ['key' => 'logo_title', 'value' => 'Logo title']]]],
        'kind',
        'title',
        'title -> logo_title',
    ],
    'a field inside a group' => [
        ['handle' => 'outer', 'field' => ['type' => 'group', 'fields' => [['handle' => 'kind', 'field' => ['type' => 'radio', 'options' => ['published' => 'Published', 'unpublished' => 'Unpublished']]]]]],
        'outer.kind',
        'published',
        'published -> unpublished',
    ],
    'a hidden field' => [
        ['handle' => 'kind', 'field' => ['type' => 'hidden', 'default' => 'datetime-local']],
        'kind',
        'time',
        'time -> datetime-local',
    ],
    'a schema_type value' => [
        ['handle' => 'schema', 'field' => ['type' => 'schema_type', 'mode' => 'chained', 'starting_types' => ['Audience']]],
        'schema.value',
        'Audience',
        'Audience -> BusinessAudience',
    ],
]);

it('reads a referenced field rule against the options the consuming blueprint sets', function (): void {
    // `uamsweb_common_status.password_protected` reads its sibling `status_and_visibility`; a
    // blueprint that overrides that sibling's options changes what the rule can over-match.
    $audit = containsAnyOverlapAudit()->auditContents(containsAnyBlueprint([
        ['handle' => 'status_and_visibility', 'field' => 'uamsweb_common_status.status_and_visibility', 'config' => [
            'options' => ['draft' => 'Draft', 'published' => 'Published', 'unpublished' => 'Unpublished'],
        ]],
        ['handle' => 'password_protected', 'field' => 'uamsweb_common_status.password_protected'],
    ]), 'planted');

    expect($audit->overlaps())->toHaveCount(1);
    expect($audit->overlaps()[0]['overlaps'])->toBe(['published -> unpublished']);
});

it('reads every condition key, including the _any forms', function (string $key): void {
    $audit = containsAnyOverlapAudit()->auditContents(containsAnyBlueprint([
        ['handle' => 'kind', 'field' => ['type' => 'select', 'options' => ['date' => 'Date', 'datetime-local' => 'Date and time']]],
        ['handle' => 'shown', 'field' => ['type' => 'text', $key => ['kind' => 'contains_any date']]],
    ]), 'planted');

    expect($audit->overlaps())->toHaveCount(1);
})->with(['if', 'if_any', 'show_when', 'show_when_any', 'unless', 'unless_any', 'hide_when', 'hide_when_any']);

it('resolves a root-scoped path from inside a replicator set', function (): void {
    $audit = containsAnyOverlapAudit()->auditContents(containsAnyBlueprint([
        ['handle' => 'kind', 'field' => ['type' => 'select', 'options' => ['date' => 'Date', 'datetime-local' => 'Date and time']]],
        ['handle' => 'blocks', 'field' => ['type' => 'replicator', 'sets' => ['main' => ['sets' => ['row' => ['fields' => [
            ['handle' => 'shown', 'field' => ['type' => 'text', 'if' => ['root.kind' => 'contains_any date']]],
        ]]]]]]],
    ]), 'planted');

    expect($audit->overlaps())->toHaveCount(1);
    expect($audit->overlaps()[0]['field'])->toBe('blocks.main.row.shown');
});

it('accepts a rule that also lists every option containing its candidate', function (): void {
    $audit = containsAnyOverlapAudit()->auditContents(containsAnyBlueprint([
        ['handle' => 'kind', 'field' => ['type' => 'select', 'options' => ['date' => 'Date', 'datetime-local' => 'Date and time', 'time' => 'Time']]],
        ['handle' => 'shown', 'field' => ['type' => 'text', 'if' => ['kind' => 'contains_any date, datetime-local']]],
    ]), 'planted');

    expect($audit->findings())->toHaveCount(1);
    expect($audit->findings()[0]['status'])->toBe('option-list');
    expect($audit->overlaps())->toBe([]);
});

it('leaves a list-valued target unchecked, since contains_any is already membership there', function (): void {
    $audit = containsAnyOverlapAudit()->auditContents(containsAnyBlueprint([
        ['handle' => 'kinds', 'field' => ['type' => 'checkboxes', 'options' => ['date' => 'Date', 'datetime-local' => 'Date and time']]],
        ['handle' => 'shown', 'field' => ['type' => 'text', 'if' => ['kinds' => 'contains_any date']]],
    ]), 'planted');

    expect($audit->findings()[0]['status'])->toBe('list-valued');
    expect($audit->overlaps())->toBe([]);
});
