<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\Doubles\IncompletePublishedEntriesWidgetTestDouble;

/**
 * Validates a committed taxonomy term the way the Control Panel term editor saves it.
 */
final class TermSaveValidation
{
    /**
     * The fields the Control Panel term editor would reject on save.
     *
     * Rebuilt the way the editor submits: the stored values are pre-processed into
     * the editor's shape, a field its display condition hides is left out, a field
     * it shows with no answer stays as null so `required` applies even beside
     * `sometimes`, and the title and slug are added, then the whole blueprint
     * validates them.
     *
     * @param  array<string, mixed>  $overrides  Values replacing the stored ones before the save
     * @return list<string> The rejected field handles, sorted
     */
    public static function rejectedFields(LocalizedTerm $term, array $overrides = []): array
    {
        $blueprint = mustBe(Blueprint::class, $term->blueprint(), 'the term blueprint');

        // Pre-process the stored values into the shape the editor submits
        /** @var array<string, mixed> $data */
        $data = mustBe(Collection::class, $term->data(), 'the term data')->all();

        /** @var Fields $stored */
        $stored = $blueprint->fields()->addValues([...$data, ...$overrides]);

        /** @var Collection<string, mixed> $preProcessed */
        $preProcessed = mustBe(Fields::class, $stored->preProcess(), 'the pre-processed fields')->values();

        /** @var array<string, mixed> $values */
        $values = [...$preProcessed->all(), 'title' => $term->title(), 'slug' => $term->slug()];

        // Leave out every field the editor would hide by its display condition
        $widget = new IncompletePublishedEntriesWidgetTestDouble;

        /** @var Collection<string, Field> $fields */
        $fields = $blueprint->fields()->all();

        foreach ($fields as $handle => $field) {
            if (! $widget->exposeFieldPassesConditions($field, $values)) {
                unset($values[$handle]);
            }
        }

        try {
            /** @var Fields $submitted */
            $submitted = $blueprint->fields()->addValues($values);
            mustBe(Validator::class, $submitted->validator(), 'the validator')->validate();
        } catch (ValidationException $validationException) {
            $keys = array_keys($validationException->errors());
            sort($keys);

            return $keys;
        }

        return [];
    }
}
