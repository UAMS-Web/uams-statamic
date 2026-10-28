<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

/**
 * One occurrence's resolution outcome, with whatever the state needs to be actionable (UAMS-Web/uams-statamic-archive#1765).
 */
final class Resolution
{
    /**
     * @param  ResolutionState  $state  What became of the target.
     * @param  string|null  $detail  The state's supporting fact: the redirect target for
     *                               `Redirected`, the container for `MissingFile`, the id that
     *                               resolved nothing for `Missing`. Null when the state needs no
     *                               qualifier.
     */
    public function __construct(
        public ResolutionState $state,
        public ?string $detail = null,
    ) {}

    /**
     * Whether this outcome needs an editor's attention.
     *
     * `Redirected` is deliberately NOT actionable here: the link works today, and recommending
     * where it should point instead is #172's job rather than this slice's.
     */
    public function isBroken(): bool
    {
        // Bare ``in_array`` (not ``\in_array``): Pest's TrueToFalse mutator skips the
        // strict-flag ``true`` on ``in_array`` / ``array_search`` by function name.
        return in_array($this->state, [
            ResolutionState::Missing,
            ResolutionState::Unpublished,
            ResolutionState::MissingFile,
        ], true);
    }
}
