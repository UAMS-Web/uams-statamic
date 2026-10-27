<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

/**
 * The result of one guarded request: what it means, and the status it saw (UAMS-Web/uams-statamic-archive#1766).
 *
 * A value object rather than an `array{outcome: …, status: …}` because the status is read back
 * by the retry decision in `ExternalProber::determine()`, where a missing key would evaluate to
 * null and silently take the retry branch. A typed property cannot go missing.
 */
final readonly class ProbeAttempt
{
    /**
     * @param  ProbeOutcome  $outcome  What this attempt established about the target.
     * @param  int|null  $status  The HTTP status seen, or null when nothing was reached.
     * @param  string|null  $reason  Why nothing was reached — the guard's refusal or the transport
     *                               error — or null when a response came back.
     * @param  bool  $answered  Whether ANY hop of the request answered, including a redirect a later
     *                          hop then failed after. It is what separates "the host could not be
     *                          reached" from "the host answered and the chain failed later" (UAMS-Web/uams-statamic-archive#1768).
     */
    public function __construct(
        public ProbeOutcome $outcome,
        public ?int $status,
        public ?string $reason = null,
        public bool $answered = false,
    ) {}
}
