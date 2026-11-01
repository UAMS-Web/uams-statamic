<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Statamic\Entries\Entry;

/**
 * An entry that records its quiet saves instead of writing to the Stache.
 *
 * A named class rather than a Mockery mock: the listener serializes the whole event to build its
 * repeat key, and a mock refuses `__sleep()` while an anonymous class cannot be serialized at all.
 */
final class TableImporterRecordingEntry extends Entry
{
    public int $quietSaves = 0;

    public function saveQuietly(): bool
    {
        $this->quietSaves++;

        return true;
    }
}
