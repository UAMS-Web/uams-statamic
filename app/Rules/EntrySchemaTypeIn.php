<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\SchemaType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Statamic\Facades\Entry;

/**
 * Validates that selected entry/entries have a schema.type in the allowed list.
 *
 * Used with the entries fieldtype in the schema object Thing set. Pass root
 * Schema.org type name(s) as rule parameters (e.g., entry_schema_type_in:Thing or
 * entry_schema_type_in:Thing,Person). Each root type is expanded to that type
 * plus all descendants (from SchemaType enum) internally, so the config stays
 * small and avoids 414 when the field config is sent in URLs.
 */
class EntrySchemaTypeIn implements ValidationRule
{
    /**
     * Allowed Schema.org type names (root types + their descendants).
     *
     * @var array<int, string>
     */
    private readonly array $allowedTypes;

    /**
     * Create a new rule instance.
     *
     * Each root type is expanded to type + descendants via SchemaType::descendantsOf().
     *
     * @param  string  ...$rootTypes  Root Schema.org type names (e.g., Thing, Person)
     */
    public function __construct(
        string ...$rootTypes
    ) {
        $allowed = [];
        foreach (array_filter($rootTypes) as $root) {
            $allowed[] = $root;
            foreach (SchemaType::descendantsOf($root) as $descendant) {
                $allowed[] = $descendant;
            }
        }

        $this->allowedTypes = array_values(array_unique($allowed));
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  The name of the attribute being validated
     * @param  mixed  $value  Entry id or array of entry ids
     * @param  Closure  $fail  The closure to call if validation fails
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Normalize to array of ids (entries field may be single id or array).
        $ids = \is_array($value) ? array_filter($value) : ($value ? [$value] : []);

        foreach ($ids as $id) {
            $idString = \is_scalar($id) ? (string) $id : '';
            $entry = Entry::find($id);
            if ($entry === null) {
                $fail(__('statamic::validation.entry_schema_type_in.not_found', ['id' => $idString]));

                return;
            }

            /** @var \Statamic\Entries\Entry $entry */
            $schema = $entry->get('schema');
            $typeRaw = \is_array($schema) ? ($schema['type'] ?? null) : null;
            $types = $this->normalizeTypeToArray($typeRaw);
            if ($types === [] || array_intersect($types, $this->allowedTypes) === []) {
                $typeString = \is_array($typeRaw)
                    ? implode(', ', array_map(static fn ($t): string => \is_scalar($t) ? (string) $t : '', $typeRaw))
                    : (\is_scalar($typeRaw) ? (string) $typeRaw : '');
                $fail(__(
                    'statamic::validation.entry_schema_type_in.not_allowed',
                    [
                        'type' => $typeString,
                        'allowed' => implode(', ', $this->allowedTypes),
                    ]
                ));

                return;
            }
        }
    }

    /**
     * Normalize type to array of type strings (single type or MTE).
     *
     * @param  mixed  $type  Stored type (string or array)
     * @return array<int, string>
     */
    private function normalizeTypeToArray(mixed $type): array
    {
        if (\is_string($type)) {
            return $type !== '' ? [$type] : [];
        }

        if (\is_array($type)) {
            return array_values(array_unique(array_filter($type, static fn ($t): bool => \is_string($t) && $t !== '')));
        }

        return [];
    }
}
