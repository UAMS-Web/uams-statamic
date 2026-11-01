<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use RuntimeException;

enum EnumCaseIntProbe: int
{
    #[EnumCaseProbeBroken]
    #[EnumCaseProbeInfo(label: 'Seven')]
    #[EnumCaseProbeOther(label: 'Other seven')]
    case Seven = 7;

    case MinusTwo = -2;

    case Zero = 0;

    public function greet(string $greeting = 'Hello', string $punctuation = '!'): string
    {
        return $greeting.' '.$this->name.$punctuation;
    }

    public function fails(): string
    {
        throw new RuntimeException('This method always fails.');
    }

    public static function make(): self
    {
        return self::Seven;
    }

    public function sum(int ...$numbers): int
    {
        return array_sum($numbers);
    }

    /**
     * Returns a value so that calling it would be visible.
     *
     * @param  list<int>  $into
     */
    public function fill(array &$into): int
    {
        $into[] = $this->value;

        return \count($into);
    }

    /**
     * One of the magic methods an enum may declare; the tag must refuse it.
     */
    public function __invoke(): string
    {
        return 'invoked';
    }

    /**
     * Routes a call to an inaccessible method here, so calling the private hidden()
     * from outside would succeed rather than throw.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): string
    {
        return 'via __call('.$name.')';
    }

    /**
     * Unused on purpose: only its visibility matters to the tag.
     */
    private function hidden(): string // @phpstan-ignore method.unused
    {
        return 'hidden';
    }
}
