<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A logger that keeps every record, so a test can read the transitions.
 */
final class HealthTalkRecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string, context: array<mixed>}>
     */
    public array $records = [];

    /**
     * @param  array<mixed>  $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => is_string($level) ? $level : '', 'message' => (string) $message, 'context' => $context];
    }

    /**
     * Records whose context names this direction.
     *
     * @return list<array{level: string, message: string, context: array<mixed>}>
     */
    public function transitions(string $direction): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (array $record): bool => ($record['context']['direction'] ?? null) === $direction
        ));
    }
}
