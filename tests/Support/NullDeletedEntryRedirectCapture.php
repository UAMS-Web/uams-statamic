<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Redirects\DeletedEntryRedirectCapture;
use Statamic\Entries\Entry;

/**
 * A {@see DeletedEntryRedirectCapture} that records nothing, for a test whose entry
 * deletes are fixture cleanup or a removal under test rather than a redirect under test.
 *
 * The real capture saves the `redirects` global set, and globals stay on the shared
 * content mirror under every isolating base but {@see StacheIsolatedTestCase}, so each
 * delete in such a test would write a row into the mirror every worker reads
 * (UAMS-Web/uams-statamic#558). Bind it with
 * `app()->instance(DeletedEntryRedirectCapture::class, new NullDeletedEntryRedirectCapture)`.
 */
final class NullDeletedEntryRedirectCapture extends DeletedEntryRedirectCapture
{
    /**
     * Record nothing.
     *
     * @param  Entry  $entry  The entry Statamic has just deleted.
     * @return int Always 0, the rows written.
     */
    public function captureFor(Entry $entry): int
    {
        return 0;
    }
}
