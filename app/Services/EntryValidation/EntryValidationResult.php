<?php

declare(strict_types=1);

namespace App\Services\EntryValidation;

use Illuminate\Support\MessageBag;

/**
 * The outcome of validating one entry against its blueprint (#275).
 *
 * `errors` holds the Control Panel's validation messages keyed by field handle,
 * and `attributes` the labels those messages name. `visibleFields` lists, in
 * blueprint order, the top-level field handles the blueprint's display conditions
 * show for the entry's current values; it is empty when the entry's data could
 * not be read at all, which is reported as a `__pre_process__` error.
 */
final readonly class EntryValidationResult
{
    /**
     * @param  MessageBag  $errors  Validation messages keyed by field handle
     * @param  array<string, string>  $attributes  Field labels keyed by field handle
     * @param  list<string>  $visibleFields  Handles of the fields visible under the display conditions
     */
    public function __construct(
        public MessageBag $errors,
        public array $attributes,
        public array $visibleFields,
    ) {}

    /**
     * Whether the entry passes validation.
     */
    public function passes(): bool
    {
        return $this->errors->isEmpty();
    }
}
