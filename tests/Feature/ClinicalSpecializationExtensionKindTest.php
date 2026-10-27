<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2823's extension kind: a `clinical_specializations` extension is either a
 * `specialization` proposed for the code set, which carries the qualification,
 * certification and training fields, or a `grouping`, a UAMS label for several code-set
 * classifications (for example "nursing support": nurse's aides and home health
 * aides), which carries none of them.
 *
 * Statamic's server-side validator does not evaluate field conditions: the Control Panel
 * evaluates them and omits a hidden field from what it submits. So the gating is pinned
 * by reading the blueprint's conditions, and validation runs on unsaved data with the
 * fields a form would send, so nothing reaches the content mirror.
 *
 * @command  php artisan test --compact tests/Feature/ClinicalSpecializationExtensionKindTest.php
 */

/**
 * The `clinical_specialization` blueprint.
 */
function extensionKindBlueprint(): Blueprint
{
    return mustBe(Blueprint::class, Collection::findByHandle('clinical_specializations')?->entryBlueprint('clinical_specialization'), 'the clinical_specialization blueprint');
}

/**
 * The fields the blueprint reports as failing for the given data, sorted.
 *
 * @param  array<string, mixed>  $data
 * @return list<string>
 */
function extensionKindErrors(array $data): array
{
    try {
        $fields = mustBe(Fields::class, extensionKindBlueprint()->fields()->addValues($data), 'the fields');
        mustBe(Validator::class, $fields->validator(), 'the validator')->validate();
    } catch (ValidationException $validationException) {
        $keys = array_keys($validationException->errors());
        sort($keys);

        return $keys;
    }

    return [];
}

it('offers specialization and grouping, defaulting to specialization, only on an extension', function (): void {
    $field = mustBe(Field::class, extensionKindBlueprint()->field('extension_kind'), 'the extension_kind field');

    expect(array_column(mustBeArray($field->get('options'), 'the options'), 'key'))->toBe(['specialization', 'grouping'])
        ->and($field->get('default'))->toBe('specialization')
        ->and($field->get('if'))->toBe(['extension_query' => 'equals true']);
});

/**
 * Whether a field shows for the given values, by its own `if` conditions.
 *
 * Statamic evaluates conditions in the Control Panel and omits a hidden field from the
 * submitted values, so the server-side validator never sees one; this reads the same
 * conditions. All keys must hold, and each uses `equals <value>` or `not <value>`, the
 * only two operators the gated fields use (asserted below).
 *
 * @param  array<string, mixed>  $values
 */
function extensionKindShows(string $handle, array $values): bool
{
    $field = mustBe(Field::class, extensionKindBlueprint()->field($handle), "the {$handle} field");

    foreach ((array) $field->get('if', []) as $key => $condition) {
        [$operator, $expected] = explode(' ', mustBeString($condition, "{$handle}'s condition"), 2) + [1 => ''];
        expect($operator)->toBeIn(['equals', 'not']);

        $actual = $values[$key] ?? null;
        $actual = match (true) {
            \is_bool($actual) => $actual ? 'true' : 'false',
            \is_scalar($actual) => (string) $actual,
            default => '',
        };

        if (($operator === 'equals') !== ($actual === $expected)) {
            return false;
        }
    }

    return true;
}

/**
 * The qualification and provider-type fields: everything later in the certification and
 * training chain is conditioned on the provider type, so hiding these hides the chain.
 */
const EXTENSION_KIND_GATED = ['extension_national_query', 'extension_unique_query', 'extension_org_query', 'extension_provider_type'];

/**
 * The gated fields that show for the given values.
 *
 * @param  array<string, mixed>  $values
 * @return list<string>
 */
function extensionKindShown(array $values): array
{
    return array_values(array_filter(EXTENSION_KIND_GATED, fn (string $handle): bool => extensionKindShows($handle, $values)));
}

it('shows the qualification and provider-type fields for a specialization extension and hides them for a grouping', function (): void {
    $shown = extensionKindShown(...);
    expect($shown(['extension_query' => true, 'extension_kind' => 'specialization']))->toBe(EXTENSION_KIND_GATED)
        ->and($shown(['extension_query' => true, 'extension_kind' => 'grouping']))->toBe([])
        // Not an extension at all: hidden, as before UAMS-Web/uams-statamic-archive#2823
        ->and($shown(['extension_query' => false]))->toBe([]);
});

it('shows them for an extension saved before the kind existed', function (): void {
    // congenital-heart-surgeon predates the field; reading a missing kind as a grouping
    // would hide the certification answers it already holds
    expect(extensionKindShown(['extension_query' => true]))->toBe(EXTENSION_KIND_GATED);
});

it('requires those fields once they show, so the gating is what spares a grouping', function (): void {
    // The fields are `sometimes`: sent empty, as the form sends a shown field, each is required
    $errors = extensionKindErrors([
        'title' => 'Probe', 'slug' => 'probe', 'extension_query' => true, 'extension_kind' => 'specialization',
        'code_name' => 'Probe', 'code_definition_query' => false,
        ...array_fill_keys(EXTENSION_KIND_GATED, null),
    ]);

    expect($errors)->toEqualCanonicalizing(EXTENSION_KIND_GATED);
});

it('validates every grouping extension in content on the fields outside the gated chain', function (): void {
    $this->requireContentCollection('clinical_specializations');

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_specializations');

    $groupings = [];
    $failures = [];

    foreach (mustBe(Illuminate\Support\Collection::class, $query->get(), 'the entries') as $entry) {
        $entry = mustBe(StatamicEntry::class, $entry, 'a clinical specialization');

        if ($entry->get('extension_kind') !== 'grouping') {
            continue;
        }

        $slug = mustBeString($entry->slug(), 'a specialization slug');
        $groupings[] = $slug;
        /** @var array<string, mixed> $data */
        $data = mustBeArray(mustBe(Illuminate\Support\Collection::class, $entry->data(), "{$slug}'s data")->all(), "{$slug}'s data");
        $errors = extensionKindErrors(['slug' => $slug, ...$data]);

        if ($errors !== []) {
            $failures[$slug] = $errors;
        }
    }

    // A non-empty population, so an empty failure list is a result rather than a miss
    expect($groupings)->toHaveCount(19)
        ->and($failures)->toBe([]);
});

it('keeps congenital-heart-surgeon a specialization', function (): void {
    $this->requireContentCollection('clinical_specializations');

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_specializations');
    $query->where('slug', 'congenital-heart-surgeon');

    $entry = mustBe(StatamicEntry::class, $query->first(), 'congenital-heart-surgeon');

    expect($entry->get('extension_kind'))->toBe('specialization');
});
