<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

/**
 * A `ValidationRule` that records the validator it was handed and the
 * arguments it was constructed with, and always passes.
 */
final class ValidatorAwareRuleProbe implements ValidationRule, ValidatorAwareRule
{
    /**
     * The validator the last instance was handed, if any.
     */
    public static ?Validator $receivedValidator = null;

    /**
     * The arguments the last instance was constructed with.
     *
     * @var list<mixed>
     */
    public static array $constructedWith = [];

    /**
     * Record the constructor arguments.
     *
     * @param  mixed  ...$arguments  Whatever the provider passed
     */
    public function __construct(mixed ...$arguments)
    {
        self::$constructedWith = array_values($arguments);
    }

    /**
     * Record the validator.
     *
     * @param  Validator  $validator  The validator running this rule
     */
    public function setValidator($validator): static
    {
        self::$receivedValidator = $validator;

        return $this;
    }

    /**
     * Always pass.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {}
}
