<?php

declare(strict_types=1);

use Illuminate\Validation\Validator;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fieldtypes\Assets\DimensionsRule;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/*
 * UAMS-Web/uams-statamic-archive#1887 — `character_limit` is a CP-only affordance: it renders `maxlength` on the
 * input and never becomes a validation rule, so an over-limit stored value is
 * invisible to server-side validation and therefore to the `IncompletePublishedEntries`
 * widget, which is driven by blueprint validation.
 *
 * The fix is explicit: a `validate: ['max:N']` alongside the limit on the two fields
 * in the shared `uamsweb_common` fieldset. `character_limit` deliberately does NOT
 * imply `max:` in general — see the audit at the bottom of this file for the count
 * that decision rests on.
 *
 * These assert blueprint *validation rules*, so no entry write is needed.
 *
 * @command php artisan test --compact tests/Feature/Blueprints/CharacterLimitValidationTest.php
 */

/**
 * The two fields whose `character_limit` is enforced server-side. Everything else
 * is a soft editor hint; the audit at the bottom of this file pins that split.
 *
 * @var array<int, array{file: string, handle: string, limit: int}>
 */
const CHARACTER_LIMIT_ENFORCED = [
    ['file' => 'resources/fieldsets/uamsweb_common.yaml', 'handle' => 'excerpt', 'limit' => 160],
    ['file' => 'resources/fieldsets/uamsweb_common.yaml', 'handle' => 'title_short', 'limit' => 43],
];

/**
 * Every file carrying a `character_limit` that is a **soft editor hint only**, and
 * how many it carries. Enforcing these is a deliberate per-field decision, not a
 * default: a scan of `content/` on 2026-08-04 found 1,757 stored values already over
 * their field's limit across these presentational block and header fields (WordPress
 * enforced no limit, so the overflow is inherited), which would land as a wall of new
 * validation failures in the audit widget rather than an actionable list.
 *
 * The counts are pinned so that adding a `character_limit` anywhere reds this test and
 * forces the author to choose: enforce it, or record it here as a hint.
 *
 * @var array<string, int>
 */
const CHARACTER_LIMIT_HINT_ONLY = [
    'resources/blueprints/collections/clinical_locations/location.yaml' => 3,
    'resources/blueprints/collections/knowledge_bases/knowledge_base.yaml' => 1,
    'resources/blueprints/collections/knowledge_bases/topic.yaml' => 1,
    'resources/blueprints/collections/logo_list_items/logo_list_item.yaml' => 1,
    'resources/blueprints/collections/people/person.yaml' => 1,
    'resources/blueprints/globals/uams_fad_settings.yaml' => 75,
    'resources/fieldsets/uamsweb_block_action_bar.yaml' => 3,
    'resources/fieldsets/uamsweb_block_button.yaml' => 1,
    'resources/fieldsets/uamsweb_block_counter.yaml' => 2,
    'resources/fieldsets/uamsweb_block_image_side_by_side.yaml' => 12,
    'resources/fieldsets/uamsweb_block_overlay.yaml' => 3,
    'resources/fieldsets/uamsweb_block_stacked.yaml' => 2,
    'resources/fieldsets/uamsweb_common_block.yaml' => 3,
    'resources/fieldsets/uamsweb_common_fad.yaml' => 2,
    'resources/fieldsets/uamsweb_common_people_background.yaml' => 1,
    'resources/fieldsets/uamsweb_common_table.yaml' => 1,
    'resources/fieldsets/uamsweb_header_graphic.yaml' => 1,
    'resources/fieldsets/uamsweb_header_hero.yaml' => 3,
    'resources/fieldsets/uamsweb_header_marketing_landing_page.yaml' => 2,
    'resources/fieldsets/uamsweb_post_curation.yaml' => 1,
];

/**
 * Import sites that drop a rule their referenced field declares, as found on
 * 2026-08-04. Every one predates UAMS-Web/uams-statamic-archive#1887 and none is a length rule.
 *
 * They are pinned rather than fixed because each needs a judgment call this ticket
 * cannot make — an image `dimensions:` guard and a custom `uams_video_url` rule look
 * like real losses, while a dropped `sometimes` may well be deliberate. Triage is
 * UAMS-Web/uams-statamic-archive#1904. Pinning stops the set growing, which is what the shallow-merge trap does if
 * nothing watches it.
 *
 * @var list<string>
 */
const CHARACTER_LIMIT_KNOWN_RULE_DROPS = [
    // HIDDEN IN THIS BLUEPRINT. Both fields carry `visibility: hidden` beside the
    // empty override, and `title_options` documents itself as legacy WordPress
    // page-header configuration that is not rendered (UAMS-Web/uams-statamic-archive#800). A `required` rule on
    // a field an editor cannot see cannot be satisfied, so restoring it would make
    // the blueprint unsavable rather than catch anything.
    'resources/blueprints/collections/clinical_expertise/clinical_expertise.yaml#header_hero drops `required` from uamsweb_header_selector_landing.header_hero',
    'resources/blueprints/collections/clinical_expertise/clinical_expertise.yaml#header_hero drops `sometimes` from uamsweb_header_selector_landing.header_hero',
    'resources/blueprints/collections/clinical_expertise/clinical_expertise.yaml#title_options drops `required` from uamsweb_header_selector_landing.title_options',

    // REFINED, NOT DROPPED. `required_without:table_data_importer` is a strictly
    // more precise statement of the same intent: the table is required unless the
    // importer field supplies it. Restating bare `required` beside it would
    // re-break the importer path this override exists to allow.
    'resources/blueprints/collections/tables/table.yaml#table_data drops `required` from uamsweb_common_table.table_data',

    // TAXONOMY REPOINTED, so the inherited term-type rule no longer applies. Each
    // of these three overrides the field onto a different taxonomy than the one
    // `specialty_taxonomy_term_type:specialty` pins: `subspecialty` for the
    // subspecialty site, `specialties_non_accredited` for the non-accredited one,
    // and `research_specialties_acgme` + `research_specialties_non_accredited`
    // (display "Specialty/Subspecialty") for the research one. Restating the
    // inherited rule would reject valid selections at all three.
    'resources/fieldsets/uamsweb_common_people_background.yaml#specialty drops `specialty_taxonomy_term_type:specialty` from uamsweb_common_people_background.specialty',
    'resources/fieldsets/uamsweb_common_people_background.yaml#specialty drops `specialty_taxonomy_term_type:specialty` from uamsweb_common_people_background.specialty',
    'resources/fieldsets/uamsweb_common_people_background.yaml#specialty drops `specialty_taxonomy_term_type:specialty` from uamsweb_common_people_background.specialty',

    // NARROWED TO ONE PROVIDER. `uams_video_url` is literally
    // `YouTubeUrl || VimeoUrl || BoxSharedStaticVideoUrl` (see App\Rules\UamsVideoUrl),
    // and each of these overrides names exactly the one arm its set accepts. A URL
    // passing `vimeo_url` necessarily passes `uams_video_url`, so restating the
    // inherited rule beside them is a proven no-op. UAMS-Web/uams-statamic-archive#1904's table calls these "a
    // real loss"; they are the opposite — the override is strictly stronger.
    'resources/fieldsets/uamsweb_set_video_box.yaml#url drops `uams_video_url` from uamsweb_common_video.video_url',
    'resources/fieldsets/uamsweb_set_video_vimeo.yaml#url drops `uams_video_url` from uamsweb_common_video.video_url',
    'resources/fieldsets/uamsweb_video_segment_box.yaml#url drops `uams_video_url` from uamsweb_common_video.video_url',
    'resources/fieldsets/uamsweb_video_segment_vimeo.yaml#url drops `uams_video_url` from uamsweb_common_video.video_url',
];

/**
 * Every `character_limit` declared in the fieldsets and blueprints, with the `max:`
 * rule (if any) that sits beside it.
 *
 * Read from the YAML rather than from resolved `Field` objects because the audit must
 * see each *declaration site* — a fieldset field and each blueprint that imports it
 * resolve to the same rules, so resolved fields cannot tell an unenforced declaration
 * from an enforced one that was imported.
 *
 * @return array<int, array{file: string, handle: string, limit: int, maxRule: int|null}>
 */
function characterLimitDeclarations(): array
{
    /** @var array<int, array{file: string, handle: string, limit: int, maxRule: int|null}> $declarations */
    $declarations = [];

    $walk = function (mixed $node, ?string $handle, string $file) use (&$walk, &$declarations): void {
        if (! is_array($node)) {
            return;
        }

        // A field map carries its handle on the enclosing node, so inherit it downward.
        $ownHandle = isset($node['handle']) && is_string($node['handle']) ? $node['handle'] : $handle;

        if (isset($node['character_limit']) && is_numeric($node['character_limit'])) {
            $rules = $node['validate'] ?? [];
            $rules = is_array($rules) ? $rules : [$rules];

            // Capture the max rule's own value, so a `max:` that drifted away from its
            // limit reads as a mismatch rather than as unenforced.
            $maxRule = null;
            foreach ($rules as $rule) {
                if (is_string($rule) && str_starts_with($rule, 'max:') && is_numeric(substr($rule, 4))) {
                    $maxRule = (int) substr($rule, 4);
                }
            }

            $declarations[] = [
                'file' => $file,
                'handle' => $ownHandle ?? '(anonymous)',
                'limit' => (int) $node['character_limit'],
                'maxRule' => $maxRule,
            ];
        }

        foreach ($node as $child) {
            $walk($child, $ownHandle, $file);
        }
    };

    foreach (characterLimitYamlFiles() as $file) {
        $walk(Yaml::parseFile($file['absolute']), null, $file['relative']);
    }

    return $declarations;
}

/**
 * Every fieldset and blueprint YAML file, absolute and checkout-relative.
 *
 * @return list<array{absolute: string, relative: string}> Files in a stable order
 */
function characterLimitYamlFiles(): array
{
    $base = str_replace(DIRECTORY_SEPARATOR, '/', base_path()).'/';

    $files = [];

    foreach (['resources/fieldsets', 'resources/blueprints'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory)));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'yaml') {
                continue;
            }

            $path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());

            $files[] = ['absolute' => $file->getPathname(), 'relative' => str_replace($base, '', $path)];
        }
    }

    usort($files, static fn (array $a, array $b): int => $a['relative'] <=> $b['relative']);

    return $files;
}

/**
 * Normalize a `validate:` value to a list of rule strings.
 *
 * A blueprint may write it as a list or as a single scalar, and a rule may be an
 * object (a Statamic rule class), which is not comparable here and drops out.
 *
 * @param  mixed  $validate  The raw `validate` value
 * @return list<string> The rules as strings
 */
function normalizeValidationRules(mixed $validate): array
{
    $rules = is_array($validate) ? $validate : [$validate];

    $normalized = [];

    foreach ($rules as $rule) {
        if (is_scalar($rule)) {
            $normalized[] = (string) $rule;
        }
    }

    return $normalized;
}

/**
 * Map every `<fieldset>.<handle>` reference to the reference it in turn points at, so
 * an import chain can be walked back to the field that actually declares the limit.
 *
 * @return array{aliases: array<string, string>, rules: array<string, list<string>>} Reference → reference, and
 *                                                                                   reference → its own `validate` list
 */
function fieldsetReferenceMap(): array
{
    $aliases = [];
    $rules = [];

    foreach (glob(resource_path('fieldsets/*.yaml')) ?: [] as $file) {
        $set = basename($file, '.yaml');

        /** @var array{fields?: array<int, mixed>} $parsed */
        $parsed = Yaml::parseFile($file);

        foreach ($parsed['fields'] ?? [] as $field) {
            if (! is_array($field) || ! isset($field['handle']) || ! is_string($field['handle'])) {
                continue;
            }

            $reference = "{$set}.{$field['handle']}";

            if (isset($field['field']) && is_string($field['field'])) {
                $aliases[$reference] = $field['field'];
            }

            $own = is_array($field['field'] ?? null) ? ($field['field']['validate'] ?? null) : null;

            if ($own !== null) {
                $rules[$reference] = normalizeValidationRules($own);
            }
        }
    }

    return ['aliases' => $aliases, 'rules' => $rules];
}

/**
 * Every `field:` import site in the fieldsets and blueprints, with the `validate` its
 * `config:` overrides (if any) and the `validate` it would otherwise inherit.
 *
 * @return list<array{file: string, handle: string, ref: string, override: list<string>|null, inherited: list<string>}>
 */
function fieldsetImportSites(): array
{
    ['aliases' => $aliases, 'rules' => $ownRules] = fieldsetReferenceMap();

    // Walk the chain to the nearest ancestor that declares its own `validate`.
    $inheritedRules = static function (string $reference) use ($aliases, $ownRules): array {
        $seen = [];

        while (! isset($ownRules[$reference])) {
            if (! isset($aliases[$reference]) || isset($seen[$reference])) {
                return [];
            }

            $seen[$reference] = true;
            $reference = $aliases[$reference];
        }

        return $ownRules[$reference];
    };

    $sites = [];

    $walk = function (mixed $node, string $file) use (&$walk, $inheritedRules, &$sites): void {
        if (! is_array($node)) {
            return;
        }

        if (isset($node['handle'], $node['field']) && is_string($node['handle']) && is_string($node['field'])) {
            $config = $node['config'] ?? [];
            $override = is_array($config) && array_key_exists('validate', $config) ? $config['validate'] : null;

            $sites[] = [
                'file' => $file,
                'handle' => $node['handle'],
                'ref' => $node['field'],
                'override' => $override === null ? null : normalizeValidationRules($override),
                'inherited' => $inheritedRules($node['field']),
            ];
        }

        foreach ($node as $child) {
            $walk($child, $file);
        }
    };

    foreach (characterLimitYamlFiles() as $file) {
        $walk(Yaml::parseFile($file['absolute']), $file['relative']);
    }

    return $sites;
}

/**
 * Every BLUEPRINT import site whose reference resolves to one of the enforced fieldset
 * fields, with the blueprint handle `Blueprint::find()` takes.
 *
 * Derived from {@see CHARACTER_LIMIT_ENFORCED} rather than hand-listed, so enforcing a
 * new field automatically extends the coverage.
 *
 * @return list<array{blueprint: string, handle: string, limit: int}>
 */
function sharedLimitImportSites(): array
{
    ['aliases' => $aliases] = fieldsetReferenceMap();

    // `resources/fieldsets/uamsweb_common.yaml` + handle `excerpt` → `uamsweb_common.excerpt`.
    $targets = [];
    foreach (CHARACTER_LIMIT_ENFORCED as $enforced) {
        $targets[basename($enforced['file'], '.yaml').'.'.$enforced['handle']] = $enforced['limit'];
    }

    $resolve = static function (string $reference) use ($aliases, $targets): ?int {
        $seen = [];

        while (! isset($targets[$reference])) {
            if (! isset($aliases[$reference]) || isset($seen[$reference])) {
                return null;
            }

            $seen[$reference] = true;
            $reference = $aliases[$reference];
        }

        return $targets[$reference];
    };

    $sites = [];

    foreach (fieldsetImportSites() as $site) {
        if (! str_starts_with($site['file'], 'resources/blueprints/')) {
            continue;
        }

        $limit = $resolve($site['ref']);

        if ($limit === null) {
            continue;
        }

        $sites[] = [
            'blueprint' => str_replace('/', '.', substr($site['file'], strlen('resources/blueprints/'), -5)),
            'handle' => $site['handle'],
            'limit' => $limit,
        ];
    }

    return $sites;
}

/**
 * Validate a CP-shaped submission against a blueprint and return the Laravel validator.
 * Other required fields on the blueprint are irrelevant; each test asserts on one key.
 *
 * @param  array<string, mixed>  $values  Field values keyed by handle
 */
function validateAgainstBlueprintForCharacterLimit(string $handle, array $values): Validator
{
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find($handle);

    /** @var Fields $fields */
    $fields = $blueprint->fields()->addValues($values);

    /** @var Statamic\Fields\Validator $fieldsValidator */
    $fieldsValidator = $fields->validator();

    /** @var Validator $validator */
    $validator = $fieldsValidator->validator();

    return $validator;
}

it('turns the shared fieldset character limits into max rules', function (string $handle, int $limit): void {
    /** @var Statamic\Fields\Fieldset $fieldset */
    $fieldset = Fieldset::find('uamsweb_common');

    $field = mustBe(Field::class, $fieldset->fields()->get($handle), "the {$handle} field");

    /** @var array<string, array<int, mixed>> $rules */
    $rules = $field->rules();

    expect($rules[$handle])->toContain("max:{$limit}");
})->with([
    'excerpt' => ['excerpt', 160],
    'title_short' => ['title_short', 43],
]);

/**
 * The rule has to survive the fieldset *import*, and a hand-picked dataset cannot
 * prove that: an adversarial review of this file found `max:160` missing from 23
 * resolved fields — `pages`, `posts`, `knowledge_bases`, `clinical_expertise`,
 * `clinical_resources` — while the four handles originally listed here all happened
 * to be override-free, so the test was green on the gap it claimed to close.
 *
 * The cause is that `Fields::getReferencedField()` shallow-merges an import site's
 * `config:` into the referenced field, so a `config.validate` **replaces** the
 * fieldset's rules rather than adding to them. This enumerates every import site
 * instead, so a new override cannot silently reopen it.
 */
it('carries the max rule through every blueprint that imports a limited shared field', function (): void {
    $missing = [];
    $checked = 0;

    foreach (sharedLimitImportSites() as $site) {
        $blueprint = Blueprint::find($site['blueprint']);

        // A fieldset that no blueprint resolves is covered through its importers.
        if (! $blueprint instanceof Statamic\Fields\Blueprint) {
            continue;
        }

        $field = $blueprint->fields()->get($site['handle']);

        if (! $field instanceof Field) {
            continue;
        }

        $checked++;

        /** @var array<string, array<int, mixed>> $rules */
        $rules = $field->rules();

        if (! in_array("max:{$site['limit']}", $rules[$site['handle']] ?? [], true)) {
            $missing[] = sprintf(
                '%s#%s (limit %d, rules %s)',
                $site['blueprint'],
                $site['handle'],
                $site['limit'],
                json_encode($rules[$site['handle']] ?? [])
            );
        }
    }

    // Guard against a vacuous pass: the shared `excerpt` is imported across the whole
    // content model, so a scan that resolved a handful of sites found almost nothing.
    expect($checked)->toBeGreaterThan(20);

    expect($missing)->toBe([], implode("\n", [
        'A blueprint imports a length-limited shared field but resolves no `max:` rule.',
        'Statamic shallow-merges an import `config:` into the referenced field, so a',
        '`config.validate` REPLACES the fieldset\'s rules. Restate the `max:N` beside',
        'the override rather than relying on the fieldset to supply it.',
    ]));
});

/**
 * The general invariant behind that trap, asserted for every rule rather than for
 * `max:` alone: an import-site override must not silently drop a rule the referenced
 * field declares. Written against the YAML rather than resolved fields, because after
 * resolution the dropped rule is simply absent and its provenance is gone.
 */
it('never lets an import override drop a rule the referenced field declares', function (): void {
    $dropped = [];

    foreach (fieldsetImportSites() as $site) {
        if ($site['override'] === null || $site['inherited'] === []) {
            continue;
        }

        foreach ($site['inherited'] as $rule) {
            if (! in_array($rule, $site['override'], true)) {
                $dropped[] = sprintf('%s#%s drops `%s` from %s', $site['file'], $site['handle'], $rule, $site['ref']);
            }
        }
    }

    sort($dropped);

    expect($dropped)->toBe(CHARACTER_LIMIT_KNOWN_RULE_DROPS, implode("\n", [
        'A `config.validate` override REPLACES the referenced field\'s rules wholesale.',
        'Restate every inherited rule beside the override, or add the site to',
        'CHARACTER_LIMIT_KNOWN_RULE_DROPS with a reason if the drop is deliberate.',
    ]));
});

/**
 * UAMS-Web/uams-statamic-archive#1904 AC2: a restored rule must be verified by RESOLVED `Field::rules()`, not by
 * reading the YAML back. The sibling guard above is deliberately YAML-based — after
 * resolution a dropped rule is simply absent and its provenance is gone — so it can
 * prove the override no longer drops the rule while saying nothing about what the
 * blueprint actually enforces. This closes that gap from the other side.
 */
it('resolves the restored image minimum on the location blueprint', function (): void {
    $blueprint = Blueprint::find('collections.clinical_locations.location');

    expect($blueprint)->toBeInstanceOf(Statamic\Fields\Blueprint::class);
    Assert::assertInstanceOf(Statamic\Fields\Blueprint::class, $blueprint);

    foreach (['featured_image', 'gallery_image', 'interior_wayfinding_image', 'wayfinding_image'] as $handle) {
        $field = $blueprint->fields()->get($handle);

        expect($field)->toBeInstanceOf(Field::class);
        Assert::assertInstanceOf(Field::class, $field);

        $resolved = $field->rules();
        Assert::assertIsArray($resolved);

        $rules = $resolved[$handle] ?? [];
        Assert::assertIsArray($rules);

        // The assets fieldtype does NOT keep `dimensions:…` as a string — it
        // resolves to a Statamic DimensionsRule carrying the parsed parameters. A
        // `toContain('dimensions:…')` assertion here silently never matches, which
        // is the trap this test would otherwise fall into itself.
        $dimensionRules = array_values(array_filter(
            $rules,
            static fn (mixed $rule): bool => $rule instanceof DimensionsRule
        ));

        expect($dimensionRules)->toHaveCount(1, $handle.' resolves no dimensions rule');

        $dimensionRule = $dimensionRules[0];
        Assert::assertInstanceOf(DimensionsRule::class, $dimensionRule);

        $parameters = (new ReflectionProperty(DimensionsRule::class, 'parameters'))
            ->getValue($dimensionRule);

        // The referenced field's own minimum, restated at the import site...
        expect($parameters)->toBe(['min_width=1200', 'min_height=675']);

        // ...which is NOT the recommended width these sites had been enforcing as a
        // minimum. `uamsweb_common_images` states recommendations in `instructions`
        // only and never in `validate`, so a 2400 minimum contradicted the fieldset.
        expect($parameters)->not->toContain('min_width=2400');
    }
});

it('rejects a short biography one character over the limit', function (): void {
    $errors = validateAgainstBlueprintForCharacterLimit('collections.people.person', [
        'bio_short' => str_repeat('a', 161),
    ])->errors();

    expect($errors->has('bio_short'))->toBeTrue();
});

it('accepts a short biography at exactly the limit', function (): void {
    $errors = validateAgainstBlueprintForCharacterLimit('collections.people.person', [
        'bio_short' => str_repeat('a', 160),
    ])->errors();

    expect($errors->has('bio_short'))->toBeFalse();
});

it('rejects a short title one character over the limit', function (): void {
    $errors = validateAgainstBlueprintForCharacterLimit('collections.knowledge_bases.topic', [
        'title_short' => str_repeat('a', 44),
    ])->errors();

    expect($errors->has('title_short'))->toBeTrue();
});

it('accepts a short title at exactly the limit', function (): void {
    $errors = validateAgainstBlueprintForCharacterLimit('collections.knowledge_bases.topic', [
        'title_short' => str_repeat('a', 43),
    ])->errors();

    expect($errors->has('title_short'))->toBeFalse();
});

/**
 * The audit the ticket asks for: every `character_limit` in the fieldsets and
 * blueprints is either enforced (and named above) or a recorded hint.
 */
it('audits every declared character limit as enforced or as a recorded hint', function (): void {
    $declarations = characterLimitDeclarations();

    // Guard against a vacuous pass: an empty walk would satisfy both comparisons below.
    expect($declarations)->not->toBeEmpty();

    $enforced = [];
    $hintCounts = [];

    foreach ($declarations as $declaration) {
        if ($declaration['maxRule'] !== null) {
            $enforced[] = [
                'file' => $declaration['file'],
                'handle' => $declaration['handle'],
                'limit' => $declaration['limit'],
            ];

            continue;
        }

        $hintCounts[$declaration['file']] = ($hintCounts[$declaration['file']] ?? 0) + 1;
    }

    ksort($hintCounts);

    expect($enforced)->toEqualCanonicalizing(CHARACTER_LIMIT_ENFORCED);

    expect($hintCounts)->toBe(CHARACTER_LIMIT_HINT_ONLY, implode("\n", [
        'A `character_limit` was added, removed, or moved.',
        'Decide whether it should be enforced server-side:',
        '  enforce  — add `validate: [\'max:N\']` beside it and list it in CHARACTER_LIMIT_ENFORCED',
        '  hint     — leave it and update the count for its file in CHARACTER_LIMIT_HINT_ONLY',
    ]));
});

it('keeps every max rule consistent with the character limit it sits beside', function (): void {
    $mismatched = [];

    foreach (characterLimitDeclarations() as $declaration) {
        if ($declaration['maxRule'] !== null && $declaration['maxRule'] !== $declaration['limit']) {
            $mismatched[] = sprintf(
                '%s#%s: character_limit %d but max:%d',
                $declaration['file'],
                $declaration['handle'],
                $declaration['limit'],
                $declaration['maxRule']
            );
        }
    }

    expect($mismatched)->toBe([]);
});
