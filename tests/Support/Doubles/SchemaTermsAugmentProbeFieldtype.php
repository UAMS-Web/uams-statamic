<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Statamic\Fields\Fieldtype;

/**
 * A fieldtype whose augmented value reports the field it was augmented through:
 * the raw value, and the field's parent, parent field, parent index and value.
 * It observes how the schema output service builds the working field for a
 * blueprint-aware terms augment.
 */
final class SchemaTermsAugmentProbeFieldtype extends Fieldtype
{
    /** @var string */
    protected static $handle = 'schema_terms_augment_probe';

    /**
     * Microseconds each augment sleeps, so a caller's elapsed time is measurable.
     */
    public static int $sleepMicroseconds = 0;

    /**
     * @return array{value: mixed, parent: mixed, parentField: mixed, parentIndex: mixed, fieldValue: mixed}
     */
    public function augment(mixed $value): array
    {
        if (self::$sleepMicroseconds > 0) {
            usleep(self::$sleepMicroseconds);
        }

        $field = $this->field();

        return [
            'value' => $value,
            'parent' => $field?->parent(),
            'parentField' => $field?->parentField(),
            'parentIndex' => $field?->parentIndex(),
            'fieldValue' => $field?->value(),
        ];
    }
}
