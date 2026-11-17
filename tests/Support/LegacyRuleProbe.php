<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Validation\Rule;

/**
 * A legacy `Rule` that records what it was constructed with and passes or
 * fails as the test set.
 */
final class LegacyRuleProbe implements Rule
{
    /**
     * The arguments the last instance was constructed with.
     *
     * @var list<mixed>
     */
    public static array $constructedWith = [];

    /**
     * Whether the next check passes.
     */
    public static bool $passes = true;

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
     * Pass or fail as the test set.
     *
     * @param  string  $attribute  The attribute under validation
     * @param  mixed  $value  The value under validation
     */
    public function passes($attribute, $value): bool
    {
        return self::$passes;
    }

    /**
     * The message Laravel's interface requires; the provider's replacer builds the real one.
     */
    public function message(): string
    {
        return 'Probe message.';
    }
}
