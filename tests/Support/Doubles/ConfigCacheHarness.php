<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Fieldtypes\Traits\ConfigCache;

/**
 * A ConfigCache user whose config() returns whatever $config holds, counting its
 * calls in $reads. When $reenter is set, config() calls getCachedConfig() before
 * answering and records what that nested call returned in $nested.
 */
final class ConfigCacheHarness
{
    use ConfigCache;

    /** @var array<string, mixed>|null */
    public ?array $config = ['a' => 1];

    public int $reads = 0;

    public bool $reenter = false;

    /** @var array<string, mixed>|null */
    public ?array $nested = null;

    /** @return array<string, mixed>|null */
    public function config(): ?array
    {
        $this->reads++;
        if ($this->reenter) {
            $this->nested = $this->getCachedConfig();
        }

        return $this->config;
    }

    /** @return array<string, mixed> */
    public function cached(): array
    {
        return $this->getCachedConfig();
    }
}
