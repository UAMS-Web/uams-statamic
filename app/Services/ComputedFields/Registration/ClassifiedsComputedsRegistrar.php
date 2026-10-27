<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Support\Classifieds\ClassifiedExpiry;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers the `is_expired` computed field on the `classifieds` collection (UAMS-Web/uams-statamic-archive#2096).
 *
 * A listing's page reads it to decide whether to say the listing has expired and
 * withhold its contact details. It is computed by `ClassifiedExpiry`, the same class
 * `App\Scopes\LiveClassifieds` uses for the board index, so a listing cannot be on
 * the board while its own page calls it expired.
 */
final readonly class ClassifiedsComputedsRegistrar
{
    /**
     * @param  ClassifiedExpiry  $expiry  The shared definition of an expired listing.
     */
    public function __construct(private ClassifiedExpiry $expiry)
    {
        //
    }

    /**
     * Register the `is_expired` computed for the `classifieds` collection.
     */
    public function register(): void
    {
        // Cache the service in a local for the closure below
        $expiry = $this->expiry;

        Collection::computed(
            'classifieds',
            'is_expired',
            fn (Entry $entry): bool => $expiry->isExpired($entry->get('expiration_date'))
        );
    }
}
