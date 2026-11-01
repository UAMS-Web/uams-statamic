<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Services\SchemaTemplates\SubsiteHubResolver;

/**
 * A resolver that exposes its real ancestor walk.
 */
final class AncestorExposingSubsiteHubResolver extends SubsiteHubResolver
{
    /** @return list<int|string> */
    public function ancestorsOf(int|string $entryId): array
    {
        return $this->ancestorIds($entryId);
    }
}
